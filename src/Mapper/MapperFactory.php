<?php

namespace LesPhp\PSR4Converter\Mapper;

use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Lexer;
use PhpParser\Parser;

class MapperFactory implements MapperFactoryInterface
{
    public function createMapper(
        Parser $parser,
        Lexer $lexer,
        string $srcPath,
        string $includesDirPath,
        ?string $prefixNamespace,
        bool $appendNamespace,
        bool $underscoreConversion,
        bool $ignoreNamespacedUnderscoreConversion,
        array $ignoreNamespaces
    ): MapperInterface {
        return new Mapper(
            new NameHelper(),
            $parser,
            $lexer,
            $srcPath,
            $includesDirPath,
            $prefixNamespace,
            $appendNamespace,
            $underscoreConversion,
            $ignoreNamespacedUnderscoreConversion,
            $ignoreNamespaces
        );
    }
}
