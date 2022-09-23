<?php

namespace LesPhp\PSR4Converter\Autoloader;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;

interface AutoloaderInterface
{
    public function generate(MappedResult $mappedResult, string $filePath): void;
}