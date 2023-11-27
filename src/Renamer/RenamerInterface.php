<?php

namespace LesPhp\PSR4Converter\Renamer;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use Symfony\Component\Finder\SplFileInfo;

interface RenamerInterface
{
    public function rename(MappedResult $mappedResult, SplFileInfo $refactoringFile): void;
}