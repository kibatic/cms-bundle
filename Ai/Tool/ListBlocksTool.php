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
final class ListBlocksTool
{
    public const NAME = 'cms_list_blocks';
    public const DESCRIPTION = 'Lists every content block of the CMS: slug, language (null: shared by every language), type, last update, whether it has its own styles, whether it is made only for a metablock (meta_only), and the beginning of its text.';

    public function __construct(
        private readonly ToolAccess $access,
        private readonly BlockRepository $blocks,
    ) {
    }

    public function __invoke(): string
    {
        if (null !== $denied = $this->access->denied()) {
            return $denied;
        }

        return json_encode(
            array_map(fn ($block) => BlockDescriber::describe($block, false), $this->blocks->findAll()),
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        );
    }
}
