<?php

namespace Lotos\ORM;

class Property
{

    private $name;
    private $value;
    private $isReadable;
    private $isWritable;
    private $isChanged;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->isChanged = false;
    }

    public function setReadable(bool $isReadable) : self
    {
        $this->isReadable = $isReadable;
        return $this;
    }

    public function setWritable(bool $isWritable) : self
    {
        $this->isWritable = $isWritable;
        return $this;
    }

    public function setValue($value, bool $changed = true) : self
    {
        $this->value = $value;
        $this->isChanged = $changed;
        return $this;
    }

    public function isReadable() : bool
    {
        return $this->isReadable;
    }

    public function isWritable() : bool
    {
        return $this->isWritable;
    }

    public function isEmpty() : bool
    {
        return (empty($this->value) === true);
    }

    public function isChanged() : bool
    {
        return $this->isChanged;
    }

    public function getName() : ?string
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }
}
