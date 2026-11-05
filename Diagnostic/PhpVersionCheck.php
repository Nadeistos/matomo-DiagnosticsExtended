<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic;

use Piwik\Date;
use Piwik\Log\LoggerInterface;
use Piwik\Piwik;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Plugins\DiagnosticsExtended\RemoteData\EndOfLifeData;
use Piwik\Plugins\DiagnosticsExtended\RemoteData\RemoteDataFetcher;
use Piwik\Plugins\DiagnosticsExtended\Utils;

class PhpVersionCheck extends AbstractCheck
{
    /**
     * End of security support, used when https://endoflife.date can't be reached.
     * From https://www.php.net/supported-versions.php and https://www.php.net/eol.php
     * (since the release cycle RFC of 2024, security support ends on December 31st)
     *
     * @var array<string, string>
     */
    private const EOL_DATES = [
        "7.2" => "2020-11-30",
        "7.3" => "2021-12-06",
        "7.4" => "2022-11-28",
        "8.0" => "2023-11-26",
        "8.1" => "2025-12-31",
        "8.2" => "2026-12-31",
        "8.3" => "2027-12-31",
        "8.4" => "2028-12-31",
        "8.5" => "2029-12-31",
    ];

    /**
     * @var EndOfLifeData
     */
    private $endOfLifeData;

    /**
     * @var RemoteDataFetcher
     */
    private $fetcher;

    public function __construct(LoggerInterface $logger, EndOfLifeData $endOfLifeData, RemoteDataFetcher $fetcher)
    {
        parent::__construct($logger);
        $this->endOfLifeData = $endOfLifeData;
        $this->fetcher = $fetcher;
    }

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . Piwik::translate("DiagnosticsExtended_PhpVersionCheckLabel");
    }

    protected function doExecute(): array
    {
        $minorVersion = PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;
        $currentVersion = $minorVersion . "." . PHP_RELEASE_VERSION;
        $isDistributionPackage = Utils::isDistributionPackagedPhp();
        $outdatedStatus = $isDistributionPackage ? DiagnosticResult::STATUS_INFORMATIONAL : DiagnosticResult::STATUS_WARNING;

        $release = $this->endOfLifeData->getRelease('php', $minorVersion);
        $latestVersion = $release['latest'] ?? $this->getLatestVersionFromPhpNet($minorVersion);

        $results = new DiagnosticResult($this->getLabel());

        if ($latestVersion === null) {
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_PhpVersionCheckNotWorking")
            ));
        } elseif (version_compare($currentVersion, $latestVersion, ">=")) {
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_OK,
                Piwik::translate("DiagnosticsExtended_PhpVersionCheckLatestVersion", [$minorVersion])
            ));
        } else {
            $results->addItem(new DiagnosticResultItem(
                $outdatedStatus,
                Piwik::translate("DiagnosticsExtended_PhpVersionCheckOutdated", [
                    htmlspecialchars($latestVersion, ENT_QUOTES, 'UTF-8'),
                    $currentVersion
                ])
                . " "
                . Piwik::translate("DiagnosticsExtended_BackportingDisclaimerPHP")
                . "."
            ));
        }

        $eolDate = $release['eolDate'] ?? (self::EOL_DATES[$minorVersion] ?? null);
        if ($eolDate === null) {
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_PhpVersionCheckNoInformation", [$currentVersion])
            ));
            return [$results];
        }

        $formattedDate = Date::factory($eolDate)->getLocalized(Date::DATE_FORMAT_LONG);
        if ($eolDate <= gmdate('Y-m-d')) {
            $results->addItem(new DiagnosticResultItem(
                $outdatedStatus,
                Piwik::translate("DiagnosticsExtended_PhpVersionCheckEol", [$currentVersion, $formattedDate])
                . " "
                . Piwik::translate("DiagnosticsExtended_BackportingDisclaimerPHP")
                . "."
            ));
        } else {
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_OK,
                Piwik::translate("DiagnosticsExtended_PhpVersionCheckNotEol", [$minorVersion, $formattedDate])
            ));
        }

        return [$results];
    }

    private function getLatestVersionFromPhpNet(string $minorVersion): ?string
    {
        $versionInfo = $this->fetcher->fetchJson(
            "https://www.php.net/releases/?json=1&version=" . rawurlencode($minorVersion),
            86400
        );
        if (
            !isset($versionInfo["version"])
            || !is_string($versionInfo["version"])
            || !preg_match('/^\d+\.\d+\.\d+$/', $versionInfo["version"])
        ) {
            return null;
        }
        return $versionInfo["version"];
    }
}
