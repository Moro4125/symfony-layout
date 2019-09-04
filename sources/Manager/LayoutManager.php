<?php

namespace Moro\SymfonyLayout\Manager;

use Moro\SymfonyLayout\Definition\Definition;
use Moro\SymfonyLayout\Definition\DefinitionInterface;
use Moro\SymfonyLayout\Exception\RequestNotFoundException;
use Moro\SymfonyLayout\Service\LayoutService;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class LayoutManager
 */
class LayoutManager
{
    /** @var RequestStack */
    protected $_stack;

    /**
     * @param RequestStack $stack
     */
    public function __construct(RequestStack $stack)
    {
        $this->_stack = $stack;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $layout = $this->_getLastAnnotation();
        $layout->setName($name);
    }

    /**
     * @param null|\Moro\SymfonyLayout\Definition\DefinitionInterface $definition
     * @return Definition
     */
    public function addDefinition(DefinitionInterface $definition = null): Definition
    {
        if (null === $definition) {
            $definition = new Definition();
        }

        $request = $this->_getCurrentRequest();
        $annotations = (array)($request->attributes->get(LayoutService::KEY_LAYOUT) ?: []);
        $annotations[] = $definition;

        $request->attributes->set(LayoutService::KEY_LAYOUT, $annotations);

        return $definition;
    }

    /**
     * @return \Moro\SymfonyLayout\Definition\DefinitionInterface
     */
    protected function _getLastAnnotation(): DefinitionInterface
    {
        $request = $this->_getCurrentRequest();

        if (!$layout = $request->attributes->get(LayoutService::KEY_LAYOUT)) {
            $layout = new Definition();
            $request->attributes->set(LayoutService::KEY_LAYOUT, $layout);
        }

        if (is_array($layout)) {
            $layout = end($layout);
        }

        if (!$layout instanceof DefinitionInterface) {
            $message = 'Attribute %1$s in request object must extends %2$s. Class %3$s received.';
            $message = sprintf($message, LayoutService::KEY_LAYOUT, DefinitionInterface::class, get_class($layout));
            throw new RuntimeException($message);
        }

        return $layout;
    }

    /**
     * @return Request
     */
    protected function _getCurrentRequest(): Request
    {
        if (!$request = $this->_stack->getCurrentRequest()) {
            $message = 'Request object not found. You must use this manager only in controller.';
            throw new RequestNotFoundException($message);
        }

        return $request;
    }
}