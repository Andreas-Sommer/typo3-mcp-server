<?php

declare(strict_types=1);

namespace Hn\McpServer\Compatibility\Schema;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * TYPO3 12 stand-in for TYPO3\CMS\Core\Schema\TcaSchemaFactory (TYPO3 13+).
 *
 * Implements only has() and get(), the subset used by this extension. Schemas
 * are built lazily from $GLOBALS['TCA'] on every call, so runtime TCA changes
 * are always visible.
 */
class LegacyTcaSchemaFactory implements SingletonInterface
{
    public function has(string $schemaName): bool
    {
        if (str_contains($schemaName, '.')) {
            [$mainSchema, $subSchema] = explode('.', $schemaName, 2);
            return $this->has($mainSchema) && $this->get($mainSchema)->hasSubSchema($subSchema);
        }
        return is_array($GLOBALS['TCA'][$schemaName] ?? null);
    }

    public function get(string $schemaName): LegacyTcaSchema
    {
        if (!$this->has($schemaName)) {
            throw new \InvalidArgumentException('No TCA schema exists for the name "' . $schemaName . '".', 1761000001);
        }
        if (str_contains($schemaName, '.')) {
            [$mainSchema, $subSchema] = explode('.', $schemaName, 2);
            return $this->get($mainSchema)->getSubSchema($subSchema);
        }
        return LegacyTcaSchema::fromTca($schemaName, $GLOBALS['TCA'][$schemaName]);
    }
}
