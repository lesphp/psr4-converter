<?php

namespace LesPhp\PSR4Converter\Renamer;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;

interface RenamerInterface
{
    public function rename(MappedResult $mappedResult, string $destinationDir): void;
}