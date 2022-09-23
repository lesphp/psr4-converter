<?php

namespace LesPhp\PSR4Converter\Converter;

use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Parser;

class ConverterFactory implements ConverterFactoryInterface
{
    public function createConverter(Parser $parser, string $destinationPath, bool $ignoreVendorNamespacePath): ConverterInterface
    {
        return new Converter(new KeywordManager(), $parser, $destinationPath, $ignoreVendorNamespacePath);
    }
}
