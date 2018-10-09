<?php

namespace Moro\SymfonyLayout\Event;

use Symfony\Component\HttpKernel\Event\KernelEvent;

/**
 * Class LayoutResolveEvent
 */
class LayoutResolveEvent extends KernelEvent
{
	const NAME = 'layout.resolve';

	/** @var string */
	private $_layout;

	/**
	 * @param string $layout
	 */
	public function setLayout($layout)
	{
		$this->_layout = $layout;
		$this->stopPropagation();
	}

	/**
	 * @return string
	 */
	public function getLayout(): string
	{
		return (string)$this->_layout;
	}

	/**
	 * @return bool
	 */
	public function hasLayout(): bool
	{
		return $this->_layout !== null;
	}
}