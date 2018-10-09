<?php

namespace Moro\SymfonyLayout;

use Moro\SymfonyLayout\Event\LayoutReceiveEvent;
use Moro\SymfonyLayout\Event\LayoutResolveEvent;
use Moro\SymfonyLayout\Expression\FunctionProvider;
use Moro\SymfonyLayout\Listener\KernelRequestListener;
use Moro\SymfonyLayout\Listener\KernelResponseListener;
use Moro\SymfonyLayout\Listener\KernelViewListener;
use Moro\SymfonyLayout\Listener\LayoutReceiveFromFileListener;
use Moro\SymfonyLayout\Listener\LayoutResolveListener;
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

		$definition = $container->register(KernelRequestListener::class);
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

		$definition = $container->register(KernelViewListener::class);
		$definition->addTag('kernel.event_listener', [
			'event'    => KernelEvents::VIEW,
			'method'   => 'onKernelView',
			'priority' => 40,
		]);

		$definition = $container->register(KernelResponseListener::class);
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

		$container->register('layout.expression_functions', FunctionProvider::class);

		$definition = $container->register('layout.expression_language', ExpressionLanguage::class);
		$definition->addArgument(new Reference('cache.annotations'));
		$definition->addMethodCall('registerProvider', [new Reference('layout.expression_functions')]);

		$definition = $container->register(LayoutResolveListener::class);
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

		$definition = $container->register(LayoutReceiveFromFileListener::class);
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
	}
}