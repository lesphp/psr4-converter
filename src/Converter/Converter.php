<?php

namespace LesPhp\PSR4Converter\Converter;

use LesPhp\PSR4Converter\Converter\Node\NodeManager;
use LesPhp\PSR4Converter\Exception\IncompatibleMergeFilesException;
use LesPhp\PSR4Converter\Mapper\Result\MappedFile;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Parser\Naming\NameManager;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;

class Converter implements ConverterInterface
{
    private readonly NameManager $nameManager;

    private readonly NodeManager $nodeManager;

    public function __construct(
        private readonly Parser $parser,
        private readonly string $destinationPath,
        private readonly bool $ignoreVendorNamespacePath
    ) {
        $this->nameManager = new NameManager();
        $this->nodeManager = new NodeManager();
    }

    /**
     * @inheritDoc
     */
    public function convert(MappedFile $mappedFile, MappedResult $mappedResult, bool $createAliases, array $additionalMappedResults = []): void
    {
        $originalFilePath = $mappedFile->getFilePath();
        $originalFileContent = file_get_contents($originalFilePath);

        if ($originalFileContent === false) {
            throw new \RuntimeException(sprintf("Error on read content of file %s", $originalFilePath));
        }

        foreach ($mappedFile->getUnits() as $mappedUnit) {
            $unitStmts = $this->parser->parse($originalFileContent);
            $unitStmts = $this->nameManager->replaceFullyQualifiedNames($unitStmts);
            $unitStmts = $this->nameManager->replaceNewNames($unitStmts, $mappedResult, $additionalMappedResults);
            $unitStmts = $this->nodeManager->extract($mappedUnit, $unitStmts, $createAliases);
            $unitStmts = $this->nameManager->createAliases($unitStmts);
            $targetFilePath = $this->destinationPath . '/' .
                ($this->ignoreVendorNamespacePath ? $mappedUnit->getTargetFileWithoutVendor() : $mappedUnit->getTargetFile());

            if ($mappedUnit->isExclusive()) {
                $this->dumpTargetFile($unitStmts, $targetFilePath);
            } else {
                $this->appendTargetFile(
                    $unitStmts,
                    $targetFilePath,
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

    /**
     * @param Node[] $stmts
     * @throws IncompatibleMergeFilesException
     * @throws \RuntimeException
     */
    private function appendTargetFile(array $stmts, string $targetFilePath, string $initialContent = null): void
    {
        $filesystem = new Filesystem();
        $currentStmts = [];

        if ($filesystem->exists($targetFilePath)) {
            $targetFileContent = file_get_contents($targetFilePath);

            if ($targetFileContent === false) {
                throw new \RuntimeException(sprintf("Error on read content of file %s", $targetFilePath));
            }

            $currentStmts = $this->parser->parse($targetFileContent);
        }

        if (count($currentStmts) === 0) {
            $this->dumpTargetFile(
                array_merge($this->parser->parse((string)$initialContent), $stmts),
                $targetFilePath
            );

            return;
        }

        $this->dumpTargetFile(array_merge($currentStmts, $stmts), $targetFilePath);
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
