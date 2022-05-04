<?php

namespace LesPhp\PSR4Converter\Exception;

class InvalidMappedResultHashException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Invalid hash for mapped result file.');
    }
}
