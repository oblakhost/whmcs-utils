<?php

namespace Oblak\WHMCS\Module;

use Oblak\WHMCS\Traits\Singleton;
use ReflectionClass;
use WHMCS\Config\Setting;
use WHMCS\Database\Capsule;
use WHMCS\Input\Sanitize;
use WHMCS\Session;

abstract class BaseModule
{
    use Singleton;

    /**
     * Module name
     *
     * @var string
     */
    protected string $moduleName = '';

    /**
     * Module configuration
     *
     * @var array
     */
    protected readonly array $settings;

    /**
     * Class constructor
     *
     * @param array|null $settings Module settings
     */
    protected function __construct()
    {
        $this->settings = $this->loadSettings();
        $this->loadLanguage();
    }

    /**
     * Returns the Gateway module configuration
     *
     * @return array
     */
    abstract public static function getConfig(): array;

    /**
     * Loads the module settings from the database
     *
     * @return array
     */
    protected function loadSettings(): array
    {
        $settings    = [];
        $rawSettings = Capsule::table('tbladdonmodules')
            ->select('setting', 'value')
            ->where('module', $this->moduleName)
            ->get();

        foreach ($rawSettings as $setting) {
            $settings[$setting->setting] = $setting->value;
        }

        return Sanitize::convertToCompatHtml($settings);
    }

    /**
     * Loads the module language file - across the WHMCS
     */
    protected function loadLanguage()
    {
        $currentLanguage = $this->getCurrentLanguage(defined('CLIENTAREA'));
        $languageFile    = $this->getLanguageFile($currentLanguage);

        if ($languageFile === null) {
            return;
        }

        global $_LANG;

        require $languageFile;

        $_LANG[$this->moduleName] = $_ADDONLANG;
    }

    /**
     * Get the language file path
     *
     * @param  string      $languageName Language name
     * @return string|null               Language file path
     */
    private function getLanguageFile(string $languageName): ?string
    {
        $languageFile = "{$this->getModuleDir()}/lang/{$languageName}.php";

        if (!file_exists($languageFile)) {
            $languageFile = "{$this->getModuleDir()}/lang/english.php";
        }

        return file_exists($languageFile) ? $languageFile : null;
    }

    /**
     * Get the module directory path
     *
     * @return string Module directory path
     */
    public function getModuleDir(): string
    {
        $path = dirname((new ReflectionClass($this))->getFileName());
        while (basename($path) != $this->moduleName) {
            $path = dirname($path);
        }

        return $path;
    }

    /**
     * Get the current language
     *
     * @param  bool   $inClientArea Whether the language is in the client area
     * @return string               Language name
     */
    public function getCurrentLanguage(bool $inClientArea = true): string
    {
        $settingLanguage = Setting::getValue('Language');
        $sessionLanguage = Session::get('Language');
        $requestLanguage = $_REQUEST['language'] ?? '';

        $languageName = $settingLanguage;

        if ($sessionLanguage != "") {
            $languageName = $sessionLanguage;
        }

        if ($inClientArea && $requestLanguage != "") {
            $languageName = $requestLanguage;
        }

        return $languageName;
    }
}
