<?php

namespace LesPhp\PSR4Converter\Exception;

use PhpParser\Node;

class InvalidRootStatementException extends \Exception
{
    public function __construct(private readonly Node $stmt, $startToken)
    {
        parent::__construct(
            sprintf(
                "Map error, unexpected %s on line %d",
                token_name($startToken[0]),
                $stmt->getStartLine()
            )
        );
    }

    /**
     * @return Node
     */
    public function getStmt(): Node
    {
        return $this->stmt;
    }
}
