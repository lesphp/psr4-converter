<?php

namespace LesPhp\PSR4Converter\Mapper\Result\Serializer;

use LesPhp\PropertyInfo\TypedArrayAttributeExtractor;
use LesPhp\PSR4Converter\Exception\InvalidMappedResultHashException;
use LesPhp\PSR4Converter\Mapper\Result\MappedResult;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer as SymfonySerializer;

class Serializer implements SerializerInterface
{
    private SymfonySerializer $symfonySerializer;

    private const MAP_FORMAT = JsonEncoder::FORMAT;

    private const SIGNATURE_HASH = 'a87c7a9c82a35358bdd7b84e2d227d8ca24b679e2f5e26405eca4517c1ebf01a';

    public function __construct()
    {
        $this->initSerializer();
    }

    /**
     * @inheritDoc
     */
    public function serialize(MappedResult $mappedResult): string
    {
        $normalizedResult = $this->symfonySerializer->normalize($mappedResult, self::MAP_FORMAT);

        $normalizedResult['signature'] = $this->calculateResultSignature($normalizedResult);

        return $this->symfonySerializer->encode($normalizedResult, self::MAP_FORMAT);
    }

    /**
     * @inheritDoc
     */
    public function deserialize(string $content): MappedResult
    {
        $normalizedResult = $this->symfonySerializer->decode($content, self::MAP_FORMAT);

        $signature = $normalizedResult['signature'];

        unset($normalizedResult['signature']);

        if ($this->calculateResultSignature($normalizedResult) !== $signature) {
            throw new InvalidMappedResultHashException();
        }

        return $this->symfonySerializer->denormalize($normalizedResult, MappedResult::class, self::MAP_FORMAT);
    }

    private function initSerializer(): void
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory);
        $propertyTypeExtractor = new PropertyInfoExtractor([new ReflectionExtractor()],
            [new TypedArrayAttributeExtractor()]);
        $propertyNormalizer = new PropertyNormalizer(
            $classMetadataFactory,
            $metadataAwareNameConverter,
            $propertyTypeExtractor
        );
        $getSetMethodNormalizer = new GetSetMethodNormalizer(
            $classMetadataFactory,
            $metadataAwareNameConverter,
            $propertyTypeExtractor
        );

        $this->symfonySerializer = new SymfonySerializer(
            [$propertyNormalizer, $getSetMethodNormalizer, new ArrayDenormalizer()],
            [new JsonEncoder(new JsonEncode([JsonEncode::OPTIONS => \JSON_PRETTY_PRINT]))]
        );
    }

    private function sortResult(array &$normalizedResult) {
        ksort($normalizedResult);

        foreach ($normalizedResult as &$value) {
            if (is_array($value)) {
                $this->sortResult($value);
            }
        }
    }

    private function calculateResultSignature(array $normalizedResult): string
    {
        $this->sortResult($normalizedResult);

        return hash_hmac(
            'sha256',
            $this->symfonySerializer->encode($normalizedResult, self::MAP_FORMAT),
            self::SIGNATURE_HASH
        );
    }
}