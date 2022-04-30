<?php

namespace LesPhp\PSR4Converter\Exception;

use PhpParser\Node;

class InvalidRootStatementException extends \Exception
{

    public function __construct(private string $filePath, private Node $stmt, private array $startToken)
    {
        parent::__construct(
            sprintf(
                "Map error, unexpected %s on line %d",
                token_name($startToken[0]),
                $stmt->getStartLine()
            )
        );
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * @return Node
     */
    public function getStmt(): Node
    {
        return $this->stmt;
    }

    public function getStartToken(): array
    {
        return $this->startToken;
    }
}