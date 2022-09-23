<?php

namespace LesPhp\PSR4Converter\Autoloader;

use PhpParser\Parser;

interface AutoloaderFactoryInterface
{
    public function createAutoloader(): AutoloaderInterface;
}
