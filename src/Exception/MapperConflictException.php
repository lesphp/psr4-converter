<?php

namespace LesPhp\PSR4Converter\Exception;

use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;

class MapperConflictException extends \Exception
{
    public function __construct(
        private readonly MappedUnit $unit,
        $conflictedUnit
    ) {
        parent::__construct(
            sprintf(
                'Map error, conflict between %s and %s on file %s',
                $unit->getDetails(),
                $conflictedUnit->getDetails(),
                $conflictedUnit->getOriginalFile()
            )
        );
    }

    /**
     * @return MappedUnit
     */
    public function getUnit(): MappedUnit
    {
        return $this->unit;
    }
}
