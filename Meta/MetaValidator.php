<?php

namespace Kibatic\CmsBundle\Meta;

use Kibatic\CmsBundle\Repository\BlockRepository;

/**
 * Rules of a metablock's content, for its editor (Form\MetaBlockType) and the assistant (Ai\Tool\SaveMetablockTool):
 * at least one item, only existing blocks, no metablock block (only sub-metablocks), no empty sub-metablock
 */
final class MetaValidator
{
    public function __construct(
        private readonly BlockRepository $blocks,
    ) {
    }

    /**
     * @return 'empty'|'invalid'|null the error, null when valid
     */
    public function validate(MetaContent $meta): ?string
    {
        if ([] === $meta->items || $meta->hasEmptyGroup()) {
            return 'empty';
        }

        $types = [];
        foreach ($this->blocks->findBy(['slug' => $meta->slugs()]) as $block) {
            $types[$block->getSlug()] = $block->getType();
        }

        foreach ($meta->slugs() as $slug) {
            if (!isset($types[$slug]) || 'meta' === $types[$slug]) {
                return 'invalid';
            }
        }

        return null;
    }
}
