<?php

namespace Kibatic\CmsBundle;

use Kibatic\CmsBundle\Form\BlockTypeInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

class BlockTypeChain
{
    /**
     * @var BlockTypeInterface[]
     */
    private $blockTypes = [];

    public function __construct(#[AutowireIterator('cms.block_type')] iterable $blockTypes)
    {
        foreach ($blockTypes as $blockType) {
            $this->addBlockType($blockType);
        }
    }

    public function addBlockType(BlockTypeInterface $blockType)
    {
        $this->blockTypes[$blockType::getBlockTypeName()] = $blockType;
    }

    public function getBlockType(string $typeName): BlockTypeInterface
    {
        return $this->blockTypes[$typeName];
    }

    public function getBlockTypes(): array
    {
        return $this->blockTypes;
    }

    public function getBlockTypeNames(): array
    {
        $names = [];

        foreach ($this->blockTypes as $blockType) {
            $names[] = $blockType::getBlockTypeName();
        }

        return $names;
    }
}
