<?php

namespace Lotos\ORM\Abstractions;

abstract class Config
{
    const
        DIR_MODELS = 'app/Models/',
        DIR_COLLECTIONS = 'app/Collections/',
        ADAPTER = 'MySql';

    public static function getConnectParams() : array
    {
        return array_filter(getenv(), function($param) {
            return !empty(trim($param));
        });
    }
}
