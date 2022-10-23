<?php

namespace LesPhp\PSR4Converter\Converter;

use PhpParser\Parser;

class ConverterFactory implements ConverterFactoryInterface
{
    public function createConverter(Parser $parser, string $destinationPath, bool $ignoreVendorNamespacePath): ConverterInterface
    {
        return new Converter($parser, $destinationPath, $ignoreVendorNamespacePath);
    }
}
