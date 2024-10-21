<?php

declare(strict_types=1);

namespace Lingoda\DomainEventsBundle\DependencyInjection;

use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Webmozart\Assert\Assert;

final class LingodaDomainEventsExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->useCustomMessageBusIfSpecified($config, $container);
        $this->configureEventPublishingSubscriber($config, $container);
    }

    /**
     * @param mixed[] $config
     */
    private function useCustomMessageBusIfSpecified(array $config, ContainerBuilder $container): void
    {
        if (isset($config['message_bus_name'])) {
            $messageBusName = $config['message_bus_name'];
            Assert::string($messageBusName);

            $definition = $container->getDefinition('lingoda_domain_events.domain_event_dispatcher_service');
            $definition->replaceArgument(0, new Reference($messageBusName));

            $definition = $container->getDefinition('lingoda_domain_events.outbox_message_handler');
            $definition->replaceArgument(1, $messageBusName);
        }
    }

    /**
     * @param mixed[] $config
     */
    private function configureEventPublishingSubscriber(array $config, ContainerBuilder $container): void
    {
        $enabled = true;
        if (isset($config['enable_event_publisher'])) {
            $enabled = $config['enable_event_publisher'];
        }

        $definition = $container->getDefinition('lingoda_domain_events.event_subscriber.publisher');
        $definition->replaceArgument(1, $enabled);
    }
}
