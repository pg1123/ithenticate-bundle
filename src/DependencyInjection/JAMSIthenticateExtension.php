<?php
namespace  JAMS\IthenticateBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use JAMS\IthenticateBundle\InthenticateManger;

class JAMSIthenticateExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        //$loader->load('manager.xml');
        $loader->load('registry.xml');

        $defaultManger = $config['default_manager'];
        if (!count($config['managers'])) {
            $config['managers'][$defaultManger] = [];
        } elseif (count($config['managers']) === 1) {
            $defaultManger = key($config['mangers']);
        }

        $managers = [];
        foreach ($config['managers'] as $name => $managerOptions) {
            $managerName = sprintf('ithenticate.manger.%s', $name);
            $managerClass = InthenticateManger::class;
            //echo $managerClass;exit;

            $managerOptions = [
                'url' => $configs[0]['managers'][$name]['url'],
                'email' => $configs[0]['managers'][$name]['email'],
                'password' => $configs[0]['managers'][$name]['password'],
                'group_folder_id' => $configs[0]['managers'][$name]['group_folder_id'],
                'dms_dir' => '/var/www',
            ];
            $managerDefinition = new Definition($managerClass, [
                $managerOptions,
                new Reference('twig'),
                new Reference('logger')
            ]);
            //$managerDefinition->setAutowired(true);
            $managerDefinition->setPublic(true);

            $managers[$name] = new Reference($managerName);

            $container->setDefinition($managerName, $managerDefinition);
            if ($name === ($config['default_manager'] ?? null)) {
                $container->setAlias('ithenticate.manager', new Alias($managerName, true));
                $container->setAlias($managerClass, new Alias($managerName, true));
            }
        }

        $registry = $container->getDefinition('ithenticate');
        $registry->replaceArgument(0, $managers);
        if (array_key_exists($defaultManger, $managers)) {
            $registry->replaceArgument(1, $defaultManger);
        }
    }
}