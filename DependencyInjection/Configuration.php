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
                ->arrayNode('uploads')
                    ->info('Images uploaded in the admin (image blocks, assistant)')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('storage')
                            ->defaultNull()
                            ->info('Service ID of a Flysystem storage of the application (e.g. "cms.storage" of flysystem.yaml, on S3), with a public URL. Null: local directory below')
                        ->end()
                        ->scalarNode('directory')->defaultValue('%kernel.project_dir%/public/uploads/cms')->info('Local storage only')->end()
                        ->scalarNode('public_path')->defaultValue('/uploads/cms')->info('Local storage only: URL of the directory')->end()
                        ->integerNode('max_size')->defaultValue(5 * 1024 * 1024)->info('In bytes; PHP\'s upload_max_filesize must allow it')->end()
                    ->end()
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
