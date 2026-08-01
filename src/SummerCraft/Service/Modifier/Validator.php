<?php

namespace SummerCraft\Service\Modifier;

class Validator
{
    /**
     * Check valid Email syntax. Thin wrapper over filter_var(FILTER_VALIDATE_EMAIL)
     * with IDN domain support — the previous hand-rolled regex
     * used a `{2,6}` TLD length limit that rejected legitimate modern TLDs
     * longer than 6 characters (`.company`, `.photography`, etc.).
     * @param string $email
     * @return bool
     */
    public static function isValidEmail($email)
    {
        if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46') && $atpos = strpos($email, '@')) {
            $email = substr($email, 0, ++$atpos) . idn_to_ascii(substr($email, $atpos), 0, INTL_IDNA_VARIANT_UTS46);
        }
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }


}
