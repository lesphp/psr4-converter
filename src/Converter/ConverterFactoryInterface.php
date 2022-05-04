<?php

namespace LesPhp\PSR4Converter\Converter;

use PhpParser\Parser;

interface ConverterFactoryInterface
{
    public function createConverter(Parser $parser, string $destinationPath): ConverterInterface;
}
