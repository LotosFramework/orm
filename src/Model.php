<?php

namespace Lotos\ORM;

use Lotos\Collection\Collection;
use Lotos\ORM\Exceptions\{PropertyIsNotWritableException, PropertyIsNotReadableException};
use Lotos\ORM\Abstractions\Config;
use Lotos\ORM\Adapters\MySql;
use Lotos\ORM\Interfaces\ModelInterface;
use ReflectionClass;

class Model implements ModelInterface
{

    use DefaultPropertiesTrait;

    private $tableName = null;
    private $properties;
    private $adapter;
    private $query;

    private $unwritable = ['id', 'createdAt', 'updatedAt', 'deletedAt'];
    private $unreadable = [];

    public function __construct(array $properties = null)
    {
        try {
            $this->adapter = 'Lotos\ORM\Adapters\\'.Config::ADAPTER;
            $this->initCollection();
            if($properties) {
                $this->addProperties($properties);
            }
            $this->query = new Query(new $this->adapter, $this);
        } catch(\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function save() : self
    {
        $property = $this->properties
            ->where('name', 'id')
            ->first();
        $property->setValue($this->query->save(), false);
        //$this->clearModel(); //@TODO починить этот метод, что-то он не всегда адекватно себя ведет
        return $this;
    }

    private function clearModel() : void
    {
        $this->properties->filter(function(Property $property) {
            return in_array(
                $property->getName(),
                $this->properties
                    ->where('name', 'unreadable')
                    ->first()
                    ->getValue()
                );
        });
        $this->properties->remove(
            $this->properties->find(
                $this->properties->where('name', 'unreadable')->first()
            )
        );
    }

    public function delete() : bool
    {
        return $this->query
            ->from($this->getTableName())
            ->where('id', $this->getId())
            ->delete();
    }

    public function __call($func, $args)
    {
        try {
            $funcType = substr($func, 0, 3);
            if(in_array($funcType, ['get', 'set'])) {
                $propertyName = lcfirst(substr($func, 3));
                $equals = $this->properties->where('name', $propertyName);
                $count = $equals->count();
                if($count == 1) {
                    $property = $this->properties->where('name', $propertyName)->first();
                } elseif($count == 0) {
                    $property = (new Property($propertyName))
                        ->setReadable(true)
                        ->setWritable(true);
                    $this->properties->push($property);
                } else {

                }
                if($funcType == 'get') {
                    return $this->getPropertyValue($property);
                } elseif($funcType == 'set') {
                    $this->setPropertyValue($property, $args);
                }
            } elseif(method_exists($this->query, $func)) {
                return call_user_func_array([$this->query, $func], $args);
            }
        } catch(\Exception $e) {
            echo $e->getMessage().$e->getTraceAsString();
        }
    }

    private function getPropertyValue(Property $property)
    {
        if($property->isReadable() === true) {
            return $property->getValue();
        } else {
            throw new PropertyIsNotReadableException('Property ' . $property->getName() . ' is not readable');
        }
    }

    private function setPropertyValue(Property $property, array $args) : void
    {
        if($property->isWritable() === true){
            $property->setValue($args[0], true);
        } else {
            throw new PropertyIsNotWritableException('Property ' . $property->getName() . ' is not writable');
        }
    }

    public static function __callStatic($method, $args)
    {
        $class = get_called_class();
        $obj = new $class;
        return call_user_func_array([$obj, $method], $args);
    }

    public function getTableName() : string
    {
        return $this->tableName ?? Utils::getTableName(get_called_class());
    }

    public function __toString()
    {
        $properties = [];
        if($this->properties) {
            $this->properties
                ->where('isReadable', true)
                ->whereNotNull('value')
                ->map(function($property) use (&$properties) {
                    $properties[$property->getName()] = $property->getValue();
                });
        }
        return serialize($properties);
    }

    public function __debugInfo()
    {
        $properties = [];
        if($this->properties instanceof Collection) {
            if($this->properties->count() > 0) {
                $this->properties
                    ->where('isReadable', true)
                    ->map(function($property) use (&$properties) {
                        $properties[$property->getName()] = $property->getValue();
                    });
            }
        } else {
            $properties = $this->data;
        }
        return $properties;
    }

    private function initCollection() : void
    {
        $this->properties = new Collection;
        $properties = (new ReflectionClass($this))->getProperties();
        foreach($properties as $property) {
            $entity = (new Property($property->getName()))
                ->setReadable(!in_array($property->getName(), $this->unreadable))
                ->setWritable(!in_array($property->getName(), $this->unwritable));
            $property->setAccessible(true);
            $entity->setValue($property->getValue($this), false);
            $property->setAccessible(false);

            $this->properties->push($entity);
        }
    }

    private function addProperties(array $properties) : void
    {
        foreach($properties as $property => $value) {
            $this->properties->map(function($entity) use ($property, $value) {
                if($entity->getName() == $property) {
                    $entity->setValue($value, false);
                }
            });
        }
    }

    private function setUnwritable(string $property) : void
    {
        $this->properties->where('name', $property)->setWritable(false);
    }

    private function setUnreadable(string $property) : void
    {
        $this->properties->where('name', $property)->setReadable(false);
    }

    public function getProperties() : Collection
    {
        return $this->properties;
    }

    public function equals($model) : bool
    {

    }

    public function hash()
    {

    }

    public function getIterator()
    {

    }

    public function fill(array $data) : void
    {
        $this->properties->map(function($property) use ($data) {
            $cell = Utils::convertPropertyToCell($property->getName());
            if($property->isEmpty() &&
               array_key_exists($cell, $data)) {
                $property->setValue($data[$cell], false);
            }
        });
    }

    public function only(array $properties) : self
    {
        $clone = clone $this;
        $clone->properties = new Collection();
        $this->properties->map(function($property) use ($properties, &$clone) {
            if(in_array($property->getName(), $properties)) {
                $clone->getProperties()->push($property);
            }
        });
        return $clone;
    }

    private function parseComment(string $comment) : array
    {
        preg_match_all(
            "#(@[a-zA-Z]+\s*[a-zA-Z0-9, ()_].*)#",
            $comment,
            $matches,
            PREG_PATTERN_ORDER);
        return $matches[0];
    }

    public function __sleep()
    {
        unset($this->query);
        $this->properties->map(function($property) {
            $property->setWritable(true);
            $name = $property->getName();
            $this->$name = $property->getValue();
            $property->setWritable(false);
        });
        $properties = get_object_vars($this);
        unset($properties['tableName'], $properties['unwritable'], $properties['unreadable'], $properties['adapter']);
        return array_keys($properties);
    }
}
