<?php

namespace LesPhp\PSR4Converter\Converter\Doc;

use LesPhp\PSR4Converter\Parser\CustomNameContext;
use PhpParser\Node\Stmt\Use_;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;

class AnnotationTagNode extends PhpDocTagNode
{
    public function __toString(): string
    {
        return trim("{$this->name}{$this->value}");
    }

    public function getAlias(): string
    {
        return self::getAliasFromTag($this);
    }

    /**
     * @param Node $node
     * @param CustomNameContext $nameContext
     * @return bool
     */
    public static function isApplicableFor(PhpDocTagNode $node, CustomNameContext $nameContext): bool
    {
        return $node->value instanceof GenericTagValueNode
            && $nameContext->aliasExists(self::getAliasFromTag($node), Use_::TYPE_NORMAL);
    }

    private static function getAliasFromTag(PhpDocTagNode $node): string
    {
        return ltrim($node->name, '@');
    }
}
