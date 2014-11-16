<?php

/*
 * Chyrp -- CAPTCHA interface
 *
 * This class was created to seperate out the CAPTCHA handling code to allow for more complex systems.
 * reCAPTCHA is still offered (as a plugin).
 */

define("PUBLIC_KEY", "YOUR PUBLIC KEY");
define("PRIVATE_KEY", "YOUR PRIVATE KEY");

interface Captcha
{
    public static function getCaptcha();
    public static function verifyCaptcha();
}
