<?php

namespace Kibatic\CmsBundle\Ai;

use Kibatic\CmsBundle\Ai\Tool\ListBlocksTool;
use Symfony\AI\McpBundle\DependencyInjection\ElementMatcher;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Address of the application's MCP server that exposes the CMS's tools (symfony/mcp-bundle, README "MCP tools"), for
 * the "Install on Claude" dialog of the assistant (chat/_mcp_dialog.html.twig)
 */
final class McpEndpoint
{
    public function __construct(
        private readonly ParameterBagInterface $parameters,
        private readonly RouterInterface $router,
    ) {
    }

    /**
     * @return string|null null without MCP server exposing the CMS's tools over HTTP
     */
    public function url(): ?string
    {
        // Set by symfony/mcp-bundle when the application has MCP servers: their elements by server and kind
        if (!$this->parameters->has('mcp.servers.elements')) {
            return null;
        }

        $servers = (new ElementMatcher($this->parameters->get('mcp.servers.elements')))
            ->match('tools', ListBlocksTool::class, ListBlocksTool::class);

        foreach ($servers as $server) {
            // Route of the servers exposed over HTTP (Symfony\AI\McpBundle\Routing\RouteLoader)
            $route = '_mcp_endpoint_'.$server;
            if (null !== $this->router->getRouteCollection()->get($route)) {
                return $this->router->generate($route, [], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }

        return null;
    }
}
