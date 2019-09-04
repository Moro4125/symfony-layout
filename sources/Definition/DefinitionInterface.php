<?php

namespace Moro\SymfonyLayout\Definition;

/**
 * Interface DefinitionInterface
 */
interface DefinitionInterface
{
    /**
     * @param string $name
     */
    function setName(string $name);

    /**
     * @return string
     */
    function getName(): string;

    /**
     * @param string|bool $rule
     */
    function setActive($rule);

    /**
     * @return bool
     */
    function hasActive(): bool;

    /**
     * @return string|bool
     */
    function getActive();

    /**
     * @param \DateTime|integer|string $from
     */
    function setFrom($from);

    /**
     * @param \DateTime|integer|string $to
     */
    function setTo($to);

    /**
     * @return string
     */
    function __toString();
}