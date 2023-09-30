<?php

/**
 * Singleton class file
 *
 * @package WHMCS Utils
 */

namespace Oblak\WHMCS\Traits;

/**
 * Singleton traits for classes
 */
trait Singleton
{
    /**
     * Singleton instances
     *
     * @var static
     */
    protected static array $instances = [];

    /**
     * Returns the singleton instance
     *
     * @return static
     */
    public static function getInstance()
    {
        $calledClass = class_basename(static::class);

        if (!static::$instances[$calledClass]) {
            static::$instances[$calledClass] = new static();
        }

        return static::$instances[$calledClass];
    }

    /**
     * Class constructor
     */
    abstract protected function __construct();
}
