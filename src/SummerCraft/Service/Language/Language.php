<?php

namespace SummerCraft\Service\Language;

interface Language
{
    public function setLanguage(string $language): void;

    /**
     * A string from a language volume.
     *
     * The alias is "volume:source text", and the text after the colon is what an
     * untranslated install shows — so it belongs in the source language, not as a
     * slug. $default is for values that cannot be written as a key: an email body
     * with placeholders, anything long or multi-line.
     *
     * @param string $alias volume and, after the colon, the source-language text
     * @param array $replacePairs key -> value replace pairs
     * @param string|null $default shown when the volume has no translation; the
     *                             text after the colon is used when this is null
     */
    public function get(string $alias, array $replacePairs = [], ?string $default = null): string;
}
