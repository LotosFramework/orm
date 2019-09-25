<?php

namespace Lotos\ORM\Interfaces;

use Ds\Hashable;
use IteratorAggregate;

interface ModelInterface extends Hashable, IteratorAggregate {
    public function equals($model) : bool;
    public function hash();
}
