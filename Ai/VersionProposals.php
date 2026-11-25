<?php

namespace Kibatic\CmsBundle\Ai;

/**
 * Actions on the versions proposed by the assistant during the request (Ai\Tool\ProposeVersionActionTool): never done
 * by it, shown under its answer as cards the admin confirms or cancels (chat/index.html.twig, ChatController)
 */
final class VersionProposals
{
    /** @var list<array{action: string, name: ?string, version: ?array{id: int, name: ?string}, changes: int}> */
    private array $proposals = [];

    /**
     * @param array{id: int, name: ?string}|null $version the version to publish or to restore
     */
    public function add(string $action, ?string $name, ?array $version, int $changes): void
    {
        $this->proposals[] = ['action' => $action, 'name' => $name, 'version' => $version, 'changes' => $changes];
    }

    public function all(): array
    {
        return $this->proposals;
    }
}
