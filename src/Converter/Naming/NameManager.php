<?php

namespace LesPhp\PSR4Converter\Converter\Naming;

use LesPhp\PSR4Converter\Converter\Doc\PhpDocNodeTraverserVisitor;
use LesPhp\PSR4Converter\Converter\Doc\Visitor\FindNamesInUseVisitor as DocFindNamesInUseVisitor;
use LesPhp\PSR4Converter\Converter\Doc\Visitor\ReplaceFullyQualifiedNameVisitor as DocReplaceFullyQualifiedNameVisitor;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\CustomNameResolver;
use PhpParser\Node;
use PhpParser\NodeTraverser;

class NameManager
{
    /**
     * @param Node[] $nodes
     * @return Node[]
     */
    public function replaceFullyQualifiedNames(MappedResult $mappedResult, array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new CustomNameResolver([
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $replacePhpDocVisitor = new DocReplaceFullyQualifiedNameVisitor($nameResolver->getNameContext(), $mappedResult);

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor(new ReplaceFullyQualifiedNameVisitor($mappedResult));
        $traverser->addVisitor(new PhpDocNodeTraverserVisitor($replacePhpDocVisitor));

        return $traverser->traverse($nodes);
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, Node\Name>>
     */
    public function findNamesInUse(array $nodes, bool $includeImports): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new CustomNameResolver([
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $visitor = new FindNamesInUseVisitor($includeImports, $nameResolver->getNameContext());

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);

        $traverser->traverse($nodes);

        return $visitor->getCurrentAliases();
    }

    /**
     * @param Node[] $nodes
     * @return array<int, array<string, Node\Name>>
     */
    public function findDocNamesInUse(array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new CustomNameResolver([
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $findNamesInUseVisitor = new DocFindNamesInUseVisitor($nameResolver->getNameContext());
        $visitor = new PhpDocNodeTraverserVisitor($findNamesInUseVisitor);

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);

        $traverser->traverse($nodes);

        return $findNamesInUseVisitor->getCurrentAliases();
    }

    /**
     * @param Node[] $nodes
     * @return array<int, Node\Name\FullyQualified[]>
     */
    public function findNewDefinitions(array $nodes): array
    {
        $traverser = new NodeTraverser();
        $nameResolver = new CustomNameResolver([
            'preserveOriginalNames' => false,
            'replaceNodes' => false,
        ]);
        $visitor = new FindNewDefinitionsVisitor();

        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($visitor);

        $traverser->traverse($nodes);

        return $visitor->getNewDefinitions();
    }
}
