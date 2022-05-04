<?php

namespace LesPhp\PSR4Converter\Mapper;

use LesPhp\PSR4Converter\Mapper\Result\MappedFile;

interface MapperInterface
{
    public function map(string $filePath): MappedFile;
}
