<?php

namespace Kibatic\CmsBundle\Version;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

/**
 * Banner of the CMS admins at the bottom of the site's HTML pages (VersionPreview::mode()): version preview (exit,
 * restore), draft preview (publish, see the published version) or published version (see the draft). The admin pages
 * show the version preview banner themselves (layout.html.twig).
 */
#[AsEventListener]
final class PreviewBannerListener
{
    public function __construct(
        private readonly VersionPreview $preview,
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

        $banner = match ($mode = $this->preview->mode()) {
            'version' => $this->twig->render('@KibaticCms/version/_preview_banner.html.twig', ['version' => $this->preview->current()]),
            'draft', 'published' => $this->twig->render('@KibaticCms/version/_draft_banner.html.twig', [
                'mode' => $mode,
                'published' => $this->preview->published(),
                'changes' => $this->preview->draftChanges(),
            ]),
            default => null,
        };

        if (null === $banner) {
            return;
        }

        $content = $response->getContent();
        $response->setContent(substr($content, 0, $position).$banner.substr($content, $position));
    }
}
