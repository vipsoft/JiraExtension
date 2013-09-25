<?php
/**
 * @copyright 2012 Anthon Pang
 * @license MIT
 */

namespace VIPSoft\JiraExtension;

use Symfony\Component\Config\FileLocator,
    Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition,
    Symfony\Component\DependencyInjection\ContainerBuilder,
    Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

use Behat\Behat\Extension\ExtensionInterface;

/**
 * A Jira Feature Loader extension for Behat
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class Extension implements ExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container)
    {
      $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/services'));
      $loader->load('core.xml');

      if (isset($config['host'])) {
          $container->setParameter('behat.jira.host', rtrim($config['host'], '/'));
      }
      if (isset($config['user'])) {
          $container->setParameter('behat.jira.user', $config['user']);
      }
      if (isset($config['password'])) {
          $container->setParameter('behat.jira.password', $config['password']);
      }
      if (isset($config['jql'])) {
          $container->setParameter('behat.jira.jql', $config['jql']);
      }
      if (isset($config['service_params'])) {
          $container->setParameter('behat.jira.service_params', $config['service_params']);
      }
      if (isset($config['cache_directory'])) {
          $directory = realpath(rtrim($config['cache_directory'], '/'));
          $container->setParameter('behat.jira.cache_directory', $directory);
      }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig(ArrayNodeDefinition $builder)
    {
        $builder->
            children()->
                scalarNode('host')->
                    defaultNull()->
                end()->
                scalarNode('user')->
                    defaultNull()->
                end()->
                scalarNode('password')->
                    defaultNull()->
                end()->
                scalarNode('jql')->
                    defaultNull()->
                end()->
                arrayNode('service_params')->
                  children()->
                    scalarNode('action_on_pass')->
                      defaultFalse()->
                    end()->
                    scalarNode('action_on_fail')->
                      defaultFalse()->
                    end()->
                    scalarNode('comment_on_pass')->
                      defaultFalse()->
                    end()->
                    scalarNode('comment_on_fail')->
                      defaultFalse()->
                    end()->
                    scalarNode('screenshot_on_fail')->
                      defaultFalse()->
                    end()->
                    scalarNode('feature_field')->
                      defaultValue('description')->
                    end()->
                  end()->
                end()->
                scalarNode('cache_directory')->
                    defaultNull()->
                end()->
            end()->
        end();
    }

    /**
     * {@inheritdoc}
     */
    public function getCompilerPasses()
    {
        return array();
    }
}
