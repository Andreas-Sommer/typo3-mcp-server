<?php

declare(strict_types=1);

namespace Hn\McpServer\Compatibility\Schema;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TYPO3 12 stand-in for TYPO3\CMS\Core\Schema\TcaSchema.
 *
 * Field resolution mirrors TcaSchemaFactory::build() of TYPO3 13.4: sub schemas
 * contain the fields referenced in "showitem" (palettes unpacked), with
 * "columnsOverrides" and showitem label overrides applied, and the "config"
 * sub array flattened into the field configuration.
 */
class LegacyTcaSchema
{
    /**
     * Field types known to the TYPO3 13 FieldTypeFactory. Columns of any other
     * type are skipped there, so they are skipped here as well.
     */
    private const KNOWN_FIELD_TYPES = [
        'category', 'check', 'color', 'datetime', 'email', 'file', 'flex', 'folder', 'group',
        'imageManipulation', 'inline', 'input', 'json', 'language', 'link', 'none', 'number',
        'passthrough', 'password', 'radio', 'select', 'slug', 'text', 'user', 'uuid',
    ];

    /**
     * @param array<string, LegacyFieldType> $fields
     * @param array<string, LegacyTcaSchema> $subSchemata
     */
    public function __construct(
        protected string $name,
        protected array $fields,
        protected array $schemaConfiguration,
        protected array $subSchemata = [],
    ) {}

    public static function fromTca(string $schemaName, array $tca): self
    {
        $allFields = [];
        foreach ($tca['columns'] ?? [] as $fieldName => $fieldConfiguration) {
            $field = self::createField((string)$fieldName, $fieldConfiguration);
            if ($field !== null) {
                $allFields[$fieldName] = $field;
            }
        }

        $schemaConfiguration = $tca['ctrl'] ?? [];
        if (is_array($tca['palettes'] ?? null)) {
            $schemaConfiguration['palettes'] = $tca['palettes'];
        }

        $subSchemata = [];
        if (isset($tca['ctrl']['type'])) {
            foreach ($tca['types'] ?? [] as $subSchemaName => $subSchemaDefinition) {
                $subSchemaName = (string)$subSchemaName;
                $subSchemaFields = [];
                foreach (self::findRelevantFieldsForSubSchema($tca, $subSchemaName) as $fieldName => $fieldConfiguration) {
                    $field = self::createField($fieldName, $fieldConfiguration);
                    if ($field !== null) {
                        $subSchemaFields[$fieldName] = $field;
                    }
                }
                $subSchemata[$subSchemaName] = new self(
                    $schemaName . '.' . $subSchemaName,
                    $subSchemaFields,
                    array_replace_recursive($schemaConfiguration, $subSchemaDefinition)
                );
            }
        }

        return new self($schemaName, $allFields, $schemaConfiguration, $subSchemata);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, LegacyFieldType>
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function hasField(string $fieldName): bool
    {
        return isset($this->fields[$fieldName]);
    }

    public function getRawConfiguration(): array
    {
        return $this->schemaConfiguration;
    }

    public function isWorkspaceAware(): bool
    {
        return (bool)($this->schemaConfiguration['versioningWS'] ?? false);
    }

    public function isLanguageAware(): bool
    {
        return isset($this->schemaConfiguration['languageField'], $this->schemaConfiguration['transOrigPointerField']);
    }

    public function hasSubSchema(string $subSchema): bool
    {
        return isset($this->subSchemata[$subSchema]);
    }

    public function getSubSchema(string $subSchema): self
    {
        if (!$this->hasSubSchema($subSchema)) {
            throw new \InvalidArgumentException('No sub schema "' . $subSchema . '" in schema "' . $this->name . '".', 1761000002);
        }
        return $this->subSchemata[$subSchema];
    }

    /**
     * @return array<string, LegacyTcaSchema>
     */
    public function getSubSchemata(): array
    {
        return $this->subSchemata;
    }

    public function supportsSubSchema(): bool
    {
        return isset($this->schemaConfiguration['type']);
    }

    public function getSubSchemaTypeInformation(): LegacySchemaTypeInformation
    {
        $typeInformation = $this->schemaConfiguration['type'] ?? null;
        if (!is_string($typeInformation) || $typeInformation === '') {
            throw new \RuntimeException('The schema "' . $this->name . '" has no type information.', 1761000003);
        }
        if (str_contains($typeInformation, ':')) {
            [$localField, $foreignField] = explode(':', $typeInformation, 2);
            return new LegacySchemaTypeInformation($this->name, $localField, $foreignField);
        }
        return new LegacySchemaTypeInformation($this->name, $typeInformation);
    }

    protected static function createField(string $fieldName, array $fieldConfiguration): ?LegacyFieldType
    {
        if (!in_array($fieldConfiguration['config']['type'] ?? '', self::KNOWN_FIELD_TYPES, true)) {
            return null;
        }
        // Same flattening as FieldTypeFactory::streamlineFieldConfiguration() in TYPO3 13
        $config = $fieldConfiguration['config'] ?? [];
        unset($fieldConfiguration['config']);
        return new LegacyFieldType($fieldName, array_replace_recursive($config, $fieldConfiguration));
    }

    /**
     * Port of TcaSchemaFactory::findRelevantFieldsForSubSchema() from TYPO3 13.4.
     *
     * @return array<string, array> Raw TCA column configuration keyed by field name
     */
    protected static function findRelevantFieldsForSubSchema(array $tca, string $subSchemaName): array
    {
        $fields = [];
        $subSchemaConfig = $tca['types'][$subSchemaName];
        foreach (GeneralUtility::trimExplode(',', (string)($subSchemaConfig['showitem'] ?? '')) as $showItem) {
            [$fieldName, $fieldLabel, $paletteName] = GeneralUtility::trimExplode(';', $showItem . ';;;');
            if ($fieldName === '--div--') {
                continue;
            }
            if ($fieldName === '--palette--' && !empty($paletteName)) {
                if (!isset($tca['palettes'][$paletteName]['showitem'])) {
                    continue;
                }
                foreach (GeneralUtility::trimExplode(',', (string)$tca['palettes'][$paletteName]['showitem']) as $paletteItem) {
                    [$paletteFieldName, $paletteFieldLabel] = GeneralUtility::trimExplode(';', $paletteItem . ';;');
                    if (isset($tca['columns'][$paletteFieldName])) {
                        $fields[$paletteFieldName] = self::getFinalFieldConfiguration($paletteFieldName, $tca, $subSchemaConfig, $paletteFieldLabel);
                    }
                }
            } elseif (isset($tca['columns'][$fieldName])) {
                $fields[$fieldName] = self::getFinalFieldConfiguration($fieldName, $tca, $subSchemaConfig, $fieldLabel);
            }
        }
        return $fields;
    }

    protected static function getFinalFieldConfiguration(string $fieldName, array $tca, array $subSchemaConfig, ?string $fieldLabel): array
    {
        $fieldConfiguration = $tca['columns'][$fieldName] ?? [];
        if (isset($subSchemaConfig['columnsOverrides'][$fieldName])) {
            $fieldConfiguration = array_replace_recursive($fieldConfiguration, $subSchemaConfig['columnsOverrides'][$fieldName]);
        }
        if (!empty($fieldLabel)) {
            $fieldConfiguration['label'] = $fieldLabel;
        }
        return $fieldConfiguration;
    }
}
