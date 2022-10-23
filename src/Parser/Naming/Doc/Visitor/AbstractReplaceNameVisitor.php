<?php

namespace LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor;

use LesPhp\PSR4Converter\Parser\Naming\CustomNameContext;
use LesPhp\PSR4Converter\Parser\Naming\Doc\AnnotationTagNode;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Node\Stmt\Use_;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Symplify\Astral\PhpDocParser\PhpDocNodeVisitor\AbstractPhpDocNodeVisitor;

abstract class AbstractReplaceNameVisitor extends AbstractPhpDocNodeVisitor
{
    public function __construct(
        protected readonly NameHelper $nameHelper,
        protected readonly CustomNameContext $currentNameContext
    )
    {
    }

    public function enterNode(Node $node) : int|Node|null
    {
        if (
            $node instanceof PhpDocTagNode
            && AnnotationTagNode::isApplicableFor($node, $this->currentNameContext)
        ) {
            return new AnnotationTagNode($node->name, $node->value);
        }

        if ($node instanceof IdentifierTypeNode) {
            return new IdentifierTypeNode($this->replaceName($node->name, Use_::TYPE_NORMAL));
        } elseif ($node instanceof ConstFetchNode) {
            $className = $node->className;
            $name = $node->name;

            // Class constant
            if (!empty($className)) {
                $className = $this->replaceName($className, Use_::TYPE_NORMAL);
            } else {
                $name = $this->replaceName($name, Use_::TYPE_CONSTANT);
            }

            return new ConstFetchNode($className, $name);
        }

        return null;
    }

    protected function isFullyQualifiedName(string $name): bool
    {
        return str_starts_with($name, '\\');
    }

    abstract protected function replaceName(string $name, int $type): string;
}
