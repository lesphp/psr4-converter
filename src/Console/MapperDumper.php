<?php

namespace LesPhp\PSR4Converter\Console;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\StatementDetailsInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class MapperDumper
{
    public function dumpResult(MappedResult $mappedResult, OutputInterface $output): void
    {
        $this->dumpInfoTable($mappedResult->getNoRisky(), $mappedResult->getSrcPath(), $output);

        $output->writeln("Risky conversions");

        if ($mappedResult->hasRisky()) {
            $this->dumpInfoTable($mappedResult->getRisky(), $mappedResult->getSrcPath(), $output);
        }
    }

    /**
     * @param StatementDetailsInterface[] $mappedStatementInfos
     */
    public function dumpInfoTable(array $mappedStatementInfos, string $srcRootPath, OutputInterface $output): void
    {
        $filesystem = new Filesystem();
        $table = new Table($output);
        $table->setHeaders(['File', 'Lines', 'Details']);

        foreach ($mappedStatementInfos as $mappedStatementInfo) {
            $originalFileInfo = new \SplFileInfo($mappedStatementInfo->getOriginalFile());
            $table->addRow([
                $filesystem->makePathRelative(
                    $originalFileInfo->getPath(),
                    $srcRootPath
                ).$originalFileInfo->getFilename(),
                $mappedStatementInfo->getStartLine().':'.$mappedStatementInfo->getEndLine(),
                $mappedStatementInfo->getDetails(),
            ]);
        }

        $table->setStyle('borderless')->render();
    }
}
