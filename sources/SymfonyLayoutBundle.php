<?php

namespace Moro\SymfonyLayout;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class SymfonyLayoutBundle
 * @package Layout
 */
class SymfonyLayoutBundle extends Bundle
{
	/**
	 * Returns the bundle's container extension class.
	 *
	 * @return string
	 */
	protected function getContainerExtensionClass()
	{
		return SymfonyLayoutExtension::class;
	}
}