<?php

namespace Slipstream;

abstract class Singleton
{
    protected static $_instance;

    /**
     * Gets the class instance
     */
    protected static function getInstance($class)
    {
        if (!self::$_instance) {
            $ref = new \ReflectionClass($class);
            self::$_instance = $ref->newInstanceArgs();
        }

        return self::$_instance;
    }
}
