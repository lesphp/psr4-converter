<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

class FindNamesInUseVisitor extends NodeVisitorAbstract
{
    /**
     * @var Name[]
     */
    private array $visitedNames;

    /**
     * @var array<int, array<string, Name>>
     */
    private array $currentAliases;

    public function __construct(private readonly bool $includeImports)
    {
    }

    public function beforeTraverse(array $nodes)
    {
        $this->currentAliases = [
            Node\Stmt\Use_::TYPE_NORMAL => [],
            Node\Stmt\Use_::TYPE_FUNCTION => [],
            Node\Stmt\Use_::TYPE_CONSTANT => [],
        ];

        $this->visitedNames = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($this->includeImports) {
            if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
                array_walk($node->uses, function (Node\Stmt\UseUse $useUse) use ($node) {
                    $namespacedName = $node instanceof Node\Stmt\GroupUse
                        ? Node\Name::concat($node->prefix, $useUse->name)
                        : $useUse->name;
                    $type = $node->type !== Node\Stmt\Use_::TYPE_UNKNOWN ? $node->type : $useUse->type;

                    $this->currentAliases[$type][(string)$useUse->getAlias()] = $namespacedName;

                    $this->visitedNames[] = $useUse->name;
                });
            }
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->visitedNames[] = $node->name;
        } elseif ($node instanceof Node\Expr\FuncCall && !$node->name->isFullyQualified()) {
            $this->currentAliases[Node\Stmt\Use_::TYPE_FUNCTION][$node->name->getFirst()] = $node->getAttribute(
                'resolvedName',
                $node->name
            );

            $this->visitedNames[] = $node->name;
        } elseif ($node instanceof Node\Expr\ConstFetch && !$node->name->isFullyQualified()) {
            $this->currentAliases[Node\Stmt\Use_::TYPE_CONSTANT][$node->name->getFirst()] = $node->getAttribute(
                'resolvedName',
                $node->name
            );

            $this->visitedNames[] = $node->name;
        } elseif ($node instanceof Node\Name && !$node->isFullyQualified() && !$node->isSpecialClassName() && !in_array(
            $node,
            $this->visitedNames,
            true
        )) {
            $this->currentAliases[Node\Stmt\Use_::TYPE_NORMAL][$node->getFirst()] = $node->getAttribute(
                'resolvedName',
                $node
            );
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        $this->visitedNames = [];

        $nameManager = new NameManager();

        foreach ($nameManager->findNewDefinitions($nodes) as $type => $newDefinitionNames) {
            foreach ($newDefinitionNames as $newDefinitionName) {
                $this->currentAliases[$type][$newDefinitionName->getLast()] = $newDefinitionName;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, Name>>
     */
    public function getCurrentAliases(): array
    {
        return $this->currentAliases;
    }
}
