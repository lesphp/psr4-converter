<?php

namespace LesPhp\PSR4Converter\Exception;

class InvalidHashException extends \Exception
{
    public function __construct(private string $filePath)
    {
        parent::__construct(sprintf('Invalid hash for file %s. The file was changed after mapping.', $this->filePath));
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }
}