<?php

namespace LesPhp\PSR4Converter\Mapper\Result;

class MappedUnit implements StatementDetailsInterface
{
    /**
     * @var string|array
     */
    private readonly string|array $originalName;

    /**
     * @var string|array
     */
    private readonly string|array $newName;


    /**
     * @var array|null
     */
    private readonly ?array $componentStmtClasses;

    /**
     * @param string|string[] $originalName
     * @param string|string[] $newName
     * @param null|string[] $componentStmtClasses
     * @throws \RuntimeException
     */
    public function __construct(
        private readonly string $originalFile,
        private readonly int $startLine,
        private readonly int $startFilePos,
        private readonly int $endLine,
        private readonly int $endFilePos,
        private readonly int $startTokenPos,
        private readonly int $endTokenPos,
        private readonly ?int $namespaceStartTokenPos,
        private readonly ?int $namespaceEndTokenPos,
        private readonly ?string $originalNamespace,
        string|array $originalName,
        private readonly ?string $newNamespace,
        string|array $newName,
        private readonly string $targetFile,
        private readonly string $targetFileWithoutVendor,
        private readonly string $stmtClass,
        private readonly bool $exclusive,
        private readonly bool $risky,
        private readonly ?string $details,
        ?array $componentStmtClasses = null
    ) {
        $validNames = (is_string($originalName) && is_string($newName) && $componentStmtClasses === null)
            || (
                is_array($originalName) && is_array($newName) && is_array($componentStmtClasses)
                && count(array_unique([count($originalName), count($newName), count($componentStmtClasses)])) === 1
            );

        if (!$validNames) {
            throw new \RuntimeException("Names and component's classes aren't valid");
        }

        $this->originalName = $originalName;
        $this->newName = $newName;
        $this->componentStmtClasses = $componentStmtClasses;
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

    /**
     * @return string
     */
    public function getOriginalFullQualifiedName(): array|string
    {
        $originalNames = is_array($this->originalName) ? $this->originalName : [$this->originalName];
        $fullOriginalNames = array_map(
            fn($originalName) => ltrim(
                $this->originalNamespace.($originalName !== null ? '\\'.$originalName : ''),
                '\\'
            ),
            $originalNames
        );

        return is_array($this->originalName) ? $fullOriginalNames : current($fullOriginalNames);
    }

    /**
     * @return string|null
     */
    public function getNewNamespace(): ?string
    {
        return $this->newNamespace;
    }

    /**
     * @return string|string[]
     */
    public function getNewName(): array|string
    {
        return $this->newName;
    }

    /**
     * @return string
     */
    public function getNewFullQualifiedName(): array|string
    {
        $newNames = is_array($this->newName) ? $this->newName : [$this->newName];
        $fullNewNames = array_map(
            fn($newName) => ltrim($this->newNamespace.($newName !== null ? '\\'.$newName : ''), '\\'),
            $newNames
        );

        return is_array($this->newName) ? $fullNewNames : current($fullNewNames);
    }

    /**
     * @return string
     */
    public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    /**
     * @return string
     */
    public function getTargetFileWithoutVendor(): string
    {
        return $this->targetFileWithoutVendor;
    }

    /**
     * @return string
     */
    public function getStmtClass(): string
    {
        return $this->stmtClass;
    }

    /**
     * @return bool
     */
    public function isExclusive(): bool
    {
        return $this->exclusive;
    }

    /**
     * @return bool
     */
    public function isRisky(): bool
    {
        return $this->risky;
    }

    /**
     * @return int
     */
    public function getStartTokenPos(): int
    {
        return $this->startTokenPos;
    }

    /**
     * @return int
     */
    public function getEndTokenPos(): int
    {
        return $this->endTokenPos;
    }

    public function getNamespaceStartTokenPos(): ?int
    {
        return $this->namespaceStartTokenPos;
    }

    /**
     * @return int|null
     */
    public function getNamespaceEndTokenPos(): ?int
    {
        return $this->namespaceEndTokenPos;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function isCompound(): bool
    {
        return is_array($this->componentStmtClasses);
    }

    /**
     * @return null|string[]
     */
    public function getComponentStmtClasses(): ?array
    {
        return $this->componentStmtClasses;
    }
}