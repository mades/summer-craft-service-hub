<?php

namespace SummerCraft\Service\Modifier;

class Debugger
{
    public static function logAndExit($message, bool $forHtml = false): void
    {
        self::log($message, $forHtml);
        exit;
    }

    public static function log($message, bool $forHtml = false, bool $asString = false): string
    {
        if(is_object($message) || is_array($message)){
            $message = print_r($message,true);
        } else {
            $message = (string) $message;
        }
        $result = '';
        if ($forHtml) {
            $result .= '<pre>';
        }
        $result .= $message;
        if ($forHtml) {
            $result .=  '</pre>';
        }
        if ($asString) {
            return $result;
        }
        echo $result;
        return '';
    }
}
