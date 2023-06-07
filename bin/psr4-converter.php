<?php

use LesPhp\PSR4Converter\Autoloader\AutoloaderFactory;
use LesPhp\PSR4Converter\Command\ClearCommand;
use LesPhp\PSR4Converter\Command\ConvertCommand;
use LesPhp\PSR4Converter\Command\InspectCommand;
use LesPhp\PSR4Converter\Command\MapCommand;
use LesPhp\PSR4Converter\Command\RenameCommand;
use LesPhp\PSR4Converter\Converter\ConverterFactory;
use LesPhp\PSR4Converter\Mapper\MapperFactory;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\Serializer;
use LesPhp\PSR4Converter\Renamer\RenamerFactory;
use Symfony\Component\Console\Application;

require __DIR__.'/../vendor/autoload.php';

$application = new Application('PSR-4 Converter', '@package_version@');
$psr4MapperFactory = new MapperFactory();
$psr4ConverterFactory = new ConverterFactory();
$psr4RenamerFactory = new RenamerFactory();
$autoloaderFactory = new AutoloaderFactory();
$resultSerializer = new Serializer();
$convertCommand = new ConvertCommand($psr4ConverterFactory, $autoloaderFactory, $resultSerializer);
$mapCommand = new MapCommand($psr4MapperFactory, $resultSerializer);
$renameCommand = new RenameCommand($psr4RenamerFactory, $resultSerializer);
$inspectCommand = new InspectCommand($resultSerializer);
$clearCommand = new ClearCommand($resultSerializer);

$application->add($mapCommand);
$application->add($inspectCommand);
$application->add($convertCommand);
$application->add($renameCommand);
$application->add($clearCommand);

$application->run();