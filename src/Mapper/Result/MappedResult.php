<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

use LesPhp\PropertyInfo\TypedArray;
use LesPhp\PSR4Converter\Exception\MapperConflictException;
use Ramsey\Uuid\Uuid;

class MappedResult
{
    /**
     * @var MappedFile[]
     */
    #[TypedArray(type: MappedFile::class, nullable: false)]
    private array $files = [];

    private string $uuid;

    public function __construct(
        private readonly int $phpParserKind,
        private readonly string $srcPath,
        private readonly string $includesDirPath
    ) {
        $this->uuid = Uuid::uuid4()->toString();
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getPhpParserKind(): int
    {
        return $this->phpParserKind;
    }

    /**
     * @return string
     */
    public function getSrcPath(): string
    {
        return $this->srcPath;
    }

    /**
     * @return string
     */
    public function getIncludesDirPath(): string
    {
        return $this->includesDirPath;
    }

    /**
     * @param MappedFile $mappedFile
     * @return void
     */
    public function addMappedFile(MappedFile $mappedFile): void
    {
        $this->files[] = $mappedFile;
    }

    /**
     * @return MappedFile[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * @return bool
     */
    public function hasInclude(): bool
    {
        foreach ($this->files as $file) {
            if ($file->isHasInclude()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function hasError(): bool
    {
        foreach ($this->files as $file) {
            if ($file->hasError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return MappedError[]
     */
    public function getErrors(): array
    {
        $errors = [];

        foreach ($this->files as $file) {
            $errors = array_merge($errors, $file->getErrors());
        }

        return $errors;
    }

    /**
     * @return bool
     */
    public function hasRisky(): bool
    {
        foreach ($this->files as $file) {
            if ($file->hasRisky()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return MappedUnit[]
     */
    public function getRisky(): array
    {
        $risky = [];

        foreach ($this->files as $file) {
            $risky = array_merge($risky, $file->getRisky());
        }

        return $risky;
    }

    /**
     * @return MappedUnit[]
     */
    public function getNoRisky(): array
    {
        $noRisky = [];

        foreach ($this->files as $file) {
            $noRisky = array_merge($noRisky, $file->getNoRisky());
        }

        return $noRisky;
    }

    /**
     * @return MappedUnit[]
     */
    public function getUnits(): array
    {
        $units = [];

        foreach ($this->files as $file) {
            $units = array_merge($units, $file->getUnits());
        }

        return $units;
    }

    /**
     * @throws MapperConflictException
     */
    public function checkConflictExclusiveUnits(MappedUnit $unit): void
    {
        if ($unit->getNewName() === null) {
            return;
        }

        foreach ($this->files as $otherFile) {
            $otherFile->checkConflictExclusiveUnits($unit, $this->srcPath);
        }
    }
}