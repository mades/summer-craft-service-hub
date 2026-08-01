<?php

namespace SummerCraft\Service\Csv;

use SummerCraft\Service\Csv\Dto\CsvRow;

class CsvWriter
{
    private $stream;
    private bool $headerWrote = false;

    public function __construct(
        string $fileName,
        bool $withHeader = true
    ) {
        if (!$withHeader) {
            $this->headerWrote = true;
        }
        $this->stream = fopen($fileName, 'w');
    }

    public function write(CsvRow $csvRow): void
    {
        if (!$this->headerWrote) {
            fputcsv($this->stream, array_keys($csvRow->fields), ',', '"', '\\');
            $this->headerWrote = true;
        }
        fputcsv($this->stream, array_values($csvRow->fields), ',', '"', '\\');
    }

    public function complete(): void
    {
        fclose($this->stream);
    }
}