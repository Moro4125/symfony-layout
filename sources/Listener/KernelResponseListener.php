<?php

namespace Moro\SymfonyLayout\Listener;

use Moro\SymfonyLayout\Event\LayoutResolveEvent;
use Moro\SymfonyLayout\Service\LayoutService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpKernel\HttpCache\SurrogateInterface;

/**
 * Class KernelResponseListener
 */
class KernelResponseListener
{
	private $_service;
	private $_handler;
	private $_dispatcher;
	private $_surrogate;
	private $_renderer;
	private $_logger;

	/**
	 * @param LayoutService $service
	 * @param FragmentHandler $handler
	 * @param EventDispatcherInterface $dispatcher
	 * @param SurrogateInterface $surrogate
	 * @param string $renderer
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		LayoutService $service,
		FragmentHandler $handler,
		EventDispatcherInterface $dispatcher,
		SurrogateInterface $surrogate,
		string $renderer,
		LoggerInterface $logger = null
	) {
		$this->_service = $service;
		$this->_handler = $handler;
		$this->_dispatcher = $dispatcher;
		$this->_surrogate = $surrogate;
		$this->_renderer = $renderer;
		$this->_logger = $logger;
	}

	/**
	 * @param FilterResponseEvent $event
	 */
	public function onKernelResponse(FilterResponseEvent $event)
	{
		$request = $event->getRequest();
		$attributes = null;
		$layout = false;

		if (false !== strpos($request->getRequestUri(), LayoutService::INTERNAL_URI)) {
			return;
		}

		$response = $event->getResponse();
		$content = $response->getContent();
		$pos1 = strpos($content, '<layout>');
		$pos2 = strpos($content, '</layout>');

		if (false === $pos1 || false === $pos2) {
			return;
		}

		if ($pos2 > $pos1 + 8) {
			$layoutChunks = [[LayoutService::CHUNK_AFTER, $pos2, 9], [LayoutService::CHUNK_BEFORE, $pos1, 8]];
		} else {
			$layoutChunks = [[null, $pos1, 17]];
		}

		$hasSurrogateCapability = $this->_surrogate->hasSurrogateCapability($request);

		if (!$request->get('without_layout')) {
			$event = new LayoutResolveEvent($event->getKernel(), $request, $event->getRequestType());
			$this->_dispatcher->dispatch(LayoutResolveEvent::NAME, $event);
			$layout = $event->hasLayout() ? $event->getLayout() : false;
		}

		if ($layout && $this->_logger) {
			$this->_logger->info(sprintf('Matched layout "%1$s".', $layout));
		} elseif ($this->_logger) {
			$route = $request->attributes->get('_route');
			$this->_logger->warning(sprintf('No layout is associated with route "%1$s".', $route));
		}

		if ($layout) {
			$attributes = array_merge($request->query->all(), $request->attributes->all());
			$attributes = $this->_service->prepareArguments((string)$layout, $attributes);
		}

		if ($layout && $this->_service->hasContentEx($layout, LayoutService::CHUNK_HEAD, $attributes)) {
			$pos3 = strpos($content, '</head>') ?: $pos1;
			array_push($layoutChunks, [LayoutService::CHUNK_HEAD, $pos3, 0]);
		}

		if ($layout && $this->_service->hasContentEx($layout, LayoutService::CHUNK_FOOT, $attributes)) {
			$pos4 = strpos($content, '</body>') ?: $pos2 + 9;
			array_unshift($layoutChunks, [LayoutService::CHUNK_FOOT, $pos4, 0]);
		}

		foreach ($layoutChunks as $rec) {
			list($chunk, $position, $length) = $rec;
			$fragment = '';

			if ($layout) {
				if ($hasSurrogateCapability) {
					$attributes[LayoutService::KEY_LAYOUT] = (string)$layout;
					$attributes[LayoutService::KEY_CHUNK] = $chunk;

					$uri = LayoutService::INTERNAL_URI . http_build_query($attributes);
					$fragment = $this->_handler->render($uri, $this->_renderer);
				} else {
					$fragment = $this->_service->getContent((string)$layout, $attributes, $chunk);
				}
			}

			$content = substr_replace($content, $fragment, (int)$position, $length);
		}

		$response->setContent($content);
	}
}