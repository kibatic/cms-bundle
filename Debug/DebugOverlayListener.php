<?php

namespace Kibatic\CmsBundle\Debug;

use Kibatic\CmsBundle\Twig\BlockExtension;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

/**
 * Debug mode of a CMS admin: adds to the site's HTML pages the overlay that outlines each block, from the comments
 * around them (BlockExtension::render()), and the pill to leave the debug mode
 */
#[AsEventListener]
final class DebugOverlayListener
{
    public function __construct(
        private readonly BlockExtension $blocks,
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$event->isMainRequest()
            || str_starts_with((string) $event->getRequest()->attributes->get('_route'), 'cms_')
            && 'cms_page' !== $event->getRequest()->attributes->get('_route')
            || !str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')
            || false === ($position = strripos((string) $response->getContent(), '</body>'))
            || !$this->blocks->isDebug()) {
            return;
        }

        $content = $response->getContent();
        $response->setContent(substr($content, 0, $position).$this->twig->render('@KibaticCms/_debug_overlay.html.twig').substr($content, $position));
    }
}
