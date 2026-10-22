<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\Ai\BlockChanges;
use Kibatic\CmsBundle\BlockTypeChain;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'cms_save_block',
    description: <<<'TXT'
        Creates a block, or replaces the content of the existing block with this slug and language. The content replaces
        the whole previous content: to change part of a block, read it first (cms_get_block) and send it back complete.
        TXT,
)]
final class SaveBlockTool
{
    /** Types whose content is a single string (the "alert" type is a form of several fields) */
    private const TYPES = ['html', 'text', 'image'];

    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly BlockTypeChain $blockTypes,
        private readonly EntityManagerInterface $entityManager,
        private readonly BlockChanges $changes,
    ) {
    }

    /**
     * @param string $slug     Slug of the block: lowercase letters, digits, "-", "_" or "." (e.g. "home-intro")
     * @param string $content  Content of the block: an HTML fragment for "html" (no <html>, <head> or <body>), plain text for "text", the image URL for "image"
     * @param string $type     Type of a new block: "html" (rich text, the default), "text" (plain text) or "image"; ignored for an existing block, which keeps its type
     * @param string $language Language code of the block (e.g. "fr", "en"); empty for a block shared by every language
     */
    public function __invoke(string $slug, string $content, string $type = 'html', string $language = ''): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $slug)) {
            return sprintf('Not saved: "%s" is not a valid slug (lowercase letters, digits, "-", "_" or ".").', $slug);
        }

        if ('' !== $language && !preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $language)) {
            return sprintf('Not saved: "%s" is not a language code (e.g. "fr", "en").', $language);
        }

        $block = $this->blocks->findOneBy(['slug' => $slug, 'language' => '' !== $language ? $language : null]);
        $created = null === $block;

        if ($created) {
            if (!\in_array($type, self::TYPES, true) || !\in_array($type, $this->blockTypes->getBlockTypeNames(), true)) {
                return sprintf('Not saved: the type must be one of %s.', implode(', ', self::TYPES));
            }

            $block = (new Block())
                ->setSlug($slug)
                ->setLanguage('' !== $language ? $language : null)
                ->setType($type);
            $this->entityManager->persist($block);
        } elseif (!\in_array($block->getType(), self::TYPES, true)) {
            return sprintf('Not saved: blocks of type "%s" are edited in the admin only.', $block->getType());
        }

        if ('image' === $block->getType() && str_starts_with($content, 'data:')) {
            return 'Not saved: give the URL of an image. Images from the computer are added in the admin.';
        }

        $block->setContent($content);
        $this->entityManager->flush();
        $this->changes->add($block, $created);

        return sprintf('Block "%s"%s %s.', $slug, '' !== $language ? ' ('.$language.')' : '', $created ? 'created' : 'saved');
    }
}
