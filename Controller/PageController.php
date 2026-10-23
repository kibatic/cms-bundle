<?php

namespace Kibatic\CmsBundle\Controller;

use Kibatic\CmsBundle\Twig\BlockExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Page of a block, in the site's layout: where the entries of a navigation block lead (BlockExtension::renderNavigation()).
 * The block of the displayed version, like everywhere on the site (published version, draft or preview).
 */
class PageController extends AbstractController
{
    public function __construct(
        private readonly BlockExtension $blocks,
    ) {
    }

    public function show(string $slug): Response
    {
        if (!$this->blocks->exists($slug)) {
            throw $this->createNotFoundException(\sprintf('No block "%s".', $slug));
        }

        return $this->render('@KibaticCms/page.html.twig', ['slug' => $slug]);
    }
}
