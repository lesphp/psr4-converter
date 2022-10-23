<?php

namespace LesPhp\PSR4Converter\Converter;

use LesPhp\PSR4Converter\Exception\IncompatibleMergeFilesException;
use LesPhp\PSR4Converter\Mapper\Result\MappedFile;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;

interface ConverterInterface
{
    /**
     * @param MappedResult[] $additionalMappedResults
     * @throws  \RuntimeException
     * @throws IncompatibleMergeFilesException
     */
    public function convert(MappedFile $mappedFile, MappedResult $mappedResult, bool $createAliases, array $additionalMappedResults = []): void;
}