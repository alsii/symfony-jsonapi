<?php

namespace NilPortugues\Symfony\JsonApiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;
use NilPortugues\Api\Mapping\Mapper;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class NilPortuguesSymfonyJsonApiExtension extends Extension
{
    /**
     * {@inheritdoc}
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        $this->setMappings($container, $config);
        $this->setAttributesCase($container, $config);
    }

    /**
     * @param ContainerBuilder $container
     * @param $config
     * @throws \ReflectionException
     */
    private function setMappings(ContainerBuilder $container, $config)
    {
        $definition = new Definition();
        $definition->setClass(Mapper::class);
        $args = $this->resolveMappings($container, $config['mappings']);
        $definition->setArguments($args);
        $definition->setLazy(true);

        $container->setDefinition('nil_portugues.api.mapping.mapper', $definition);
    }

    /**
     * @param ContainerBuilder $container
     * @param $mappings
     * @return array
     * @throws \ReflectionException
     */
    private function resolveMappings(ContainerBuilder $container, $mappings)
    {
        $loadedMappings = [];

        foreach ($mappings as $mapping) {
            if (0 === strpos($mapping, '@')) {
                $name = substr($mapping, 1, strpos($mapping, '/') - 1);

                $dir = $this->resolveBundle($container, $name);
                $mapping = str_replace('@'.$name, $dir, $mapping);
            }

            if (true === \file_exists($mapping)) {
                $finder = new Finder();
                $finder->files()->in($mapping);
                foreach ($finder as $file) {
                    /* @var \Symfony\Component\Finder\SplFileInfo $file */
                    $mapping = \file_get_contents($file->getPathname());
                    $mapping = Yaml::parse($mapping);
                    $loadedMappings[] = $mapping['mapping'];
                }
            }
        }

        return [$loadedMappings];
    }

    /**
     * @param ContainerBuilder $container
     * @param                  $config
     */
    private function setAttributesCase(ContainerBuilder $container, $config)
    {
        $container->setParameter('nil_portugues.api.attributes_case', $config['attributes_case']);
    }

    /**
     * @param ContainerBuilder $container
     * @param $name
     * @return null|string
     * @throws \ReflectionException
     */
    private function resolveBundle(ContainerBuilder $container, $name)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles[$name])) {
            return null;
        }

        $class = $bundles[$name];
        $refClass = new \ReflectionClass($class);

        return dirname($refClass->getFileName());
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return 'nilportugues_json_api';
    }
}
