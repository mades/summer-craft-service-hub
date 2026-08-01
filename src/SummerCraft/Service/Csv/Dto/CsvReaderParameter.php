<?php

namespace SummerCraft\Service\Csv\Dto;

class CsvReaderParameter
{
    public function __construct(
        public CsvHeader $csvHeader,
        public CsvRow $csvRow,
        public int $rowNumber,
        public float $percentage,
    ) {
    }
}
