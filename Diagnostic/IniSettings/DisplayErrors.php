<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic\IniSettings;

class DisplayErrors extends IniSetting
{
    public static $key = "display_errors";

    public static $targetValue = false;

    public static $url = "https://www.php.net/manual/en/errorfunc.configuration.php#ini.display-errors";

    public function getCurrentValue(): bool
    {
        $value = ini_get(static::$key);
        if (is_string($value) && strtolower(trim($value)) === 'stderr') {
            // errors are sent to stderr (the log of PHP-FPM or the webserver), not to the visitor
            return false;
        }
        return parent::getCurrentValue();
    }

    public function isRelevantForCli(): bool
    {
        // the PHP CLI usually has its own php.ini where display_errors is enabled, which is fine
        return false;
    }
}
