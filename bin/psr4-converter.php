<?php

use LesPhp\PSR4Converter\Command\ConvertCommand;
use LesPhp\PSR4Converter\Command\InspectCommand;
use LesPhp\PSR4Converter\Command\MapCommand;
use LesPhp\PSR4Converter\Converter\ConverterFactory;
use LesPhp\PSR4Converter\Mapper\MapperFactory;
use LesPhp\PSR4Converter\Mapper\Result\Serializer\Serializer;
use Symfony\Component\Console\Application;

require __DIR__.'/../vendor/autoload.php';

$application = new Application('PSR-4 Converter', '@package_version@');
$psr4MapperFactory = new MapperFactory();
$psr4ConverterFactory = new ConverterFactory();
$resultSerializer = new Serializer();
$convertCommand = new ConvertCommand($psr4ConverterFactory, $resultSerializer);
$mapCommand = new MapCommand($psr4MapperFactory, $resultSerializer);
$inspectCommand = new InspectCommand($resultSerializer);

$application->add($mapCommand);
$application->add($inspectCommand);
$application->add($convertCommand);

$application->run();