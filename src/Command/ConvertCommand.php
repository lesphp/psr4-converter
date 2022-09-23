<?php

namespace LesPhp\PSR4Converter\Command;

use LesPhp\PSR4Converter\Autoloader\AutoloaderFactoryInterface;
use LesPhp\PSR4Converter\Converter\ConverterFactoryInterface;
use LesPhp\PSR4Converter\Exception\InvalidHashException;
use LesPhp\PSR4Converter\Parser\CustomEmulativeLexer;
use LesPhp\PSR4Converter\Mapper\Mapper;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\SerializerInterface;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConvertCommand extends Command
{
    private const MAP_FILE_PATH_ARGUMENT = 'map-file';

    private const DESTINATION_DIR_ARGUMENT = 'destination-dir';

    private const ALLOW_RISKY_OPTION = 'allow-risky';

    private const IGNORE_VENDOR_NAMESPACE_PATH = 'ignore-vendor-path';

    private const CREATE_ALIASES = 'create-aliases';

    protected static $defaultName = 'convert';

    protected static $defaultDescription = 'Convert the mapped result';

    public function __construct(
        private readonly ConverterFactoryInterface $converterFactory,
        private readonly AutoloaderFactoryInterface $autoloaderFactory,
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
                self::DESTINATION_DIR_ARGUMENT,
                InputArgument::REQUIRED,
                'directories to result conversion'
            )
            ->addOption(
                self::ALLOW_RISKY_OPTION,
                null,
                InputOption::VALUE_NEGATABLE,
                'allow risky conversion',
                false
            )
            ->addOption(
                self::IGNORE_VENDOR_NAMESPACE_PATH,
                null,
                InputOption::VALUE_NEGATABLE,
                'create target files without vendor namespace paths',
                false
            )
            ->addOption(
                self::CREATE_ALIASES,
                null,
                InputOption::VALUE_NEGATABLE,
                'create aliases with class_alias and autoload files for old names',
                false
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $mapFilenamePath = $input->getArgument(self::MAP_FILE_PATH_ARGUMENT);
        $destinationDir = $input->getArgument(self::DESTINATION_DIR_ARGUMENT);
        $allowRisky = $input->getOption(self::ALLOW_RISKY_OPTION);
        $ignoreVendorNamespacePath = $input->getOption(self::IGNORE_VENDOR_NAMESPACE_PATH);
        $createAliases = $input->getOption(self::CREATE_ALIASES);

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


        $mappedResult = $this->resultSerializer->deserialize($mapFileContent);

        if (!$allowRisky && ($mappedResult->hasRisky() || $mappedResult->hasInclude())) {
            $errorOutput->writeln(
                sprintf("The conversion has risk, add option --%s to force it.", self::ALLOW_RISKY_OPTION)
            );

            return Command::INVALID;
        }

        $lexer = new CustomEmulativeLexer();
        $parser = (new ParserFactory())->create($mappedResult->getPhpParserKind(), $lexer);
        $converter = $this->converterFactory->createConverter($parser, $destinationDir, $ignoreVendorNamespacePath);

        foreach ($mappedResult->getFiles() as $mappedFile) {
            if ($mappedFile->getHash() !== Mapper::calculateHash($mappedFile->getFilePath())) {
                throw new InvalidHashException($mappedFile->getFilePath());
            }
        }

        foreach ($mappedResult->getFiles() as $mappedFile) {
            $converter->convert($mappedFile, $mappedResult, $createAliases);
        }

        if ($createAliases) {
            $autoloader = $this->autoloaderFactory->createAutoloader();

            $autoloader->generate($mappedResult, $destinationDir . '/' . $mappedResult->getIncludesDirPath() . '/autoload.php');
        }

        return Command::SUCCESS;
    }
}
