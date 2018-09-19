<?php

namespace Moro\SymfonyLayout\Listener;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Moro\SymfonyLayout\Service\LayoutService;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
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
	/** @var ClientInterface|null */
	private $_client;
	/** @var LoggerInterface|null */
	private $_logger;

	/**
	 * @param LayoutService $service
	 * @param ClientInterface|null $client
	 * @param LoggerInterface|null $logger
	 */
	public function __construct(LayoutService $service, ClientInterface $client = null, LoggerInterface $logger = null)
	{
		$this->_service = $service;
		$this->_client = $client;
		$this->_logger = $logger;
	}

	/**
	 * @param GetResponseEvent $event
	 */
	public function onKernelRequest(GetResponseEvent $event)
	{
		$request = $event->getRequest();
		$requestUri = $request->getRequestUri();

		if (0 === strpos($requestUri, LayoutService::INTERNAL_URI)) {
			$request->attributes->set('_controller', [$this, 'layout']);
			$request->attributes->set('_route', '.layout:' . $request->query->get(LayoutService::KEY_CHUNK));
		}

		if (0 === strpos($requestUri, LayoutService::EXTERNAL_URI)) {
			$request->attributes->set('_controller', [$this, 'external']);
			$request->attributes->set('_route', ltrim(explode('?', $requestUri)[0], '/'));
		}
	}

	/**
	 * @param Request $request
	 * @return Response
	 */
	public function layout(Request $request)
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

	/** @noinspection PhpDocMissingThrowsInspection */
	/**
	 * @param Request $request
	 * @return Response
	 */
	public function external(Request $request)
	{
		$requestUri = $request->getRequestUri();
		$requestUri = substr($requestUri, strlen(LayoutService::EXTERNAL_URI));
		list($host, $uri) = explode('/', $requestUri, 2);
		$content = '<!-- ' . $requestUri . ' -->';

		if ($this->_client) {
			try {
				$psrResponse = $this->_client->request('GET', 'http://' . $host . '/' . $uri);
				$response = (new HttpFoundationFactory())->createResponse($psrResponse);

				if ($response->isCacheable()) {
					return $response;
				}

				$content = $response->getContent();
			} catch (ConnectException $exception) {
				if ($this->_logger) {
					$this->_logger->error($exception->getMessage(), $exception->getHandlerContext());
				}
			} catch (GuzzleException $exception) {
				if ($this->_logger) {
					$this->_logger->error($exception->getMessage(), ['code' => $exception->getCode()]);
				}
			}
		} else {
			$content = file_get_contents('http://' . $host . '/' . $uri);
		}

		$response = new Response($content);
		$response->setPublic();
		$response->setMaxAge(60);

		return $response;
	}
}