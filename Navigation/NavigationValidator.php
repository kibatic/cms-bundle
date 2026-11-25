<?php

namespace Kibatic\CmsBundle\Navigation;

use Kibatic\CmsBundle\Repository\BlockRepository;

/**
 * Rules of a navigation's content, for its editor (Form\NavigationBlockType) and the assistant
 * (Ai\Tool\SaveNavigationTool): at least one entry, each one with a label, linking to existing blocks (no navigation)
 */
final class NavigationValidator
{
    public function __construct(
        private readonly BlockRepository $blocks,
    ) {
    }

    /**
     * @return 'empty'|'invalid'|null the error, null when valid
     */
    public function validate(NavigationContent $navigation): ?string
    {
        if ([] === $navigation->items || $navigation->hasUnlabelledItem()) {
            return 'empty';
        }

        $types = [];
        foreach ($this->blocks->findBy(['slug' => $navigation->slugs()]) as $block) {
            $types[$block->getSlug()] = $block->getType();
        }

        foreach ($navigation->slugs() as $slug) {
            if (!isset($types[$slug]) || 'navigation' === $types[$slug]) {
                return 'invalid';
            }
        }

        return null;
    }
}
