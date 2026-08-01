<?php

namespace SummerCraft\Service\Modifier;


use RuntimeException;

class StringModifier
{
    private const CHARSET = 'UTF-8';
    public const FILTER_BASIC_EN = 1;
    public const FILTER_BASIC_EN_RU = 2;
    public const FILTER_BASIC_EN_RU_DOT = 3;
    public const FILTER_BASIC_EN_BY = 4;

    public static function mbInit(): void
    {
        mb_internal_encoding(self::CHARSET);
        mb_regex_encoding(self::CHARSET);
    }

    /**
     * @param string $textPattern Init string
     * @param array $replacePairs key value replaces
     */
    public static function replace(string $textPattern, array $replacePairs = []): string
    {
        $result = $textPattern;
        foreach ($replacePairs as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        return $result;
    }

    public static function replaceTemplate(string $textPattern, callable $callback): string
    {
        return preg_replace_callback('/\{#([^\}]+)\}/', function($matches) use ($callback) {
            $placeholder = $matches[1];
            return $callback($placeholder);
        }, $textPattern);
    }

    public static function toLower(string $string): string
    {
        return mb_strtolower($string, self::CHARSET);
    }

    public static function toUpper(string $string): string
    {
        return mb_strtoupper($string, self::CHARSET);
    }

    public static function sub(string $string, $start, $length = null): string
    {
        return mb_substr($string, $start, $length, self::CHARSET);
    }

    public static function pos(string $haystack, string $needle, $offset = 0): ?int
    {
        $result = mb_strpos($haystack, $needle, $offset, self::CHARSET);
        if ($result === false) {
            return null;
        }
        return $result;
    }

    /**
     * NOT IMPLEMENTED. Intended to filter risky content from an HTML string before
     * re-embedding it in HTML (needs a real sanitizer, e.g. HTMLPurifier), but
     * currently returns its input unchanged — calling it is worse than not calling
     * it, since the name/docblock read as "this is safe to embed" when it isn't.
     * Throws instead of silently passing through unsanitized HTML.
     * @throws RuntimeException always
     */
    public static function htmlForHtml(string $string): string
    {
        throw new RuntimeException(
            __METHOD__ . '() is not implemented and must not be used to sanitize HTML for re-embedding'
        );
    }

    /**
     * Filter risk content from non HTML string to insert to HTML
     */
    public static function textForHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, self::CHARSET);
    }

    /**
     * NOT IMPLEMENTED. Intended to strip HTML down to plain text, but currently
     * discards the input and always returns ''. Throws instead of silently
     * returning a value indistinguishable from "the input actually was empty".
     * @throws RuntimeException always
     */
    public static function fromHtml(string $string): string
    {
        throw new RuntimeException(__METHOD__ . '() is not implemented');
    }

    /**
     * When $filterOption is a string, it is interpolated verbatim
     * into a `[^...]` regex character class — it is regex-character-class syntax,
     * not plain text, and is NOT escaped. Every current caller (grepped across
     * all 4 repos) passes a hardcoded literal or a class constant, so this is not
     * exploitable today; it becomes a regex-injection/ReDoS risk the moment a
     * caller ever builds $filterOption from dynamic/user-controlled input.
     * Deliberately not auto-escaping here: the strings already in use rely on
     * unescaped ranges (e.g. 'A-Za-z') and hand-placed backslash escapes for
     * literal ']'/'\\' — a generic escaper would corrupt those, with zero test
     * coverage to catch the regression. Keep $filterOption trusted/static.
     */
    public static function filterChars($textPattern, $filterOption)
    {
        $result = $textPattern;
        if (is_array($filterOption)) {
            foreach ($filterOption as $option) {
                $result = self::filterChars($result, $option);
            }
        }
        if (is_int($filterOption)) {
            if ($filterOption === self::FILTER_BASIC_EN) {
                $result = preg_replace('/[^A-Za-z0-9_-]/ui', '', $result);
            }
            if ($filterOption === self::FILTER_BASIC_EN_RU) {
                $result = preg_replace('/[^A-Za-zА-Яа-я0-9_-]/ui', '', $result);
            }
            if ($filterOption === self::FILTER_BASIC_EN_RU_DOT) {
                $result = preg_replace('/[^A-Za-zА-Яа-я0-9\._-]/ui', '', $result);
            }
            if ($filterOption === self::FILTER_BASIC_EN_BY) {
                $result = preg_replace('/[^A-Za-zА-Яа-яіў0-9_-]/ui', '', $result);
            }
        }
        if (is_string($filterOption)) {
            $result = preg_replace('/[^' . $filterOption . ']/ui', '', $result);
        }

        return $result;
    }

    /**
     * get word for 1 2 5th count (russian)
     * Вставляет слово со склонением в зависимости от числа
     */
    public static function wordEnd(int $count, string $word1, string $word2, string $word5): string
    {
        $mod100 = $count % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return $word5;
        }
        switch ($mod100 % 10) {
            case 1:  { return $word1; }
            case 2: case 3: case 4:  { return $word2; }
            default: { return $word5; }
        }
    }

    public static function fillWordEnd(int $count, string $word125String, bool $fillCount = true): string
    {
        $word125Arr = explode(';', $word125String);
        if (!isset($word125Arr[1])) $word125Arr[1] = $word125Arr[0];
        if (!isset($word125Arr[2])) $word125Arr[2] = $word125Arr[0];
        return (($fillCount ? $count . ' '  : ' ')
            . self::wordEnd($count, $word125Arr[0], $word125Arr[1], $word125Arr[2]));
    }

    /**
     * Convert between two date formats
     * <br/> m: 2014-05-22
     * <br/> en: 05/22/2014
     * <br/> ru: 22.05.2014
     * @param string $input Input date string
     * @param string $type m-en, m-ru, ru-m, en-m, ru-en, en-ru
     */
    public static function dateConvert(string $input, string $type = 'm-en'): string
    {
        $type_arr = explode('-', $type);
        if (count($type_arr) !== 2) {
            throw new RuntimeException(sprintf('Invalid argument %s got [%s]', 'dateConvertType', $type));
        }
        [$from, $to] = $type_arr;
        $m_date = $input;
        if ($from === 'en') {
            $date_arr = explode('/', $input);
            if (count($date_arr) !== 3) {
                throw new RuntimeException(sprintf('Invalid argument %s got [%s]', 'dateConvertEnInput', $input));
            }
            $m_date = str_pad((string)(int)$date_arr[2], 4, '0', STR_PAD_LEFT).'-'
                .str_pad((string)(int)$date_arr[0], 2, '0', STR_PAD_LEFT).'-'
                .str_pad((string)(int)$date_arr[1], 2, '0', STR_PAD_LEFT);
        }
        if ($from === 'ru') {
            $date_arr = explode('.', $input);
            if (count($date_arr) != 3) {
                throw new RuntimeException(sprintf('Invalid argument %s got [%s]', 'dateConvertRuInput', $input));
            }
            $m_date = str_pad((string)(int)$date_arr[2], 4, '0', STR_PAD_LEFT).'-'
                .str_pad((string)(int)$date_arr[1], 2, '0', STR_PAD_LEFT).'-'
                .str_pad((string)(int)$date_arr[0], 2, '0', STR_PAD_LEFT);
        }
        $ret_date = $m_date;
        $date_arr = explode('-', $m_date);
        if ($to === 'm') {
            $ret_date = (int)$date_arr[0] . '-'
                . str_pad((string)(int)$date_arr[1], 2, '0', STR_PAD_LEFT) . '-'
                . str_pad((string)(int)$date_arr[2], 2, '0', STR_PAD_LEFT);
        }
        if ($to === 'en') {
            $ret_date = str_pad((string)(int)$date_arr[1], 2, '0', STR_PAD_LEFT).'/'
                .str_pad((string)(int)$date_arr[2], 2, '0', STR_PAD_LEFT).'/'
                . (int)$date_arr[0];
        }
        if ($to === 'ru') {
            $ret_date = str_pad((string)(int)$date_arr[2], 2, '0', STR_PAD_LEFT).'.'
                .str_pad((string)(int)$date_arr[1], 2, '0', STR_PAD_LEFT).'.'
                . (int)$date_arr[0];
        }
        return $ret_date;
    }

    public static function mbStrPadLeft(string $input, int $pad_length, string $pad_string, string $encoding="UTF-8"): string
    {
        return str_pad($input,strlen($input)-mb_strlen($input,$encoding)+$pad_length, $pad_string, STR_PAD_LEFT);
    }

    public static function mbStrPadRight(string $input, int $pad_length, string $pad_string, string $encoding="UTF-8"): string
    {
        return str_pad($input,strlen($input)-mb_strlen($input,$encoding)+$pad_length, $pad_string, STR_PAD_RIGHT);
    }

    public static function mbStrPad(string $input, int $pad_length, string $pad_string, $pad_style, string $encoding="UTF-8"): string
    {
        return str_pad($input,strlen($input)-mb_strlen($input,$encoding)+$pad_length, $pad_string, $pad_style);
    }

    /**
     * Encode to JSON
     */
    public static function jsonEncode(array $param, bool $pretty = false): string
    {
        $options = JSON_UNESCAPED_SLASHES ^ JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $options ^= JSON_PRETTY_PRINT;
        }
        $result = json_encode($param, $options);
        if (!$result) {
            $lastError = json_last_error_msg();
            $paramPrint = print_r($param, true);
            throw new RuntimeException("Json Encode Error: $lastError; [$paramPrint], [$result]");
        }
        return $result;
    }

    public static function uriEncode(string $string): string
    {
        $res = urlencode($string);
        $res = str_replace("%3A",":",$res);
        $res = str_replace("%2F","/",$res);
        return $res;
    }

    public static function uriDecode(string $string): string
    {
        return urldecode($string);
    }

    public static function objectToJsonEncode(object $param, bool $pretty = false): string
    {
        $options = JSON_UNESCAPED_SLASHES ^ JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $options ^= JSON_PRETTY_PRINT;
        }
        $result = json_encode($param, $options);
        if (!$result) {
            $lastError = json_last_error_msg();
            $paramPrint = print_r($param, true);
            throw new RuntimeException("Json Encode Error: $lastError; [$paramPrint], [$result]");
        }
        return $result;
    }

    /**
     * Encode a scalar string as a JSON string literal — a self-contained,
     * already-quoted JS string value (e.g. 'foo' -> '"foo"'). Unlike
     * jsonEncode()/objectToJsonEncode(), this exists specifically for embedding
     * PHP strings safely into a JS-string-literal context (inside a <script>
     * block): json encoding correctly escapes backslash, quotes and control
     * characters for that context, which htmlspecialchars() (used by
     * StringModifier::textForHtml()/AbstractBuilder::attr()/html()) does not.
     */
    public static function stringToJsonEncode(string $param, bool $pretty = false): string
    {
        $options = JSON_UNESCAPED_SLASHES ^ JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $options ^= JSON_PRETTY_PRINT;
        }
        $result = json_encode($param, $options);
        if (!$result) {
            $lastError = json_last_error_msg();
            throw new RuntimeException("Json Encode Error: $lastError; [$param]");
        }
        return $result;
    }

    /**
     * @param string $encoded json string
     * @param bool $force return empty array if json is broken
     * @return array|null
     */
    public static function jsonDecode(string $encoded, bool $force = true): ?array
    {
        if ($encoded === '') {
            return ($force) ? [] : null;
        }
        $result = json_decode($encoded, true);
        if (json_last_error()) {
            return ($force) ? [] : null;
        }
        return $result;
    }

    public static function charEncode(string $input, $from = 'UTF-8', $to = 'UTF-8'): string
    {
        return mb_convert_encoding($input, $to, $from);
        //return iconv($from, $to, $input);
    }

    public static function rootFileNamespace(string $rootClassName): string
    {
        $rootClassNameSegments = explode('\\', $rootClassName);
        unset($rootClassNameSegments[count($rootClassNameSegments) - 1]);
        return '\\' . implode('\\', $rootClassNameSegments );
    }

    public static function floatToNormalString(float $number, int $maxDecimals): string
    {
        return rtrim(rtrim(number_format($number, $maxDecimals, '.', ''), '0'), '.');
    }

    public static function floatStringToNormalString(string $number): string
    {
        return rtrim(rtrim($number, '0'), '.');
    }

}
