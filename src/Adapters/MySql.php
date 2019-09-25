<?php

namespace Lotos\ORM\Adapters;

use Lotos\ORM\Abstractions\Adapter as AbstractAdapter;
use Lotos\ORM\Interfaces\AdapterInterface;

class MySql extends AbstractAdapter implements AdapterInterface
{
    public function __call($method, $args)
    {
        echo $method;
        print_r($args);
    }
}
