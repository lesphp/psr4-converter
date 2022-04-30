<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

use LesPhp\PSR4Converter\Exception\MapperConflictException;

class MappedError implements StatementDetailsInterface
{
    public function __construct(
        private readonly string $originalFile,
        private readonly int $startLine,
        private readonly int $startFilePos,
        private readonly int $endLine,
        private readonly int $endFilePos,
        private readonly string $errorMessage
    ) {
    }

    public static function createForConflict(MapperConflictException $e): self
    {
        $mappedUnit = $e->getUnit();

        return new self(
            $mappedUnit->getOriginalFile(),
            $mappedUnit->getStartLine(),
            $mappedUnit->getStartFilePos(),
            $mappedUnit->getEndLine(),
            $mappedUnit->getEndFilePos(),
            $e->getMessage()
        );
    }

    /**
     * @return string
     */
    public function getOriginalFile(): string
    {
        return $this->originalFile;
    }

    /**
     * @return int
     */
    public function getStartLine(): int
    {
        return $this->startLine;
    }

    /**
     * @return int
     */
    public function getStartFilePos(): int
    {
        return $this->startFilePos;
    }

    /**
     * @return int
     */
    public function getEndLine(): int
    {
        return $this->endLine;
    }

    /**
     * @return int
     */
    public function getEndFilePos(): int
    {
        return $this->endFilePos;
    }

    public function getDetails(): ?string
    {
        return $this->getErrorMessage();
    }

    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}