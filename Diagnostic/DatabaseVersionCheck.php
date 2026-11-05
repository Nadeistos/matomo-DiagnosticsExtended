<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic;

use Piwik\Date;
use Piwik\Db;
use Piwik\Log\LoggerInterface;
use Piwik\Piwik;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Plugins\DiagnosticsExtended\RemoteData\EndOfLifeData;
use Piwik\Plugins\DiagnosticsExtended\Utils;

class DatabaseVersionCheck extends AbstractCheck
{
    /**
     * End of (extended) support of MySQL releases, used when https://endoflife.date can't be reached.
     *
     * @var array<string, string>
     */
    private const MYSQL_EOL_DATES = [
        "5.5" => "2018-12-31",
        "5.6" => "2021-02-28",
        "5.7" => "2023-10-31",
        "8.0" => "2026-04-30",
        "8.4" => "2032-04-30",
    ];

    /**
     * @var EndOfLifeData
     */
    private $endOfLifeData;

    public function __construct(LoggerInterface $logger, EndOfLifeData $endOfLifeData)
    {
        parent::__construct($logger);
        $this->endOfLifeData = $endOfLifeData;
    }

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . Piwik::translate("DiagnosticsExtended_DatabaseVersionCheckLabel");
    }

    protected function doExecute(): array
    {
        $rawVersion = (string) Db::get()->getServerVersion();
        $lowerVersion = strtolower($rawVersion);

        if (strpos($lowerVersion, 'tidb') !== false) {
            // TiDB reports a fake MySQL version (e.g. "5.7.25-TiDB-v7.1.0")
            return [$this->failedResult()];
        }

        $isMariaDb = strpos($lowerVersion, 'mariadb') !== false;
        // MariaDB < 11 may report "5.5.5-10.x.y-MariaDB" for compatibility with old MySQL clients
        $versionString = $isMariaDb ? (string) preg_replace('/^5\.5\.5-/', '', $rawVersion) : $rawVersion;
        $versionParts = Utils::parseVersion($versionString);
        if ($versionParts === null) {
            return [$this->failedResult()];
        }

        $minorVersion = $versionParts[0] . "." . $versionParts[1];
        $currentVersion = $minorVersion . "." . $versionParts[2];
        $product = $isMariaDb ? 'mariadb' : 'mysql';
        $keyPrefix = $isMariaDb ? 'DiagnosticsExtended_DatabaseVersionCheckMariaDB' : 'DiagnosticsExtended_DatabaseVersionCheckMySQL';
        $disclaimer = Piwik::translate($isMariaDb
            ? 'DiagnosticsExtended_BackportingDisclaimerMariaDB'
            : 'DiagnosticsExtended_BackportingDisclaimerMySQL');
        $outdatedStatus = $this->isDistributionBuild($rawVersion)
            ? DiagnosticResult::STATUS_INFORMATIONAL
            : DiagnosticResult::STATUS_WARNING;

        $release = $this->endOfLifeData->getRelease($product, $minorVersion);
        $latestVersion = $release['latest'] ?? null;
        $eolDate = $release['eolDate'] ?? null;
        $isEol = $release['isEol'] ?? null;
        if ($eolDate === null && $isEol === null && !$isMariaDb && isset(self::MYSQL_EOL_DATES[$minorVersion])) {
            $eolDate = self::MYSQL_EOL_DATES[$minorVersion];
            $isEol = $eolDate <= gmdate('Y-m-d');
        }

        if ($latestVersion === null && $isEol === null) {
            return [$this->failedResult()];
        }

        $results = new DiagnosticResult($this->getLabel());

        if ($latestVersion !== null) {
            if (version_compare($currentVersion, $latestVersion, ">=")) {
                $results->addItem(new DiagnosticResultItem(
                    DiagnosticResult::STATUS_OK,
                    Piwik::translate($keyPrefix . "LatestVersion", [$minorVersion])
                ));
            } else {
                $results->addItem(new DiagnosticResultItem(
                    $outdatedStatus,
                    Piwik::translate($keyPrefix . "Outdated", [$latestVersion, $rawVersion, $currentVersion])
                    . " " . $disclaimer . "."
                ));
            }
        }

        if ($isEol !== null) {
            $formattedDate = $eolDate !== null
                ? Date::factory($eolDate)->getLocalized(Date::DATE_FORMAT_LONG)
                : '?';
            if ($isEol) {
                $results->addItem(new DiagnosticResultItem(
                    $outdatedStatus,
                    Piwik::translate($keyPrefix . "Eol", [$currentVersion, $formattedDate])
                    . " " . $disclaimer . "."
                ));
            } elseif ($eolDate !== null) {
                $results->addItem(new DiagnosticResultItem(
                    DiagnosticResult::STATUS_OK,
                    Piwik::translate($keyPrefix . "NotEol", [$minorVersion, $formattedDate])
                ));
            }
        }

        return [$results];
    }

    /**
     * Distributions (Debian, Ubuntu, RHEL, ...) backport security fixes to the version they ship and usually
     * mark their builds in the version string (e.g. "10.11.6-MariaDB-0+deb12u1" or "8.0.39-0ubuntu0.24.04.2").
     */
    private function isDistributionBuild(string $rawVersion): bool
    {
        return (bool) preg_match('/deb\d+u\d|ubuntu|\.el\d|[-.]fc\d|suse|alpine|cll-lve/i', $rawVersion);
    }

    private function failedResult(): DiagnosticResult
    {
        return DiagnosticResult::singleResult(
            $this->getLabel(),
            DiagnosticResult::STATUS_INFORMATIONAL,
            Piwik::translate("DiagnosticsExtended_DatabaseVersionCheckFailed")
        );
    }
}
