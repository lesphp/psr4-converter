<?php

namespace LesPhp\PSR4Converter\Converter\Doc;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Symplify\Astral\PhpDocParser\Contract\PhpDocNodeVisitorInterface;
use Symplify\Astral\PhpDocParser\PhpDocNodeTraverser;
use Symplify\Astral\PhpDocParser\SimplePhpDocParser;
use Symplify\Astral\PhpDocParser\ValueObject\Ast\PhpDoc\SimplePhpDocNode;

class PhpDocNodeTraverserVisitor extends NodeVisitorAbstract
{
    private SimplePhpDocParser $simplePhpDocParser;

    public function __construct(
        private readonly PhpDocNodeVisitorInterface $phpDocNodeVisitor
    ) {
        $this->initSimplePhpDocParser();
    }

    public function enterNode(Node $node)
    {
        $docNode = $this->parseDocFromNode($node);

        if ($docNode !== null) {
            $traversedPhpDoc = $this->traversePhpDoc($docNode);

            $node->setDocComment($traversedPhpDoc);

            return $node;
        }

        return null;
    }

    private function traversePhpDoc(SimplePhpDocNode $phpDocNode): Doc
    {
        $phpDocNodeTraverser = new PhpDocNodeTraverser();
        $phpDocNodeTraverser->addPhpDocNodeVisitor($this->phpDocNodeVisitor);

        $phpDocNodeTraverser->traverse($phpDocNode);

        return new Doc((string)$phpDocNode);
    }

    private function parseDocFromNode(Node $node): ?SimplePhpDocNode
    {
        $docComment = $node->getDocComment();

        if ($docComment === null) {
            return null;
        }

        return $this->simplePhpDocParser->parseDocBlock((string)$docComment);
    }

    private function initSimplePhpDocParser(): void
    {
        $constExprParser = new ConstExprParser();

        $this->simplePhpDocParser = new SimplePhpDocParser(
            new PhpDocParser(new TypeParser($constExprParser), $constExprParser),
            new Lexer()
        );
    }
}
