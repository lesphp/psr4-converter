<?php

namespace LesPhp\PSR4Converter\Autoloader;

use PhpParser\Parser;

class AutoloaderFactory implements AutoloaderFactoryInterface
{
    public function createAutoloader(): AutoloaderInterface
    {
        return new Autoloader();
    }
}
