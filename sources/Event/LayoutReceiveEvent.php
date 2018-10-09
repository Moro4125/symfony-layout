<?php

namespace Moro\SymfonyLayout\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class LayoutReceiveEvent
 */
class LayoutReceiveEvent extends Event
{
	const NAME = 'layout.receive';

	/** @var string */
	private $_layout;
	/** @var string */
	private $_xml;

	/**
	 * @param string $layout
	 */
	public function __construct(string $layout)
	{
		$this->_layout = $layout;
	}

	/**
	 * @return string
	 */
	public function getLayout(): string
	{
		return $this->_layout;
	}

	/**
	 * @param string $xml
	 */
	public function setXml(string $xml)
	{
		$this->_xml = $xml;
		$this->stopPropagation();
	}

	/**
	 * @return string
	 */
	public function getXml(): string
	{
		return (string)$this->_xml;
	}

	/**
	 * @return bool
	 */
	public function hasXml(): bool
	{
		return $this->_xml !== null;
	}
}