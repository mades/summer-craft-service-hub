<?php

namespace SummerCraft\Service\Modifier;


class Filter
{
    public static function phoneClearHard($phoneString, $replacePrefixes = [])
    {
        //$result = strtr($phoneString,[' '=>'','+'=>'','-'=>'',"\t"=>'','('=>'',')'=>'']);

        $clearedPhone = preg_replace('%[^0-9]%', '', $phoneString);
        if (strpos($phoneString,'+') === 0) {
            $clearedPhone = '+' . $clearedPhone;
        }
        if (strpos($phoneString,'+') === false) {
            // maybe prefix
            $prefixReplaced = false;
            foreach ($replacePrefixes as $fromPrefix => $toPrefix) {
                if (strpos($clearedPhone, $fromPrefix) === 0) {
                    $clearedPhone = $toPrefix . substr($clearedPhone, strlen($fromPrefix));
                    $prefixReplaced = true;
                }
            }

        }

        return $clearedPhone;
    }



}
