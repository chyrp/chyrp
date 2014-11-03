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
    public $templatePaths = array();
    private $getLoader    = null;

    private function __construct()
    {
        $this->twig = new Twig_Environment($this->getLoader(), $this->getOptions());
        $this->loadExtensions();
    }

    public function addTemplatePath($path = null)
    {
        $this->loader->prependPath($path);
    }

    private function getLoader()
    {
        $this->setTemplatePaths();
        $this->loader = new Twig_Loader_Filesystem($this->templatePaths);

        return $this->loader;
    }

    private function setTemplatePaths()
    {
        if (ADMIN) {
            $adminTheme = fallback(Config::current()->admin_theme, "default");
            $this->templatePaths[] = ADMIN_THEMES_DIR.'/'.$adminTheme;
        } else {
            $this->templatePaths[] = THEME_DIR;
        }
    }

    private function loadExtensions()
    {
        if ($this->getDebug())
            $this->twig->addExtension(new Twig_Extension_Debug());

        $this->twig->addExtension(new Chyrp_Twig_Extension());
    }

    private function getOptions()
    {
        return array('cache' => $this->getCache(),
                     'debug' => $this->getDebug(),
                     'autoescape' => false);
    }

    private function getDebug()
    {
        return DEBUG ? true : false ;
    }

    private function getCache()
    {
        $cache = (is_writable(INCLUDES_DIR."/caches") && !DEBUG &&
            !PREVIEWING && !defined('CACHE_TWIG') || CACHE_TWIG);

        return ($cache ? INCLUDES_DIR."/caches" : false);
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
