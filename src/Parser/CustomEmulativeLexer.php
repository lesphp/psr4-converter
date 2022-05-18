<?php

namespace LesPhp\PSR4Converter\Parser;

use PhpParser\Lexer\Emulative;
use PhpParser\Parser\Tokens;

class CustomEmulativeLexer extends Emulative
{
    public function __construct(array $options = [])
    {
        // force usedAttributes option
        $options = array_merge($options, [
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startTokenPos',
                'endTokenPos',
                'startFilePos',
                'endFilePos',
            ],
        ]);

        parent::__construct($options);
    }

    /**
     * @inheritDoc
     */
    public function getNextToken(&$value = null, &$startAttributes = null, &$endAttributes = null): int
    {
        $tokenId = parent::getNextToken($value, $startAttributes, $endAttributes);

        if ($tokenId == Tokens::T_CONSTANT_ENCAPSED_STRING   // non-interpolated string
            || $tokenId == Tokens::T_ENCAPSED_AND_WHITESPACE // interpolated string
            || $tokenId == Tokens::T_LNUMBER                 // integer
            || $tokenId == Tokens::T_DNUMBER                 // floating point number
        ) {
            $endAttributes['originalValue'] = $value;
        }

        return $tokenId;
    }
}
