<?php

namespace LesPhp\PSR4Converter\Inspector;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\StatementDetailsInterface;
use Symfony\Component\Console\Output\OutputInterface;

interface DumperInterface
{
    /**
     * @param StatementDetailsInterface[] $mappedStatementInfos
     */
    public function dumpStmts(array $mappedStatementInfos, string $srcRootPath, OutputInterface $output): void;
}
