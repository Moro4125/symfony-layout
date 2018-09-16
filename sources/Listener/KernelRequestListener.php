<?php

namespace Moro\SymfonyLayout\Listener;

use Moro\SymfonyLayout\Service\LayoutService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Class KernelRequestListener
 * @package Layout\Listener
 */
class KernelRequestListener
{
	/** @var LayoutService */
	private $_service;

	/**
	 * @param LayoutService $service
	 */
	public function __construct(LayoutService $service)
	{
		$this->_service = $service;
	}

	/**
	 * @param GetResponseEvent $event
	 */
	public function onKernelRequest(GetResponseEvent $event)
	{
		$request = $event->getRequest();

		if (false === strpos($request->getRequestUri(), LayoutService::INTERNAL_URI)) {
			return;
		}

		$request->attributes->set('_controller', [$this, 'handler']);
		$request->attributes->set('_route', '.layout:' . $request->query->get(LayoutService::KEY_CHUNK));
	}

	/**
	 * @param Request $request
	 * @return Response
	 */
	public function handler(Request $request)
	{
		$arguments = $request->query->all();
		$layout = $arguments[LayoutService::KEY_LAYOUT];
		$chunk = $arguments[LayoutService::KEY_CHUNK];
		unset($arguments[LayoutService::KEY_LAYOUT], $arguments[LayoutService::KEY_CHUNK]);
		$content = $this->_service->getContent($layout, $arguments, $chunk);

		$response = new Response($content);
		$response->setPublic();
		$response->setMaxAge(300);

		return $response;
	}
}