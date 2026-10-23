<?php

namespace Kibatic\CmsBundle\Controller;

use Kibatic\CmsBundle\Ai\BlockChanges;
use Kibatic\CmsBundle\Ai\VersionProposals;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Kibatic\CmsBundle\Upload\ImageStorage;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Agent\Execution\Update\Progress;
use Symfony\AI\Agent\Execution\Update\Result;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Assistant of the admin: a chat with the Symfony AI agent of kibatic_cms.ai.agent, which creates and modifies blocks
 * with the tools of Kibatic\CmsBundle\Ai\Tool. The answer is streamed as it is produced (message()), the conversation
 * is kept in the cache, for the admin's session (not in the session itself: it would stay locked during the answer).
 */
class ChatController extends AbstractController
{
    private const CACHE_KEY = 'kibatic_cms_chat_';
    private const CACHE_LIFETIME = 86400;
    /** Seconds without news after which a line is sent anyway, so that no proxy cuts the connection */
    private const KEEP_ALIVE = 2;
    /** Messages of the conversation sent back to the model (the oldest ones are forgotten) */
    private const HISTORY_SIZE = 20;
    /** Images joined to a message (uploaded first: UploadController) */
    private const MAX_IMAGES = 4;
    public const CSRF_TOKEN_ID = 'kibatic_cms_chat';

    public function __construct(
        private readonly ?AgentInterface $agent,
        private readonly BlockRepository $blockRepository,
        private readonly BlockChanges $changes,
        private readonly VersionProposals $proposals,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly ImageStorage $imageStorage,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        #[Autowire(param: 'kibatic_cms.ai.style_guide')]
        private readonly ?string $styleGuide,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->denyUnlessAvailable();

        return $this->render('@KibaticCms/chat/index.html.twig', [
            'history' => $this->history($request),
            'slug' => $request->query->get('slug'),
        ]);
    }

