<?php

namespace Kibatic\CmsBundle\Ai;

use Kibatic\CmsBundle\Controller\BlockController;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Access to the CMS's tools: the CMS's admins only (BlockController::$role), whoever calls them.
 * The assistant is already restricted to them (ChatController), an MCP server of the application may not be.
 */
final class ToolAccess
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @return string|null the answer of the tool when the caller is not allowed, null when it is
     */
    public function denied(): ?string
    {
        // Console, e.g. the stdio transport of an MCP server: no user, run by someone with a shell on the server
        if ('cli' === \PHP_SAPI) {
            return null;
        }

        return $this->authorizationChecker->isGranted(BlockController::$role)
            ? null
            : 'Not allowed: the CMS\'s tools are for the CMS\'s admins only.';
    }
}
