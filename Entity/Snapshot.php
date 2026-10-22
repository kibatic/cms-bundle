<?php

namespace Kibatic\CmsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Copy of every block at a given time (Snapshot\Snapshots), to preview the site as it was or to restore it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_snapshot')]
class Snapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var list<array{slug: string, language: ?string, type: string, content: ?string, created_at: string, updated_at: string}>
     */
    #[ORM\Column(type: 'json')]
    private array $blocks;

    /** @var list<Block>|null blocks rebuilt from $blocks, not persisted */
    private ?array $blockObjects = null;

    /**
     * @param list<Block> $blocks
     */
    public function __construct(?string $name, array $blocks)
    {
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->blocks = array_map(fn (Block $block) => [
            'slug' => $block->getSlug(),
            'language' => $block->getLanguage(),
            'type' => $block->getType(),
            'content' => $block->getContent(),
            'created_at' => $block->getCreatedAt()->format(\DATE_ATOM),
            'updated_at' => $block->getUpdatedAt()->format(\DATE_ATOM),
        ], $blocks);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * The blocks of the snapshot as Block objects, never persisted (no id)
     *
     * @return list<Block>
     */
    public function getBlocks(): array
    {
        return $this->blockObjects ??= array_map(fn (array $data) => (new Block())
            ->setSlug($data['slug'])
            ->setLanguage($data['language'])
            ->setType($data['type'])
            ->setContent($data['content'])
            ->setCreatedAt(new \DateTime($data['created_at']))
            ->setUpdatedAt(new \DateTime($data['updated_at'])), $this->blocks);
    }

    public function getBlockCount(): int
    {
        return \count($this->blocks);
    }

    /** Size of the blocks' contents, in bytes */
    public function getSize(): int
    {
        return array_sum(array_map(fn (array $data) => \strlen((string) $data['content']), $this->blocks));
    }

    /**
     * @return list<string>
     */
    public function getLanguages(): array
    {
        $languages = array_values(array_unique(array_filter(array_column($this->blocks, 'language'))));
        sort($languages);

        return $languages;
    }
}
