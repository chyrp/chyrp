<?php

require_once __DIR__."/lib/captcha.php";
require_once __DIR__."/lib/recaptchalib.php";

class ReCaptcha extends Modules
{
    public function __init()
    {
        global $captchaHooks;
        $captchaHooks[] = "ReCaptchaCaptcha";
    }
}

class ReCaptchaCaptcha implements Captcha
{
    public static function getCaptcha()
    {
        return recaptcha_get_html(PUBLIC_KEY);
    }

    public static function verifyCaptcha()
    {
        $resp = recaptcha_check_answer(
            PRIVATE_KEY,
            $_SERVER['REMOTE_ADDR'],
            $_POST['recaptcha_challenge_field'],
            $_POST['recaptcha_response_field']
        );

        if (!$resp->is_valid) {
            return false;
        } else {
            return true;
        }
    }
}
