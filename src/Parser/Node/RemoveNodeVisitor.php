<?php

namespace LesPhp\PSR4Converter\Parser\Node;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class RemoveNodeVisitor extends NodeVisitorAbstract
{
    /**
     * @var Node[]
     */
    private array $nodesToRemove = [];

    /**
     * @var Node[]
     */
    private array $parentToRemove = [];

    /**
     * @param string[] $subNodeNames
     */
    public function __construct(
        private readonly \Closure $shouldRemoveCallback,
        private readonly \Closure $shouldRemoveParentCallback
    )
    {
    }

    public function beforeTraverse(array $nodes)
    {
        $this->parentToRemove = [];
        $this->nodesToRemove = [];
    }

    public function enterNode(Node $node)
    {
        if (($this->shouldRemoveCallback)($node, $node->getAttribute('parent'))) {
            $this->nodesToRemove[] = $node;

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if (in_array($node, $this->nodesToRemove)) {
            $parentToRemove = $node->getAttribute('parent');

            if (
                $parentToRemove !== null
                && $this->shouldRemoveParentCallback !== null
                && ($this->shouldRemoveParentCallback)($parentToRemove)
            ) {
                $this->parentToRemove[] = $parentToRemove;
            }

            return NodeTraverser::REMOVE_NODE;
        }

        if (in_array($node, $this->parentToRemove)) {
            return NodeTraverser::REMOVE_NODE;
        }

        return null;
    }
}
