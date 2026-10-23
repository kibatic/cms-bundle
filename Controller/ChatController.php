<?php

namespace Kibatic\CmsBundle\Controller;

use Kibatic\CmsBundle\Ai\BlockChanges;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Kibatic\CmsBundle\Upload\ImageStorage;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Assistant of the admin: a chat with the Symfony AI agent of kibatic_cms.ai.agent, which creates and modifies blocks
 * with the tools of Kibatic\CmsBundle\Ai\Tool. The conversation is kept in the session.
 */
class ChatController extends AbstractController
{
    private const SESSION_KEY = 'kibatic_cms_chat';
    /** Messages of the conversation sent back to the model (the oldest ones are forgotten) */
    private const HISTORY_SIZE = 20;
    /** Images joined to a message (uploaded first: UploadController) */
    private const MAX_IMAGES = 4;
    public const CSRF_TOKEN_ID = 'kibatic_cms_chat';

    public function __construct(
        private readonly ?AgentInterface $agent,
        private readonly BlockRepository $blockRepository,
        private readonly BlockChanges $changes,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
        private readonly ImageStorage $imageStorage,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->denyUnlessAvailable();

        return $this->render('@KibaticCms/chat/index.html.twig', [
            'history' => $request->getSession()->get(self::SESSION_KEY, []),
            'slug' => $request->query->get('slug'),
        ]);
    }

    public function message(Request $request): JsonResponse
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

        $session = $request->getSession();
        $history = $session->get(self::SESSION_KEY, []);

        $messages = new MessageBag(Message::forSystem($this->systemPrompt($request->getLocale())));
        foreach ($history as $message) {
            $messages->add('user' === $message['role'] ? $this->userMessage($message['content'], $message['images'] ?? []) : Message::ofAssistant($message['content']));
        }
        $messages->add($this->userMessage($text, $images));

        try {
            $answer = trim((string) $this->agent->call($messages)->getContent());
        } catch (AgentException|PlatformException|HttpClientException $e) {
            $this->logger->error('CMS assistant: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return $this->json(['error' => $this->trans('chat.error.model')], Response::HTTP_BAD_GATEWAY);
        }

        $history[] = ['role' => 'user', 'content' => $text, 'images' => $images];
        $history[] = ['role' => 'assistant', 'content' => $answer];
        $session->set(self::SESSION_KEY, \array_slice($history, -self::HISTORY_SIZE));

        return $this->json([
            'answer' => $answer,
            'changes' => array_map(fn (array $change) => [
                'slug' => $change['block']->getSlug(),
                'language' => $change['block']->getLanguage(),
                'created' => $change['created'],
                'url' => $this->generateUrl('cms_block_edit', ['id' => $change['block']->getId()]),
            ], $this->changes->all()),
        ]);
    }

    public function reset(Request $request): Response
    {
        $this->denyUnlessAvailable();

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $request->getSession()->remove(self::SESSION_KEY);
        }

        return $this->redirectToRoute('cms_chat');
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

        return <<<PROMPT
            You are the assistant of the administration of a website's CMS. The website displays content blocks, each
            identified by a slug and a language, which you create and modify with your tools for the admin talking to you.

            - Always answer in the language of the admin's last message (the admin interface is in "{$locale}").
            - Languages of the existing blocks: {$this->languageList($languages)}. A block without language is shared by
              every language; a translation is a block with the same slug in another language.
            - Before modifying a block, read it with cms_get_block. Saving replaces the whole content of the block.
            - HTML blocks are displayed inside the website's pages: write a clean HTML fragment (h2, h3, p, ul, ol, li, a,
              strong, em, img), without <html>, <body>, <style>, <script>, inline styles or classes.
            - New slugs are short, lowercase, in English and with dashes (e.g. "home-intro", "footer-legal").
            - You cannot delete a block: the admin does it in the block list.
            - The admin can join images to a message: you see them, and their URLs follow the message. To put one in an
              HTML block, use its URL as is: <img src="URL" alt="short description of the image">; for an image block,
              its URL is the whole content. Never write an image in base64 (data:).
            - When the request is ambiguous (which block, which language), ask before saving.
            - After saving, say in one or two sentences what you created or changed. Do not paste the saved HTML back.
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
