<?php

namespace LesPhp\PSR4Converter\Converter\Clean;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class RemoveNodeVisitor extends NodeVisitorAbstract
{
    private ?Node $nodeToRemove;

    private ?Node $parentToRemove;

    /**
     * @param string[] $subNodeNames
     */
    public function __construct(
        private \Closure $shouldRemoveCallback,
        private bool $deleteEmptyParent,
        private array $subNodeNames
    ) {
    }

    public function beforeTraverse(array $nodes)
    {
        $this->parentToRemove = null;
        $this->nodeToRemove = null;

        return null;
    }

    public function enterNode(Node $node)
    {
        if (($this->shouldRemoveCallback)($node)) {
            $this->nodeToRemove = $node;

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node === $this->nodeToRemove) {
            $parentToRemove = $node->getAttribute('parent', null);

            if (
                $parentToRemove !== null
                && $this->deleteEmptyParent
                && count($this->subNodeNames) > 0
                && count(
                    array_filter(
                        $this->subNodeNames,
                        fn($subNodeName) => is_countable($parentToRemove->{$subNodeName}) && count(
                                $parentToRemove->{$subNodeName}
                            ) > 1
                    )
                ) == 0
            ) {
                $this->parentToRemove = $parentToRemove;
            }

            return NodeTraverser::REMOVE_NODE;
        }

        if ($node === $this->parentToRemove) {
            return NodeTraverser::REMOVE_NODE;
        }

        return null;
    }
}