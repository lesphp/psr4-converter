<?php

namespace LesPhp\PSR4Converter\Converter\Doc\Visitor;

class ReplaceNameWithImportVisitor extends AbstractReplaceNameVisitor
{
    protected function replaceName(string $name, int $type): string
    {
        if (!$this->isFullyQualifiedName($name)) {
            return $name;
        }

        $newName = $this->nameContext->getShortName(substr($name, strlen('\\')), $type);

        return $newName->toCodeString();
    }
}
