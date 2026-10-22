<?php

namespace Kibatic\CmsBundle\Snapshot;

use Kibatic\CmsBundle\Controller\BlockController;
use Kibatic\CmsBundle\Entity\Snapshot;
use Kibatic\CmsBundle\Repository\SnapshotRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Snapshot previewed by the CMS admin: cms_block() then displays its blocks instead of the current ones, for this
 * admin only (session), with a banner on the site's pages (PreviewBannerListener).
 */
final class SnapshotPreview
{
    private const SESSION_KEY = 'kibatic_cms_snapshot_preview';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SnapshotRepository $snapshots,
        private readonly Security $security,
    ) {
    }

    public function current(): ?Snapshot
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request || !$request->hasPreviousSession() || !$this->security->isGranted(BlockController::$role)) {
            return null;
        }

        $id = $request->getSession()->get(self::SESSION_KEY);

        // find() is cached by Doctrine's identity map: one query per request
        return null !== $id ? $this->snapshots->find($id) : null;
    }

    public function start(Snapshot $snapshot): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $snapshot->getId());
    }

    public function stop(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }
}
