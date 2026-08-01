<?php

namespace SummerCraft\Service\Tests\Unit\Csv;

use PHPUnit\Framework\TestCase;
use SummerCraft\Service\Csv\CsvReader;
use SummerCraft\Service\Csv\CsvWriter;
use SummerCraft\Service\Csv\Dto\CsvReaderParameter;
use SummerCraft\Service\Csv\Dto\CsvRow;

class CsvReaderWriterTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'csv-roundtrip-test');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $writer = new CsvWriter($this->file);
        $writer->write(new CsvRow(['id' => '1', 'name' => 'Alice']));
        $writer->write(new CsvRow(['id' => '2', 'name' => 'Bob']));
        $writer->complete();

        $rows = [];
        (new CsvReader($this->file))->foreach(function (CsvReaderParameter $parameter) use (&$rows): void {
            $rows[] = $parameter->csvRow->fields;
        });

        self::assertSame(
            [
                ['id' => '1', 'name' => 'Alice'],
                ['id' => '2', 'name' => 'Bob'],
            ],
            $rows
        );
    }

    public function testReaderReportsRowNumberAndPercentage(): void
    {
        $writer = new CsvWriter($this->file);
        $writer->write(new CsvRow(['id' => '1']));
        $writer->write(new CsvRow(['id' => '2']));
        $writer->complete();

        $rowNumbers = [];
        (new CsvReader($this->file))->foreach(function (CsvReaderParameter $parameter) use (&$rowNumbers): void {
            $rowNumbers[] = $parameter->rowNumber;
            self::assertGreaterThan(0, $parameter->percentage);
        });

        self::assertSame([2, 3], $rowNumbers);
    }
}
