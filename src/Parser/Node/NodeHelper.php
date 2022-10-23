<?php

namespace LesPhp\PSR4Converter\Parser\Node;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;

class NodeHelper
{
    private NodeFinder $nodeFinder;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function removeNode(
        array $nodes,
        Node $nodeToRemove,
        \Closure $shouldRemoveParentCallback = null
    ): array {
        return $this->removeNodesWithCallback(
            $nodes,
            fn (Node $searchNode) => $searchNode === $nodeToRemove,
            $shouldRemoveParentCallback
        );
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function removeNodesWithCallback(array $nodes, \Closure $shouldRemoveCallback, \Closure $shouldRemoveParentCallback = null): array
    {
        $traverser = new NodeTraverser();

        $traverser->addVisitor(new ParentConnectingVisitor());
        $traverser->addVisitor(new RemoveNodeVisitor($shouldRemoveCallback, $shouldRemoveParentCallback));

        return $traverser->traverse($nodes);
    }

    public function hasNoBlockModeDeclare(array $nodes): bool
    {
        /** @var Node\Stmt\Declare_[] $declareNodes */
        $declareNodes = $this->nodeFinder->findInstanceOf($nodes, Node\Stmt\Declare_::class);

        foreach ($declareNodes as $declareNode) {
            if (empty($declareNode->stmts)) {
                return true;
            }
        }

        return false;
    }
}
