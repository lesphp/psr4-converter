<?php

namespace LesPhp\PSR4Converter\Autoloader;

use PhpParser\Parser;

class AutoloaderFactory implements AutoloaderFactoryInterface
{
    public function createAutoloader(Parser $parser): AutoloaderInterface
    {
        return new Autoloader($parser);
    }
}
