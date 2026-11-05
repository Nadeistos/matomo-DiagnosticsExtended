<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended;

class Utils
{
    /**
     * Returns the value of a boolean php.ini directive.
     * Directives that don't exist (e.g. because the extension is not loaded) are treated as disabled.
     */
    public static function booleanIni(string $key): bool
    {
        return self::iniValueToBoolean(ini_get($key));
    }

    /**
     * Returns the value of an integer php.ini directive (0 if it doesn't exist).
     */
    public static function intIni(string $key): int
    {
        $value = ini_get($key);
        if ($value === false) {
            return 0;
        }
        return (int) $value;
    }

    /**
     * Converts a raw php.ini value ("On", "off", "1", "", ...) to a boolean.
     *
     * @param string|false|null $iniValue
     */
    public static function iniValueToBoolean($iniValue): bool
    {
        if ($iniValue === false || $iniValue === null) {
            return false;
        }
        switch (strtolower(trim((string) $iniValue))) {
            case "on":
            case "true":
            case "yes":
            case "1":
                return true;
            case "off":
            case "false":
            case "no":
            case "none":
            case "0":
            case "":
                return false;
            default:
                // any other non-empty value (e.g. "stderr" for display_errors) means the setting is active
                return true;
        }
    }

    /**
     * Tries to guess whether PHP (and therefore most likely also libcurl, OpenSSL and the database server) has been
     * installed from a Linux distribution package. Distributions usually backport security fixes without changing
     * the upstream version number, so an "outdated" version number is not necessarily a security issue.
     */
    public static function isDistributionPackagedPhp(): bool
    {
        if (defined('PHP_EXTRA_VERSION') && PHP_EXTRA_VERSION !== '') {
            return true;
        }
        $iniFile = php_ini_loaded_file();
        if (is_string($iniFile) && strpos($iniFile, '/etc/') === 0) {
            // Debian/Ubuntu (/etc/php/8.x/...), RHEL/Fedora (/etc/php.ini), Alpine (/etc/php83/...), SUSE, ...
            // official upstream builds (e.g. the php Docker image) use /usr/local/etc/php instead
            return true;
        }
        return false;
    }

    /**
     * Extracts "major.minor.patch" from a version string like "10.11.6-MariaDB-0+deb12u1" or "8.0.36".
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    public static function parseVersion(string $version): ?array
    {
        if (!preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', $version, $matches)) {
            return null;
        }
        return [(int) $matches[1], (int) $matches[2], isset($matches[3]) ? (int) $matches[3] : 0];
    }
}
