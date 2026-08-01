<?php

namespace SummerCraft\Service\Csv;

use RuntimeException;
use SummerCraft\Service\Csv\Dto\CsvHeader;
use SummerCraft\Service\Csv\Dto\CsvReaderParameter;
use SummerCraft\Service\Csv\Dto\CsvRow;
use Throwable;

class CsvReader
{
    public function __construct(
        private string $fileName,
        private bool $withHeaders = true,
    ) {
    }

    public function foreach(callable $callable): void
    {
        $inputHandle = fopen($this->fileName, 'r');
        if ($inputHandle === false) {
            throw new RuntimeException("Can not open file {$this->fileName}");
        }
        $size = filesize($this->fileName);
        $size = $size !== 0 ? $size : 1;
        $csvHeader = null;
        $parameter = null;
        $i = 0;
        $percentage = 0.0;
        while (($lineData = fgetcsv($inputHandle, null, ',', '"', '\\')) !== false) {
            $i++;
            $percentage = ftell($inputHandle) * 100 / $size;
            if ($csvHeader === null) {
                if ($this->withHeaders) {
                    $csvHeader = new CsvHeader($lineData);
                    continue;
                } else {
                    $numericHeaders = [];
                    foreach ($lineData as $key => $line) {
                        $numericHeaders[] = (string)$key;
                    }
                    $csvHeader = new CsvHeader($numericHeaders);
                }
            }

            try {
                $csvRow = CsvRow::build($this->fileName, $csvHeader, $lineData, $i);
            } catch (Throwable $throwable) {
                throw new RuntimeException($throwable->getMessage(), $throwable->getCode(), $throwable);
            }

            if ($parameter === null) {
                $parameter = new CsvReaderParameter($csvHeader, $csvRow, $i, $percentage);
            } else {
                $parameter->csvRow = $csvRow;
                $parameter->rowNumber = $i;
                $parameter->percentage = $percentage;
            }

            $callable($parameter);
        }

        fclose($inputHandle);
    }
}
