<?php

namespace LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor;

use LesPhp\PSR4Converter\Parser\Naming\CustomNameContext;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Node\Name;

class FullyQualifierNameVisitor extends AbstractReplaceNameVisitor
{
    public function __construct(
        NameHelper $nameHelper,
        CustomNameContext $currentNameContext
    ) {
        parent::__construct($nameHelper, $currentNameContext);
    }

    protected function replaceName(string $name, int $type): string
    {
        if ($this->nameHelper->isReservedKeyword($name) || $this->nameHelper->isBuiltInTypeHint($name)) {
            return $name;
        }

        $nameNode = $this->isFullyQualifiedName($name)
            ? new Name\FullyQualified(substr($name, strlen('\\')))
            : new Name($name);

        return $this->currentNameContext->getResolvedName($nameNode, $type)?->toCodeString() ?? $name;
    }
}
