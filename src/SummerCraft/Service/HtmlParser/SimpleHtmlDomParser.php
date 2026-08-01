<?php

namespace SummerCraft\Service\HtmlParser;

use SummerCraft\Service\HtmlParser\SimpleHtmlDom\SimpleHtmlDomNode;
use SummerCraft\Service\HtmlParser\SimpleHtmlDom\SimpleHtmlDom;

class SimpleHtmlDomParser implements HtmlParser
{
    /**
     * @var HtmlParser[]
     */
    private array $childInstances = [];

    public function __construct(
        private SimpleHtmlDom|SimpleHtmlDomNode|null $instance = null,
    ) {
    }

    public function load(?string $html): HtmlParser
    {
        if ($this->instance !== null) {
            $this->unload();
        }
        // the constructor no longer accepts raw $str at all (its URL/file
        // sniffing was removed), so parse $html as literal text via load(),
        // same as the vendored str_get_html() helper does.
        $this->instance = new SimpleHtmlDom();
        if ($html) {
            $this->instance->load($html);
        }
        if ($this->instance->root === null) {
            // Load was failed
            $this->unload();
        }
        return $this;
    }

    public function find(string $selector): array
    {
        if ($this->instance === null) {
            return [];
        }
        $foundInstances = $this->instance->find($selector);
        $result = [];
        foreach ($foundInstances as $foundInstance) {
            $newInstance = new SimpleHtmlDomParser($foundInstance);
            $this->childInstances[] = $newInstance;
            $result[] = $newInstance;
        }
        return $result;
    }


    public function findOne(string $selector): HtmlParser
    {
        if ($this->instance === null) {
            return new SimpleHtmlDomParser();
        }
        $foundInstances = $this->instance->find($selector);
        foreach ($foundInstances as $foundInstance) {
            $newInstance = new SimpleHtmlDomParser($foundInstance);
            $this->childInstances[] = $newInstance;
            return $newInstance;
        }
        return new SimpleHtmlDomParser();
    }

    public function exist(): bool
    {
        return $this->instance !== null;
    }

    public function content(): string
    {
        if (!$this->instance instanceof SimpleHtmlDomNode) {
            return '';
        }
        return $this->instance->innertext();
    }

    public function attr(string $key): string
    {
        if (!$this->instance instanceof SimpleHtmlDomNode) {
            return '';
        }
        return $this->instance->$key ?? '';
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

        if ($this->instance !== null) {
            $this->instance->clear();
            unset($this->instance);
            $this->instance = null;
        }
    }
}