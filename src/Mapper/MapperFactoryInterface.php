<?php

namespace LesPhp\PSR4Converter\Mapper;

use LesPhp\PSR4Converter\Exception\InvalidNamespaceException;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Lexer;
use PhpParser\Parser;

interface MapperFactoryInterface
{
    /**
     * @throws InvalidNamespaceException
     */
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
    ): MapperInterface;
}
