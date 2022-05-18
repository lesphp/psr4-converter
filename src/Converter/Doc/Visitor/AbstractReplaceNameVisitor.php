<?php

namespace LesPhp\PSR4Converter\Converter\Doc\Visitor;

use LesPhp\PSR4Converter\Converter\Doc\AnnotationTagNode;
use LesPhp\PSR4Converter\Parser\CustomNameContext;
use PhpParser\Node\Stmt\Use_;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Symplify\Astral\PhpDocParser\PhpDocNodeVisitor\AbstractPhpDocNodeVisitor;

abstract class AbstractReplaceNameVisitor extends AbstractPhpDocNodeVisitor
{
    public function __construct(
        protected readonly CustomNameContext $nameContext
    ) {
    }

    public function enterNode(Node $node) : ?Node
    {
        if ($node instanceof PhpDocTagNode && AnnotationTagNode::isApplicableFor($node, $this->nameContext)) {
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
        } elseif ($node instanceof GenericTagValueNode) {
            // Maybe a class name, e.g. @uses
            return new GenericTagValueNode($this->replaceName($node->value, Use_::TYPE_NORMAL));
        }

        return null;
    }

    abstract protected function replaceName(string $name, int $type): string;

    protected function isFullyQualifiedName(string $name): bool
    {
        return str_starts_with($name, '\\');
    }
}
