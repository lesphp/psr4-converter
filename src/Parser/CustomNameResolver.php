<?php

namespace LesPhp\PSR4Converter\Parser;

use PhpParser\ErrorHandler;
use PhpParser\NodeVisitor\NameResolver as PhpParserNameResolver;

class CustomNameResolver extends PhpParserNameResolver
{
    public function __construct(array $options = [])
    {
        $errorHandler = new ErrorHandler\Throwing();

        parent::__construct($errorHandler, $options);

        $this->nameContext = new CustomNameContext($errorHandler);
    }

    public function getNameContext(): CustomNameContext
    {
        return $this->nameContext;
    }
}
