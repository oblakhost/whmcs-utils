<?php

/**
 * HookManager class file
 *
 * @package WHMCS Utils
 */

namespace Oblak\WHMCS\Hooks;

use Oblak\WHMCS\Traits\Singleton;

/**
 * Instatiates all the hooks
 */
class HookManager
{
    use Singleton;

    /**
     * Hooks to be loaded
     *
     * @var Hookable[]
     */
    protected $hooks = [];

    /**
     * Class constructor
     */
    protected function __construct()
    {
    }

    public function registerHooks(string $module, string ...$hooks): void
    {
        foreach ($hooks as $hook) {
            $this->hooks[$module][] = new $hook();
        }
    }
}
