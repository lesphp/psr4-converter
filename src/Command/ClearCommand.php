<?php

namespace LesPhp\PSR4Converter\Command;

use LesPhp\PSR4Converter\Mapper\Mapper;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ClearCommand extends Command
{
    private const MAP_FILE_PATH_ARGUMENT = 'map-file';

    private const DRY_RUN = 'dry-run';

    protected static $defaultName = 'clear';

    protected static $defaultDescription = 'Remove all mapped files from the source directory';

    public function __construct(
        private SerializerInterface $resultSerializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Remove all mapped files from the source directory')
            ->addArgument(
                self::MAP_FILE_PATH_ARGUMENT,
                InputArgument::REQUIRED,
                'mapped result file'
            )
            ->addOption(
                self::DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Dry run only'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $mapFilePath = $input->getArgument(self::MAP_FILE_PATH_ARGUMENT);
        $dryRun = $input->getOption(self::DRY_RUN);

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

        $mappedResult = $this->resultSerializer->deserialize($mapFileContent);
        $filesystem = new Filesystem();

        Mapper::verifyHash($mappedResult);

        if ($dryRun) {
            $output->writeln("The following files will be removed from source directory " . $mappedResult->getSrcPath());
        } else {
            $output->writeln("The following files was removed from source directory " . $mappedResult->getSrcPath());
        }

        foreach ($mappedResult->getFiles() as $mappedFile) {
            if ($output->isDebug()) {
                $output->writeln("Processing file: " . $mappedFile->getFilePath());
            }

            $filePath = $mappedFile->getFilePath();

            try {
                if ($filesystem->exists($filePath)) {
                    $relativeFilePath = substr($filePath, strlen($mappedResult->getSrcPath() . DIRECTORY_SEPARATOR));

                    if (!$dryRun) {
                        $filesystem->remove($filePath);
                    }

                    $output->writeln($relativeFilePath);
                }
            } catch (\Throwable $t) {
                $output->writeln("Error processing file: " . $filePath);

                throw $t;
            }
        }

        return Command::SUCCESS;
    }
}
