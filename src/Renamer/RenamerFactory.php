<?php

namespace LesPhp\PSR4Converter\Renamer;

use PhpParser\Parser;

class RenamerFactory implements RenamerFactoryInterface
{
    public function createRenamer(Parser $parser): RenamerInterface
    {
        return new Renamer($parser);
    }
}
