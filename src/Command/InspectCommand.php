<?php

namespace LesPhp\PSR4Converter\Command;

use LesPhp\PSR4Converter\Console\MapperDumper;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InspectCommand extends Command
{
    private const MAP_FILE_PATH_ARGUMENT = 'map-file';

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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $mapFilePath = $input->getArgument(self::MAP_FILE_PATH_ARGUMENT);

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

        $statementsDumper = new MapperDumper();

        $mappedResult = $this->resultSerializer->deserialize($mapFileContent);

        $statementsDumper->dumpResult($mappedResult, $output);

        return Command::SUCCESS;
    }
}
