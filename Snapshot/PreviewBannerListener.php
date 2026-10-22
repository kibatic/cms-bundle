<?php

namespace Kibatic\CmsBundle\Snapshot;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

/**
 * During a snapshot preview, adds a banner at the bottom of the site's HTML pages (exit, restore). The admin pages
 * show it themselves (layout.html.twig).
 */
#[AsEventListener]
final class PreviewBannerListener
{
    public function __construct(
        private readonly SnapshotPreview $preview,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $route = (string) $event->getRequest()->attributes->get('_route');

        if (!$event->isMainRequest()
            || str_starts_with($route, 'cms_')
            || !str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')
            || false === $position = strripos((string) $response->getContent(), '</body>')) {
            return;
        }

        $snapshot = $this->preview->current();

        if (null === $snapshot) {
            return;
        }

        $content = $response->getContent();
        $banner = $this->twig->render('@KibaticCms/snapshot/_preview_banner.html.twig', ['snapshot' => $snapshot]);
        $response->setContent(substr($content, 0, $position).$banner.substr($content, $position));
    }
}
