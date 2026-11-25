<?php

namespace Kibatic\CmsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Copy of every block at a given time (Version\Versions), to preview the site as it was or to restore it.
 * The published version, if any, is what the site displays (Version\VersionPreview::displayed()).
 */
#[ORM\Entity]
#[ORM\Table(name: 'cms_version')]
class Version
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** Null: not published; only one version is published at a time (Versions::publish()) */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /**
     * @var list<array{slug: string, language: ?string, type: string, content: ?string, styles?: ?string, meta_only?: bool, created_at: string, updated_at: string}>
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
            'styles' => $block->getStyles(),
            'meta_only' => $block->isMetaOnly(),
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

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    /**
     * The blocks of the version as Block objects, never persisted (no id)
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
            // Versions saved before the styles of the blocks have none
            ->setStyles($data['styles'] ?? null)
            ->setMetaOnly($data['meta_only'] ?? false)
            ->setCreatedAt(new \DateTime($data['created_at']))
            ->setUpdatedAt(new \DateTime($data['updated_at'])), $this->blocks);
    }

    /**
     * Block of the version with this slug and language (null: shared by every language)
     */
    public function getBlock(string $slug, ?string $language): ?Block
    {
        foreach ($this->getBlocks() as $block) {
            if ($block->getSlug() === $slug && $block->getLanguage() === $language) {
                return $block;
            }
        }

        return null;
    }

    /**
     * Replaces the content and the styles of a block of the version (VersionController::editBlock()); its slug,
     * language and type stay
     */
    public function setBlockContent(string $slug, ?string $language, ?string $content, ?string $styles): void
    {
        foreach ($this->blocks as $i => $data) {
            if ($data['slug'] === $slug && $data['language'] === $language) {
                $this->blocks[$i]['content'] = $content;
                $this->blocks[$i]['styles'] = $styles;
                $this->blocks[$i]['updated_at'] = (new \DateTimeImmutable())->format(\DATE_ATOM);
                $this->blockObjects = null;

                return;
            }
        }
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
