<?php

namespace Moro\SymfonyLayout\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Moro\SymfonyLayout\Definition\DefinitionInterface;
use Moro\SymfonyLayout\Definition\DefinitionTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationAnnotation;

/**
 * @Annotation
 */
class Layout extends ConfigurationAnnotation implements DefinitionInterface
{
    use DefinitionTrait;

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
	 * @param string $value
	 */
	public function setValue(string $value)
	{
		$this->setName($value);
	}
}