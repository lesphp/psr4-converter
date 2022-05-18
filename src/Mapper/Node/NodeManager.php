<?php

namespace LesPhp\PSR4Converter\Mapper\Node;

use LesPhp\PSR4Converter\Mapper\MapperContext;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use LesPhp\PSR4Converter\Parser\CustomNameResolver;
use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Node;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeTraverser;

class NodeManager
{
    /**
     * @param Node[] $nodes
     * @return MappedUnit[]
     */
    public function mapFile(MapperContext $mapperContext, array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new CustomNameResolver([
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $mapFileVisitor = new MapFileVisitor($mapperContext, $nameResolver->getNameContext(), new KeywordManager());

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($mapFileVisitor);

        $traverser->traverse($nodes);

        return $mapFileVisitor->getMappedUnits();
    }

    /**
     * @return Node[]
     */
    public function getAllConditionalStmts(If_ $node): array
    {
        $stmts = array_merge(
            $node->stmts,
            ...array_map(fn (Node\Stmt\ElseIf_ $elseIf) => $elseIf->stmts, $node->elseifs)
        );
        $stmts = array_merge($stmts, (array)$node->else?->stmts);
        $allStmts = [];

        array_walk_recursive(
            $stmts,
            function (Node $stmt) use (&$allStmts) {
                if ($stmt instanceof If_) {
                    $allStmts = array_merge($allStmts, $this->getAllConditionalStmts($stmt));
                } else {
                    $allStmts[] = $stmt;
                }
            }
        );

        return $allStmts;
    }
}
