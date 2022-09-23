<?php

namespace LesPhp\PSR4Converter\Renamer;

use PhpParser\Parser;

interface RenamerFactoryInterface
{
    public function createRenamer(Parser $parser): RenamerInterface;
}
