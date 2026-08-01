<?php

namespace SummerCraft\Service\Tests\Unit\Csv\Dto;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SummerCraft\Service\Csv\Dto\CsvHeader;
use SummerCraft\Service\Csv\Dto\CsvRow;

class CsvRowTest extends TestCase
{
    public function testBuildMapsLineDataByHeader(): void
    {
        $header = new CsvHeader(['id', 'name']);

        $row = CsvRow::build('test.csv', $header, ['1', 'Alice'], 2);

        self::assertSame(['id' => '1', 'name' => 'Alice'], $row->fields);
    }

    public function testBuildThrowsOnMissingColumn(): void
    {
        $header = new CsvHeader(['id', 'name']);

        $this->expectException(RuntimeException::class);
        CsvRow::build('test.csv', $header, ['1'], 2);
    }

    /**
     * build() used to print_r() the header and row
     * data straight to output before throwing — dumping raw file contents into
     * whatever output buffer happened to be live (e.g. an HTTP response body).
     */
    public function testBuildDoesNotPrintAnythingWhenRejectingAMalformedRow(): void
    {
        $header = new CsvHeader(['id', 'name']);

        ob_start();
        try {
            CsvRow::build('test.csv', $header, ['1'], 2);
        } catch (RuntimeException $exception) {
            // expected — assert on the output buffer below regardless
        }
        $output = ob_get_clean();

        self::assertSame('', $output);
    }
}
