<?php

/*
 * This file is part of Chyrp.
 *
 * (c) 2014 Arian Xhezairi
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Autoloads Chyrp's Twig Extensions classes.
 *
 * @package    chyrp
 * @author     Arian Xhezairi <email@chyrp.net>
 */
class Twig_Extensions_Autoloader
{
    /**
     * Registers Twig_Extensions_Autoloader as an SPL autoloader.
     */
    public static function register()
    {
        spl_autoload_register(array(new self(), 'autoload'));
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name.
     *
     * @return boolean Returns true if the class has been loaded
     */
    public static function autoload($class)
    {
        if (strpos($class, 'Twig_Extension') === false) {
            return;
        }

        if (file_exists($file = dirname(__FILE__).'/'.str_replace('/', '_', $class).'.php')) {
            require $file;
        }
    }
}
