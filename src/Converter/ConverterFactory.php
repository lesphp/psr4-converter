<?php

namespace LesPhp\PSR4Converter\Converter;

use LesPhp\PSR4Converter\KeywordManager;
use PhpParser\Parser;

class ConverterFactory implements ConverterFactoryInterface
{
    public function createConverter(Parser $parser, string $destinationPath): ConverterInterface
    {
        return new Converter(new KeywordManager(), $parser, $destinationPath);
    }
}