    /**
     * Streamed answer, one JSON object per line: {type: "status"} (model working), {type: "tool", label} (a tool used),
     * {type: "text", text} (piece of the answer), {type: "ping"} (keep-alive), then {type: "done", answer, changes} or
     * {type: "error", error}. The request goes on to the end even if the connection is cut.
     */
    public function message(Request $request): Response
    {
        $this->denyUnlessAvailable();

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => $this->trans('chat.error.csrf')], Response::HTTP_FORBIDDEN);
        }

        $text = trim((string) $request->getPayload()->get('message'));
        // Only images of the storage: their file is sent to the model
        $images = \array_slice(array_values(array_filter(
            (array) ($request->getPayload()->all()['images'] ?? []),
            fn ($url) => \is_string($url) && str_starts_with($url, $this->imageStorage->getPublicPath()),
        )), 0, self::MAX_IMAGES);

        if ('' === $text && [] === $images) {
            return $this->json(['error' => $this->trans('chat.error.empty')], Response::HTTP_BAD_REQUEST);
        }

        $history = $this->history($request);
        $cacheItem = $this->cache->getItem($this->cacheKey($request));
        // The answer is saved in the cache, not in the session: release it for the other pages of the admin
        $request->getSession()->save();

        $messages = new MessageBag(Message::forSystem($this->systemPrompt($request->getLocale())));
        foreach ($history as $message) {
            $messages->add('user' === $message['role'] ? $this->userMessage($message['content'], $message['images'] ?? []) : Message::ofAssistant($message['content']));
        }
        $messages->add($this->userMessage($text, $images));

        $response = new StreamedResponse(function () use ($messages, $history, $text, $images, $cacheItem): void {
            ignore_user_abort(true);
            $send = function (array $event): void {
                echo json_encode($event, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)."\n";
                flush();
            };
            $lastSent = time();

            try {
                $answer = null;
                foreach ($this->agent->call($messages, ['stream' => true]) as $update) {
                    $event = match (true) {
                        $update instanceof Result => null,
                        'model_request' === $update->getStage() => ['type' => 'status'],
                        'tool_call' === $update->getStage() => ['type' => 'tool', 'label' => $this->toolLabel($update->getPayload())],
                        $update->getPayload() instanceof TextDelta => ['type' => 'text', 'text' => $update->getPayload()->getText()],
                        default => time() - $lastSent >= self::KEEP_ALIVE ? ['type' => 'ping'] : null,
                    };

                    if ($update instanceof Result) {
                        $answer = trim((string) $update->getResult()->getContent());
                    } elseif (null !== $event) {
                        $send($event);
                        $lastSent = time();
                    }
                }
            } catch (AgentException|PlatformException|HttpClientException $e) {
                $this->logger->error('CMS assistant: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
                $send(['type' => 'error', 'error' => $this->trans('chat.error.model')]);

                return;
            }

            $history[] = ['role' => 'user', 'content' => $text, 'images' => $images];
            $history[] = ['role' => 'assistant', 'content' => $answer];
            $this->cache->save($cacheItem->set(\array_slice($history, -self::HISTORY_SIZE))->expiresAfter(self::CACHE_LIFETIME));

            $send([
                'type' => 'done',
                'answer' => $answer,
                'changes' => array_map(fn (array $change) => [
                    'slug' => $change['block']->getSlug(),
                    'language' => $change['block']->getLanguage(),
                    'created' => $change['created'],
                    'url' => $this->generateUrl('cms_block_edit', ['id' => $change['block']->getId()]),
                ], $this->changes->all()),
                'proposals' => array_map($this->proposalCard(...), $this->proposals->all()),
            ]);
        });

        // Not buffered by a proxy (nginx), not cached
        $response->headers->set('Content-Type', 'application/x-ndjson');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    public function reset(Request $request): Response
    {
        $this->denyUnlessAvailable();

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->cache->deleteItem($this->cacheKey($request));
        }

        return $this->redirectToRoute('cms_chat');
    }

    /**
     * Confirmation card of an action proposed by the assistant: the admin's form posts to the VersionController route
     */
    private function proposalCard(array $proposal): array
    {
        $version = $proposal['version'];
        $versionName = null !== $version ? ($version['name'] ?? $this->trans('version.unnamed')) : '';

        return [
            'action' => $proposal['action'],
            'url' => match ($proposal['action']) {
                'save' => $this->generateUrl('cms_version_create'),
                'publish_draft' => $this->generateUrl('cms_version_publish_draft'),
                'publish' => $this->generateUrl('cms_version_publish', ['id' => $version['id']]),
                'restore' => $this->generateUrl('cms_version_restore', ['id' => $version['id']]),
                'unpublish' => $this->generateUrl('cms_version_unpublish'),
            },
            'name' => $proposal['name'],
            'title' => $this->translator->trans('chat.proposal.'.$proposal['action'].'.title', ['name' => $versionName, 'count' => $proposal['changes']], 'KibaticCmsBundle'),
            'help' => $this->translator->trans('chat.proposal.'.$proposal['action'].'.help', ['count' => $proposal['changes']], 'KibaticCmsBundle'),
            'button' => $this->translator->trans('chat.proposal.'.$proposal['action'].'.button', [], 'KibaticCmsBundle'),
            'done' => $this->translator->trans('chat.proposal.'.$proposal['action'].'.done', [], 'KibaticCmsBundle'),
        ];
    }

    /**
     * @return list<array{role: 'user'|'assistant', content: string, images?: list<string>}>
     */
    private function history(Request $request): array
    {
        return $this->cache->getItem($this->cacheKey($request))->get() ?? [];
    }

    private function cacheKey(Request $request): string
    {
        return self::CACHE_KEY.hash('sha256', $request->getSession()->getId());
    }

    /**
     * What a tool call does, for the admin (shown while the answer is produced)
     */
    private function toolLabel(ToolCall $toolCall): string
    {
        $key = 'chat.tool.'.$toolCall->getName();
        $label = $this->translator->trans($key, ['slug' => $toolCall->getArguments()['slug'] ?? ''], 'KibaticCmsBundle');

        // Tool added by the application, without translation
        return $label !== $key ? $label : $this->translator->trans('chat.tool.other', ['name' => $toolCall->getName()], 'KibaticCmsBundle');
    }

    private function denyUnlessAvailable(): void
    {
        if (null === $this->agent) {
            throw $this->createNotFoundException('The CMS assistant is not configured (kibatic_cms.ai.agent).');
        }

        $this->denyAccessUnlessGranted(BlockController::$role);
    }

    private function systemPrompt(string $locale): string
    {
        $languages = $this->blockRepository->getExistingLanguages();
        $styleGuide = null !== $this->styleGuide ? "\n\nStyle guide of the website, for the styles of the blocks:\n".trim($this->styleGuide) : '';

        return <<<PROMPT
            You are the assistant of the administration of a website's CMS. The website displays content blocks, each
            identified by a slug and a language, which you create and modify with your tools for the admin talking to you.

            - Always answer in the language of the admin's last message (the admin interface is in "{$locale}").
            - Languages of the existing blocks: {$this->languageList($languages)}. A block without language is shared by
              every language; a translation is a block with the same slug in another language.
            - Before modifying a block, read it with cms_get_block. Saving replaces the whole content of the block.
            - HTML blocks are displayed inside the website's pages: write a clean HTML fragment (h2, h3, p, ul, ol, li, a,
              strong, em, img), without <html>, <body>, <style>, <script> or inline styles.
            - New slugs are short, lowercase, in English and with dashes (e.g. "home-intro", "footer-legal").
            - The pages show the blocks their templates ask for: a new block appears nowhere until a developer adds it to a
              template. To change something on a page, modify its existing blocks (images included: the URL of an image
              block); create a block only when the admin asks for a new one, and if no block matches, say so.
            - You cannot delete a block: the admin does it in the block list.
            - A metablock (type "meta") shows existing blocks together with a layout (stack, columns, grid, tabs), and may
              contain sub-metablocks made inside it; save it with cms_save_metablock, never with cms_save_block. To put
              blocks side by side or in tabs, make a metablock of them. A metablock is shared by every language, with the
              titles of its tabs in each language.
            - The admin can join images to a message: you see them, and their URLs follow the message. To put one in an
              HTML block, use its URL as is: <img src="URL" alt="short description of the image">; for an image block,
              its URL is the whole content. Never write an image in base64 (data:).
            - When the request is ambiguous (which block, which language), ask before saving.
            - Each block has its own CSS ("styles"), scoped to it by the CMS: plain declarations apply to the block itself,
              nested rules to its content (e.g. "padding: 40px; border-radius: 28px; background: var(--x); h2 { margin-top: 0; }
              li { padding: 8px; }"); @media rules can be nested too. Classes in the HTML are fine when the block's styles use them.
            - A block must look the same on every page that shows it (a page, a metablock, another page): its styles carry
              its whole look, its background, padding, rounded corners and text colors included, and never rely on the page
              around it. A block with a dark background sets its own text colors; one without background inherits them.
            - Before styling a new block, read with cms_get_block one or two blocks of the site that have styles
              (cms_list_blocks says which ones), and give it the same kind of look: same spacing, corners, colors and cards.
              Use the site's CSS variables rather than fixed colors.
            - When you only change the text of a block, do not send its styles (they are kept); send them only to change
              its look, complete, as they replace the previous ones.
            - Versions: a version is a copy of every block. When a version is published, the visitors see it, and the changes
              made since (the draft) are not online until a new publication; without published version, every change is
              online right away. Read them with cms_list_versions.
            - You never save, publish, restore or unpublish a version yourself: propose it with cms_propose_version_action,
              which shows the admin a confirmation card under your answer. Publishing makes content public: always propose it,
              never say it is done. For a new version ("save" or "publish_draft"), suggest a very short name (2 to 5 words)
              saying what changed (read the changes of the draft first), and tell the admin it can be changed in the card.
              When the admin asks for one of these actions, propose it right away: the card is the confirmation, do not ask
              before proposing. Propose one action per answer. In French, the draft is called « brouillon ».
            - The card exists only if you call cms_propose_version_action in this answer: never say you proposed an action
              without calling it, even if you proposed the same one before.
            - After saving, say in one or two sentences what you created or changed. Do not paste the saved HTML back.{$styleGuide}
            PROMPT;
    }

    /**
     * Message of the admin with its images: their files, and their URLs to put them in the blocks
     *
     * @param list<string> $images URLs of the storage (ImageStorage)
     */
    private function userMessage(string $text, array $images): UserMessage
    {
        $files = array_filter(array_combine($images, array_map(fn (string $url) => $this->imageStorage->read($url), $images)));

        if ([] === $files) {
            return Message::ofUser($text);
        }

        return Message::ofUser(
            $text."\n\n[Images joined, in this order: ".implode(', ', array_keys($files)).']',
            ...array_map(fn (array $file) => new Image($file['content'], $file['type']), array_values($files)),
        );
    }

    private function languageList(array $languages): string
    {
        return [] === $languages ? 'none (no block has a language yet)' : implode(', ', $languages);
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'KibaticCmsBundle');
    }
}
