<?php

namespace Moro\SymfonyLayout\Definition;

use DateTime;

/**
 * Trait DefinitionTrait
 */
trait DefinitionTrait
{
    /** @var string */
    private $_name;
    /** @var string */
    private $_active;

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
     * @param \DateTime|integer|string $from
     */
    public function setFrom($from)
    {
        if (is_numeric($from)) {
            $from = new DateTime('@' . $from);
        }

        if ($from instanceof DateTime) {
            $from = $from->format(DateTime::ATOM);
        }

        $this->_active .= $this->_active ? ' and ' : '';

        if (strpos($from, ',')) {
            list($from, $timezone) = explode(',', $from, 2);
            $this->_active .= sprintf('from(\'%1$s\', \'%2$s\')', trim($from), trim($timezone));
        } else {
            $this->_active .= sprintf('from(\'%1$s\')', $from);
        }
    }

    /**
     * @param \DateTime|integer|string $to
     */
    public function setTo($to)
    {
        if (is_numeric($to)) {
            $to = new DateTime('@' . $to);
        }

        if ($to instanceof DateTime) {
            $to = $to->format(DateTime::ATOM);
        }

        $this->_active .= $this->_active ? ' and ' : '';

        if (strpos($to, ',')) {
            list($to, $timezone) = explode(',', $to, 2);
            $this->_active .= sprintf('to(\'%1$s\', \'%2$s\')', trim($to), trim($timezone));
        } else {
            $this->_active .= sprintf('to(\'%1$s\')', $to);
        }
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string)$this->_name;
    }
}