<?php

declare(strict_types=1);

namespace Hn\McpServer\Compatibility\Schema;

/**
 * TYPO3 12 stand-in for TYPO3\CMS\Core\Schema\Field\FieldTypeInterface.
 */
class LegacyFieldType
{
    public function __construct(
        protected string $name,
        protected array $configuration,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return (string)($this->configuration['type'] ?? '');
    }

    /**
     * Flattened configuration: the TCA "config" sub array merged with the column's top-level keys.
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }
}
