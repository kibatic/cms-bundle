<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\Ai\BlockChanges;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Meta\MetaContent;
use Kibatic\CmsBundle\Meta\MetaValidator;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'cms_save_metablock',
    description: <<<'TXT'
        Creates a metablock, or replaces the content of the existing metablock with this slug and language. A metablock
        shows existing blocks (by slug, each one in the page's language) together with a layout, and may contain
        sub-metablocks: groups made inside it, with their own layout, that show existing blocks only. Read a metablock
        with cms_get_block before modifying it: its content is this JSON, and saving replaces it whole.
        TXT,
)]
final class SaveMetablockTool
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly MetaValidator $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly BlockChanges $changes,
    ) {
    }

    /**
     * @param string $slug     Slug of the metablock: lowercase letters, digits, "-", "_" or "." (e.g. "home-sections")
     * @param array  $items    What it shows, in order. Each item is either an existing block: {"slug": "home-intro", "titles": {"fr": "Titre de l'onglet", "en": "Tab title"}}, or a sub-metablock: {"titles": {…}, "layout": "columns", "columns": 2, "items": [{"slug": "…", "titles": {}}]}. Titles (labels of the tabs) in each language of the site, {} without tabs. A sub-metablock contains existing blocks only, at least one; no block of type "meta" anywhere
     * @param string $layout   Layout of the metablock: "stack" (one after the other), "columns", "grid" (cards) or "tabs" (one tab per item, labelled with its title, else its slug)
     * @param int    $columns  Number of columns (2, 3 or 4) for the layout "columns"
     * @param ?string $styles  CSS of the metablock only, scoped to it (e.g. a background around its blocks); null keeps the current styles, a string replaces them, an empty string removes them
     */
    public function __invoke(string $slug, array $items, string $layout = 'stack', int $columns = 2, ?string $styles = null): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $slug)) {
            return \sprintf('Not saved: "%s" is not a valid slug (lowercase letters, digits, "-", "_" or ".").', $slug);
        }

        if (!\in_array($layout, MetaContent::LAYOUTS, true)) {
            return \sprintf('Not saved: the layout must be one of %s.', implode(', ', MetaContent::LAYOUTS));
        }

        $meta = MetaContent::fromArray(['layout' => $layout, 'columns' => $columns, 'items' => $items]);
        $error = $this->validator->validate($meta);

        if (null !== $error) {
            return 'empty' === $error
                ? 'Not saved: give at least one item, and at least one block in each sub-metablock.'
                : 'Not saved: an item is not an existing block, or is a metablock (use a sub-metablock instead). Use cms_list_blocks to see the blocks.';
        }

        // Shared by every language (Block::SHARED_TYPES): its blocks are shown in the page's language, its titles are translated
        $block = $this->blocks->findOneBy(['slug' => $slug, 'language' => null]);
        $created = null === $block;

        if ($created) {
            $block = (new Block())->setSlug($slug)->setType('meta');
            $this->entityManager->persist($block);
        } elseif ('meta' !== $block->getType()) {
            return \sprintf('Not saved: the block "%s" is not a metablock (its type is "%s"); use cms_save_block for it.', $slug, $block->getType());
        }

        $block->setContent($meta->toJson());
        if (null !== $styles) {
            $block->setStyles($styles);
        }
        $this->entityManager->flush();
        $this->changes->add($block, $created);

        return \sprintf('Metablock "%s" %s.', $slug, $created ? 'created' : 'saved');
    }
}
