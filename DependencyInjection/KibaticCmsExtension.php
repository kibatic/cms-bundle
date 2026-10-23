<?php

namespace Kibatic\CmsBundle\DependencyInjection;

use Kibatic\CmsBundle\Controller\ChatController;
use Symfony\AI\Agent\AgentInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class KibaticCmsExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(dirname(__DIR__) . '/Resources/config')
        );
        $loader->load('services.yaml');

        $container->setParameter('kibatic_cms.preview_path', $config['preview_path']);
        $container->setParameter('kibatic_cms.uploads.max_size', $config['uploads']['max_size']);

        // Storage of the uploaded images (Upload\ImageStorage): the application's, or a local directory of the site
        if (null !== $config['uploads']['storage']) {
            $container->setAlias('kibatic_cms.uploads.storage', $config['uploads']['storage']);
        } else {
            $container->register('kibatic_cms.uploads.storage', Filesystem::class)
                ->setArguments([
                    (new Definition(LocalFilesystemAdapter::class))->setArguments([
                        $config['uploads']['directory'],
                        // The directories it creates, its own included, readable by the web server (private by default)
                        (new Definition(PortableVisibilityConverter::class))
                            ->setFactory([PortableVisibilityConverter::class, 'fromArray'])
                            ->setArguments([[], 'public']),
                    ]),
                    // Public: files and directories readable by the web server (Flysystem makes them private by default)
                    ['public_url' => $config['uploads']['public_path'], 'visibility' => 'public', 'directory_visibility' => 'public'],
                ]);
        }

        $agent = $config['ai']['agent'];
        $container->setParameter('kibatic_cms.ai.enabled', null !== $agent);

        if (null !== $agent) {
            if (!interface_exists(AgentInterface::class)) {
                throw new LogicException('The CMS assistant (kibatic_cms.ai.agent) requires Symfony AI: composer require symfony/ai-bundle symfony/ai-agent');
            }

            $loader->load('ai.yaml');
        }

        // Without an agent, the assistant's routes answer 404
        $container->getDefinition(ChatController::class)
            ->setArgument('$agent', null !== $agent ? new Reference($agent) : null);
    }
}
