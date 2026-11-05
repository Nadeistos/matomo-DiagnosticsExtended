<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic;

use Piwik\Piwik;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Plugins\DiagnosticsExtended\Utils;

class OpcacheCheck extends AbstractCheck
{
    private const MEGABYTE = 1024 * 1024;

    /**
     * OPcache rounds max_accelerated_files up to the next prime of
     * 223, 463, 983, 1979, 3907, 7963, 16229, 32531, 65407, 130987, ...
     */
    private const MINIMUM_FILES = 7963;
    private const MINIMUM_MEMORY_MB = 128;
    private const MINIMUM_INTERNED_STRINGS_MB = 8;

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . "OPcache";
    }

    protected function doExecute(): array
    {
        $result = new DiagnosticResult($this->getLabel());

        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        if ($isCli && !Utils::booleanIni("opcache.enable_cli")) {
            // e.g. ./console diagnostics:run - the OPcache of the webserver can't be inspected from here
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckCli")
            ));
            return [$result];
        }

        if (!function_exists('opcache_get_status') || !Utils::booleanIni("opcache.enable")) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckOpcacheDisabled")
            ));
            return [$result];
        }

        // returns false (with a warning) if opcache.restrict_api prevents access
        $status = @opcache_get_status(false);
        if (is_array($status) && isset($status['opcache_enabled']) && !$status['opcache_enabled']) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckOpcacheDisabled")
            ));
            return [$result];
        }

        if (is_array($status) && isset($status["memory_usage"], $status["interned_strings_usage"], $status["opcache_statistics"])) {
            $this->addUsageInformation($result, $status);
        } else {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckNoStatus")
            ));
        }

        if (!Utils::booleanIni("opcache.save_comments")) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_ERROR,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckSaveComments")
            ));
        }
        if (Utils::intIni("opcache.max_accelerated_files") <= self::MINIMUM_FILES) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckMaxFiles", [self::MINIMUM_FILES])
            ));
        }
        if (Utils::intIni("opcache.memory_consumption") < self::MINIMUM_MEMORY_MB) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckMemory")
            ));
        }
        if (Utils::intIni("opcache.interned_strings_buffer") < self::MINIMUM_INTERNED_STRINGS_MB) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckInternedStrings")
            ));
        }
        if (!Utils::booleanIni("opcache.validate_timestamps")) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckValidateTimestamps")
            ));
        }
        if (PHP_MAJOR_VERSION >= 8 && !$this->isJitEnabled($status)) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckJIT")
            ));
        }
        return [$result];
    }

    private function addUsageInformation(DiagnosticResult $result, array $status): void
    {
        $memoryUsage = $status["memory_usage"];
        $interned = $status["interned_strings_usage"];
        $statistics = $status["opcache_statistics"];

        $result->addItem(new DiagnosticResultItem(
            DiagnosticResult::STATUS_OK,
            Piwik::translate("DiagnosticsExtended_OpcacheCheckOpcacheEnabled", [
                round(($memoryUsage["used_memory"] ?? 0) / self::MEGABYTE),
                round((($memoryUsage["used_memory"] ?? 0) + ($memoryUsage["free_memory"] ?? 0)) / self::MEGABYTE),
                // already a percentage
                round($memoryUsage["current_wasted_percentage"] ?? 0, 2),
                round(($interned["used_memory"] ?? 0) / self::MEGABYTE),
                round(($interned["buffer_size"] ?? 0) / self::MEGABYTE),
                round($statistics["opcache_hit_rate"] ?? 0),
            ])
        ));

        // restarts are clear evidence that the cache is too small
        $oomRestarts = (int) ($statistics["oom_restarts"] ?? 0);
        if ($oomRestarts > 0) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckOomRestarts", [$oomRestarts])
            ));
        }
        $hashRestarts = (int) ($statistics["hash_restarts"] ?? 0);
        if ($hashRestarts > 0) {
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpcacheCheckHashRestarts", [$hashRestarts])
            ));
        }
    }

    /**
     * @param array|false $status
     */
    private function isJitEnabled($status): bool
    {
        if (is_array($status) && isset($status['jit']['enabled'])) {
            return (bool) $status['jit']['enabled'];
        }
        // the JIT is only active with a buffer size > 0 and a mode other than "disable"/"off"/"0" (default in PHP 8.4+)
        $jit = strtolower(trim((string) ini_get("opcache.jit")));
        $bufferSize = trim((string) ini_get("opcache.jit_buffer_size"));
        return !in_array($jit, ['', '0', 'off', 'disable'], true)
            && $bufferSize !== ''
            && (int) $bufferSize > 0;
    }
}
