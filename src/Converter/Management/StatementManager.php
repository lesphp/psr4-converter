<?php

namespace LesPhp\PSR4Converter\Converter\Management;

use LesPhp\PSR4Converter\Exception\IncompatibleMergeFilesException;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class StatementManager extends NodeVisitorAbstract
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function extract(MappedUnit $mappedUnit, array $nodes): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new UnitExtractorVisitor($mappedUnit));

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $currentNodes
     * @return Node[]
     * @throws IncompatibleMergeFilesException
     */
    public function append(array $currentNodes, array $appendNodes): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new StmtsAppendVisitor($appendNodes));

        return $traverser->traverse($currentNodes);
    }
}