<?php

namespace LesPhp\PSR4Converter\Command;

use LesPhp\PSR4Converter\Inspector\DumperInterface;
use LesPhp\PSR4Converter\Inspector\TableDumper;
use LesPhp\PSR4Converter\Inspector\NamesChangedDumper;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InspectCommand extends Command
{
    private const MAP_FILE_PATH_ARGUMENT = 'map-file';
    private const OUTPUT_FORMAT = 'output';
    private const OUTPUT_TABLE_OPTION = 'table';
    private const OUTPUT_ARRAY_NAMES_CHANGES = 'names-changes';

    protected static $defaultName = 'inspect';

    protected static $defaultDescription = 'Inspect all mapped code from a result file';

    public function __construct(
        private SerializerInterface $resultSerializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Inspect all mapped code from a result file')
            ->addArgument(
                self::MAP_FILE_PATH_ARGUMENT,
                InputArgument::REQUIRED,
                'mapped result file'
            )
            ->addOption(
                self::OUTPUT_FORMAT,
                'o',
                InputOption::VALUE_OPTIONAL,
                self::OUTPUT_TABLE_OPTION . ' or ' . self::OUTPUT_ARRAY_NAMES_CHANGES,
                self::OUTPUT_TABLE_OPTION
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $mapFilePath = $input->getArgument(self::MAP_FILE_PATH_ARGUMENT);
        $outputFormat = $input->getOption(self::OUTPUT_FORMAT);

        if (is_dir($mapFilePath)) {
            $mapDirPath = $mapFilePath;
            $mapFilenamePath = MapCommand::DEFAULT_MAP_FILENAME;
        } else {
            $mapDirPath = dirname($mapFilePath);
            $mapFilenamePath = basename($mapFilePath);
        }

        $mapFileContent = file_get_contents($mapDirPath.'/'.$mapFilenamePath);

        if ($mapFileContent === false) {
            $errorOutput->writeln("The map file doesn't exists or isn't readable.");

            return Command::INVALID;
        }

        $statementsDumper = match ($outputFormat) {
            self::OUTPUT_TABLE_OPTION => new TableDumper(),
            self::OUTPUT_ARRAY_NAMES_CHANGES => new NamesChangedDumper(),
            default => null,
        };

        if ($statementsDumper === null) {
            $errorOutput->writeln("The output format is invalid.");

            return Command::INVALID;
        }

        $mappedResult = $this->resultSerializer->deserialize($mapFileContent);

        $this->dumpResult($statementsDumper, $mappedResult, $output);

        return Command::SUCCESS;
    }

    public function dumpResult(DumperInterface $statementsDumper, MappedResult $mappedResult, OutputInterface $output): void
    {
        $statementsDumper->dumpStmts($mappedResult->getNoRisky(), $mappedResult->getSrcPath(), $output);

        $output->writeln("Risky conversions");

        if ($mappedResult->hasRisky()) {
            $statementsDumper->dumpStmts($mappedResult->getRisky(), $mappedResult->getSrcPath(), $output);
        }
    }
}
