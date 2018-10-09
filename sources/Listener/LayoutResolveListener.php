<?php

namespace Moro\SymfonyLayout\Listener;

use DateTime;
use Moro\SymfonyLayout\Annotation\Layout;
use Moro\SymfonyLayout\Event\LayoutResolveEvent;
use Moro\SymfonyLayout\Expression\FunctionProvider;
use Moro\SymfonyLayout\Service\LayoutService;
use SplObjectStorage;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class LayoutResolveListener
 */
class LayoutResolveListener
{
	/** @var ExpressionLanguage */
	private $_language;
	/** @var FunctionProvider */
	private $_provider;
	/** @var SplObjectStorage */
	private $_requests;

	/**
	 * @param ExpressionLanguage $language
	 * @param FunctionProvider $provider
	 */
	public function __construct(ExpressionLanguage $language, FunctionProvider $provider)
	{
		$this->_language = $language;
		$this->_provider = $provider;
		$this->_requests = new SplObjectStorage();
	}

	/**
	 * @param LayoutResolveEvent $event
	 */
	public function onLayoutResolve(LayoutResolveEvent $event)
	{
		$request = $event->getRequest();
		$this->_provider->from = null;
		$this->_provider->to = null;

		if ($layouts = $request->attributes->get(LayoutService::KEY_LAYOUT)) {
			foreach ((array)$layouts as $layout) {
				if ($layout instanceof Layout && $layout->hasActive() && !$this->_isActive($layout, $request)) {
					continue;
				}

				if (!$event->hasLayout()) {
					$event->setLayout($layout);
				}
			}
		}

		foreach ([$this->_provider->from, $this->_provider->to] as $timestamp) {
			if ($timestamp !== null && $timestamp >= time()) {
				if (isset($this->_requests[$request])) {
					$this->_requests[$request] = min($this->_requests[$request], $timestamp);
				} else {
					$this->_requests[$request] = $timestamp;
				}
			}
		}
	}

	/**
	 * @param FilterResponseEvent $event
	 */
	public function onKernelResponse(FilterResponseEvent $event)
	{
		$request = $event->getRequest();

		if (isset($this->_requests[$request])) {
			$timestamp = $this->_requests[$request];
			$response = $event->getResponse();
			$headers = $response->headers;
			$delta = $timestamp - time();

			if ($headers->hasCacheControlDirective('s-maxage')) {
				$sMaxAge = $headers->getCacheControlDirective('s-maxage');
				$response->setSharedMaxAge(min($delta, $sMaxAge));
			}

			if ($headers->hasCacheControlDirective('maxage')) {
				$maxAge = $headers->getCacheControlDirective('maxage');
				$response->setMaxAge(min($delta, $maxAge));
			}

			if ($headers->hasCacheControlDirective('max-stale')) {
				$maxStale = $headers->getCacheControlDirective('max-stale');
				$headers->addCacheControlDirective('max-stale', min($delta, $maxStale));
			}

			if ($headers->has('expires')) {
				$expires = $response->getExpires();
				$date = new DateTime('@' . min($expires->getTimestamp(), $timestamp));
				$response->setExpires($date);
			}

			unset($this->_requests[$request]);
		}
	}


	/**
	 * @param Layout $layout
	 * @param Request $request
	 * @return bool
	 */
	private function _isActive(Layout $layout, Request $request): bool
	{
		$expression = $layout->getActive();

		if (is_string($expression)) {
			$values = array_merge($request->attributes->all(), ['request' => $request]);
			$result = $this->_language->evaluate($expression, $values);

			return !empty($result);
		}

		return !empty($expression);
	}
}