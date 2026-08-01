<?php

namespace SummerCraft\Service\Network;

/**
 * Cookies received via Set-Cookie, scoped by the domain that's allowed to receive
 * them back — not a single flat bag shared by every host talked to. A cookie
 * whose Set-Cookie had an explicit `Domain=` attribute is stored
 * under that domain and matches the domain itself plus any subdomain
 * (`Domain=example.com` also applies to `www.example.com`). A cookie with no
 * Domain attribute is host-only per RFC 6265 §5.3 — stored under the exact host
 * that sent it, no subdomain matching.
 */
class CookieJar
{
    /** @var array<string, array{exact: bool, values: array<string, string>}> */
    private array $entries = [];

    /**
     * @param string $setCookieLine everything after "Set-Cookie:", e.g.
     *                              "sid=abc123; Domain=example.com; Path=/"
     */
    public function storeFromSetCookieHeader(string $setCookieLine, string $requestHost): void
    {
        $parts = explode(';', $setCookieLine);
        $nameValue = array_shift($parts);
        $eqPos = strpos($nameValue, '=');
        if ($eqPos === false) {
            return;
        }
        $name = trim(substr($nameValue, 0, $eqPos));
        $value = trim(substr($nameValue, $eqPos + 1));
        if ($name === '') {
            return;
        }

        $domain = null;
        foreach ($parts as $attribute) {
            $attribute = trim($attribute);
            if (stripos($attribute, 'Domain=') === 0) {
                $domain = ltrim(substr($attribute, 7), '.');
            }
        }

        $scopeKey = strtolower($domain ?? $requestHost);
        if ($scopeKey === '') {
            return;
        }
        if (!isset($this->entries[$scopeKey])) {
            $this->entries[$scopeKey] = ['exact' => $domain === null, 'values' => []];
        }
        $this->entries[$scopeKey]['values'][$name] = $value;
    }

    /**
     * @return array<string, string> name => value, for every stored scope that covers $host
     */
    public function forHost(string $host): array
    {
        $host = strtolower($host);
        if ($host === '') {
            return [];
        }
        $result = [];
        foreach ($this->entries as $scopeDomain => $entry) {
            $matches = $entry['exact']
                ? $host === $scopeDomain
                : ($host === $scopeDomain || str_ends_with($host, '.' . $scopeDomain));
            if ($matches) {
                $result = array_merge($result, $entry['values']);
            }
        }
        return $result;
    }
}
