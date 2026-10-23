<?php

namespace Kibatic\CmsBundle\Ai;

use Kibatic\CmsBundle\Entity\Block;

/**
 * A block as the assistant's tools return it. Images saved inside a block (data: URLs) are replaced by a short
 * mention: their base64 would fill the model's context for nothing.
 */
final class BlockDescriber
{
    public static function describe(Block $block, bool $withContent): array
    {
        $content = (string) $block->getContent();

        if (str_starts_with($content, 'data:')) {
            $content = sprintf('[image saved inside the block, %d KB]', strlen($content) * 3 / 4 / 1024);
        }

        return [
            'slug' => $block->getSlug(),
            'language' => $block->getLanguage(),
            'type' => $block->getType(),
            'updated_at' => $block->getUpdatedAt()->format(\DATE_ATOM),
        ] + ($withContent
            ? ['content' => $content, 'styles' => $block->getStyles()]
            : ['excerpt' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', strip_tags($content))), 0, 100, '…')]);
    }
}
