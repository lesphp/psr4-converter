<?php

namespace LesPhp\PSR4Converter\Renamer;

use LesPhp\PSR4Converter\Parser\KeywordManager;
use PhpParser\Parser;

class RenamerFactory implements RenamerFactoryInterface
{
    public function createRenamer(Parser $parser): RenamerInterface
    {
        return new Renamer(new KeywordManager(), $parser);
    }
}
