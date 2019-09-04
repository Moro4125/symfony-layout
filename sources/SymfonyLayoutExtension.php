<?php

namespace Moro\SymfonyLayout;

use Moro\SymfonyLayout\Event\LayoutReceiveEvent;
use Moro\SymfonyLayout\Event\LayoutResolveEvent;
use Moro\SymfonyLayout\Expression\FunctionProvider;
use Moro\SymfonyLayout\Listener\Kernel\RequestListener;
use Moro\SymfonyLayout\Listener\Kernel\ResponseListener;
use Moro\SymfonyLayout\Listener\Kernel\ViewListener;
use Moro\SymfonyLayout\Listener\Layout\ReceiveFromFileListener;
use Moro\SymfonyLayout\Listener\Layout\ResolveListener;
use Moro\SymfonyLayout\Manager\LayoutManager;
use Moro\SymfonyLayout\Service\LayoutService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class SymfonyLayout
 */
class SymfonyLayoutExtension extends Extension implements CompilerPassInterface, PrependExtensionInterface
{
	/**
	 * @param array $config
	 * @param ContainerBuilder $container
	 * @return SymfonyLayoutConfiguration|null|object|\Symfony\Component\Config\Definition\ConfigurationInterface
	 */
	public function getConfiguration(array $config, ContainerBuilder $container)
	{
		return new SymfonyLayoutConfiguration();
	}

	/**
	 * @param ContainerBuilder $container
	 */
	public function prepend(ContainerBuilder $container)
	{
		$container->prependExtensionConfig('framework', [
			'esi'       => true,
			'fragments' => true,
		]);
		$container->prependExtensionConfig('monolog', [
			'channels' => ['layout'],
		]);
		$container->prependExtensionConfig('twig', [
			'paths' => ['%kernel.project_dir%/vendor/moro/symfony-layout/sources/Resources/views' => 'SymfonyLayout'],
		]);
		$container->prependExtensionConfig('eight_points_guzzle', [
			'clients' => [
				'symfony_layout' => [
					'options' => [
						'timeout' => 3,
						'headers' => [
							'User-Agent' => 'EightpointsGuzzleBundle/SymfonyLayout'
						],
					],
				],
			],
		]);
	}

	/**
	 * @param array $configs
	 * @param ContainerBuilder $container
	 */
	public function load(array $configs, ContainerBuilder $container)
	{
		$configuration = $this->getConfiguration($configs, $container);
		$config = $this->processConfiguration($configuration, $configs);
		$useNull = ContainerInterface::NULL_ON_INVALID_REFERENCE;

		$definition = $container->register(LayoutService::class);
		$definition->addArgument(new Reference('event_dispatcher'));
		$definition->addArgument(new Reference('twig'));
		$definition->addArgument(new Reference('debug.stopwatch', $useNull));
		$definition->addArgument($config[SymfonyLayoutConfiguration::P_OPTIONS]);

        $definition = $container->register(LayoutManager::class);
        $definition->addArgument(new Reference('request_stack'));

        $definition = $container->register(RequestListener::class);
		$definition->addArgument(new Reference(LayoutService::class));
		$definition->addArgument(new Reference('eight_points_guzzle.client.symfony_layout', $useNull));
		$definition->addArgument(new Reference('logger', $useNull));
		$definition->addTag('monolog.logger', [
			'channel' => 'layout',
		]);
		$definition->addTag('kernel.event_listener', [
			'event'    => KernelEvents::REQUEST,
			'method'   => 'onKernelRequest',
			'priority' => 40,
		]);

        $definition = $container->register(ViewListener::class);
		$definition->addTag('kernel.event_listener', [
			'event'    => KernelEvents::VIEW,
			'method'   => 'onKernelView',
			'priority' => 40,
		]);

        $definition = $container->register(ResponseListener::class);
		$definition->addArgument(new Reference(LayoutService::class));
		$definition->addArgument(new Reference('fragment.handler'));
		$definition->addArgument(new Reference('event_dispatcher'));
		$definition->addArgument(new Reference('esi'));
		$definition->addArgument($config[SymfonyLayoutConfiguration::P_RENDERER]);
		$definition->addArgument(new Reference('logger', $useNull));
		$definition->addTag('monolog.logger', [
			'channel' => 'layout',
		]);
		$definition->addTag('kernel.event_listener', [
			'event'    => KernelEvents::RESPONSE,
			'method'   => 'onKernelResponse',
			'priority' => 40,
		]);

        $definition = $container->register('layout.expression_functions', FunctionProvider::class);
        $definition->addTag('layout.expression_provider');

		$definition = $container->register('layout.expression_language', ExpressionLanguage::class);
		$definition->addArgument(new Reference('cache.annotations'));

        $definition = $container->register(ResolveListener::class);
		$definition->addArgument(new Reference('layout.expression_language'));
		$definition->addArgument(new Reference('layout.expression_functions'));
		$definition->addTag('kernel.event_listener', [
			'event'    => LayoutResolveEvent::NAME,
			'method'   => 'onLayoutResolve',
			'priority' => 40,
		]);
		$definition->addTag('kernel.event_listener', [
			'event'    => KernelEvents::RESPONSE,
			'method'   => 'onKernelResponse',
			'priority' => -40,
		]);

        $definition = $container->register(ReceiveFromFileListener::class);
		$definition->addArgument($config[SymfonyLayoutConfiguration::P_PATHS] ?? []);
		$definition->addTag('kernel.event_listener', [
			'event'    => LayoutReceiveEvent::NAME,
			'method'   => 'onLayoutReceive',
			'priority' => 40,
		]);
	}

	/**
	 * @param ContainerBuilder $container
	 */
	public function process(ContainerBuilder $container)
	{
        if ($container->has('layout.expression_language')) {
            $definition = $container->findDefinition('layout.expression_language');
            $taggedServices = $container->findTaggedServiceIds('layout.expression_provider');

            foreach ($taggedServices as $id => $tags) {
                $definition->addMethodCall('registerProvider', [new Reference($id)]);
            }
        }
	}
}