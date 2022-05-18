<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

class FindNewDefinitionsVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, Name\FullyQualified[]>
     */
    private array $newDefinitions;

    public function beforeTraverse(array $nodes)
    {
        $this->newDefinitions = [
            Node\Stmt\Use_::TYPE_NORMAL => [],
            Node\Stmt\Use_::TYPE_FUNCTION => [],
            Node\Stmt\Use_::TYPE_CONSTANT => [],
        ];

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Function_) {
            $this->newDefinitions[Node\Stmt\Use_::TYPE_FUNCTION][] = new Name\FullyQualified(
                $node->namespacedName ?? $node->name,
                $node->name->getAttributes()
            );
        } elseif ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                $this->newDefinitions[Node\Stmt\Use_::TYPE_CONSTANT][] = new Name\FullyQualified(
                    $const->namespacedName ?? $const->name,
                    $const->name->getAttributes()
                );
            }
        } elseif ($node instanceof Node\Stmt\ClassLike) {
            $this->newDefinitions[Node\Stmt\Use_::TYPE_NORMAL][] = new Name\FullyQualified(
                $node->namespacedName ?? $node->name,
                $node->name->getAttributes()
            );
        }

        return null;
    }

    /**
     * @return array<int, Name\FullyQualified[]>
     */
    public function getNewDefinitions(): array
    {
        return $this->newDefinitions;
    }
}
