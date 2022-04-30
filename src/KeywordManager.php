<?php

namespace LesPhp\PSR4Converter;

class KeywordManager
{
    /**
     * @see https://www.php.net/manual/en/reserved.php
     */
    private const RESERVED_KEYWORDS = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable',
        'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default',
        'die', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'false',
        'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function', 'global',
        'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'int',
        'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never',
        'new', 'null', 'numeric', 'object', 'or', 'print', 'private', 'protected',
        'public', 'readonly', 'require', 'require_once', 'resource', 'return', 'static', 'string',
        'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var',
        'void', 'while','xor', 'yield', 'yield from',
    ];

    private const RESERVED_CONSTANTS = [
        'true',
        'false',
        'null',
    ];

    private const VALID_NAME_REGEX = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    public function isValidNamespace(string $namespace): bool
    {
        $invalidParts = array_filter(
            explode('\\', $namespace),
            fn($part) => !$this->isValidNamespaceComponent($part)
        );

        return count($invalidParts) == 0;
    }

    public function isValidNamespaceComponent(string $name): bool
    {
        return !$this->isReservedKeyword($name) && preg_match(self::VALID_NAME_REGEX, $name);
    }

    public function isReservedKeyword(string $name): bool
    {
        return in_array(strtolower($name), self::RESERVED_KEYWORDS);
    }

    public function isSpecialConstants(string $name): bool
    {
        return in_array(strtolower($name), self::RESERVED_CONSTANTS);
    }

    /**
     * @param string $namespace
     * @param string $suffix Used when $namespace is a reserved keyword
     * @return string
     */
    public function sanitizeNamespace(string $namespace, string $suffix): string
    {
        $parts = array_map(
            fn($part) => $this->sanitizeNameWithSuffix($part, $suffix),
            explode('\\', $namespace)
        );

        return implode('\\', $parts);
    }

    /**
     * @param string $name
     * @param string $suffix Used when $name is a reserved keyword
     * @return string
     */
    public function sanitizeNameWithSuffix(string $name, string $suffix): string
    {
        return $this->isReservedKeyword($name) ? $name.$suffix : $name;
    }

    /**
     * @param string $name
     * @param string $prefix Used when $name is a reserved keyword
     * @return string
     */
    public function sanitizeNameWithPrefix(string $name, string $prefix): string
    {
        return $this->isReservedKeyword($name) ? $prefix.$name : $name;
    }
}