<?php

namespace Kibatic\CmsBundle\Version;

use Kibatic\CmsBundle\Controller\BlockController;
use Kibatic\CmsBundle\Entity\Version;
use Kibatic\CmsBundle\Repository\VersionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Which blocks the site displays (cms_block()):
 * - the visitors see the published version, or the current blocks when no version is published;
 * - the CMS admins see the version they preview, or else the draft (the current blocks: when a version is published,
 *   they differ from it once a block is modified), unless they chose to see the published version.
 * The admin's choice is kept in the session; PreviewBannerListener shows it in a banner on the site's pages.
 */
final class VersionPreview implements ResetInterface
{
    private const SESSION_KEY = 'kibatic_cms_version_preview';
    /** Session value: the admin sees the published version instead of the draft */
    private const PUBLISHED = 'published';

    /** @var Version|false|null published version of the request, false: not read yet */
    private Version|false|null $published = false;
    private ?int $draftChanges = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly VersionRepository $versions,
        private readonly Versions $versionService,
        private readonly Security $security,
    ) {
    }

    /**
     * Version whose blocks cms_block() displays, null for the current blocks
     */
    public function displayed(): ?Version
    {
        if (!$this->isAdmin()) {
            return $this->published();
        }

        return $this->current() ?? (self::PUBLISHED === $this->choice() ? $this->published() : null);
    }

    /**
     * Version previewed by the admin
     */
    public function current(): ?Version
    {
        $choice = $this->isAdmin() ? $this->choice() : null;

        // find() is cached by Doctrine's identity map: one query per request
        return \is_int($choice) ? $this->versions->find($choice) : null;
    }

    public function published(): ?Version
    {
        if (false === $this->published) {
            $this->published = $this->versions->findPublished();
        }

        return $this->published;
    }

    /**
     * Number of blocks modified, added or deleted since the published version, 0 when none is published
     */
    public function draftChanges(): int
    {
        if (null === $this->draftChanges) {
            $published = $this->published();
            $this->draftChanges = null === $published ? 0 : \count(array_filter(
                $this->versionService->compare($published),
                fn (array $change) => 'unchanged' !== $change['status'],
            ));
        }

        return $this->draftChanges;
    }

    /**
     * What the admin sees on the site: "version" (a previewed version), "draft" (changes not published yet),
     * "published" (the published version, chosen while there is a draft), or null (the same as the visitors)
     */
    public function mode(): ?string
    {
        if (!$this->isAdmin()) {
            return null;
        }

        if (null !== $this->current()) {
            return 'version';
        }

        if (0 === $this->draftChanges()) {
            return null;
        }

        return self::PUBLISHED === $this->choice() ? 'published' : 'draft';
    }

    public function start(Version $version): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $version->getId());
    }

    public function showPublished(): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, self::PUBLISHED);
    }

    /**
     * Back to the default: the draft, if any
     */
    public function stop(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    public function reset(): void
    {
        $this->published = false;
        $this->draftChanges = null;
    }

    private function choice(): int|string|null
    {
        $request = $this->requestStack->getMainRequest();

        return null !== $request && $request->hasPreviousSession() ? $request->getSession()->get(self::SESSION_KEY) : null;
    }

    private function isAdmin(): bool
    {
        return $this->security->isGranted(BlockController::$role);
    }
}
