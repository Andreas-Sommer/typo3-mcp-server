<?php

declare(strict_types=1);

namespace Hn\McpServer\Compatibility;

use Hn\McpServer\Compatibility\Schema\LegacyTcaSchemaFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Single place for every API difference between TYPO3 12 and TYPO3 13/14.
 *
 * Upstream code only targets TYPO3 13+. The TYPO3 12 backport routes each
 * call that differs through this class, so the rest of the code base stays
 * as close to upstream as possible and a future upstream merge only touches
 * the call sites listed in BACKPORT.md.
 *
 * Remove this class (and Classes/Compatibility/) once TYPO3 12 support is dropped.
 */
final class Typo3Compatibility
{
    public static function isTypo3V12(): bool
    {
        return GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() < 13;
    }

    /**
     * TYPO3 13 introduced TYPO3\CMS\Core\Schema\TcaSchemaFactory. On TYPO3 12
     * a small read-only replacement with the same method subset is returned.
     *
     * @return \TYPO3\CMS\Core\Schema\TcaSchemaFactory|LegacyTcaSchemaFactory
     */
    public static function getTcaSchemaFactory(): object
    {
        if (class_exists(\TYPO3\CMS\Core\Schema\TcaSchemaFactory::class)) {
            return GeneralUtility::makeInstance(\TYPO3\CMS\Core\Schema\TcaSchemaFactory::class);
        }
        return GeneralUtility::makeInstance(LegacyTcaSchemaFactory::class);
    }

    /**
     * Build the full TCA. TYPO3 13 moved this into TcaFactory, TYPO3 12 still
     * populates $GLOBALS['TCA'] through ExtensionManagementUtility.
     */
    public static function loadTca(): array
    {
        if (class_exists(\TYPO3\CMS\Core\Configuration\Tca\TcaFactory::class)) {
            return GeneralUtility::getContainer()->get(\TYPO3\CMS\Core\Configuration\Tca\TcaFactory::class)->get();
        }
        ExtensionManagementUtility::loadBaseTca();
        return $GLOBALS['TCA'];
    }

    /**
     * TYPO3 12 DataHandler and WorkspaceService read $GLOBALS['LANG'] while
     * listing and creating workspaces, TYPO3 13 no longer does. Without it the
     * MCP workspace creation fails and writes would fall back to live.
     */
    public static function ensureLanguageService(): void
    {
        if (!isset($GLOBALS['LANG'])) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)->create('default');
        }
    }

    /**
     * Conflict mode "rename" for ResourceStorage::addFile(). TYPO3 13 uses a
     * backed enum, TYPO3 12 the string constant of an Enumeration class.
     *
     * @return \TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior|string
     */
    public static function duplicationBehaviorRename(): mixed
    {
        if (enum_exists(\TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior::class)) {
            return \TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior::RENAME;
        }
        return \TYPO3\CMS\Core\Resource\DuplicationBehavior::RENAME;
    }

    /**
     * Integer value of a t3ver_state. VersionState is a backed enum since
     * TYPO3 13 and a class with int constants in TYPO3 12.
     *
     * @param string $name Case name, e.g. 'DELETE_PLACEHOLDER'
     */
    public static function versionStateValue(string $name): int
    {
        $state = constant(\TYPO3\CMS\Core\Versioning\VersionState::class . '::' . $name);
        return $state instanceof \BackedEnum ? (int)$state->value : (int)$state;
    }
}
