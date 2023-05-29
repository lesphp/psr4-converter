<?php

namespace LesPhp\PSR4Converter\Command;

use LesPhp\PSR4Converter\Inspector\DumperInterface;
use LesPhp\PSR4Converter\Inspector\TableDumper;
use LesPhp\PSR4Converter\Exception\InvalidNamespaceException;
use LesPhp\PSR4Converter\Exception\MapperConflictException;
use LesPhp\PSR4Converter\Parser\CustomEmulativeLexer;
use LesPhp\PSR4Converter\Mapper\Node\MapFileVisitor;
use LesPhp\PSR4Converter\Mapper\MapperFactoryInterface;
use LesPhp\PSR4Converter\Mapper\Result\MappedError;
use LesPhp\PSR4Converter\Mapper\Result\MappedFile;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\SerializerInterface;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class MapCommand extends Command
{
    private const SRC_ARGUMENT = 'src';

    private const PREFIX_NAMESPACE = 'prefix';

    private const INCLUDES_DIR_PATH = 'includes-dir';

    private const MAP_FILE_PATH = 'map-file';

    private const APPEND_NAMESPACE = 'append-namespace';

    private const FOLLOW_SYMLINK = 'follow-symlink';

    private const IGNORE_DOT_FILES = 'ignore-dot-files';

    private const IGNORE_VCS_IGNORED = 'ignore-vcs-ignored';

    private const IGNORE_PATH = 'ignore-path';

    private const IGNORE_ERRORS = 'ignore-errors';

    private const IGNORE_NAMESPACE = 'ignore-namespace';

    private const USE_PHP5 = 'use-php5';

    private const DRY_RUN = 'dry-run';

    private const UNDERSCORE_CONVERSION = 'underscore-conversion';

    private const IGNORE_NAMESPACED_UNDERSCORE_CONVERSION = 'ignore-namespaced-underscore';

    public const DEFAULT_MAP_FILENAME = '.psr4-converter.map.json';

    protected static $defaultName = 'map';

    protected static $defaultDescription = 'Map a directory for a PSR-4 conversion';

    public function __construct(
        private readonly MapperFactoryInterface $mapperFactory,
        private readonly SerializerInterface $resultSerializer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Map all classes from a directory to PSR-4 conversion')
            ->addArgument(
                self::PREFIX_NAMESPACE,
                InputArgument::REQUIRED,
                'Vendor Namespace'
            )
            ->addArgument(
                self::SRC_ARGUMENT,
                InputArgument::REQUIRED,
                'source path to convert'
            )
            ->addOption(
                self::INCLUDES_DIR_PATH,
                'f',
                InputOption::VALUE_REQUIRED,
                'Path to include files',
                'includes'
            )
            ->addOption(
                self::MAP_FILE_PATH,
                'm',
                InputOption::VALUE_REQUIRED,
                'Path to map file',
                self::DEFAULT_MAP_FILENAME
            )
            ->addOption(
                self::APPEND_NAMESPACE,
                null,
                InputOption::VALUE_NONE,
                'append current namespace at vendor namespace'
            )
            ->addOption(
                self::IGNORE_ERRORS,
                null,
                InputOption::VALUE_NONE,
                'ignore map errors'
            )
            ->addOption(
                self::FOLLOW_SYMLINK,
                null,
                InputOption::VALUE_NONE,
                'Follow symlink'
            )
            ->addOption(
                self::IGNORE_DOT_FILES,
                null,
                InputOption::VALUE_NONE,
                'Ignore dot files'
            )
            ->addOption(
                self::IGNORE_PATH,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Ignore path patterns'
            )
            ->addOption(
                self::IGNORE_NAMESPACE,
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                sprintf(
                    'namespace to be ignored. To ignore all namespaces, except global, use %s. To ignore only global namespace use %s ',
                    MapFileVisitor::IGNORE_ALL_NAMESPACES,
                    MapFileVisitor::IGNORE_GLOBAL_NAMESPACE
                )
            )
            ->addOption(
                self::IGNORE_VCS_IGNORED,
                null,
                InputOption::VALUE_NONE,
                'Ignore VCS ignored'
            )
            ->addOption(
                self::USE_PHP5,
                null,
                InputOption::VALUE_NONE,
                'Use PHP5 parser'
            )
            ->addOption(
                self::DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Dry run only'
            )
            ->addOption(
                self::UNDERSCORE_CONVERSION,
                null,
                InputOption::VALUE_NONE,
                'Underscores will means namespace separator. With this option, already namespaced statement with name containing underscore may differ from converted constants and functions from same namespace.'
            )
            ->addOption(
                self::IGNORE_NAMESPACED_UNDERSCORE_CONVERSION,
                null,
                InputOption::VALUE_NONE,
                'Ignore underscores for already namespaced statement.'
            );
    }

    /**
     * @throws InvalidNamespaceException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $includeDirPath = $input->getOption(self::INCLUDES_DIR_PATH);
        $mapFile = $input->getOption(self::MAP_FILE_PATH);
        $isAppendNamespace = $input->getOption(self::APPEND_NAMESPACE);
        $followSymlink = $input->getOption(self::FOLLOW_SYMLINK);
        $ignoreDotFiles = $input->getOption(self::IGNORE_DOT_FILES);
        $ignoreVCSIgnored = $input->getOption(self::IGNORE_VCS_IGNORED);
        $ignorePaths = $input->getOption(self::IGNORE_PATH);
        $ignoreNamespaces = $input->getOption(self::IGNORE_NAMESPACE);
        $phpParserKind = $input->getOption(self::USE_PHP5) ? ParserFactory::PREFER_PHP5 : ParserFactory::PREFER_PHP7;
        $dryRun = $input->getOption(self::DRY_RUN);
        $underscoreConversion = $input->getOption(self::UNDERSCORE_CONVERSION);
        $ignoreErrors = $input->getOption(self::IGNORE_ERRORS);
        $ignoreNamespacedUnderscoreConversion = $input->getOption(self::IGNORE_NAMESPACED_UNDERSCORE_CONVERSION);
        $prefixNamespace = $input->getArgument(self::PREFIX_NAMESPACE);
        $srcPath = $input->getArgument(self::SRC_ARGUMENT);
        $statementsDumper = new TableDumper();

        // This ensures that there will be no errors when traversing highly nested node trees.
        if (extension_loaded('xdebug')) {
            ini_set('xdebug.max_nesting_level', -1);
        }

        $filesystem = new Filesystem();
        $finder = new Finder();
        $lexer = new CustomEmulativeLexer();
        $parser = (new ParserFactory())->create($phpParserKind, $lexer);
        $srcRealPath = realpath($srcPath);
        $mapFileRealPath = Path::isAbsolute($mapFile) ? $mapFile : $srcRealPath.'/'.$mapFile;

        if ($srcRealPath === false || !is_dir($srcRealPath)) {
            $errorOutput->writeln("The source directory doesn't exists or isn't readable.");

            return Command::INVALID;
        }

        if (Path::isAbsolute($includeDirPath)) {
            $errorOutput->writeln("The includes dir must be a relative path.");

            return Command::INVALID;
        }

        if ($followSymlink) {
            $finder->followLinks();
        }

        $finder->in($srcRealPath)
            ->ignoreDotFiles($ignoreDotFiles)
            ->ignoreVCSIgnored($ignoreVCSIgnored)
            ->files()
            ->name('*.php');

        foreach ($ignorePaths as $ignorePath) {
            $finder->notPath($ignorePath);
        }

        $mapper = $this->mapperFactory->createMapper(
            $parser,
            $lexer,
            $srcRealPath,
            $includeDirPath,
            $prefixNamespace,
            $isAppendNamespace,
            $underscoreConversion,
            $ignoreNamespacedUnderscoreConversion,
            $ignoreNamespaces
        );

        /** @var MappedFile[] $mappedFiles */
        $mappedFiles = [];

        foreach ($finder as $file) {
            $mappedFiles[] = $mapper->map($file->getRealPath());
        }

        $mappedResult = new MappedResult($phpParserKind, $srcRealPath, $includeDirPath, $mappedFiles);

        if ($mappedResult->hasError() && !$ignoreErrors) {
            $errorOutput->writeln('There are errors on conversions attempts, fix it.');

            $statementsDumper->dumpStmts($mappedResult->getErrors(), $srcRealPath, $errorOutput);

            return Command::INVALID;
        }

        if ($mappedResult->hasInclude()) {
            $output->writeln(
                "There are includes/require clauses in the file for conversion. The statements will be preserved, bus this turns the conversion risky."
            );
        }

        $this->dumpResult($statementsDumper, $mappedResult, $output);

        if (!$dryRun) {
            $filesystem->dumpFile($mapFileRealPath, $this->resultSerializer->serialize($mappedResult));

            $output->writeln("Map successfully saved to $mapFile.");
        }

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
