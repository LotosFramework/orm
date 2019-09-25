<?php

namespace Lotos\ORM;

use Lotos\Collection\Collection;
use Lotos\ORM\Interfaces\{AdapterInterface, ModelInterface};
use Lotos\ORM\Abstractions\Config;

class Query
{

    private $model;
    private $table;
    private $adapter;
    private $sql;

    public function __construct(AdapterInterface $adapter, ModelInterface &$model)
    {
        $this->model = $model;
        $this->table = $this->model->getTableName();
        $this->adapter = $adapter;
        $this->collection = new Collection;
        $this->connector = new Connector(Config::getConnectParams());
    }

    public function save() : ?int
    {
        try {
            if(is_null($this->model->getId())) {
                return $this->createRecord();
            } else {
                $this->updateRecord();
                return null;
            }
        } catch(\Exception $e) {//@TODO сделать логичное исключение
            throw new \Exception($e->getMessage());
        }
    }

    private function updateRecord() : void
    {
        $properties = [];
        $this->model->getProperties()
            ->where('isChanged', true)
            ->map(function($property) use (&$properties){
                $properties[Utils::convertPropertyToCell($property->getName())] = $property->getValue();
            });
        $this->connector->update($this->model->getTableName(), $properties, $this->model->getId());
    }

    private function createRecord() : ?int
    {
        $properties = [];
        $this->model
            ->getProperties()
            ->whereNotIn('name', [
                'unreadable',
                'unwritable',
                'createdAt',
                'deletedAt',
                'updatedAt'
            ])->map(function($property) use (&$properties) {
                $cellName = Utils::convertPropertyToCell($property->getName());
                $properties[$cellName] = $property->getValue();
            });
        return $this->connector->insert($this->model->getTableName(), $properties);
    }

    public function getSql() : string
    {
        return $this->sql;
    }

    private function findBy($cell, $arguments) : void
    {
        $model = $this->getModelByProperty($cell);
    }

    private function getModelByProperty($cell)
    {
        if(!property_exists($this, $cell)) {
            foreach($this->model->entity as $property) {
                $types = ['int', 'string', 'bool', 'array'];
                if(!in_array($property->getType(), $types) &&
                    get_parent_class($property->getType()) == 'ORM\Model') {
                    $class = $property->getType();
                    return new $class;
                }
            }
        } else {
            return $this;
        }
    }

    public function select(array $args = null) : Query
    {
        $args = (is_null($args)) ? ' * ' : implode(', ', $args);
        $this->sql = 'select ' . $args;
        return $this;
    }

    public function from(string $table) : Query
    {
        $this->sql .= ' from ' . Utils::toCellName($table);
        return $this;
    }

    //первый аргумент должен быть именем поля
    //это может быть строка или closure, который возвращает имя поля
    //или это может быть объект query

    //второй аргумент может быть символом сравнения или может быть вообще не передан

    public function where(...$params) : Query
    {
        if(count($params) == 3) {
            list($prop, $symbol, $value) = $params;
        } elseif(count($params) == 2) {
            list($prop, $value) = $params;
        }
        $symbol = ($symbol) ?? '=';
        if($symbol == '=' && is_array($value)) {
            $symbol = ' in ';
        }
        if(!$this->sql) {
            return $this->select()
                ->from($this->table)
                ->where($prop, $symbol, $value); //@TODO а если prop и value это closure или другой sql-запрос? а если это table.cell?
        } else {
            $word = (substr_count($this->sql, ' where ') == 0)
                ? ' where '
                : ' and ';

            $this->sql .= $word . Utils::getCellName($prop) . $symbol . ':'.$prop;
            $this->params[$prop] = $value;
            return $this;
        }
    }

    public function first() : ?Model
    {
        $this->sql .= ' limit 1;';
        return $this->fillRow();
    }

    public function last() : ?Model
    {
        $this->sql .= ' order by `id` desc limit 1;';
        return $this->fillRow();
    }

    public function all() : ?Collection
    {
        if(empty($this->sql)) {
            $this->select()
                ->from($this->table);
        }
        $data = $this->connector->query($this->sql, $this->params);
        if(is_null($data) || count($data) ==0) {
            return null;
        } else {
            foreach($data as $entity) {
                $class = get_class($this->model);
                $model = new $class();
                $model->fill($entity);
                $this->collection->push($model);
            }
            return $this->collection;
        }
    }

    private function fillRow() : ?Model
    {
        $data = $this->connector->row($this->sql, $this->params);
        if($data) {
            $this->model->fill($data);
            return $this->model;
        }
        return null;
    }

    public function setTable(string $table) : void
    {
        $this->table = $table;
    }

    public function count() : int
    {
        $this->sql = str_replace('select *', 'select count(*)', str_replace('  ', ' ', $this->sql));
        $data = $this->connector->single($this->sql, $this->params);
        return $data;
    }
}
