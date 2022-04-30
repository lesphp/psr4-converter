<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

use LesPhp\PropertyInfo\TypedArray;
use LesPhp\PSR4Converter\Exception\MapperConflictException;
use LesPhp\PSR4Converter\Mapper\Mapper;
use Symfony\Component\Serializer\Annotation\Ignore;

class MappedFile
{
    private string $hash;

    private bool $hasInclude;

    /**
     * @var MappedUnit[]
     */
    #[TypedArray(type: MappedUnit::class, nullable: false)]
    private array $units = [];

    /**
     * @var MappedError[]
     */
    #[Ignore]
    private array $errors = [];

    public function __construct(private readonly string $filePath)
    {
        $this->hasInclude = false;
        $this->hash = Mapper::calculateHash($filePath);
    }

    /**
     * @return string
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
    }

    public function isHasInclude(): bool
    {
        return $this->hasInclude;
    }

    public function setHasInclude(bool $hasInclude): void
    {
        $this->hasInclude = $hasInclude;
    }

    /**
     * @return MappedUnit[]
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    /**
     * @throws MapperConflictException
     */
    public function addMappedUnit(MappedUnit $unit): void
    {
        $this->units[] = $unit;
    }

    public function removeMappedUnit(MappedUnit $unitToRemove): void
    {
        foreach ($this->units as $i => $unit) {
            if ($unit === $unitToRemove) {
                unset($this->units[$i]);
            }
        }
    }

    /**
     * @return MappedError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasError(): bool
    {
        return count($this->errors) > 0;
    }

    public function addError(MappedError $error): void
    {
        $this->errors[] = $error;
    }

    /**
     * @return bool
     */
    public function hasRisky(): bool
    {
        foreach ($this->units as $unit) {
            if ($unit->isRisky()) {
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

        foreach ($this->units as $unit) {
            if ($unit->isRisky()) {
                $risky[] = $unit;
            }
        }

        return $risky;
    }

    /**
     * @return MappedUnit[]
     */
    public function getNoRisky(): array
    {
        $noRisky = [];

        foreach ($this->units as $unit) {
            if (!$unit->isRisky()) {
                $noRisky[] = $unit;
            }
        }

        return $noRisky;
    }

    /**
     * @throws MapperConflictException
     */
    public function checkConflictExclusiveUnits(MappedUnit $unitForCheck, string $sourcePath): void
    {
        if ($unitForCheck->getNewName() === null) {
            return;
        }

        $originalNames = (array)$unitForCheck->getNewName();

        foreach ($this->units as $unit) {
            if ($unit->getNewName() === null) {
                return;
            }

            $newNames = (array)$unit->getNewName();

            if (
                $unit->getNewNamespace() === $unitForCheck->getNewNamespace()
                && count(array_intersect($originalNames, $newNames)) > 0
            ) {
                throw new MapperConflictException($unitForCheck, $unit, $sourcePath);
            }
        }
    }
}