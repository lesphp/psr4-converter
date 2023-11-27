<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

use PhpParser\ErrorHandler;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver as PhpParserNameResolver;

class CustomNameResolver extends PhpParserNameResolver
{
    private NamespacedNameContext $namespacedNameContext;

    private ErrorHandler $errorHandler;

    private NameHelper $nameHelper;

    public function __construct(ErrorHandler $errorHandler = null, array $options = [])
    {
        $errorHandler = $errorHandler ?? new ErrorHandler\Throwing();

        parent::__construct($errorHandler, $options);

        $this->errorHandler = $errorHandler;
        $this->nameHelper = new NameHelper();

        $this->nameContext = new CustomNameContext($errorHandler, $this->nameHelper);
        $this->namespacedNameContext = new NamespacedNameContext();
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->nameContext = new CustomNameContext($this->errorHandler, $this->nameHelper);

            $this->namespacedNameContext->addNameContext($this->nameContext, $node);
        }

        parent::enterNode($node);

        if ($node instanceof Node\Stmt\GroupUse || $node instanceof Node\Stmt\Use_) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        } elseif ($node instanceof Node\Stmt\Class_ && !$node->isAnonymous()) {
            $this->getNameContext()->addDefinition($node->name->toString(), Node\Stmt\Use_::TYPE_NORMAL);
        } elseif ($node instanceof Node\Stmt\ClassLike && !$node instanceof Node\Stmt\Class_) {
            $this->getNameContext()->addDefinition($node->name->toString(), Node\Stmt\Use_::TYPE_NORMAL);
        } elseif ($node instanceof Node\Stmt\Function_) {
            $this->getNameContext()->addDefinition($node->name->toString(), Node\Stmt\Use_::TYPE_FUNCTION);
        } elseif ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                $this->getNameContext()->addDefinition($const->name->toString(), Node\Stmt\Use_::TYPE_CONSTANT);
            }
        } elseif (
            $node instanceof Node\Expr\FuncCall
            && $node->name instanceof Name
            && $node->name->isUnqualified()
        ) {
            $resolvedName = $node->name->getAttribute('resolvedName');

            if ($resolvedName !== null) {
                $this->getNameContext()->addReference(
                    $node->name->getFirst(),
                    $resolvedName,
                    Node\Stmt\Use_::TYPE_FUNCTION
                );
            }

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        } elseif (
            $node instanceof Node\Expr\ConstFetch
            && $node->name instanceof Name
            && $node->name->isUnqualified()
        ) {
            $resolvedName = $node->name->getAttribute('resolvedName');

            if ($resolvedName !== null) {
                $this->getNameContext()->addReference(
                    $node->name->getFirst(),
                    $resolvedName,
                    Node\Stmt\Use_::TYPE_CONSTANT
                );
            }

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        } elseif (
            $node instanceof Node\Name
            && $node->isQualified()
            && $node->getAttribute('resolvedName') !== null
        ) {
            $this->getNameContext()->addReference(
                $node->getFirst(),
                $node->getAttribute('resolvedName'),
                Node\Stmt\Use_::TYPE_NORMAL
            );
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        parent::leaveNode($node);

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->nameContext->enableChangeMonitor();
        }

        return null;
    }

    public function getNameContext(): CustomNameContext
    {
        return $this->nameContext;
    }

    public function getNamespacedNameContext(): NamespacedNameContext
    {
        return $this->namespacedNameContext;
    }
}
