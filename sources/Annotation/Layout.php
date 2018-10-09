<?php

namespace Moro\SymfonyLayout\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class Layout extends ConfigurationAnnotation
{
	/** @var string */
	private $_name;
	/** @var string */
	private $_active;

	/**
	 * @return string
	 */
	public function getAliasName()
	{
		return 'layout';
	}

	/**
	 * @return bool
	 */
	public function allowArray()
	{
		return true;
	}

	/**
	 * @param string $name
	 */
	public function setName(string $name)
	{
		$this->_name = $name;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return (string)$this->_name;
	}

	/**
	 * @param string|bool $rule
	 */
	public function setActive($rule)
	{
		$this->_active = $rule;
	}

	/**
	 * @return bool
	 */
	public function hasActive(): bool
	{
		return $this->_active !== null;
	}

	/**
	 * @return string|bool
	 */
	public function getActive()
	{
		return $this->_active;
	}

	/**
	 * @param string $from
	 */
	public function setFrom(string $from)
	{
		$this->_active .= $this->_active ? ' and ' : '';

		if (strpos($from, ',')) {
			list($from, $timezone) = explode(',', $from);
			$this->_active .= sprintf('from(\'%1$s\', \'%2$s\')', trim($from), trim($timezone));
		} else {
			$this->_active .= sprintf('from(\'%1$s\')', $from);
		}
	}

	/**
	 * @param string $to
	 */
	public function setTo(string $to)
	{
		$this->_active .= $this->_active ? ' and ' : '';

		if (strpos($to, ',')) {
			list($to, $timezone) = explode(',', $to);
			$this->_active .= sprintf('to(\'%1$s\', \'%2$s\')', trim($to), trim($timezone));
		} else {
			$this->_active .= sprintf('to(\'%1$s\')', $to);
		}
	}

	/**
	 * @param string $value
	 */
	public function setValue(string $value)
	{
		$this->setName($value);
	}

	/**
	 * @return string
	 */
	public function __toString()
	{
		return (string)$this->_name;
	}
}