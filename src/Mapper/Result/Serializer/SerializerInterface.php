<?php

namespace LesPhp\PSR4Converter\Mapper\Result\Serializer;

use LesPhp\PSR4Converter\Exception\InvalidMappedResultHashException;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;

interface SerializerInterface
{
    public function serialize(MappedResult $mappedResult): string;

    /**
     * @throws InvalidMappedResultHashException
     */
    public function deserialize(string $content): MappedResult;
}