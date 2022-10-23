<?php

namespace LesPhp\PSR4Converter\Parser\Naming;

use PhpParser\Node;
use SplObjectStorage;

class NamespacedNameContext
{
    /**
     * @var SplObjectStorage|array<Node\Stmt\Namespace_, CustomNameContext>
     */
    private SplObjectStorage $nameContextByNamespace;

    public function __construct()
    {
        $this->nameContextByNamespace = new SplObjectStorage();
    }

    public function addNameContext(CustomNameContext $nameContext, Node\Stmt\Namespace_ $namespace): void
    {
        $this->nameContextByNamespace[$namespace] = $nameContext;
    }

    public function getNameContextForNamespace(Node\Stmt\Namespace_ $namespace): CustomNameContext
    {
        return $this->nameContextByNamespace[$namespace];
    }
}
