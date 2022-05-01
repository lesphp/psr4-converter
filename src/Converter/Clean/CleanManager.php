<?php

namespace LesPhp\PSR4Converter\Converter\Clean;

use LesPhp\PSR4Converter\KeywordManager;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

class CleanManager
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function createAliases(array $nodes, KeywordManager $keywordHelper): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new CreateImportsVisitor($keywordHelper));

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function removeUnusedImports(array $nodes): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new RemoveUnusedImportsVisitor());

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function removeNode(
        array $nodes,
        Node $nodeToRemove,
        bool $deleteEmptyParent,
        array $subNodeNames = []
    ): array {
        return $this->remove(
            $nodes,
            fn (Node $searchNode) => $searchNode === $nodeToRemove,
            $deleteEmptyParent,
            $subNodeNames
        );
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function remove(array $nodes, \Closure $callback, bool $deleteEmptyParent, array $subNodeNames = []): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor(new RemoveNodeVisitor($callback, $deleteEmptyParent, $subNodeNames));

        return $traverser->traverse($nodes);
    }
}
