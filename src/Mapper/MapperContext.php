<?php

namespace LesPhp\PSR4Converter\Mapper;

class MapperContext
{

    /**
     * @param string[] $ignoreNamespaces
     */
    public function __construct(
        private string $rootSourcePath,
        private string $includesDirPath,
        private ?string $prefixNamespace,
        private bool $appendNamespace,
        private bool $underscoreConversion,
        private bool $ignoreNamespacedUnderscoreConversion,
        private array $ignoreNamespaces,
        private string $uuid
    ) {
    }

    public function getRootSourcePath(): string
    {
        return $this->rootSourcePath;
    }

    public function getPrefixNamespace(): ?string
    {
        return $this->prefixNamespace;
    }

    public function getIncludesDirPath(): string
    {
        return $this->includesDirPath;
    }

    public function isAppendNamespace(): bool
    {
        return $this->appendNamespace;
    }

    public function isUnderscoreConversion(): bool
    {
        return $this->underscoreConversion;
    }

    public function isIgnoreNamespacedUnderscoreConversion(): bool
    {
        return $this->ignoreNamespacedUnderscoreConversion;
    }

    /**
     * @return string[]
     */
    public function getIgnoreNamespaces(): array
    {
        return $this->ignoreNamespaces;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }
}