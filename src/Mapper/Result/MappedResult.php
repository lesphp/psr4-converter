<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

use LesPhp\PropertyInfo\TypedArray;
use LesPhp\PSR4Converter\Exception\MapperConflictException;
use LesPhp\PSR4Converter\Parser\Naming\NameHelper;
use Symfony\Component\Serializer\Annotation\Ignore;
use PhpParser\Node;

class MappedResult
{
    /**
     * @var MappedFile[]
     */
    #[TypedArray(type: MappedFile::class, nullable: false)]
    private array $files = [];

    /**
     * @var MappedError[]
     */
    #[Ignore]
    private array $errors = [];

    /**
     * @param MappedFile[] $files
     */
    public function __construct(
        private readonly int $phpParserKind,
        private readonly string $srcPath,
        private readonly string $includesDirPath,
        array $files
    ) {
        foreach ($files as $mappedFile) {
            foreach ($mappedFile->getUnits() as $mappedUnit) {
                try {
                    $this->checkConflictExclusiveUnits($mappedUnit);
                } catch (MapperConflictException $e) {
                    $this->errors[] = MappedError::createForConflict($e);
                }
            }

            $this->files[] = $mappedFile;
        }
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
        if (count($this->errors) > 0) {
            return true;
        }

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
        $errors = $this->errors;

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
     * @return array<int, array<string, string>>
     */
    public function getConvertedNamesMap(NameHelper $nameHelper): array
    {
        $convertedNamesMap = [
            Node\Stmt\Use_::TYPE_NORMAL => [],
            Node\Stmt\Use_::TYPE_FUNCTION => [],
            Node\Stmt\Use_::TYPE_CONSTANT => [],
        ];

        foreach ($this->getUnits() as $mappedUnit) {
            if ($mappedUnit->isCompound()) {
                $types = array_map(
                    fn (string $componentStmtClass) => $this->getUseTypeByStmtClass($componentStmtClass),
                    $mappedUnit->getComponentStmtClasses()
                );
                $originalFullQualifiedNames = $mappedUnit->getOriginalFullQualifiedName();
                $newFullQualifiedNames = $mappedUnit->getNewFullQualifiedName();
            } else {
                $types = (array)$this->getUseTypeByStmtClass($mappedUnit->getStmtClass());
                $originalFullQualifiedNames = (array)$mappedUnit->getOriginalFullQualifiedName();
                $newFullQualifiedNames = (array)$mappedUnit->getNewFullQualifiedName();
            }

            array_walk(
                $types,
                function ($type, $i) use (&$convertedNamesMap, $newFullQualifiedNames, $originalFullQualifiedNames, $nameHelper) {
                    if ($type === Node\Stmt\Use_::TYPE_UNKNOWN) {
                        return;
                    }

                    $convertedNamesMap[$type][$newFullQualifiedNames[$i]] = $nameHelper->lookupNameByType($originalFullQualifiedNames[$i], $type);
                }
            );
        }

        return $convertedNamesMap;
    }

    /**
     * @param MappedResult[] $mappedResults
     * @return array<int, array<string, string>>
     */
    public function mergeConvertedNamesMap(array $mappedResults, NameHelper $nameHelper): array
    {
        $mergedConvertedNamesMap = $this->getConvertedNamesMap($nameHelper);
        $types = [Node\Stmt\Use_::TYPE_NORMAL, Node\Stmt\Use_::TYPE_FUNCTION, Node\Stmt\Use_::TYPE_CONSTANT];

        foreach ($mappedResults as $mappedResult) {
            $otherConvertedNamesMap = $mappedResult->getConvertedNamesMap($nameHelper);

            foreach ($types as $type) {
                $mergedConvertedNamesMap[$type] = array_merge($mergedConvertedNamesMap[$type], $otherConvertedNamesMap[$type]);
            }
        }

        return $mergedConvertedNamesMap;
    }

    /**
     * @throws MapperConflictException
     */
    private function checkConflictExclusiveUnits(MappedUnit $unit): void
    {
        if ($unit->getNewName() === null) {
            return;
        }

        foreach ($this->files as $file) {
            $file->checkConflictExclusiveUnits($unit);
        }
    }

    private function getUseTypeByStmtClass(string $stmtClass): int
    {
        if (is_a($stmtClass, Node\Stmt\Function_::class, true)) {
            $type = Node\Stmt\Use_::TYPE_FUNCTION;
        } elseif (is_a($stmtClass, Node\Const_::class, true)) {
            $type = Node\Stmt\Use_::TYPE_CONSTANT;
        } elseif (is_a($stmtClass, Node\Stmt\If_::class, true)) {
            $type = Node\Stmt\Use_::TYPE_UNKNOWN;
        } else {
            $type = Node\Stmt\Use_::TYPE_NORMAL;
        }

        return $type;
    }
}