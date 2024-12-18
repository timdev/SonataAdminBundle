<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\AdminBundle\Form;

use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\DataTransformer\BackedEnumTransformer;
use Sonata\AdminBundle\Form\DataTransformer\ModelToIdTransformer;
use Sonata\AdminBundle\Model\ModelManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;

/**
 * @author Peter Gribanov <info@peter-gribanov.ru>
 */
final class DataTransformerResolver implements DataTransformerResolverInterface
{
    /**
     * @phpstan-var array<string, DataTransformerInterface<mixed, mixed>>
     * @psalm-var array<string, DataTransformerInterface>
     */
    private array $globalCustomTransformers = [];

    /**
     * @phpstan-param array<string, DataTransformerInterface<mixed, mixed>> $customGlobalTransformers
     * @psalm-param array<string, DataTransformerInterface> $customGlobalTransformers
     */
    public function __construct(array $customGlobalTransformers = [])
    {
        foreach ($customGlobalTransformers as $fieldType => $dataTransformer) {
            $this->addCustomGlobalTransformer($fieldType, $dataTransformer);
        }
    }

    public function addCustomGlobalTransformer(string $fieldType, DataTransformerInterface $dataTransformer): void
    {
        $this->globalCustomTransformers[$fieldType] = $dataTransformer;
    }

    public function resolve(
        FieldDescriptionInterface $fieldDescription,
        ModelManagerInterface $modelManager,
    ): ?DataTransformerInterface {
        $dataTransformer = $fieldDescription->getOption('data_transformer');

        // allow override predefined transformers for 'date' and 'choice' field types
        if ($dataTransformer instanceof DataTransformerInterface) {
            return $dataTransformer;
        }

        $fieldType = (string) $fieldDescription->getType();

        // allow override predefined transformers on a global level
        if (\array_key_exists($fieldType, $this->globalCustomTransformers)) {
            return $this->globalCustomTransformers[$fieldType];
        }

        // Handle date type has setter expect a DateTime object
        if (FieldDescriptionInterface::TYPE_DATE === $fieldType) {
            $this->globalCustomTransformers[$fieldType] = new DateTimeToStringTransformer(
                null,
                $this->getOutputTimezone($fieldDescription),
                'Y-m-d'
            );

            return $this->globalCustomTransformers[$fieldType];
        }

        if (FieldDescriptionInterface::TYPE_ENUM === $fieldType) {
            $className = $fieldDescription->getOption('class');

            if (
                null !== $className
                && \is_string($className)
                && is_a($className, \BackedEnum::class, true)
            ) {
                return new BackedEnumTransformer($className);
            }
        }

        // Handle entity choice association type, transforming the value into entity
        if (FieldDescriptionInterface::TYPE_CHOICE === $fieldType) {
            $targetModel = $fieldDescription->getTargetModel();
            $className = $fieldDescription->getOption('class');

            if (
                null !== $targetModel
                && \is_string($className)
                && class_exists($className)
                && is_a($targetModel, $className, true)
            ) {
                return new ModelToIdTransformer($modelManager, $className);
            }
        }

        return null;
    }

    private function getOutputTimezone(FieldDescriptionInterface $fieldDescription): ?string
    {
        $outputTimezone = $fieldDescription->getOption('timezone');

        if ($outputTimezone instanceof \DateTimeZone) {
            return $outputTimezone->getName();
        }

        if (!\is_string($outputTimezone)) {
            return null;
        }

        return $outputTimezone;
    }
}
