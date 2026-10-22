<?php

namespace Kibatic\CmsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('kibatic_cms');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('preview_path')
                    ->defaultValue('/')
                    ->info('Page of the site opened by the preview of a version')
                ->end()
                ->arrayNode('ai')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('agent')
                            ->defaultNull()
                            ->info('Service ID of the Symfony AI agent of the assistant (e.g. "ai.agent.cms"), with the tools of Kibatic\CmsBundle\Ai\Tool. Null: no assistant.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
