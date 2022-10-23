<?php

namespace LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\Naming\CustomNameContext;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Node\Name;

class ReplaceFullyQualifiedNameVisitor extends AbstractReplaceNameVisitor
{
    /**
     * @param MappedResult[] $additionalMappedResults
     */
    public function __construct(
        NameHelper $nameHelper,
        CustomNameContext $currentNameContext,
        private readonly MappedResult $mappedResult,
        private readonly array $additionalMappedResults = []
    ) {
        parent::__construct($nameHelper, $currentNameContext);
    }

    protected function replaceName(string $name, int $type): string
    {
        if ($this->nameHelper->isReservedKeyword($name) || $this->nameHelper->isBuiltInTypeHint($name)) {
            return $name;
        }

        $nameNode = $this->isFullyQualifiedName($name) ? new Name\FullyQualified(substr($name, strlen('\\'))) : new Name($name);

        $convertedNamesMap = $this->mappedResult->mergeConvertedNamesMap($this->additionalMappedResults);
        $resolvedName = $this->currentNameContext->getResolvedName($nameNode, $type);

        if ($resolvedName === null) {
            return $name;
        }

        $newName = array_search(
            (string)$resolvedName,
            $convertedNamesMap[$type]
        );

        return $newName !== false ? '\\' . $newName : $resolvedName->toCodeString();
    }
}
