<?php

namespace LesPhp\PSR4Converter\Mapper\Node;

use LesPhp\PSR4Converter\Mapper\MapperContext;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use LesPhp\PSR4Converter\Parser\CustomNameContext;
use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class MapFileVisitor extends NodeVisitorAbstract
{
    public const IGNORE_ALL_NAMESPACES = '*';

    public const IGNORE_GLOBAL_NAMESPACE = '?';

    private ?int $namespaceStartTokenPos;

    private ?int $namespaceEndTokenPos;

    /**
     * @var MappedUnit[]
     */
    private array $mappedUnits;

    /**
     * @var Node\Stmt\Declare_[]
     */
    private array $openDeclares;

    public function __construct(
        private readonly MapperContext $mapperContext,
        private readonly CustomNameContext $nameContext,
        private readonly KeywordManager $keywordHelper
    ) {
    }

    public function beforeTraverse(array $nodes)
    {
        $this->namespaceStartTokenPos = null;
        $this->namespaceEndTokenPos = null;
        $this->openDeclares = [];
        $this->mappedUnits = [];
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Declare_) {
            $this->openDeclares[] = $node;
        } elseif ($node instanceof Node\Stmt\Namespace_) {
            $this->namespaceStartTokenPos = $node->getStartTokenPos();
            $this->namespaceEndTokenPos = $node->getEndTokenPos();
        }

        if ($this->isValidRootStatement($node)) {
            $nameContext = $this->nameContext;
            $filePath = $this->mapperContext->getFilePath();
            $vendorNamespace = $this->mapperContext->getPrefixNamespace();
            $includesDirPath = $this->mapperContext->getIncludesDirPath();
            $isAppendNamespace = $this->mapperContext->isAppendNamespace();
            $ignoreNamespaces = $this->mapperContext->getIgnoreNamespaces();
            $originalNamespace = $nameContext->getNamespace() !== null ? (string)$nameContext->getNamespace() : null;
            $ignoreNamespacedUnderscoreConversion = $this->mapperContext->isIgnoreNamespacedUnderscoreConversion();
            $underscoreConversion = $this->mapperContext->isUnderscoreConversion()
                && ($originalNamespace === null || !$ignoreNamespacedUnderscoreConversion);
            $isNamespaceIgnored = in_array($originalNamespace, $ignoreNamespaces, true)
                || ($originalNamespace !== null && in_array(self::IGNORE_ALL_NAMESPACES, $ignoreNamespaces, true))
                || ($originalNamespace === null && in_array(self::IGNORE_GLOBAL_NAMESPACE, $ignoreNamespaces, true));
            $originalName = $this->getNodeName($node);
            $newNamespace = $isNamespaceIgnored ? $originalNamespace : $this->generateNewNamespace(
                $originalNamespace,
                $vendorNamespace,
                $isAppendNamespace,
                $underscoreConversion,
                $node
            );
            $newName = $isNamespaceIgnored ? $originalName : $this->generateNewName(
                $node,
                $newNamespace,
                $underscoreConversion,
                true
            );
            $targetFile = $this->generateTargetFile(
                $newNamespace,
                $newName,
                $includesDirPath,
                $filePath,
                $node
            );
            $isExclusive = $this->isExclusive($node);
            $hasRisky = $this->hasRisky($node, $originalNamespace, $originalName, $underscoreConversion);
            $statementDetails = $this->generateStatementDetails($node);
            $componentStmtClasses = $this->generateComponentStmtClasses($node);

            $mappedUnit = new MappedUnit(
                $filePath,
                $node->getStartLine(),
                $node->getStartFilePos(),
                $node->getEndLine(),
                $node->getEndFilePos(),
                $node->getStartTokenPos(),
                $node->getEndTokenPos(),
                $this->namespaceStartTokenPos,
                $this->namespaceEndTokenPos,
                $originalNamespace,
                $originalName,
                $newNamespace,
                $newName,
                $targetFile,
                $this->getStmtClass($node),
                $isExclusive,
                $hasRisky,
                $statementDetails,
                $componentStmtClasses
            );

            $this->mappedUnits[] = $mappedUnit;

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        return null;
    }

    private function isValidRootStatement(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_
            || $node instanceof Node\Stmt\Interface_
            || $node instanceof Node\Stmt\Trait_
            || $node instanceof Node\Stmt\Enum_
            || $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_
            || $this->isConditionalRootStatement($node);
    }

    private function isConditionalRootStatement(Node $node): bool
    {
        return $node instanceof Node\Stmt\If_;
    }

    private function getNodeName(Node $node): string|array
    {
        $node = $node instanceof Node\Stmt\Expression ? $node->expr : $node;

        if ($node instanceof Node\Stmt\If_) {
            return array_map(
                fn (Node $conditionalNode) => $this->getNodeName($conditionalNode),
                (new NodeManager())->getAllConditionalStmts($node)
            );
        } elseif ($node instanceof Node\Stmt\Const_) {
            return array_map(fn (Node\Const_ $const) => (string)$const->name, $node->consts);
        }

        return property_exists($node, 'name') ? (string)$node?->name : '';
    }

    private function generateNewNamespace(
        ?string $originalNamespace,
        ?string $vendorNamespace,
        bool $isAppendNamespace,
        bool $underscoreConversion,
        Node $node
    ): ?string {
        if (
            $originalNamespace === null
            && (
                $node instanceof Node\Stmt\Function_
                || $node instanceof Node\Stmt\Const_
                || $node instanceof Node\Stmt\If_
            )
        ) {
            return null;
        }

        if (
            !$isAppendNamespace
            && $originalNamespace !== null
            && $vendorNamespace !== null
            && str_starts_with($originalNamespace, $vendorNamespace)
        ) {
            $originalNamespace = substr($originalNamespace, strlen($vendorNamespace));
        }

        $prefixNamespace = (string)Name::concat(
            trim($vendorNamespace) !== '' ? $vendorNamespace : null,
            trim($originalNamespace) !== '' ? $originalNamespace : null
        );

        if (
            $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_
            || $node instanceof Node\Stmt\If_
            || !$underscoreConversion
        ) {
            return $this->keywordHelper->sanitizeNamespace($prefixNamespace, '_');
        }

        $nodeName = property_exists($node, 'name') ? (string)$node?->name: '';
        $psr4FromPsr0 = str_replace('_', '\\', substr($nodeName, 0, strrpos($nodeName, '_')));
        $newNamespace = $prefixNamespace.($psr4FromPsr0 !== '' ? '\\'.$psr4FromPsr0 : '');

        return $this->keywordHelper->sanitizeNamespace($newNamespace, '_');
    }

    private function generateNewName(
        Node $node,
        ?string $newNamespace,
        bool $underscoreConversion,
        bool $fixCase
    ): string|array {
        $node = $node instanceof Node\Stmt\Expression ? $node->expr : $node;

        if ($node instanceof Node\Stmt\If_) {
            return array_map(
                fn (Node $conditionalNode) => $this->generateNewName($conditionalNode, $newNamespace, false, false),
                (new NodeManager())->getAllConditionalStmts($node)
            );
        } elseif ($node instanceof Node\Stmt\Function_) {
            return (string)$node->name;
        } elseif ($node instanceof Node\Stmt\Const_) {
            return array_map(fn (Node\Const_ $const) => (string)$const->name, $node->consts);
        } elseif ($node instanceof Node\Expr\FuncCall) {
            return (string)$node->name;
        }

        $nodeName = property_exists($node, 'name') ? (string)$node?->name: '';

        if (!$underscoreConversion || !str_contains($nodeName, '_')) {
            $newName = $nodeName;
        } else {
            $newName = substr($nodeName, strrpos($nodeName, '_') + 1);
        }

        $newName = $fixCase ? ucfirst($newName) : $newName;

        if ($newNamespace !== null) {
            $newNamespaceParts = explode('\\', $newNamespace);

            return $this->keywordHelper->sanitizeNameWithPrefix($newName, end($newNamespaceParts));
        } else {
            return $this->keywordHelper->sanitizeNameWithSuffix($newName, '_');
        }
    }

    private function generateTargetFile(
        ?string $newNamespace,
        string|array $newName,
        string $includesDirPath,
        string $originalFilePath,
        Node $node
    ): string {
        if (
            $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_
            || $node instanceof Node\Stmt\If_
        ) {
            $declarePrefix = [];

            foreach ($this->openDeclares as $openDeclare) {
                foreach ($openDeclare->declares as $declare) {
                    $declarePrefix[] = $declare->key
                        .(property_exists($declare->value, 'value') ? $declare->value->value : '');
                }
            }

            if (count($declarePrefix) > 0) {
                $declarePrefix = array_unique($declarePrefix);
                sort($declarePrefix);
            }

            return $includesDirPath.'/include.'.substr(
                sha1(implode('', $declarePrefix).$originalFilePath),
                0,
                7
            ).'.php';
        }

        $pathFromNamespace = str_replace('\\', '/', $newNamespace);

        return ltrim($pathFromNamespace.'/'.$newName.'.php', '/');
    }

    private function isExclusive(Node $node): bool
    {
        return !(
            $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_
            || $node instanceof Node\Stmt\If_
        );
    }

    private function hasRisky(
        Node $node,
        ?string $originalNamespace,
        string|array $originalName,
        bool $underscoreConversion
    ): bool {
        $conditionalDefinitions = $node instanceof Node\Stmt\If_;
        $namespaceMistache = $underscoreConversion
            && $originalNamespace !== null
            && (
                $node instanceof Node\Stmt\Class_
                || $node instanceof Node\Stmt\Interface_
                || $node instanceof Node\Stmt\Trait_
                || $node instanceof Node\Stmt\Enum_
            )
            && str_contains($originalName, '_');

        return $conditionalDefinitions || $namespaceMistache;
    }

    private function generateStatementDetails(Node $node): ?string
    {
        $node = $node instanceof Node\Stmt\Expression ? $node->expr : $node;

        return match (true) {
            $node instanceof Node\Stmt\Class_ => 'class '.$this->getNamespacedName($node),
            $node instanceof Node\Stmt\Interface_ => 'interface '.$this->getNamespacedName($node),
            $node instanceof Node\Stmt\Trait_ => 'trait '.$this->getNamespacedName($node),
            $node instanceof Node\Stmt\Enum_ => 'enum '.$this->getNamespacedName($node),
            $node instanceof Node\Stmt\Function_ => 'function '.$this->getNamespacedName($node),
            $node instanceof Node\Const_ => 'const '.$this->getNamespacedName($node),
            $node instanceof Node\Expr\FuncCall => 'call '.$node->name,
            $node instanceof Node\Stmt\Const_ => implode(
                ', ',
                array_map(fn (Node $constNode) => $this->generateStatementDetails($constNode), $node->consts)
            ),
            $node instanceof Node\Stmt\If_ => implode(
                ', ',
                array_map(
                    fn (Node $conditionalNode) => (!$conditionalNode instanceof Node\Stmt\If_ ? 'conditional ' : '')
                        .$this->generateStatementDetails($conditionalNode),
                    (new NodeManager())->getAllConditionalStmts($node)
                )
            ),
            default => null,
        };
    }

    private function getNamespacedName(Node\Stmt\ClassLike|Node\Stmt\Function_|Node\Const_ $node): Node\Name
    {
        $currentNamespace = $this->nameContext->getNamespace();

        return $node->namespacedName ?? Name::concat($currentNamespace, (string)$node?->name);
    }

    private function generateComponentStmtClasses(Node $node): ?array
    {
        if ($node instanceof Node\Stmt\If_) {
            return array_map(
                fn (Node $conditionalNode) => $this->getStmtClass($conditionalNode),
                (new NodeManager())->getAllConditionalStmts($node)
            );
        } elseif ($node instanceof Node\Stmt\Const_) {
            return array_map(
                fn (Node $constNode) => $this->getStmtClass($constNode),
                $node->consts
            );
        }

        return null;
    }

    private function getStmtClass(Node $node): string
    {
        $node = $node instanceof Node\Stmt\Expression ? $node->expr : $node;

        return $node::class;
    }

    public function leaveNode(Node $node)
    {
        // No block declare have global scope
        if ($node instanceof Node\Stmt\Declare_ && $node->stmts !== null && in_array($node, $this->openDeclares)) {
            unset($this->openDeclares[array_search($node, $this->openDeclares)]);
        }

        return null;
    }

    /**
     * @return MappedUnit[]
     */
    public function getMappedUnits(): array
    {
        return $this->mappedUnits;
    }
}
