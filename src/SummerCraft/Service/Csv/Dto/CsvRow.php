<?php

namespace SummerCraft\Service\Csv\Dto;

class CsvRow
{
    /**
     * @param string[] $fields key-values
     */
    public function __construct(
        public array $fields,
    ) {
    }

    public static function build(string $fileName, CsvHeader $csvHeader, array $lineData, ?int $rowNumber): self
    {
        $fields = [];
        foreach ($csvHeader->fields as $key => $headerValue) {
            if (!isset($lineData[$key])) {
                throw new \RuntimeException(sprintf(
                    "File %s, Header key %s not found in row on row %s. Header: %s Row: %s",
                    $fileName,
                    $key,
                    $rowNumber,
                    print_r($csvHeader->fields, true),
                    print_r($lineData, true)
                ));
            }
            $fields[$headerValue] = $lineData[$key];
        }
        return new self($fields);
    }
}
