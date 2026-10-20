<?php

declare(strict_types=1);

namespace Hn\McpServer\Compatibility\Schema;

/**
 * TYPO3 12 stand-in for TYPO3\CMS\Core\Schema\SchemaTypeInformation.
 */
class LegacySchemaTypeInformation
{
    public function __construct(
        protected string $schemaName,
        protected string $fieldName,
        protected ?string $foreignFieldName = null,
    ) {}

    public function getSchemaName(): string
    {
        return $this->schemaName;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function isPointerToForeignFieldInForeignSchema(): bool
    {
        return $this->foreignFieldName !== null;
    }

    public function getPointerFieldInForeignSchema(): ?string
    {
        return $this->foreignFieldName;
    }
}
