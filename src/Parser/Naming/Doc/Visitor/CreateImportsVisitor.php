<?php

namespace LesPhp\PSR4Converter\Parser\Naming\Doc\Visitor;

use LesPhp\PSR4Converter\Parser\Naming\CustomNameContext;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Use_;

class CreateImportsVisitor extends AbstractReplaceNameVisitor
{
    public function __construct(
        NameHelper $nameHelper,
        CustomNameContext $currentNameContext
    ) {
        parent::__construct($nameHelper, $currentNameContext);
    }

    protected function replaceName(string $name, int $type): string
    {
        if ($this->nameHelper->isReservedKeyword($name) || $this->nameHelper->isBuiltInTypeHint($name)) {
            return $name;
        }

        $isFullyQualifiedName = $this->isFullyQualifiedName($name);
        $nameNode = $isFullyQualifiedName
            ? new Name\FullyQualified(substr($name, strlen('\\')))
            : new Name($name);

        if (
            $type === Use_::TYPE_CONSTANT
            && $isFullyQualifiedName
            && $this->nameHelper->isSpecialConstants($nameNode->toString())
            && !$this->currentNameContext->aliasExists($nameNode->toString(), $type)
        ) {
            $this->currentNameContext->addReference($nameNode->toString(), $nameNode, $type);

            return $nameNode->toString();
        }

        // Same namespace
        if (
            $type === Use_::TYPE_NORMAL
            && $isFullyQualifiedName
            && $nameNode->slice(0, -1)?->toLowerString() === $this->currentNameContext->getNamespace()?->toLowerString()
            && !$this->currentNameContext->aliasExists($nameNode->getLast(), $type)
            && !$this->currentNameContext->definitionExists($nameNode->getLast(), $type)
        ) {
            $this->currentNameContext->addReference($nameNode->getLast(), $nameNode, $type);

            return $nameNode->getLast();
        }

        if ($isFullyQualifiedName) {
            $name = $this->replaceNameWithAlias($type, $nameNode);
        }

        return $name;
    }

    private function replaceNameWithAlias(int $type, FullyQualified $node): string
    {
        $alias = $this->currentNameContext->getAliasForName($node, $type);

        if ($alias === null) {
            $alias = $this->currentNameContext->generateAliasForName($node, $type);

            $this->currentNameContext->addAlias($node, $alias, $type);
        }

        return $alias;
    }
}
