<?php

namespace Kibatic\CmsBundle\Entity;

use Kibatic\CmsBundle\Form\TextBlockType;
use Webmozart\Assert\Assert;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
#[ORM\Table(name: 'cms_block')]
#[ORM\UniqueConstraint(name: 'block_unique', columns: ['slug', 'language'])]
class Block
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string')]
    private string $slug;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private string $type = TextBlockType::class;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'create')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    private \DateTime $updatedAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $language = null;

    /**
     * CSS of this block only: cms_block() scopes it to the block (Twig\BlockExtension::render()), so that it looks the
     * same on every page; declarations apply to the block itself, nested rules to its content
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $styles = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setCreatedAt($createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setUpdatedAt($updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setSlug($slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getStyles(): ?string
    {
        return $this->styles;
    }

    public function setStyles(?string $styles): static
    {
        $styles = trim((string) $styles);
        $this->styles = '' !== $styles ? $styles : null;

        return $this;
    }
}
