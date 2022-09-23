<?php

namespace LesPhp\PSR4Converter\Renamer;

use LesPhp\PSR4Converter\Converter\Clean\CleanManager;
use LesPhp\PSR4Converter\Converter\Naming\NameManager;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Renamer implements RenamerInterface
{
    public function __construct(
        private readonly KeywordManager $keywordHelper,
        private readonly Parser $parser
    ) {

    }

    /**
     * @inheritDoc
     */
    public function rename(MappedResult $mappedResult, string $destinationDir): void
    {
        $finder = (new Finder())
            ->in($destinationDir)
            ->followLinks()
            ->ignoreDotFiles(false)
            ->ignoreVCSIgnored(false)
            ->files()
            ->name('*.php');

        foreach ($finder as $refactoringFile) {
            $stmts = $this->parser->parse($refactoringFile->getContents());

            $stmts = $this->fullyQualifyNames($stmts, $mappedResult, $this->keywordHelper);
            $stmts = $this->clearStmts($stmts);

            $this->dumpTargetFile($stmts, $refactoringFile->getRealPath());
        }
    }

    /**
     * @param Node[] $stmts
     */
    private function dumpTargetFile(array $stmts, string $targetFilePath): void
    {
        $filesystem = new Filesystem();
        $prettyPrinter = new PrettyPrinter\Standard();

        $filesystem->mkdir(dirname($targetFilePath));

        $filesystem->dumpFile($targetFilePath, $prettyPrinter->prettyPrintFile($stmts));
    }

    /**
     * @param Node[] $stmts
     * @return Node[]
     */
    private function fullyQualifyNames(array $stmts, MappedResult $mappedResult, KeywordManager $keywordHelper): array
    {
        $nameManager = new NameManager();

        return $nameManager->replaceFullyQualifiedNames($mappedResult, $stmts, $keywordHelper);
    }

    /**
     * @param Node[] $stmts
     * @return Node[]
     */
    private function clearStmts(array $stmts): array
    {
        $cleanManager = new CleanManager();

        $stmts = $cleanManager->createAliases($stmts, $this->keywordHelper);
        $stmts = $cleanManager->createAliasesFoDoc($stmts);

        return $cleanManager->removeUnusedImports($stmts);
    }
}