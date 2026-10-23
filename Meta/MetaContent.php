<?php

namespace Kibatic\CmsBundle\Meta;

/**
 * Content of a metablock (type "meta"), saved in JSON in the block's content: what it shows, with a layout
 * (BlockExtension::renderMeta()). Each item is either an existing block, by slug (shown in the page's language), or a
 * sub-metablock made in the metablock's editor and saved inside it (referenced nowhere else), which only shows
 * existing blocks: one level of nesting, and no metablock block inside a metablock.
 */
final class MetaContent
{
    public const LAYOUTS = ['stack', 'columns', 'grid', 'tabs'];
    public const COLUMNS = [2, 3, 4];

    /**
     * @param list<array{slug: string, titles: array<string, string>}|array{titles: array<string, string>, meta: self}> $items
     *        titles: label of the tab (layout "tabs") by language code ("": any language, e.g. a metablock saved before the
     *        translated titles); a sub-metablock is an item with "meta" instead of "slug"
     */
    public function __construct(
        public readonly string $layout = 'stack',
        public readonly int $columns = 2,
        public readonly array $items = [],
    ) {
    }

    /**
     * From the JSON of a block's content; anything unexpected is dropped (a block edited by hand, an old version)
     */
    public static function fromJson(?string $json): self
    {
        $data = json_decode((string) $json, true);

        return self::fromArray(\is_array($data) ? $data : []);
    }

    /**
     * From decoded JSON ({layout, columns, items}), e.g. the arguments of the assistant's tool
     *
     * @param bool $groups whether its items may be sub-metablocks (only at the first level)
     */
    public static function fromArray(array $data, bool $groups = true): self
    {
        $items = [];
        foreach (\is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $titles = [];
            // A single title: metablock saved before the translated titles
            foreach (\is_array($item['titles'] ?? null) ? $item['titles'] : ['' => $item['title'] ?? null] as $language => $title) {
                if (\is_string($title) && '' !== trim($title)) {
                    $titles[(string) $language] = mb_substr(trim($title), 0, 80);
                }
            }

            // A sub-metablock: an item with its own items
            if ($groups && \is_array($item['items'] ?? null)) {
                $items[] = ['titles' => $titles, 'meta' => self::fromArray($item, false)];
            } elseif (\is_string($item['slug'] ?? null) && '' !== $item['slug']) {
                $items[] = ['slug' => $item['slug'], 'titles' => $titles];
            }
        }

        return new self(
            \in_array($data['layout'] ?? null, self::LAYOUTS, true) ? $data['layout'] : 'stack',
            \in_array($data['columns'] ?? null, self::COLUMNS, true) ? $data['columns'] : 2,
            $items,
        );
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private function toArray(): array
    {
        return [
            'layout' => $this->layout,
            'columns' => $this->columns,
            'items' => array_map(fn (array $item) => isset($item['meta'])
                ? ['titles' => $item['titles']] + $item['meta']->toArray()
                : $item, $this->items),
        ];
    }

    /**
     * Slugs of the blocks it shows, those of its sub-metablocks included
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        $slugs = [];
        foreach ($this->items as $item) {
            array_push($slugs, ...(isset($item['meta']) ? $item['meta']->slugs() : [$item['slug']]));
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Whether a sub-metablock has no block
     */
    public function hasEmptyGroup(): bool
    {
        foreach ($this->items as $item) {
            if (isset($item['meta']) && [] === $item['meta']->items) {
                return true;
            }
        }

        return false;
    }
}
