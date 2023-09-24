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
     * Singleton instance
     *
     * @var static
     */
    private static $instance;

    /**
     * Returns the singleton instance
     *
     * @return static
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Class constructor
     */
    abstract protected function __construct();
}
