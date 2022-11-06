<?php

namespace LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\Naming\CustomNameContext;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Node\Name;

class ReplaceNewNameVisitor extends AbstractReplaceNameVisitor
{
    /**
     * @var array<int, array<string, string>>
     */
    private array $convertedNamesMap;

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

        $this->convertedNamesMap = $this->mappedResult->mergeConvertedNamesMap($this->additionalMappedResults, $this->nameHelper);
    }

    protected function replaceName(string $name, int $type): string
    {
        if (
            $this->nameHelper->isReservedKeyword($name)
            || $this->nameHelper->isBuiltInTypeHint($name)
            || !$this->isFullyQualifiedName($name)
        ) {
            return $name;
        }

        // Remove starting prefix \
        $oldName = substr($name, 1);
        $oldNameNode = new Name\FullyQualified($oldName);

        $newName = array_search(
            $this->nameHelper->lookupNameByType($oldName, $type),
            $this->convertedNamesMap[$type]
        );

        return $newName !== false ? '\\' . $newName : $oldNameNode->toCodeString();
    }
}
