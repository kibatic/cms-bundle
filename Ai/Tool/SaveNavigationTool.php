<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\Ai\BlockChanges;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Navigation\NavigationContent;
use Kibatic\CmsBundle\Navigation\NavigationValidator;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'cms_save_navigation',
    description: <<<'TXT'
        Creates a navigation block (a menu), or replaces the entries of the existing one with this slug. A navigation is a
        tree of entries as deep as wanted, shared by every language: each entry has its label in each language of the
        site and leads to the page of a block (/page/its-slug), or is the heading of its sub-entries. Read a navigation
        with cms_get_block before modifying it: its content is this JSON, and saving replaces every entry.
        TXT,
)]
final class SaveNavigationTool
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly NavigationValidator $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly BlockChanges $changes,
    ) {
    }

    /**
     * @param string  $slug   Slug of the navigation: lowercase letters, digits, "-", "_" or "." (e.g. "footer-nav")
     * @param array   $items  The entries, in order: [{"labels": {"fr": "Équipe", "en": "Team"}, "slug": "team", "children": []}, {"labels": {"fr": "Croquettes", "en": "Kibble"}, "slug": null, "children": [{"labels": {…}, "slug": "…", "children": []}]}]. "labels": in each language of the site, at least one; "slug": the block whose page the entry leads to (any type but "navigation"), null for a heading of its sub-entries; "children": sub-entries, same format
     * @param ?string $styles CSS of the navigation only, scoped to it; null keeps the current styles, a string replaces them, an empty string removes them
     */
    public function __invoke(string $slug, array $items, ?string $styles = null): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $slug)) {
            return \sprintf('Not saved: "%s" is not a valid slug (lowercase letters, digits, "-", "_" or ".").', $slug);
        }

        $navigation = NavigationContent::fromItems($items);
        $error = $this->validator->validate($navigation);

        if (null !== $error) {
            return 'empty' === $error
                ? 'Not saved: give at least one entry, each one with a label in at least one language.'
                : 'Not saved: an entry leads to a block that does not exist, or to a navigation. Use cms_list_blocks to see the blocks.';
        }

        // Shared by every language (Block::SHARED_TYPES): the labels of every language are in its entries
        $block = $this->blocks->findOneBy(['slug' => $slug, 'language' => null]);
        $created = null === $block;

        if ($created) {
            $block = (new Block())->setSlug($slug)->setType('navigation');
            $this->entityManager->persist($block);
        } elseif ('navigation' !== $block->getType()) {
            return \sprintf('Not saved: the block "%s" is not a navigation (its type is "%s").', $slug, $block->getType());
        }

        $block->setContent($navigation->toJson());
        if (null !== $styles) {
            $block->setStyles($styles);
        }
        $this->entityManager->flush();
        $this->changes->add($block, $created);

        return \sprintf('Navigation "%s" %s.', $slug, $created ? 'created' : 'saved');
    }
}
