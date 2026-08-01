<?php

namespace SummerCraft\Service\HtmlParser;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;

/**
 * Alternative to SimpleHtmlDomParser built on the native DOMDocument/DOMXPath
 * extensions instead of the vendored SimpleHtmlDom library.
 *
 * Supports exactly the selector vocabulary actually used across all 4 repos
 * (verified 2026-07-12): a tag name, a `.class` name, optionally combined
 * (`tag.class`), chained with a descendant (space) or direct-child (`>`)
 * combinator. Anything outside that — attribute selectors, `#id`,
 * pseudo-classes — throws rather than silently matching the wrong nodes.
 */
class DomHtmlParser implements HtmlParser
{
    /**
     * @var HtmlParser[]
     */
    private array $childInstances = [];

    public function __construct(
        private DOMDocument|DOMElement|null $instance = null,
        private ?DOMXPath $xpath = null,
    ) {
    }

    public function load(?string $html): HtmlParser
    {
        if ($this->instance !== null) {
            $this->unload();
        }
        if (!$html) {
            return $this;
        }

        $document = new DOMDocument();
        // Force UTF-8 interpretation without literally injecting a <meta>
        // tag into the source (DOMDocument otherwise guesses encoding from
        // the raw bytes and mangles anything non-ASCII, e.g. Cyrillic/Polish
        // diacritics on the real sites this parses).
        $previousInternalErrors = libxml_use_internal_errors(true);
        // DOMDocument::loadHTML() always parses $source as literal HTML text —
        // unlike the SimpleHtmlDom constructor this replaces, there is no
        // URL/file-path sniffing to worry about here. Deliberately
        // NOT passing LIBXML_NOENT/LIBXML_DTDLOAD/LIBXML_DTDATTR, so a crafted
        // <!DOCTYPE> with an external entity in $html cannot make libxml fetch
        // anything (see DomHtmlParserTest for a regression test).
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousInternalErrors);

        $this->instance = $document;
        $this->xpath = new DOMXPath($document);
        return $this;
    }

    public function find(string $selector): array
    {
        if ($this->instance === null) {
            return [];
        }
        $result = [];
        foreach ($this->queryNodes($selector) as $node) {
            $newInstance = new DomHtmlParser($node, $this->xpath);
            $this->childInstances[] = $newInstance;
            $result[] = $newInstance;
        }
        return $result;
    }

    public function findOne(string $selector): HtmlParser
    {
        if ($this->instance === null) {
            return new DomHtmlParser();
        }
        foreach ($this->queryNodes($selector) as $node) {
            $newInstance = new DomHtmlParser($node, $this->xpath);
            $this->childInstances[] = $newInstance;
            return $newInstance;
        }
        return new DomHtmlParser();
    }

    public function exist(): bool
    {
        return $this->instance !== null;
    }

    public function content(): string
    {
        if (!$this->instance instanceof DOMElement) {
            return '';
        }
        $html = '';
        foreach ($this->instance->childNodes as $child) {
            $html .= $this->instance->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    public function attr(string $key): string
    {
        if (!$this->instance instanceof DOMElement) {
            return '';
        }
        return $this->instance->getAttribute($key);
    }

    /**
     * Free resources and all children resources
     */
    public function unload(): void
    {
        foreach ($this->childInstances as $childInstance) {
            $childInstance->unload();
        }
        $this->childInstances = [];
        $this->instance = null;
        $this->xpath = null;
    }

    /**
     * @return DOMElement[]
     */
    private function queryNodes(string $selector): array
    {
        if ($this->xpath === null) {
            return [];
        }
        $context = $this->instance instanceof DOMElement ? $this->instance : null;
        $found = $this->xpath->query(self::cssSelectorToXPath($selector), $context);
        if ($found === false) {
            return [];
        }
        $result = [];
        foreach ($found as $node) {
            if ($node instanceof DOMElement) {
                $result[] = $node;
            }
        }
        return $result;
    }

    private static function cssSelectorToXPath(string $selector): string
    {
        $tokens = preg_split('/\s*(>)\s*|\s+/', trim($selector), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            throw new InvalidArgumentException("Empty CSS selector");
        }

        $xpath = '.';
        $combinator = '//';
        foreach ($tokens as $token) {
            if ($token === '>') {
                $combinator = '/';
                continue;
            }
            $xpath .= $combinator . self::simpleSelectorToXPathStep($token);
            $combinator = '//';
        }
        return $xpath;
    }

    private static function simpleSelectorToXPathStep(string $simpleSelector): string
    {
        if (!preg_match('/^([a-zA-Z][a-zA-Z0-9]*)?(\.[\w-]+)?$/', $simpleSelector, $matches)) {
            throw new InvalidArgumentException("Unsupported CSS selector part: {$simpleSelector}");
        }
        $tag = ($matches[1] ?? '') !== '' ? $matches[1] : '*';
        $class = ($matches[2] ?? '') !== '' ? substr($matches[2], 1) : null;
        if ($class === null) {
            return $tag;
        }
        return "{$tag}[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]";
    }
}
