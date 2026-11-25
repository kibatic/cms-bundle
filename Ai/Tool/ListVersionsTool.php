<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Kibatic\CmsBundle\Repository\VersionRepository;
use Kibatic\CmsBundle\Version\VersionPreview;
use Kibatic\CmsBundle\Version\Versions;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(name: self::NAME, description: self::DESCRIPTION)]
// Also a tool of the application's MCP server, when it exposes the CMS's (Resources/config/tools.yaml)
#[McpTool(name: self::NAME, description: self::DESCRIPTION, annotations: new ToolAnnotations(readOnlyHint: true))]
final class ListVersionsTool
{
    public const NAME = 'cms_list_versions';
    public const DESCRIPTION = <<<'TXT'
        Lists the versions of the CMS (copies of every block at a given time) and says which one is published (what the
        visitors see). The draft is the current blocks: when a version is published, the blocks changed since are not
        online until a new publication; the changes of the draft are listed one by one.
        TXT;

    public function __construct(
        private readonly VersionRepository $versions,
        private readonly VersionPreview $preview,
        private readonly Versions $versionService,
    ) {
    }

    public function __invoke(): string
    {
        $published = $this->preview->published();
        $changes = null === $published ? [] : array_values(array_filter(
            $this->versionService->compare($published),
            fn (array $change) => 'unchanged' !== $change['status'],
        ));

        return json_encode([
            'published' => null !== $published ? ['id' => $published->getId(), 'name' => $published->getName(), 'published_at' => $published->getPublishedAt()->format(\DATE_ATOM)] : null,
            'site' => null !== $published
                ? 'The visitors see the published version; the draft is not online.'
                : 'No version is published: the visitors see the current blocks, every change is online right away.',
            // "added": created since the published version, "removed": deleted since
            'draft_changes' => array_map(fn (array $change) => ['slug' => $change['slug'], 'language' => $change['language'], 'status' => $change['status']], $changes),
            'versions' => array_map(fn ($version) => [
                'id' => $version->getId(),
                'name' => $version->getName(),
                'created_at' => $version->getCreatedAt()->format(\DATE_ATOM),
                'published' => $version->isPublished(),
                'blocks' => $version->getBlockCount(),
            ], $this->versions->findAllByDate()),
        ], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }
}
