<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

interface StatementDetailsInterface
{
    public function getOriginalFile(): string;

    public function getDetails(): ?string;

    public function getStartLine(): int;

    public function getStartFilePos(): int;

    public function getEndLine(): int;

    public function getEndFilePos(): int;
}