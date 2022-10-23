<?php

namespace LesPhp\PSR4Converter\Inspector;

use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use Symfony\Component\Console\Output\OutputInterface;

class NamesChangedDumper implements DumperInterface
{
    /**
     * @inheritDoc
     */
    public function dumpStmts(array $mappedStatementInfos, string $srcRootPath, OutputInterface $output): void
    {
        $namesChanged = [];

        foreach ($mappedStatementInfos as $mappedStatementInfo) {
            if (!$mappedStatementInfo instanceof MappedUnit || !$mappedStatementInfo->isExclusive()) {
                continue;
            }

            $originalNames = $mappedStatementInfo->isCompound()
                ? $mappedStatementInfo->getOriginalFullQualifiedName()
                : [$mappedStatementInfo->getOriginalFullQualifiedName()];
            $newNames = $mappedStatementInfo->isCompound()
                ? $mappedStatementInfo->getNewFullQualifiedName()
                : [$mappedStatementInfo->getNewFullQualifiedName()];

            foreach ($originalNames as $i => $originalName) {
                $namesChanged[$originalName] = $newNames[$i];
            }
        }

        $output->writeln(var_export($namesChanged, true));
    }
}
