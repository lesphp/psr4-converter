<?php

namespace LesPhp\PSR4Converter\Autoloader;

use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use PhpParser\Node;
use PhpParser\Parser;
use PhpParser\PrettyPrinter;
use Symfony\Component\Filesystem\Filesystem;

class Autoloader implements AutoloaderInterface
{
    public function __construct(private readonly Parser $parser)
    {
    }

    /**
     * @inheritDoc
     */
    public function generate(MappedResult $mappedResult, string $filePath): void
    {
        $aliasMap = [];

        foreach ($mappedResult->getFiles() as $mappedFile) {
            foreach ($mappedFile->getUnits() as $mappedUnit) {
                if ($mappedUnit->isCompound()) {
                    $componentStmtClasses = $mappedUnit->getComponentStmtClasses();
                    $originalFullQualifiedNames = $mappedUnit->getOriginalFullQualifiedName();
                    $newFullQualifiedNames = $mappedUnit->getNewFullQualifiedName();
                } else {
                    $componentStmtClasses = (array)$mappedUnit->getStmtClass();
                    $originalFullQualifiedNames = (array)$mappedUnit->getOriginalFullQualifiedName();
                    $newFullQualifiedNames = (array)$mappedUnit->getNewFullQualifiedName();
                }


                foreach ($componentStmtClasses as $i => $componentStmtClass) {
                    if (!$this->isAllowAlias(
                            $componentStmtClass
                        ) || $newFullQualifiedNames[$i] === $originalFullQualifiedNames[$i]) {
                        continue;
                    }

                    $aliasMap[$originalFullQualifiedNames[$i]] = $newFullQualifiedNames[$i];
                }
            }
        }

        $this->dumpAutoloadFile($aliasMap, $filePath);
    }

    private function isAllowAlias(string $stmtClass): bool
    {
        return is_a($stmtClass, Node\Stmt\Class_::class, true)
            || is_a($stmtClass, Node\Stmt\Interface_::class, true)
            || is_a($stmtClass, Node\Stmt\Trait_::class, true)
            || is_a($stmtClass, Node\Stmt\Enum_::class, true);
    }

    /**
     * @param Node[] $stmts
     */
    private function dumpAutoloadFile(array $aliasMap, string $filePath): void
    {
        $filesystem = new Filesystem();
        $prettyPrinter = new PrettyPrinter\Standard();

        $autoloadContentTemplate = <<<'EOF'
        <?php

        namespace LesPhp\PSR4Converter\Autoloader;

        if (!class_exists(ConvertedNames::class, false)) {
            class ConvertedNames {
                private static array $convertedNames = [];

                /**
                 * @param array $map Key is the old name and value is the new name
                 * @return void
                 */
                public static function addConvertedNames(array $map)
                {
                    static::$convertedNames = array_unique(array_merge(static::$convertedNames, $map));
                }

                /**
                 * @return array Key is the old name and value is the new name
                 */
                public static function getConvertedNames(): array
                {
                    return static::$convertedNames;
                }
            }

            spl_autoload_register(function ($class) {
                $aliasMap = ConvertedNames::getConvertedNames();

                if (isset($aliasMap[$class]) && class_exists($aliasMap[$class])) {
                    return true;
                }

                return null;
            });
        }

        ConvertedNames::addConvertedNames(%s);
        EOF;

        $stmts = $this->parser->parse(sprintf($autoloadContentTemplate, var_export($aliasMap, true)));

        $filesystem->mkdir(dirname($filePath));

        $filesystem->dumpFile($filePath, $prettyPrinter->prettyPrintFile($stmts));
    }
}
