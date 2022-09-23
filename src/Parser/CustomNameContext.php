<?php

namespace LesPhp\PSR4Converter\Parser;

use PhpParser\NameContext as PhpParserNameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

class CustomNameContext extends PhpParserNameContext
{
    public function getAliasForName(Name\FullyQualified $name, int $type): ?string
    {
        if (isset($this->origAliases[$type])) {
            foreach ($this->origAliases[$type] as $alias => $contextName) {
                if ($type === Use_::TYPE_CONSTANT && $this->normalizeConstName($name->toString()) === $this->normalizeConstName($contextName->toString())) {
                    return $alias;
                } elseif ($type !== Use_::TYPE_CONSTANT && $name->toLowerString() === $contextName->toLowerString()) {
                    return $alias;
                }
            }
        }

        return null;
    }

    public function removeAlias(string $alias, int $type): void
    {
        foreach ([&$this->origAliases, &$this->aliases] as &$aliasesMap) {
            if (isset($aliasesMap[$type])) {
                foreach ($aliasesMap[$type] as $existingAlias => $contextName) {
                    if (
                        ($type === Use_::TYPE_CONSTANT && $alias === $existingAlias)
                        || ($type !== Use_::TYPE_CONSTANT && strtolower($existingAlias) === strtolower($alias))
                    ) {
                        unset($aliasesMap[$type][$existingAlias]);
                    }
                }
            }
        }
    }

    public function aliasExists(string $alias, int $type): bool
    {
        $lcAlias = strtolower($alias);

        if ($type === Use_::TYPE_CONSTANT) {
            return isset($this->origAliases[$type][$alias]);
        }

        return isset($this->aliases[$type][$lcAlias]);
    }

    private function normalizeConstName(string $name): string
    {
        $nsSep = strrpos($name, '\\');

        if (false === $nsSep) {
            return $name;
        }

        // Constants have case-insensitive namespace and case-sensitive short-name
        $ns = substr($name, 0, $nsSep);
        $shortName = substr($name, $nsSep + 1);

        return strtolower($ns) . '\\' . $shortName;
    }
}
