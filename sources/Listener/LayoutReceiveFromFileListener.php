<?php

namespace Moro\SymfonyLayout\Listener;

use Moro\SymfonyLayout\Event\LayoutReceiveEvent;

/**
 * Class LayoutReceiveFromFileListener
 */
class LayoutReceiveFromFileListener
{
	/** @var array */
	private $_paths;

	/**
	 * @param array $paths
	 */
	public function __construct(array $paths)
	{
		$this->_paths = $paths;
	}

	/**
	 * @param LayoutReceiveEvent $event
	 */
	public function onLayoutReceive(LayoutReceiveEvent $event)
	{
		$layout = $event->getLayout();
		$subPath = (dirname($layout) !== '.') ? DIRECTORY_SEPARATOR . trim(dirname($layout), '/\\') : '';
		$subPath .= DIRECTORY_SEPARATOR . basename($layout, '.xml') . '.xml';

		if (strncmp($layout, '@', 1) === 0) {
			$ns = substr(explode('/', $layout)[0], 1);
			$subPath = substr($subPath, strlen($ns) + 1);

			if ($path = array_search($ns, $this->_paths, true)) {
				if (file_exists($path . $subPath)) {
					$event->setXml(file_get_contents($path . $subPath));
				}
			}
		} else {
			foreach (array_keys($this->_paths) as $path) {
				if (file_exists($path . $subPath)) {
					$event->setXml(file_get_contents($path . $subPath));
					break;
				}
			}
		}
	}
}