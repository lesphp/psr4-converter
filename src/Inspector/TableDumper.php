<?php

namespace LesPhp\PSR4Converter\Inspector;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class TableDumper implements DumperInterface
{
    /**
     * @inheritDoc
     */
    public function dumpStmts(array $mappedStatementInfos, string $srcRootPath, OutputInterface $output): void
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
