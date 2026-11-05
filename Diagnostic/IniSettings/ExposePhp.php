<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic\IniSettings;

class ExposePhp extends IniSetting
{
    public static $key = "expose_php";

    public static $targetValue = false;

    public static $severe = false;

    public static $url = "https://www.php.net/manual/en/ini.core.php#ini.expose-php";

    public function isRelevantForCli(): bool
    {
        // only affects the X-Powered-By HTTP header
        return false;
    }
}
