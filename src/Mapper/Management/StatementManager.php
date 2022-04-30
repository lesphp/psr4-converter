<?php

namespace LesPhp\PSR4Converter\Mapper\Management;

use LesPhp\PSR4Converter\KeywordManager;
use LesPhp\PSR4Converter\Mapper\MapperContext;
use LesPhp\PSR4Converter\Mapper\Result\MappedFile;
use PhpParser\Node;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class StatementManager
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function mapFile(MappedFile $mappedFile, MapperContext $mapperContext, array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new NameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor(
            new MapperNodeVisitor(
                $mappedFile, $mapperContext, $nameResolver->getNameContext(), new KeywordManager()
            )
        );

        return $traverser->traverse($nodes);
    }

    /**
     * @return Node[]
     */
    public function getAllConditionalStmts(If_ $node): array
    {
        $stmts = array_merge(
            $node->stmts,
            ...array_map(fn(Node\Stmt\ElseIf_ $elseIf) => $elseIf->stmts, $node->elseifs)
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