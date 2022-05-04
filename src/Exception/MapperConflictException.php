<?php

namespace LesPhp\PSR4Converter\Exception;

use LesPhp\PSR4Converter\Mapper\Result\MappedUnit;
use Symfony\Component\Filesystem\Filesystem;

class MapperConflictException extends \Exception
{
    public function __construct(
        private readonly MappedUnit $unit,
        $conflictedUnit,
        private readonly string $sourcePath
    ) {
        $filesystem = new Filesystem();

        parent::__construct(
            sprintf(
                'Map error, conflict between %s and %s on file %s',
                $unit->getDetails(),
                $conflictedUnit->getDetails(),
                $filesystem->makePathRelative(dirname($conflictedUnit->getOriginalFile()), $this->sourcePath).basename(
                    $conflictedUnit->getOriginalFile()
                )
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
