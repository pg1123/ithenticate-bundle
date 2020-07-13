<?php
namespace JAMS\IthenticateBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('jams_ithenticate');


        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('default_manager')->cannotBeEmpty()->defaultValue('default')->end()
            ->arrayNode('managers')
            ->canBeUnset()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->addDefaultsIfNotSet()
            ->children()
            ->arrayNode('default')
            ->children()
            ->scalarNode('url')->defaultValue('default text')->end()
            ->scalarNode('email')->defaultValue('default text')->end()
            ->scalarNode('password')->defaultValue('default text')->end()
            ->scalarNode('group_folder_id')->defaultValue('default text')->end()
            ->end()
            ->end()
            ->arrayNode('custom')
            ->children()
            ->scalarNode('url')->defaultValue('default text')->end()
            ->scalarNode('email')->defaultValue('default text')->end()
            ->scalarNode('password')->defaultValue('default text')->end()
            ->scalarNode('group_folder_id')->defaultValue('default text')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }

    private function getNormalizeListToArrayClosure()
    {
        return function ($endpointList) {
            return preg_split('/\s*,\s*/', $endpointList);
        };
    }
}