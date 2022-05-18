<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

use LesPhp\PropertyInfo\TypedArray;
use LesPhp\PSR4Converter\Exception\MapperConflictException;
use LesPhp\PSR4Converter\Mapper\Mapper;
use Symfony\Component\Serializer\Annotation\Ignore;

class MappedFile
{
    private string $hash;

    /**
     * @var MappedUnit[]
     */
    #[Ignore]
    private array $risky = [];

    /**
     * @var MappedUnit[]
     */
    #[Ignore]
    private array $noRisky = [];

    /**
     * @var MappedUnit[]
     */
    #[TypedArray(type: MappedUnit::class, nullable: false)]
    private array $units = [];

    /**
     * @param MappedUnit[] $units
     * @param MappedError[] $errors
     */
    public function __construct(
        private readonly string $filePath,
        private readonly bool $hasInclude,
        array $units,
        #[Ignore]
        private array $errors = []
    ) {
        $this->hash = Mapper::calculateHash($filePath);

        foreach ($units as $mappedUnit) {
            try {
                $this->checkConflictExclusiveUnits($mappedUnit);

                $this->units[] = $mappedUnit;

                if ($mappedUnit->isRisky()) {
                    $this->risky[] = $mappedUnit;
                } else {
                    $this->noRisky[] = $mappedUnit;
                }
            } catch (MapperConflictException $e) {
                $this->errors[] = MappedError::createForConflict($e);
            }
        }
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

    /**
     * @return MappedUnit[]
     */
    public function getUnits(): array
    {
        return $this->units;
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

    /**
     * @return bool
     */
    public function hasRisky(): bool
    {
        return count($this->risky) > 0;
    }

    /**
     * @return MappedUnit[]
     */
    public function getRisky(): array
    {
        return $this->risky;
    }

    /**
     * @return MappedUnit[]
     */
    public function getNoRisky(): array
    {
        return $this->noRisky;
    }

    /**
     * @throws MapperConflictException
     */
    public function checkConflictExclusiveUnits(MappedUnit $unitForCheck): void
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
                throw new MapperConflictException($unitForCheck, $unit);
            }
        }
    }
}
