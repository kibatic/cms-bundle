<?php

namespace Kibatic\CmsBundle\Meta;

/**
 * Content of a metablock (type "meta"), saved in JSON in the block's content: the blocks it shows, by slug (each one
 * displayed in the page's language), and their layout (MetaRenderer). A metablock contains no other metablock.
 */
final class MetaContent
{
    public const LAYOUTS = ['stack', 'columns', 'grid', 'tabs'];
    public const COLUMNS = [2, 3, 4];

    /**
     * @param list<array{slug: string, title: ?string}> $items title: label of the tab (layout "tabs")
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

        if (!\is_array($data)) {
            return new self();
        }

        $items = [];
        foreach (\is_array($data['items'] ?? null) ? $data['items'] : [] as $item) {
            if (\is_array($item) && \is_string($item['slug'] ?? null) && '' !== $item['slug']) {
                $title = \is_string($item['title'] ?? null) ? trim($item['title']) : '';
                $items[] = ['slug' => $item['slug'], 'title' => '' !== $title ? mb_substr($title, 0, 80) : null];
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
        return json_encode(['layout' => $this->layout, 'columns' => $this->columns, 'items' => $this->items], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_column($this->items, 'slug');
    }
}
