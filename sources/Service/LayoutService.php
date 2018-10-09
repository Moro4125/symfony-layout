<?php

namespace Moro\SymfonyLayout\Service;

use Moro\SymfonyLayout\Event\LayoutReceiveEvent;
use Moro\SymfonyLayout\Exception\LayoutNotFoundException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Twig_Environment;

/**
 * Class LayoutService
 */
class LayoutService
{
	const INTERNAL_URI = '/.layout?';
	const EXTERNAL_URI = '/.external/';
	const KEY_LAYOUT   = '_layout';
	const KEY_CHUNK    = '_chunk';
	const DELIMITER    = '<!--=# delimiter #=-->';
	const CHUNK_HEAD   = 'head';
	const CHUNK_BEFORE = 'before';
	const CHUNK_AFTER  = 'after';
	const CHUNK_FOOT   = 'foot';

	/** @var EventDispatcherInterface */
	private $_dispatcher;
	/** @var Twig_Environment */
	private $_twig;
	/** @var Stopwatch|null */
	private $_stopwatch;
	/** @var array */
	private $_options;
	/** @var string */
	private $_cacheLayout;
	/** @var LayoutEntity */
	private $_cacheEntity;
	/** @var string */
	private $_cacheContent;
	/** @var bool */
	private $_isStarted;

	/**
	 * @param EventDispatcherInterface $dispatcher
	 * @param Twig_Environment $twig
	 * @param Stopwatch $stopwatch
	 * @param array $options
	 */
	public function __construct(
		EventDispatcherInterface $dispatcher,
		Twig_Environment $twig,
		?Stopwatch $stopwatch,
		array $options
	) {
		$this->_dispatcher = $dispatcher;
		$this->_twig = $twig;
		$this->_stopwatch = $stopwatch;
		$this->_options = $options;
		$this->_isStarted = false;
	}

	/**
	 * @param string $layout
	 * @param array $attributes
	 * @return array
	 */
	public function prepareArguments(string $layout, array $attributes): array
	{
		if ($this->_stopwatch) {
			$watch = $this->_stopwatch->start(__METHOD__);
		}

		$layout = $this->_getEntity($layout);
		$arguments = array_intersect_key($attributes, array_fill_keys($layout->getUsedAttributes(), true));

		if (isset($watch)) {
			$watch->stop();
		}

		return $arguments;
	}

	/**
	 * @param string $layout
	 * @param string $chunk
	 * @param array $attributes
	 * @return bool
	 */
	public function hasContentEx(string $layout, string $chunk, array $attributes): bool
	{
		if ($this->_stopwatch) {
			$watch = $this->_stopwatch->start(__METHOD__);
		}

		$layout = $this->_getEntity($layout);
		$templates = $layout->getTemplatesEx($chunk, $attributes, $this->_options);

		if (isset($watch)) {
			$watch->stop();
		}

		return !empty($templates);
	}

	/**
	 * @param string $layout
	 * @param array $attributes
	 * @param string|null $chunk
	 * @return string
	 */
	public function getContent(string $layout, array $attributes, string $chunk = null): string
	{
		if ($this->_stopwatch) {
			$watch = $this->_stopwatch->start(__METHOD__);
		}

		try {
			switch ($chunk) {
				case self::CHUNK_BEFORE:
					$content = $this->_getContent($layout, $attributes);

					return explode(self::DELIMITER, $content)[0];

				case self::CHUNK_AFTER:
					$content = $this->_getContent($layout, $attributes);

					return explode(self::DELIMITER, $content)[1] ?? '';

				case self::CHUNK_HEAD:
				case self::CHUNK_FOOT:
					return $this->_getContentEx($layout, $attributes, $chunk);
			}
		}
		finally {
			if (isset($watch)) {
				$watch->stop();
			}
		}

		return $this->_getContent($layout, $attributes);
	}

	/**
	 * @param string $layout
	 * @return LayoutEntity
	 */
	protected function _getEntity(string $layout): LayoutEntity
	{
		if ($this->_cacheLayout === $layout) {
			return $this->_cacheEntity;
		}

		if ($this->_stopwatch) {
			$watch = $this->_stopwatch->start('LayoutLoadEntity');
		}

		$event = new LayoutReceiveEvent($layout);
		$this->_dispatcher->dispatch(LayoutReceiveEvent::NAME, $event);

		if (!$event->hasXml()) {
			$message = sprintf('Layout "%1$s" is not exists.', $layout);
			$exception = new LayoutNotFoundException($message);
			$exception->layout = $layout;
			throw $exception;
		}

		$entity = new LayoutEntity($event->getXml());
		$this->_cacheLayout = $layout;
		$this->_cacheEntity = $entity;
		$this->_cacheContent = null;

		$keys = array_keys($entity->getExternals());
		$tasks = array_map(function () {
			return func_get_args();
		}, $keys, array_fill(0, count($keys), $entity));
		$cache = [];

		while ($temp = array_shift($tasks)) {
			/** @var LayoutEntity $currentEntity */
			list($key, $currentEntity) = $temp;

			if (empty($cache[$key])) {
				$event = new LayoutReceiveEvent($key);
				$this->_dispatcher->dispatch(LayoutReceiveEvent::NAME, $event);

				if ($event->hasXml()) {
					$extendsEntity = new LayoutEntity($event->getXml());
					$currentEntity->setExternal($key, $extendsEntity);

					$cache[$key] = $extendsEntity;
				}
			} else {
				$currentEntity->setExternal($key, $cache[$key]);
			}
		}

		if (isset($watch)) {
			$watch->stop();
		}

		return $entity;
	}

	/** @noinspection PhpDocMissingThrowsInspection */
	/**
	 * @param string $layout
	 * @param array $attributes
	 * @return string
	 */
	protected function _getContent(string $layout, array $attributes)
	{
		$entity = $this->_getEntity($layout);

		if ($this->_cacheContent !== null) {
			return $this->_cacheContent;
		}

		unset($attributes[self::KEY_LAYOUT], $attributes[self::KEY_CHUNK]);

		$template = $entity->getTemplateName();
		$parameters = $entity->getTemplateParameters($attributes, $this->_options);

		/** @noinspection PhpUnhandledExceptionInspection */
		return $this->_cacheContent = $this->_twig->render($template, $parameters);
	}

	/** @noinspection PhpDocMissingThrowsInspection */
	/**
	 * @param string $layout
	 * @param array $attributes
	 * @param string $chunk
	 * @return string
	 */
	protected function _getContentEx(string $layout, array $attributes, string $chunk)
	{
		$entity = $this->_getEntity($layout);
		$templates = $entity->getTemplatesEx($chunk, $attributes, $this->_options);
		$result = '';

		foreach ($templates as $template => $parameters) {
			/** @noinspection PhpUnhandledExceptionInspection */
			$result .= $this->_twig->render($template, ['contexts' => $parameters]);
		}

		return $result;
	}
}