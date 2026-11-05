<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic\IniSettings;

use Piwik\Plugins\DiagnosticsExtended\Utils;

abstract class IniSetting
{
    /**
     * @var string
     */
    public static $key;

    /**
     * @var bool
     */
    public static $targetValue;

    /**
     * @var bool
     */
    public static $severe = true;

    /**
     * @var string
     */
    public static $url;

    public function getKey(): string
    {
        return static::$key;
    }

    public function getTargetValue(): bool
    {
        return (bool) static::$targetValue;
    }

    public function isSevere(): bool
    {
        return (bool) static::$severe;
    }

    /**
     * Whether the value seen by the PHP CLI (e.g. ./console diagnostics:run) is meaningful.
     */
    public function isRelevantForCli(): bool
    {
        return true;
    }

    public function getCurrentValue(): bool
    {
        return Utils::booleanIni($this->getKey());
    }

    /**
     * The name of the setting, linked to the PHP documentation
     */
    public function getFormattedKey(): string
    {
        $key = '<code>' . htmlspecialchars($this->getKey()) . '</code>';
        if (empty(static::$url)) {
            return $key;
        }
        return '<a href="' . htmlspecialchars(static::$url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noreferrer noopener">' . $key . '</a>';
    }
}
