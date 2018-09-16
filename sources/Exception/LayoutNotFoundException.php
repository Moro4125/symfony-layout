<?php

namespace Moro\SymfonyLayout\Exception;

use RuntimeException;

/**
 * Class LayoutNotFoundException
 * @package Layout\Exception
 */
class LayoutNotFoundException extends RuntimeException
{
	/** @var string */
	public $layout;
}