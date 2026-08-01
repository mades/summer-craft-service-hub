<?php

namespace SummerCraft\Service\HtmlParser;

interface HtmlParser
{
    /**
     * @param string|null $html
     */
    public function load(?string $html): HtmlParser;

    /**
     * @return HtmlParser[]
     */
    public function find(string $selector): array;

    public function findOne(string $selector): HtmlParser;

    public function exist(): bool;

    public function content(): string;

    public function attr(string $key): string;

    /**
     * Free resources and all children resources
     */
    public function unload(): void;
}
