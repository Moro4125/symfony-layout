<?php

namespace Moro\SymfonyLayout\Listener\Kernel;

use Moro\SymfonyLayout\Annotation\Layout;
use Moro\SymfonyLayout\Service\LayoutService;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

/**
 * Class KernelViewListener
 */
class ViewListener
{
	/**
	 * @param GetResponseForControllerResultEvent $event
	 */
	public function onKernelView(GetResponseForControllerResultEvent $event)
	{
		$request = $event->getRequest();

		if ($layouts = (array)$request->attributes->get(LayoutService::KEY_LAYOUT)) {
			$parameters = (array)$event->getControllerResult();

			foreach ($layouts as &$layout) {
				if ($layout instanceof Layout) {
					$layout->setName($this->_handler($layout->getName(), $parameters));
				} elseif (is_string($layout)) {
					$layout = $this->_handler($layout, $parameters);
				}
			}

			$request->attributes->set(LayoutService::KEY_LAYOUT, $layouts);
		}
	}

	/**
	 * @param string $layout
	 * @param array $parameters
	 * @return string
	 */
	private function _handler(string $layout, array $parameters): string
	{
		return preg_replace_callback('~\\{(.+?)\\}~', function ($match) use ($parameters) {
			return $parameters[$match[1]] ?? $match[0];
		}, $layout);
	}
}