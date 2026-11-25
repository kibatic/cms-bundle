<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Kibatic\CmsBundle\Ai\ContentRules;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

/**
 * The rules the CMS's assistant gets in its prompt (ChatController), for the MCP clients of the application, which
 * do not get them otherwise
 */
#[McpTool(
    name: 'cms_guide',
    description: 'How to write the blocks of the CMS (languages, HTML, styles, metablocks, navigations, versions) and the website\'s style guide. Call it once before creating or modifying blocks with the other cms_ tools.',
    annotations: new ToolAnnotations(readOnlyHint: true),
)]
final class GuideTool
{
    public function __construct(
        private readonly ContentRules $rules,
    ) {
    }

    public function __invoke(): string
    {
        return $this->rules->text()."\n- You cannot save, publish, restore or unpublish a version: the admin does it in the CMS's administration (Versions).";
    }
}
