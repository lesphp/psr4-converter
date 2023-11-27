<?php

namespace LesPhp\PSR4Converter\Renamer;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\Naming\NameManager;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;

class Renamer implements RenamerInterface
{
    private readonly NameManager $nameManager;

    public function __construct(
        private readonly Parser $parser
    ) {
        $this->nameManager = new NameManager();
    }

    /**
     * @inheritDoc
     */
    public function rename(MappedResult $mappedResult, SplFileInfo $refactoringFile): void
    {
        $stmts = $this->parser->parse($refactoringFile->getContents());

        $stmts = $this->nameManager->replaceFullyQualifiedNames($stmts);
        $stmts = $this->nameManager->replaceNewNames($stmts, $mappedResult);
        $stmts = $this->nameManager->createAliases($stmts);

        $this->dumpTargetFile($stmts, $refactoringFile->getRealPath());
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
}
