<?php

namespace LesPhp\PSR4Converter\Mapper;

use LesPhp\PSR4Converter\Exception\InvalidHashException;
use LesPhp\PSR4Converter\Exception\InvalidNamespaceException;
use LesPhp\PSR4Converter\Exception\InvalidRootStatementException;
use LesPhp\PSR4Converter\Mapper\Node\MapFileVisitor;
use LesPhp\PSR4Converter\Mapper\Node\NodeManager;
use LesPhp\PSR4Converter\Mapper\Result\MappedError;
use LesPhp\PSR4Converter\Mapper\Result\MappedFile;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use Symfony\Component\Filesystem\Filesystem;

class Mapper implements MapperInterface
{
    private readonly ?string $prefixNamespace;

    /**
     * @var string[]
     */
    private readonly array $ignoreNamespaces;

    /**
     * @param string[] $ignoreNamespaces
     * @throws InvalidNamespaceException
     */
    public function __construct(
        private readonly NameHelper $keywordHelper,
        private readonly Parser $parser,
        private readonly Lexer $lexer,
        private readonly string $srcPath,
        private readonly string $includesDirPath,
        ?string $prefixNamespace,
        private readonly bool $appendNamespace,
        private readonly bool $underscoreConversion,
        private readonly bool $ignoreNamespacedUnderscoreConversion,
        array $ignoreNamespaces,
        private readonly bool $pathBasedConversion,
        private readonly bool $forceNamesCamelCase
    ) {
        if ($prefixNamespace !== null && !$this->keywordHelper->isValidNamespace($prefixNamespace)) {
            throw new InvalidNamespaceException();
        }

        $this->prefixNamespace = $prefixNamespace;

        foreach ($ignoreNamespaces as $ignoreNamespace) {
            if (
                $ignoreNamespace !== MapFileVisitor::IGNORE_ALL_NAMESPACES
                && $ignoreNamespace !== MapFileVisitor::IGNORE_GLOBAL_NAMESPACE
                && !$this->keywordHelper->isValidNamespace($ignoreNamespace)) {
                throw new InvalidNamespaceException();
            }
        }

        $this->ignoreNamespaces = $ignoreNamespaces;
    }

    public static function calculateHash(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }

    public static function verifyHash(MappedResult $mappedResult): void
    {
        $filesystem = new Filesystem();

        foreach ($mappedResult->getFiles() as $mappedFile) {
            if (!$filesystem->exists($mappedFile->getFilePath()) || $mappedFile->getHash() !== Mapper::calculateHash($mappedFile->getFilePath())) {
                throw new InvalidHashException($mappedFile->getFilePath());
            }
        }
    }

    public function map(string $filePath): MappedFile
    {
        $content = file_get_contents($filePath);
        $hasInclude = false;

        if ($content === false) {
            throw new \RuntimeException(sprintf("Error on read content of file %s", $filePath));
        }

        try {
            $stmts = $this->parser->parse($content);
            $hasInclude = $this->hasInclude($stmts);

            $this->checkNodesConstraints($stmts, false, false);
        } catch (InvalidRootStatementException $e) {
            $stmt = $e->getStmt();
            $mappedError = new MappedError(
                $filePath,
                $stmt->getStartLine(),
                $stmt->getStartFilePos(),
                $stmt->getEndLine(),
                $stmt->getEndFilePos(),
                $e->getMessage()
            );

            return new MappedFile($filePath, $hasInclude, static::calculateHash($filePath), [], [$mappedError]);
        } catch (Error $e) {
            $startPos = $e->getAttributes()['startFilePos'] ?? -1;
            $endPos = $e->getAttributes()['endFilePos'] ?? -1;
            $mappedError = new MappedError($filePath, $e->getStartLine(), $startPos, $e->getEndLine(), $endPos, $e->getMessage());

            return new MappedFile($filePath, $hasInclude, static::calculateHash($filePath), [], [$mappedError]);
        }

        $nodeManager = new NodeManager();
        $mapperContext = new MapperContext(
            $filePath,
            $this->srcPath,
            $this->includesDirPath,
            $this->prefixNamespace,
            $this->appendNamespace,
            $this->underscoreConversion,
            $this->ignoreNamespacedUnderscoreConversion,
            $this->ignoreNamespaces,
            $this->pathBasedConversion,
            $this->forceNamesCamelCase
        );

        $mappedUnits = $nodeManager->mapFile($mapperContext, $stmts);

        return new MappedFile($filePath, $hasInclude, static::calculateHash($filePath), $mappedUnits);
    }

    /**
     * @param Node[] $nodes
     */
    private function hasInclude(array $nodes): bool
    {
        $nodeFinder = new NodeFinder();

        return $nodeFinder->findFirstInstanceOf($nodes, Node\Expr\Include_::class) !== null;
    }

    /**
     * @param Node[] $nodes
     * @throws InvalidRootStatementException
     */
    private function checkNodesConstraints(array $nodes, bool $allowFuncCall, bool $allowReturnOrExit): void
    {
        foreach ($nodes as $stmt) {
            $stmt = $stmt instanceof Node\Stmt\Expression ? $stmt->expr : $stmt;

            switch (true) {
                case $stmt instanceof Node\Stmt\Namespace_:
                    $this->checkNodesConstraints((array)$stmt->stmts, false, false);
                    continue 2;
                case $stmt instanceof Node\Stmt\If_:
                    $this->checkNodesConstraints(
                        (new NodeManager())->getAllConditionalStmts($stmt),
                        true,
                        true
                    );
                    continue 2;
                case $stmt instanceof Node\Stmt\Declare_:
                case $stmt instanceof Node\Stmt\Use_:
                case $stmt instanceof Node\Stmt\GroupUse:
                case $stmt instanceof Node\Stmt\Class_:
                case $stmt instanceof Node\Stmt\Interface_:
                case $stmt instanceof Node\Stmt\Trait_:
                case $stmt instanceof Node\Stmt\Enum_:
                case $stmt instanceof Node\Stmt\Function_:
                case $stmt instanceof Node\Stmt\Const_:
                case $stmt instanceof Node\Stmt\Nop:
                case $allowFuncCall
                    && $stmt instanceof Node\Expr\FuncCall
                    && $this->isFuncCallAllowed($stmt):
                    continue 2;
                case $allowReturnOrExit
                    && (
                        $stmt instanceof Node\Expr\Exit_
                        || $stmt instanceof Node\Stmt\Return_
                    ):
                    continue 2;
                default:
                    throw new InvalidRootStatementException(
                        $stmt,
                        $this->lexer->getTokens()[$stmt->getStartTokenPos()]
                    );
            }
        }
    }

    private function isFuncCallAllowed(Node\Expr\FuncCall $node): bool
    {
        return (string)$node->name === 'define';
    }
}
