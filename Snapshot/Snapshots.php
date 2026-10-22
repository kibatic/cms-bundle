<?php

namespace Kibatic\CmsBundle\Snapshot;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Entity\Snapshot;
use Kibatic\CmsBundle\Repository\BlockRepository;

/**
 * Takes snapshots of the blocks, compares them to the current blocks and restores them.
 * A block is identified by its slug and its language (unique index of cms_block).
 */
final class Snapshots
{
    public function __construct(
        private readonly BlockRepository $blocks,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function take(?string $name): Snapshot
    {
        $snapshot = new Snapshot($name, $this->blocks->findAll());
        $this->entityManager->persist($snapshot);
        $this->entityManager->flush();

        return $snapshot;
    }

    /**
     * Makes the blocks exactly those of the snapshot: modifies the blocks that exist in both, creates the missing ones
     * and deletes the others. Take a snapshot of the current blocks first to be able to come back.
     */
    public function restore(Snapshot $snapshot): void
    {
        $current = self::byKey($this->blocks->findAll());

        foreach (self::byKey($snapshot->getBlocks()) as $key => $saved) {
            $block = $current[$key] ?? null;
            unset($current[$key]);

            if (null === $block) {
                $block = (new Block())
                    ->setSlug($saved->getSlug())
                    ->setLanguage($saved->getLanguage())
                    ->setCreatedAt($saved->getCreatedAt());
                $this->entityManager->persist($block);
            }

            $block->setType($saved->getType())->setContent($saved->getContent());
        }

        foreach ($current as $block) {
            $this->entityManager->remove($block);
        }

        $this->entityManager->flush();
    }

    /**
     * What changed since the snapshot, block by block
     *
     * @return list<array{slug: string, language: ?string, status: 'unchanged'|'modified'|'added'|'removed', snapshot: ?Block, current: ?Block}>
     *         "added": created since the snapshot (a restore deletes it), "removed": deleted since (a restore brings it back)
     */
    public function compare(Snapshot $snapshot): array
    {
        $saved = self::byKey($snapshot->getBlocks());
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
                    $before->getContent() !== $now->getContent() || $before->getType() !== $now->getType() => 'modified',
                    default => 'unchanged',
                },
                'snapshot' => $before,
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
