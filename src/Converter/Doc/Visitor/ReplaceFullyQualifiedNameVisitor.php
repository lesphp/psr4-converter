<?php

namespace LesPhp\PSR4Converter\Converter\Doc\Visitor;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\CustomNameContext;
use PhpParser\Node\Name;

class ReplaceFullyQualifiedNameVisitor extends AbstractReplaceNameVisitor
{
    public function __construct(
        CustomNameContext $nameContext,
        private readonly MappedResult $mappedResult
    ) {
        parent::__construct($nameContext);
    }

    protected function replaceName(string $name, int $type): string
    {
        $nameNode = $this->isFullyQualifiedName($name) ? new Name\FullyQualified(substr($name, strlen('\\'))) : new Name($name);

        $convertedNamesMap = $this->mappedResult->getConvertedNamesMap();
        $resolvedName = $this->nameContext->getResolvedName($nameNode, $type);

        if ($resolvedName === null) {
            return $name;
        }

        $newName = array_search(
            (string)$resolvedName,
            $convertedNamesMap[$type]
        );

        return $newName !== false ? '\\' . $newName : $name;
    }
}
