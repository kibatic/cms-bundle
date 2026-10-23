<?php

namespace Kibatic\CmsBundle\Navigation;

/**
 * Content of a navigation block (type "navigation"), saved in JSON in the block's content: a tree of entries, as deep
 * as wanted, rendered as a menu (BlockExtension::renderNavigation()). An entry has a label in each language and links to
 * the page of a block (PageController); without block, it is a heading of its sub-entries.
 */
final class NavigationContent
{
    /**
     * @param list<array{labels: array<string, string>, slug: ?string, children: list<array>}> $items labels by language
     *        code ("" when the site has no language)
     */
    public function __construct(
        public readonly array $items = [],
    ) {
    }

    /**
     * From the JSON of a block's content; anything unexpected is dropped
     */
    public static function fromJson(?string $json): self
    {
        $data = json_decode((string) $json, true);

        return new self(self::items(\is_array($data['items'] ?? null) ? $data['items'] : []));
    }

    private static function items(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $labels = [];
            foreach (\is_array($item['labels'] ?? null) ? $item['labels'] : [] as $language => $label) {
                if (\is_string($label) && '' !== trim($label)) {
                    $labels[(string) $language] = mb_substr(trim($label), 0, 80);
                }
            }

            $normalized[] = [
                'labels' => $labels,
                'slug' => \is_string($item['slug'] ?? null) && '' !== $item['slug'] ? $item['slug'] : null,
                'children' => self::items(\is_array($item['children'] ?? null) ? $item['children'] : []),
            ];
        }

        return $normalized;
    }

    public function toJson(): string
    {
        return json_encode(['items' => $this->items], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * Slugs of the blocks its entries link to, at every level
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        $slugs = [];
        $walk = function (array $items) use (&$walk, &$slugs): void {
            foreach ($items as $item) {
                null !== $item['slug'] && $slugs[] = $item['slug'];
                $walk($item['children']);
            }
        };
        $walk($this->items);

        return array_values(array_unique($slugs));
    }

    /**
     * Whether an entry, at any level, has no label
     */
    public function hasUnlabelledItem(): bool
    {
        $walk = function (array $items) use (&$walk): bool {
            foreach ($items as $item) {
                if ([] === $item['labels'] || $walk($item['children'])) {
                    return true;
                }
            }

            return false;
        };

        return $walk($this->items);
    }
}
