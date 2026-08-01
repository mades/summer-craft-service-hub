<?php

namespace SummerCraft\Service\Csv\Dto;

class CsvHeader
{
    /**
     * @param string[] $fields
     */
    public function __construct(
        public  array $fields,
    ) {
    }
}
