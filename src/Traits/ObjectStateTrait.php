<?php

namespace Fuzzy\Fzpkg\Traits;

trait ObjectStateTrait
{
    private $__trait_objectState__ = [];

    protected function storeState() : void
    {
        $object = new \ReflectionObject($this);

        foreach ($object->getProperties() as $property) {
            if ($property->getName() !== '__trait_objectState__') {
                $this->__trait_objectState__[$property->getName()] = $property->getValue($this);
            }
        }
    }

    protected function restoreState() : void
    {
        $object = new \ReflectionObject($this);

        foreach ($this->__trait_objectState__ as $propertyName => $propertyValue) {
            $object->getProperty($propertyName)->setValue($this, $propertyValue);
        }
    }

    protected function flushStoredState() : void
    {
        $this->__trait_objectState__ = [];
    }

    protected function getStoredState() : array
    {
        return $this->__trait_objectState__;
    }
}