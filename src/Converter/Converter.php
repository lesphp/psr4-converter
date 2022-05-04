<?php

namespace LesPhp\PSR4Converter\Converter;

use LesPhp\PSR4Converter\Converter\Clean\CleanManager;
use LesPhp\PSR4Converter\Converter\Node\NodeManager;
use LesPhp\PSR4Converter\Converter\Naming\NameManager;
use LesPhp\PSR4Converter\Exception\IncompatibleMergeFilesException;
use LesPhp\PSR4Converter\KeywordManager;
use LesPhp\PSR4Converter\Mapper\Result\MappedFile;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Converter implements ConverterInterface
{
    private Parser $parser;

    private string $destinationPath;

    public function __construct(
        private readonly KeywordManager $keywordHelper,
        Parser $parser,
        string $destinationPath
    ) {
        $this->parser = $parser;
        $this->destinationPath = $destinationPath;
    }

    /**
     * @inheritDoc
     */
    public function convert(MappedFile $mappedFile, MappedResult $mappedResult): void
    {
        $originalFilePath = $mappedFile->getFilePath();
        $originalFileContent = file_get_contents($originalFilePath);

        if ($originalFileContent === false) {
            throw new \RuntimeException(sprintf("Error on read content of file %s", $originalFilePath));
        }

        foreach ($mappedFile->getUnits() as $mappedUnit) {
            $this->addResultToAliases($mappedUnit, $mappedResult->getIncludesDirPath());

            $unitStmts = $this->extractUnitStmts($mappedUnit, $mappedResult, $originalFileContent);
            $unitStmts = $this->clearStmts($unitStmts);

            if ($mappedUnit->isExclusive()) {
                $this->dumpTargetFile($unitStmts, $mappedUnit->getTargetFile());
            } else {
                $this->appendTargetFile(
                    $unitStmts,
                    $mappedUnit->getTargetFile(),
                    <<<EOL
                    <?php
                    
                    /**
                     * @see $originalFilePath
                     */
                    EOL
                );
            }
        }
    }

    public function refactor(MappedResult $mappedResult, string $refactoringDir): void
    {
        $finder = (new Finder())
            ->in($refactoringDir)
            ->followLinks()
            ->ignoreDotFiles(false)
            ->ignoreVCSIgnored(false)
            ->files()
            ->name('*.php');

        foreach ($finder as $refactoringFile) {
            $stmts = $this->parser->parse($refactoringFile->getContents());

            $stmts = $this->fullyQualifyNames($stmts, $mappedResult);
            $stmts = $this->clearStmts($stmts);

            $this->dumpTargetFile($stmts, $refactoringFile->getRealPath());
        }
    }

    /**
     * @throws IncompatibleMergeFilesException
     */
    private function addResultToAliases(MappedUnit $mappedUnit, string $includesDirPath): void
    {
        if ($mappedUnit->isCompound()) {
            $componentStmtClasses = $mappedUnit->getComponentStmtClasses();
            $originalFullQualifiedNames = $mappedUnit->getOriginalFullQualifiedName();
            $newFullQualifiedNames = $mappedUnit->getNewFullQualifiedName();
        } else {
            $componentStmtClasses = (array)$mappedUnit->getStmtClass();
            $originalFullQualifiedNames = (array)$mappedUnit->getOriginalFullQualifiedName();
            $newFullQualifiedNames = (array)$mappedUnit->getNewFullQualifiedName();
        }


        foreach ($componentStmtClasses as $i => $componentStmtClass) {
            if (!$this->isAllowAlias(
                $componentStmtClass
            ) || $newFullQualifiedNames[$i] === $originalFullQualifiedNames[$i]) {
                continue;
            }

            $aliasCall = new Node\Stmt\Expression(
                new Node\Expr\FuncCall(
                    new Node\Name('class_alias'),
                    [
                        new Node\Arg(new Node\Scalar\String_($newFullQualifiedNames[$i])),
                        new Node\Arg(new Node\Scalar\String_($originalFullQualifiedNames[$i])),
                        new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('true'))),
                    ]
                )
            );

            $this->appendTargetFile([$aliasCall], $includesDirPath.'/aliases.php');
        }
    }

    private function isAllowAlias(string $stmtClass): bool
    {
        return is_a($stmtClass, Node\Stmt\Class_::class, true)
            || is_a($stmtClass, Node\Stmt\Interface_::class, true)
            || is_a($stmtClass, Node\Stmt\Trait_::class, true)
            || is_a($stmtClass, Node\Stmt\Enum_::class, true);
    }

    /**
     * @param Node[] $stmts
     * @throws IncompatibleMergeFilesException
     * @throws \RuntimeException
     */
    private function appendTargetFile(array $stmts, string $targetFilePath, string $initialContent = null): void
    {
        $filesystem = new Filesystem();
        $nodeManager = new NodeManager();
        $targetFileAbsolutePath = Path::isAbsolute($targetFilePath)
            ? $targetFilePath
            : $this->destinationPath.'/'.$targetFilePath;
        $currentStmts = [];

        if ($filesystem->exists($targetFileAbsolutePath)) {
            $targetFileContent = file_get_contents($targetFileAbsolutePath);

            if ($targetFileContent === false) {
                throw new \RuntimeException(sprintf("Error on read content of file %s", $targetFileAbsolutePath));
            }

            $currentStmts = $this->parser->parse($targetFileContent);
        }

        if (count($currentStmts) === 0) {
            $this->dumpTargetFile(
                array_merge($this->parser->parse((string)$initialContent), $stmts),
                $targetFileAbsolutePath
            );

            return;
        }

        $this->dumpTargetFile($nodeManager->append($currentStmts, $stmts), $targetFileAbsolutePath);
    }

    /**
     * @param Node[] $stmts
     */
    private function dumpTargetFile(array $stmts, string $targetFilePath): void
    {
        $filesystem = new Filesystem();
        $prettyPrinter = new PrettyPrinter\Standard();
        $targetFileAbsolutePath = Path::isAbsolute(
            $targetFilePath
        ) ? $targetFilePath : $this->destinationPath.'/'.$targetFilePath;

        $filesystem->mkdir(dirname($targetFileAbsolutePath));

        $filesystem->dumpFile($targetFileAbsolutePath, $prettyPrinter->prettyPrintFile($stmts));
    }

    /**
     * @return Node[]
     */
    private function extractUnitStmts(MappedUnit $mappedUnit, MappedResult $mappedResult, string $originalFileContent): array
    {
        $nodeManager = new NodeManager();

        $stmts = $this->parser->parse($originalFileContent);

        return $nodeManager->extract($mappedUnit, $mappedResult, $stmts);
    }

    /**
     * @param Node[] $stmts
     * @return Node[]
     */
    private function fullyQualifyNames(array $stmts, MappedResult $mappedResult): array
    {
        $nameManager = new NameManager();

        return $nameManager->replaceFullyQualifiedNames($mappedResult, $stmts);
    }

    /**
     * @param Node[] $stmts
     * @return Node[]
     */
    private function clearStmts(array $stmts): array
    {
        $cleanManager = new CleanManager();

        $stmts = $cleanManager->createAliases($stmts, $this->keywordHelper);

        return $cleanManager->removeUnusedImports($stmts);
    }
}
