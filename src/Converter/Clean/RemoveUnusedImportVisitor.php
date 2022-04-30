<?php

namespace LesPhp\PSR4Converter\Converter\Clean;

use LesPhp\PSR4Converter\Converter\Naming\NameManager;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class RemoveUnusedImportVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, array<string, Node\Name>>
     */
    private array $currentAliases;

    public function beforeTraverse(array $nodes)
    {
        $nodeFinder = new NodeFinder();

        if ($nodeFinder->findFirstInstanceOf($nodes, Node\Stmt\Namespace_::class) === null) {
            $this->searchCurrentAliases($nodes);
        }

        return null;
    }

    /**
     * @param Node[] $nodes
     */
    private function searchCurrentAliases(array $nodes): void
    {
        $nameManager = new NameManager();

        $this->currentAliases = $nameManager->findCurrentAliases($nodes, false);
    }

    public function enterNode(Node $node)
    {
        if (
            $node instanceof Node\Stmt\Use_
            || $node instanceof Node\Stmt\GroupUse
        ) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->searchCurrentAliases([$node]);
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if (
            $node instanceof Node\Stmt\Use_
            || $node instanceof Node\Stmt\GroupUse
        ) {
            foreach ($node->uses as $useUse) {
                $type = $node->type !== Node\Stmt\Use_::TYPE_UNKNOWN ? $node->type : $useUse->type;

                if (!isset($this->currentAliases[$type][(string)$useUse->getAlias()])) {
                    array_splice($node->uses, array_search($useUse, $node->uses), 1);
                }
            }

            if (count($node->uses) > 0) {
                return $node;
            } else {
                return NodeTraverser::REMOVE_NODE;
            }
        }

        return null;
    }
}