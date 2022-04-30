<?php

namespace LesPhp\PSR4Converter\Converter\Management;

use LesPhp\PSR4Converter\Converter\Clean\StatementCleaner;
use LesPhp\PSR4Converter\Exception\IncompatibleMergeFilesException;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

class StmtsAppendVisitor extends NodeVisitorAbstract
{
    /**
     * @param Node[] $appendStmts
     */
    public function __construct(private array $appendStmts)
    {
    }

    public function beforeTraverse(array $nodes)
    {
        $nodeFinder = new NodeFinder();
        $statementCleaner = new StatementCleaner();

        /** @var Node\Stmt\Declare_|null $currentDeclare */
        $currentDeclare = $nodeFinder->findFirstInstanceOf($nodes, Node\Stmt\Declare_::class);

        foreach ($this->appendStmts as $appendStmt) {
            if ($appendStmt instanceof Node\Stmt\Declare_) {
                if ($currentDeclare === null) {
                    throw new IncompatibleMergeFilesException();
                }

                foreach ($appendStmt->declares as $declareToAppend) {
                    /** @var Node\Stmt\DeclareDeclare|null $matchDeclare */
                    $matchDeclare = $nodeFinder->findFirst(
                        $currentDeclare->declares,
                        fn(Node $searchNode) => $searchNode instanceof Node\Stmt\DeclareDeclare
                            && (string)$searchNode->key === (string)$declareToAppend->key
                            && $searchNode->value?->value === $declareToAppend->value?->value
                    );

                    if ($matchDeclare === null) {
                        throw new IncompatibleMergeFilesException();
                    }
                }

                $this->appendStmts = $statementCleaner->removeNode($this->appendStmts, $appendStmt, false);
            }
        }

        return $nodes;
    }

    public function enterNode(Node $node)
    {
        $nodeFinder = new NodeFinder();
        $statementCleaner = new StatementCleaner();

        if ($node instanceof Node\Stmt\Namespace_) {
            /** @var Node\Stmt\Namespace_[] $appendNamespaces */
            $appendNamespaces = $nodeFinder->find(
                $this->appendStmts,
                fn(Node $searchNode) => $searchNode instanceof Node\Stmt\Namespace_ && $searchNode->name?->toString(
                    ) === $node->name?->toString()
            );

            foreach ($appendNamespaces as $appendNamespace) {
                $appendNamespaceStmts = $statementCleaner->remove(
                    $appendNamespace->stmts,
                    function (Node $useUseSearch) use ($nodeFinder, $node) {
                        if (!$useUseSearch instanceof Node\Stmt\UseUse) {
                            return false;
                        }

                        /** @var Node\Stmt\Use_|Node\Stmt\GroupUse $useSearch */
                        $useSearch = $useUseSearch->getAttribute('parent');
                        /** @var Node\Stmt\Use_[]|Node\Stmt\GroupUse[] $currentUses */
                        $currentUses = $nodeFinder->find(
                            $node,
                            fn(Node $searchCurrentUse
                            ) => $searchCurrentUse instanceof Node\Stmt\Use_ || $searchCurrentUse instanceof Node\Stmt\GroupUse
                        );

                        foreach ($currentUses as $currentUse) {
                            $existentUseUse = $nodeFinder->findFirst(
                                $currentUse,
                                function (Node $currentUseUseSearch) use ($currentUse, $useSearch, $useUseSearch) {
                                    if (!$currentUseUseSearch instanceof Node\Stmt\UseUse) {
                                        return false;
                                    }

                                    $currentUseUseName = $currentUse instanceof Node\Stmt\GroupUse
                                        ? (string)Node\Name::concat(
                                            $currentUse->prefix,
                                            $currentUseUseSearch->name?->toString()
                                        )
                                        : $currentUseUseSearch->name?->toString();

                                    $useUseName = $useSearch instanceof Node\Stmt\GroupUse
                                        ? (string)Node\Name::concat(
                                            $useSearch->prefix,
                                            $useUseSearch->name?->toString()
                                        )
                                        : $useUseSearch->name?->toString();

                                    return $currentUse->type === $useSearch->type
                                        && (string)$currentUseUseSearch->alias === (string)$useUseSearch->alias
                                        && $currentUseUseName === $useUseName;
                                }
                            );

                            if ($existentUseUse !== null) {
                                return true;
                            }
                        }

                        return false;
                    },
                    true,
                    ['uses']
                );

                $newUsesMatch = fn(Node $searchUse
                ) => $searchUse instanceof Node\Stmt\Use_ || $searchUse instanceof Node\Stmt\GroupUse;

                $newUses = $nodeFinder->find($appendNamespaceStmts, $newUsesMatch);

                $node->stmts = array_merge($newUses, $node->stmts);

                $appendNamespaceStmts = $statementCleaner->remove($appendNamespaceStmts, $newUsesMatch, false);

                $node->stmts = array_merge($node->stmts, $appendNamespaceStmts);
                $this->appendStmts = $statementCleaner->removeNode($this->appendStmts, $appendNamespace, false);
            }

            return $node;
        }

        return null;
    }

    public function afterTraverse(array $nodes)
    {
        return array_merge($nodes, $this->appendStmts);
    }
}