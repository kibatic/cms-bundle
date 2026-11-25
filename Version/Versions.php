<?php

namespace Kibatic\CmsBundle\Version;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Entity\Version;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Kibatic\CmsBundle\Repository\VersionRepository;

/**
 * Takes versions of the blocks, compares them to the current blocks and restores them.
 * A block is identified by its slug and its language (unique index of cms_block).
 */
final class Versions
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly VersionRepository $versions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function take(?string $name): Version
    {
        $version = new Version($name, $this->blocks->findAll());
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $version;
    }

    /**
     * The site displays the blocks of this version, whatever the changes made since
     */
    public function publish(Version $version): void
    {
        $this->unpublish();
        $version->setPublishedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * The site displays the current blocks again
     */
    public function unpublish(): void
    {
        $this->versions->findPublished()?->setPublishedAt(null);
        $this->entityManager->flush();
    }

    /**
     * Makes the blocks exactly those of the version: modifies the blocks that exist in both, creates the missing ones
     * and deletes the others. Take a version of the current blocks first to be able to come back.
     */
    public function restore(Version $version): void
    {
        $current = self::byKey($this->blocks->findAll());

        foreach (self::byKey($version->getBlocks()) as $key => $saved) {
            $block = $current[$key] ?? null;
            unset($current[$key]);

            if (null === $block) {
                $block = (new Block())
                    ->setSlug($saved->getSlug())
                    ->setLanguage($saved->getLanguage())
                    ->setCreatedAt($saved->getCreatedAt());
                $this->entityManager->persist($block);
            }

            $block->setType($saved->getType())->setContent($saved->getContent())->setStyles($saved->getStyles())
                ->setMetaOnly($saved->isMetaOnly());
        }

        foreach ($current as $block) {
            $this->entityManager->remove($block);
        }

        $this->entityManager->flush();
    }

    /**
     * What changed since the version, block by block
     *
     * @return list<array{slug: string, language: ?string, status: 'unchanged'|'modified'|'added'|'removed', version: ?Block, current: ?Block}>
     *         "added": created since the version (a restore deletes it), "removed": deleted since (a restore brings it back)
     */
    public function compare(Version $version): array
    {
        $saved = self::byKey($version->getBlocks());
        $current = self::byKey($this->blocks->findAll());
        $keys = array_unique([...array_keys($saved), ...array_keys($current)]);
        sort($keys);

        return array_map(function (string $key) use ($saved, $current) {
            $before = $saved[$key] ?? null;
            $now = $current[$key] ?? null;

            return [
                'slug' => ($before ?? $now)->getSlug(),
                'language' => ($before ?? $now)->getLanguage(),
                'status' => match (true) {
                    null === $before => 'added',
                    null === $now => 'removed',
                    $before->getContent() !== $now->getContent() || $before->getType() !== $now->getType()
                        || $before->getStyles() !== $now->getStyles() || $before->isMetaOnly() !== $now->isMetaOnly() => 'modified',
                    default => 'unchanged',
                },
                'version' => $before,
                'current' => $now,
            ];
        }, $keys);
    }

    /**
     * @param list<Block> $blocks
     *
     * @return array<string, Block>
     */
    private static function byKey(array $blocks): array
    {
        $byKey = [];
        foreach ($blocks as $block) {
            $byKey[$block->getSlug()."\0".$block->getLanguage()] = $block;
        }

        return $byKey;
    }
}
