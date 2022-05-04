<?php

namespace LesPhp\PSR4Converter\Exception;

class InvalidHashException extends \Exception
{
    public function __construct(string $filePath)
    {
        parent::__construct(sprintf('Invalid hash for file %s. The file was changed after mapping.', $filePath));
    }
}
