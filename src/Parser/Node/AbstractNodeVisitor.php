<?php

namespace LesPhp\PSR4Converter\Parser\Node;

use LesPhp\PSR4Converter\Parser\Naming\CustomNameContext;
use LesPhp\PSR4Converter\Parser\Naming\CustomNameResolver;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use LesPhp\PSR4Converter\Parser\Naming\NameManager;
use LesPhp\PSR4Converter\Parser\Naming\NamespacedNameContext;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract as PhpParserNodeVisitor;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use Symplify\Astral\PhpDocParser\Contract\PhpDocNodeVisitorInterface;
use Symplify\Astral\PhpDocParser\PhpDocNodeTraverser;
use Symplify\Astral\PhpDocParser\SimplePhpDocParser;
use Symplify\Astral\PhpDocParser\ValueObject\Ast\PhpDoc\SimplePhpDocNode;

abstract class AbstractNodeVisitor extends PhpParserNodeVisitor
{
    protected readonly NodeFinder $nodeFinder;

    protected readonly NameHelper $nameHelper;

    protected readonly NameManager $nameManager;

    protected readonly NamespacedNameContext $namespacedNameContext;

    protected readonly SimplePhpDocParser $simplePhpDocParser;

    protected CustomNameContext $currentNameContext;

    protected readonly NodeHelper $nodeHelper;

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
        $this->nameHelper = new NameHelper();
        $this->nameManager = new NameManager();
        $this->nodeHelper = new NodeHelper();

        $this->initSimplePhpDocParser();
    }

    public function beforeTraverse(array $nodes)
    {
        if ($this->nodeFinder->findFirstInstanceOf($nodes, Node\Stmt\Namespace_::class) === null) {
            $nodes = $this->injectGlobalNamespace($nodes);
        }

        $this->namespacedNameContext = $this->generateNamespacedNameContext($nodes);

        return $this->before($nodes) ?? $nodes;
    }

    public function before(array $nodes)
    {
        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNameContext = $this->namespacedNameContext->getNameContextForNamespace($node);
        }

        return $this->enter($node);
    }

    public function enter(Node $node)
    {
        return null;
    }

    public function leaveNode(Node $node)
    {
        $newNode = $this->leave($node) ?? $node;

        if ($node instanceof Node\Stmt\Namespace_) {
            $this->processImportChanges($node);
        }

        return $newNode;
    }

    public function leave(Node $node)
    {
        return null;
    }

    public function afterTraverse(array $nodes)
    {
        return $this->after($nodes);
    }

    public function after(array $nodes)
    {
        return null;
    }

    protected function traversePhpDoc(SimplePhpDocNode $phpDocNode, PhpDocNodeVisitorInterface $docNodeVisitor): Doc
    {
        $phpDocNodeTraverser = new PhpDocNodeTraverser();
        $phpDocNodeTraverser->addPhpDocNodeVisitor($docNodeVisitor);

        $phpDocNodeTraverser->traverse($phpDocNode);

        return new Doc((string)$phpDocNode);
    }

    protected function parseDocFromNode(Node $node): ?SimplePhpDocNode
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

    private function generateNamespacedNameContext(array $nodes): NamespacedNameContext
    {
        $traverser = new NodeTraverser();
        $nameResolver = new CustomNameResolver(null, [
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);

        $traverser->addVisitor($nameResolver);

        $traverser->traverse($nodes);

        return $nameResolver->getNamespacedNameContext();
    }

    private function cleanRemovedUses(Node\Stmt\Namespace_ $namespace): void
    {
        $namespace->stmts = $this->nodeHelper->removeNodesWithCallback(
            $namespace->stmts,
            function (Node $node, ?Node $parentNode) {
                if (!$node instanceof Node\Stmt\UseUse) {
                    return false;
                }

                if (!$parentNode instanceof Node\Stmt\GroupUse && !$parentNode instanceof Node\Stmt\Use_) {
                    return false;
                }

                $type = $node->type | $parentNode->type;
                $alias = $node->getAlias() === null ? $node->getAlias()->toString() : $node->name->getLast();

                return $this->currentNameContext->aliasIsRemoved($alias, $type);
            },
            function (Node\Stmt\GroupUse|Node\Stmt\Use_ $parentNode) {
                return count($parentNode->uses) == 0;
            }
        );
    }

    private function injectNewUses(Node\Stmt\Namespace_ $namespace): void
    {
        $newUses = [];

        foreach ($this->currentNameContext->getAddedAlias() as $type => $newAliases) {
            $newUseStmts = array_map(
                function (string $newAlias) use ($type) {
                    $name = $this->currentNameContext->getNameForAlias($newAlias, $type);

                    $newUseUse = new Node\Stmt\UseUse(
                        new Node\Name($name->toString()),
                        $newAlias === $name->getLast() ? null : $newAlias
                    );

                    return new Node\Stmt\Use_([$newUseUse], $type);
                },
                $newAliases
            );

            $newUses = array_merge($newUses, $newUseStmts);
        }

        $namespace->stmts = array_merge($newUses, $namespace->stmts);
    }

    private function processImportChanges(Node\Stmt\Namespace_ $namespace): void
    {

        $this->cleanRemovedUses($namespace);

        $this->injectNewUses($namespace);
    }

    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    private function injectGlobalNamespace(array $nodes): array
    {
        $newNodes = [];
        $namespaceInjected = false;

        $namespace = new Node\Stmt\Namespace_(null);

        foreach ($nodes as $node) {
            /** @see https://www.php.net/manual/en/control-structures.declare.php */
            if ($node instanceof Node\Stmt\Declare_ && empty($node->stmts) && !$namespaceInjected) {
                $newNodes[] = $node;

                continue;
            }

            if (!$namespaceInjected) {
                $namespaceInjected = true;

                $newNodes[] = $namespace;
            }

            $namespace->stmts[] = $node;
        }

        return $newNodes;
    }
}