<?php

namespace LesPhp\PSR4Converter\Command;

use LesPhp\PSR4Converter\Parser\CustomEmulativeLexer;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\SerializerInterface;
use LesPhp\PSR4Converter\Renamer\RenamerFactoryInterface;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class RenameCommand extends Command
{
    private const MAP_FILE_PATH_ARGUMENT = 'map-file';

    private const DESTINATION_DIRS_ARGUMENT = 'destination-dirs';

    protected static $defaultName = 'rename';

    protected static $defaultDescription = 'Rename all mapped entities on destination dirs';

    public function __construct(
        private readonly RenamerFactoryInterface $renamerFactory,
        private readonly SerializerInterface $resultSerializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Convert the mapped result into a target directory')
            ->addArgument(
                self::MAP_FILE_PATH_ARGUMENT,
                InputArgument::REQUIRED,
                'mapped result file'
            )
            ->addArgument(
                self::DESTINATION_DIRS_ARGUMENT,
                InputArgument::IS_ARRAY,
                'directories to rename entities'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $mapFilenamePath = $input->getArgument(self::MAP_FILE_PATH_ARGUMENT);
        $destinationDirs = $input->getArgument(self::DESTINATION_DIRS_ARGUMENT);

        // This ensures that there will be no errors when traversing highly nested node trees.
        if (extension_loaded('xdebug')) {
            ini_set('xdebug.max_nesting_level', -1);
        }

        if (is_dir($mapFilenamePath)) {
            $mapDirPath = $mapFilenamePath;
            $mapFilenamePath = MapCommand::DEFAULT_MAP_FILENAME;
        } else {
            $mapDirPath = dirname($mapFilenamePath);
            $mapFilenamePath = basename($mapFilenamePath);
        }

        $mapFileContent = file_get_contents($mapDirPath.'/'.$mapFilenamePath);

        if ($mapFileContent === false) {
            $errorOutput->writeln("The map file doesn't exists or isn't readable.");

            return Command::INVALID;
        }

        foreach ($destinationDirs as $destinationDir) {
            if (!is_dir($destinationDir) || !is_readable($destinationDir)) {
                $errorOutput->writeln(
                    sprintf("The additional refactoring directory %s doesn't exists or isn't readable.", $destinationDir)
                );

                return Command::INVALID;
            }
        }

        $mappedResult = $this->resultSerializer->deserialize($mapFileContent);

        $lexer = new CustomEmulativeLexer();
        $parser = (new ParserFactory())->create($mappedResult->getPhpParserKind(), $lexer);
        $renamer = $this->renamerFactory->createRenamer($parser);

        foreach ($destinationDirs as $destinationDir) {
            $finder = (new Finder())
                ->in($destinationDir)
                ->followLinks()
                ->ignoreDotFiles(false)
                ->ignoreVCSIgnored(false)
                ->files()
                ->name('*.php');

            foreach ($finder as $refactoringFile) {
                if ($output->isDebug()) {
                    $output->writeln("Processing file: " . $refactoringFile->getRealPath());
                }

                try {
                    $renamer->rename($mappedResult, $refactoringFile);
                } catch (\Throwable $t) {
                    $output->writeln("Error processing file: " . $refactoringFile->getRealPath());

                    throw $t;
                }
            }
        }

        return Command::SUCCESS;
    }
}
