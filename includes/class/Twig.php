<?php

if (!defined('TWIG_BASE'))
    define('TWIG_BASE', dirname(__FILE__) . '/Twig');

# Load automatically on initialization.
require TWIG_BASE . '/Autoloader.php';
Twig_Autoloader::register();

# Chyrp Twig extensions.
require_once "TwigExtensions/Chyrp_Twig_Extension.php";

/**
* Twig Engine Setup
*/
class Twig
{
    /**
     * Templates path on filesystem
     * @var string
     */
    public $templateDir = '';

    function __construct()
    {
        $this->templateDir = $this->getTemplateDirectory();
        $this->twig = new Twig_Environment($this->getLoader($this->templateDir),
            $this->getOptions());
        $this->loadExtensions();
    }

    private function getLoader($directory)
    {
        $loader = new Twig_Loader_Filesystem($directory);

        return $loader;
    }

    private function getTemplateDirectory()
    {
        if (ADMIN) {
            $adminTheme = fallback(Config::current()->admin_theme, "default");
            $templateDir = ADMIN_THEMES_DIR.'/'.$adminTheme;
        } else {
            $templateDir = THEME_DIR;
        }

        return $templateDir;
    }

    private function getOptions()
    {
        return array('cache' => $this->getCache(),
                     'debug' => $this->getDebug(),
                     'autoescape' => false);
    }

    private function getDebug()
    {
        return defined('DEBUG') ? true : false ;
    }

    private function getCache()
    {
        $cache = (is_writable(INCLUDES_DIR."/caches") && !DEBUG &&
            !PREVIEWING && !defined('CACHE_TWIG') || CACHE_TWIG);

        return ($cache ? INCLUDES_DIR."/caches" : false);
    }

    private function loadExtensions()
    {
        if ($this->getDebug())
            $this->twig->addExtension(new Twig_Extension_Debug());

        $this->twig->addExtension(new Chyrp_Twig_Extension());
    }

    public function display($file = null, $context = null)
    {
        return $this->twig->display($file, $context);
    }

    /**
     * Function: current
     * Returns a singleton reference to the current configuration.
     */
    public static function & current() {
        static $instance = null;
        return $instance = (empty($instance)) ? new self() : $instance ;
    }
}
