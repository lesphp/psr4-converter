<?php

namespace LesPhp\PSR4Converter\Converter\Doc\Visitor;

use LesPhp\PSR4Converter\Converter\Doc\AnnotationTagNode;
use LesPhp\PSR4Converter\Parser\CustomNameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Symplify\Astral\PhpDocParser\PhpDocNodeVisitor\AbstractPhpDocNodeVisitor;

class FindNamesInUseVisitor extends AbstractPhpDocNodeVisitor
{
    /**
     * @var array<int, array<string, Name>>
     */
    private array $currentAliases;

    public function __construct(
        protected readonly CustomNameContext $nameContext
    ) {
        $this->currentAliases = [
            Use_::TYPE_NORMAL => [],
            Use_::TYPE_FUNCTION => [],
            Use_::TYPE_CONSTANT => [],
        ];
    }

    public function enterNode(Node $node) : ?Node
    {
        if ($node instanceof PhpDocTagNode && AnnotationTagNode::isApplicableFor($node, $this->nameContext)) {
            $annotation = new AnnotationTagNode($node->name, $node->value);

            $this->currentAliases[Use_::TYPE_NORMAL][$annotation->getAlias()] = $this->nameContext->getResolvedClassName(
                new Name($annotation->getAlias())
            );

            return new AnnotationTagNode($node->name, $node->value);
        } elseif ($node instanceof IdentifierTypeNode && !$this->isFullyQualifiedName($node->name)) {
            $name = new Name($node->name);

            $this->currentAliases[Use_::TYPE_NORMAL][$name->getFirst()] = $this->nameContext->getResolvedClassName($name);
        } elseif ($node instanceof ConstFetchNode) {
            $className = $node->className;
            $name = $node->name;

            // Class constant
            if (!empty($className)) {
                if (!$this->isFullyQualifiedName($className)) {
                    $name = new Name($className);

                    $this->currentAliases[Use_::TYPE_NORMAL][$name->getFirst()] = $this->nameContext->getResolvedClassName($name);
                }
            } elseif (!$this->isFullyQualifiedName($name)) {
                $name = new Name($name);

                if ($name->isQualified()) {
                    $this->currentAliases[Use_::TYPE_NORMAL][$name->getFirst()] = $this->nameContext->getResolvedClassName($name->slice(0, 1));
                } elseif ($name->isUnqualified()) {
                    $this->currentAliases[Use_::TYPE_CONSTANT][$name->getFirst()] = $this->nameContext->getResolvedName($name, Use_::TYPE_CONSTANT) ?? $name;
                }
            }
        } elseif ($node instanceof GenericTagValueNode) {
            if (!$this->isFullyQualifiedName($node->value)) {
                $name = new Name($node->value);

                if ($this->nameContext->aliasExists($name->getFirst(), Use_::TYPE_NORMAL)) {
                    $this->currentAliases[Use_::TYPE_NORMAL][$name->getFirst()] = $this->nameContext->getResolvedClassName($name);
                }
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

    protected function isFullyQualifiedName(string $name): bool
    {
        return str_starts_with($name, '\\');
    }
}
