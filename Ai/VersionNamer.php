<?php

namespace Kibatic\CmsBundle\Ai;

use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Entity\Version;
use Kibatic\CmsBundle\Repository\VersionRepository;
use Kibatic\CmsBundle\Version\VersionPreview;
use Kibatic\CmsBundle\Version\Versions;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Exception\ExceptionInterface as AgentException;
use Symfony\AI\Platform\Exception\ExceptionInterface as PlatformException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

/**
 * Very short name of a new version, suggested by the assistant's agent from what changed (VersionController::draftName()):
 * since the published version for the publication of the draft, since the last version for a new version
 */
final class VersionNamer
{
    /** Characters of each block's content shown to the model, before and after */
    private const EXCERPT = 300;

    public function __construct(
        private readonly ?AgentInterface $agent,
        private readonly Versions $versions,
        private readonly VersionPreview $preview,
        private readonly VersionRepository $versionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Name of the draft published: its changes since the published version
     *
     * @return string|null null without assistant, without change, or when the model does not answer
     */
    public function suggestDraftName(string $locale): ?string
    {
        return $this->suggestName($locale, $this->preview->published());
    }

    /**
     * Name of a new version of the current blocks: their changes since the last version
     */
    public function suggestNewVersionName(string $locale): ?string
    {
        return $this->suggestName($locale, $this->versionRepository->findAllByDate()[0] ?? null);
    }

    /**
     * @param Version|null $since null: every current block is new
     */
    private function suggestName(string $locale, ?Version $since): ?string
    {
        if (null === $this->agent) {
            return null;
        }

        $changes = array_filter(
            $this->versions->compare($since ?? new Version(null, [])),
            fn (array $change) => 'unchanged' !== $change['status'],
        );

        if ([] === $changes) {
            return null;
        }

        $list = implode("\n", array_map(fn (array $change) => sprintf(
            '- %s "%s"%s: before: %s / after: %s',
            $change['status'],
            $change['slug'],
            null !== $change['language'] ? ' ('.$change['language'].')' : '',
            self::excerpt($change['version']),
            self::excerpt($change['current']),
        ), $changes));

        try {
            $name = (string) $this->agent->call(new MessageBag(
                Message::forSystem('You name versions of the content of a website. Do not use any tool.'),
                Message::ofUser(<<<PROMPT
                    Give a very short name (2 to 5 words, in the language "{$locale}") for the version of the website's content
                    made of these changes, that says what changed (e.g. "Theme dogs", "New kitten recipe"). Answer with the
                    name only, without introduction, quotes, Markdown or final punctuation.

                    {$list}
                    PROMPT),
            ))->getContent();
        } catch (AgentException|PlatformException|HttpClientException $e) {
            $this->logger->warning('CMS version name: {message}', ['message' => $e->getMessage(), 'exception' => $e]);

            return null;
        }

        // First line, without what the model may add anyway: an introduction ("Suggested name: …"), Markdown, quotes, a dot
        $name = strtok(trim($name), "\n") ?: '';
        $name = str_contains($name, ':') ? substr($name, strrpos($name, ':') + 1) : $name;
        $name = trim(str_replace(['**', '__', '`'], '', $name), " \t\"'«»“”.*_");

        return '' !== $name ? mb_substr($name, 0, 80) : null;
    }

    private static function excerpt(?Block $block): string
    {
        if (null === $block) {
            return '(none)';
        }

        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $block->getContent())));

        return '"'.mb_strimwidth($text, 0, self::EXCERPT, '…').'"';
    }
}
