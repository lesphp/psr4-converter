<?php

namespace LesPhp\PSR4Converter\Mapper;

use LesPhp\PSR4Converter\KeywordManager;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Lexer;
use PhpParser\Parser;

class MapperFactory implements MapperFactoryInterface
{
    public function createMapper(
        Parser $parser,
        Lexer $lexer,
        MappedResult $mappedResult,
        ?string $prefixNamespace,
        bool $appendNamespace,
        bool $underscoreConversion,
        bool $ignoreNamespacedUnderscoreConversion,
        array $ignoreNamespaces
    ): MapperInterface {
        return new Mapper(
            new KeywordManager(),
            $parser,
            $lexer,
            $mappedResult,
            $prefixNamespace,
            $appendNamespace,
            $underscoreConversion,
            $ignoreNamespacedUnderscoreConversion,
            $ignoreNamespaces
        );
    }
}
