<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

use LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor\FullyQualifierNameVisitor as DocFullyQualifierNameVisitor;
use LesPhp\PSR4Converter\Parser\Node\AbstractNodeVisitor;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;

class FullyQualifierNameVisitor extends AbstractNodeVisitor
{
   public function enter(Node $node)
    {
        $docNode = $this->parseDocFromNode($node);

        if ($docNode !== null) {
            $traversedPhpDoc = $this->traversePhpDoc(
                $docNode,
                new DocFullyQualifierNameVisitor(
                    $this->nameHelper, $this->currentNameContext
                )
            );

            $node->setDocComment($traversedPhpDoc);
        }

        if ($node instanceof Node\Stmt\GroupUse || $node instanceof Node\Stmt\Use_) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Name && !$node->name->isFullyQualified()) {
            $node->name = $this->replaceName($node->name, Node\Stmt\Use_::TYPE_FUNCTION);

            if (!$node->name->isFullyQualified()) {
                $node->name->setAttribute('ignoreFullyQualify', true);
            }

            return $node;
        } elseif ($node instanceof Node\Expr\ConstFetch && !$node->name->isFullyQualified()) {
            $node->name = $this->replaceName($node->name, Node\Stmt\Use_::TYPE_CONSTANT);

            if (!$node->name->isFullyQualified()) {
                $node->name->setAttribute('ignoreFullyQualify', true);
            }

            return $node;
        } elseif (
            $node instanceof Node\Name
            && !$node->isSpecialClassName()
            && !$node->getAttribute('ignoreFullyQualify')
        ) {
            return $this->replaceName($node, Node\Stmt\Use_::TYPE_NORMAL);
        }

        return $node;
    }

    private function replaceName(Name $name, int $type): Name
    {
        return $this->currentNameContext->getResolvedName($name, $type) ?? $name;
    }
}
