<?php

namespace Kibatic\CmsBundle\Ai;

use Kibatic\CmsBundle\Entity\Block;

/**
 * Blocks created or modified by the assistant's tools during the request, listed under its answer (ChatController).
 */
final class BlockChanges
{
    /** @var array<int, array{block: Block, created: bool}> by block id */
    private array $changes = [];

    public function add(Block $block, bool $created): void
    {
        // A block created then modified in the same answer stays "created"
        $this->changes[$block->getId()] = [
            'block' => $block,
            'created' => ($this->changes[$block->getId()]['created'] ?? false) || $created,
        ];
    }

    /**
     * @return list<array{block: Block, created: bool}>
     */
    public function all(): array
    {
        return array_values($this->changes);
    }
}
