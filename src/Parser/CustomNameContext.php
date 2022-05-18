<?php

namespace LesPhp\PSR4Converter\Parser;

use PhpParser\NameContext as PhpParserNameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;

class CustomNameContext extends PhpParserNameContext
{
    public function aliasExistsForName(Name $name, int $type): bool
    {
        return $this->aliasExists($name->getFirst(), $type);
    }

    public function aliasExists(string $alias, int $type): bool
    {
        $lcAlias = strtolower($alias);

        if ($type === Use_::TYPE_FUNCTION) {
            return isset($this->origAliases[$type][$alias]);
        }

        return isset($this->aliases[$type][$lcAlias]);
    }
}
