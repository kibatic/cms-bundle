<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Kibatic\CmsBundle\Ai\BlockDescriber;
use Kibatic\CmsBundle\Ai\ToolAccess;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(name: self::NAME, description: self::DESCRIPTION)]
// Also a tool of the application's MCP server, when it exposes the CMS's (Resources/config/tools.yaml)
#[McpTool(name: self::NAME, description: self::DESCRIPTION, annotations: new ToolAnnotations(readOnlyHint: true))]
final class GetBlockTool
{
    public const NAME = 'cms_get_block';
    public const DESCRIPTION = 'Returns the full content of a block, in every language, or in one language only. Read a block before modifying it.';

    public function __construct(
        private readonly ToolAccess $access,
        private readonly BlockRepository $blocks,
    ) {
    }

    /**
     * @param string $slug     Slug of the block
     * @param string $language Language code (e.g. "fr"); empty for every language of the block
     */
    public function __invoke(string $slug, string $language = ''): string
    {
        if (null !== $denied = $this->access->denied()) {
            return $denied;
        }

        $blocks = $this->blocks->findBySlug($slug);

        if ('' !== $language) {
            $blocks = array_filter($blocks, fn ($block) => $block->getLanguage() === $language);
        }

        if ([] === $blocks) {
            return sprintf('No block "%s"%s. Use cms_list_blocks to see the existing blocks.', $slug, '' !== $language ? ' in language "'.$language.'"' : '');
        }

        return json_encode(
            array_values(array_map(fn ($block) => BlockDescriber::describe($block, true), $blocks)),
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        );
    }
}
