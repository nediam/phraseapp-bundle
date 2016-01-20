<?php

namespace nediam\PhraseAppBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('phrase_app');

        $rootNode
            ->children()
            ->scalarNode('token')->isRequired()->end()
            ->scalarNode('project_id')->isRequired()->end()
            ->scalarNode('output_format')
                ->defaultValue('yml')
            ->end()
            ->scalarNode('translations_path')->isRequired()->end()
            ->arrayNode('catalogues')
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->children()
                        ->arrayNode('tags')
                            ->prototype('scalar')->end()
                        ->end()
                        ->scalarNode('output_format')->defaultValue('yml')->end()
                        ->scalarNode('path')->defaultValue(null)->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('locales')
                ->prototype('scalar')->end()
            ->end()
        ;

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
