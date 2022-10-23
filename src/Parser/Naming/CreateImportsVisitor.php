<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

use LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor\CreateImportsVisitor as DocCreateImportsVisitor;
use LesPhp\PSR4Converter\Parser\Node\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;

class CreateImportsVisitor extends AbstractNodeVisitor
{
    public function enter(Node $node)
    {
        $docNode = $this->parseDocFromNode($node);

        if ($docNode !== null) {
            $traversedPhpDoc = $this->traversePhpDoc(
                $docNode,
                new DocCreateImportsVisitor($this->nameHelper, $this->currentNameContext)
            );

            $node->setDocComment($traversedPhpDoc);
        }

        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            $this->replaceImportName($node);

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof FullyQualified) {
            $node->name = $this->replaceNameWithAlias(Node\Stmt\Use_::TYPE_FUNCTION, $node->name);

            return $node;
        } elseif ($node instanceof Node\Expr\ConstFetch && $node->name instanceof FullyQualified) {
            if (
                $this->nameHelper->isSpecialConstants($node->name->toString())
                && !$this->currentNameContext->aliasExists($node->name->toString(), Node\Stmt\Use_::TYPE_CONSTANT)
            ) {
                $this->currentNameContext->addReference($node->name->toString(), $node->name, Node\Stmt\Use_::TYPE_CONSTANT);

                $node->name = new Node\Name($node->name->toString());

                return $node;
            }

            $node->name = $this->replaceNameWithAlias(Node\Stmt\Use_::TYPE_CONSTANT, $node->name);

            return $node;
        } elseif ($node instanceof FullyQualified) {
            // Same namespace
            if (
                $node->slice(0, -1)?->toLowerString() === $this->currentNameContext->getNamespace()?->toLowerString()
                && !$this->currentNameContext->aliasExists($node->getLast(), Node\Stmt\Use_::TYPE_NORMAL)
                && !$this->currentNameContext->definitionExists($node->getLast(), Node\Stmt\Use_::TYPE_NORMAL)
            ) {
                $this->currentNameContext->addReference($node->getLast(), $node, Node\Stmt\Use_::TYPE_NORMAL);

                return new Node\Name($node->getLast());
            }

            return $this->replaceNameWithAlias(Node\Stmt\Use_::TYPE_NORMAL, $node);
        }

        return null;
    }

    private function replaceNameWithAlias(int $type, FullyQualified $node): Node\Name
    {
        $alias = $this->currentNameContext->getAliasForName($node, $type);

        if ($alias === null) {
            $alias = $this->currentNameContext->generateAliasForName($node, $type);

            $this->currentNameContext->addAlias($node, $alias, $type);
        }

        return new Node\Name($alias);
    }

    private function replaceImportName(Node\Stmt\GroupUse|Node\Stmt\Use_ $use): void
    {
        foreach ($use->uses as $useUse) {
            $type = $useUse->type | $use->type;
            $oldAlias = $useUse->alias?->toString() ?? $useUse->name->getLast();
            $name = $use instanceof Node\Stmt\GroupUse ? Node\Name::concat($use->prefix, $useUse->name) : $useUse->name;

            if ($this->currentNameContext->definitionExists($oldAlias, $type)) {
                $newAlias = $this->currentNameContext->generateAliasForName(new FullyQualified($name), $type);

                $useUse->alias = new Node\Identifier($newAlias);

                $this->currentNameContext->updateAlias($oldAlias, $type, $newAlias, $name);
            }
        }
    }
}
