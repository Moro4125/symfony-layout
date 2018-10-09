<?php

namespace Moro\SymfonyLayout\Exception;

use RuntimeException;

/**
 * Class LayoutNotFoundException
 */
class LayoutNotFoundException extends RuntimeException
{
	/** @var string */
	public $layout;
}