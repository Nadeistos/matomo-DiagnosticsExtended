<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic;

use Piwik\Log\LoggerInterface;
use Piwik\Piwik;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\DiagnosticsExtended\RemoteData\RemoteDataFetcher;
use Piwik\Plugins\DiagnosticsExtended\Utils;

class CurlVersionCheck extends AbstractCheck
{
    public const VULNERABILITIES_URL = "https://curl.se/docs/vuln.pm";

    /**
     * Only list this many CVEs, old curl versions can be affected by more than a hundred.
     */
    private const MAX_LISTED_VULNERABILITIES = 10;

    /**
     * @var RemoteDataFetcher
     */
    private $fetcher;

    public function __construct(LoggerInterface $logger, RemoteDataFetcher $fetcher)
    {
        parent::__construct($logger);
        $this->fetcher = $fetcher;
    }

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . Piwik::translate("DiagnosticsExtended_CurlVersionCheckLabel");
    }

    protected function doExecute(): array
    {
        if (!extension_loaded('curl') || !function_exists('curl_version')) {
            return [DiagnosticResult::singleResult(
                $this->getLabel(),
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_CurlVersionCheckNoCurl")
            )];
        }
        $curlInfo = curl_version();
        $version = is_array($curlInfo) && isset($curlInfo["version"]) ? (string) $curlInfo["version"] : '';

        $response = $this->fetcher->fetch(self::VULNERABILITIES_URL, 86400);
        $vulnerabilities = $response === null ? [] : $this->parseVulnerabilities($response);
        if ($version === '' || $response === null || count($vulnerabilities) === 0) {
            // count() === 0 means the file format has changed and couldn't be parsed
            return [$this->failedResult()];
        }

        $matches = [];
        foreach ($vulnerabilities as $vulnerability) {
            if (
                version_compare($version, $vulnerability['first'], ">=")
                && version_compare($version, $vulnerability['last'], "<=")
            ) {
                $matches[] = $vulnerability;
            }
        }

        if (count($matches) === 0) {
            return [DiagnosticResult::singleResult(
                $this->getLabel(),
                DiagnosticResult::STATUS_OK,
                Piwik::translate("DiagnosticsExtended_CurlVersionCheckUpToDate", [htmlspecialchars($version)])
            )];
        }

        $links = [];
        foreach (array_slice($matches, 0, self::MAX_LISTED_VULNERABILITIES) as $vulnerability) {
            $links[] = sprintf(
                '<a target="_blank" rel="noreferrer noopener" href="https://curl.se/docs/%s">%s</a>',
                htmlspecialchars($vulnerability['page'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($vulnerability['cve'], ENT_QUOTES, 'UTF-8')
            );
        }
        $list = implode(", ", $links);
        if (count($matches) > self::MAX_LISTED_VULNERABILITIES) {
            $list .= sprintf(" … (+%d)", count($matches) - self::MAX_LISTED_VULNERABILITIES);
        }

        // libcurl is usually provided by the operating system, which backports security fixes
        $status = Utils::isDistributionPackagedPhp()
            ? DiagnosticResult::STATUS_INFORMATIONAL
            : DiagnosticResult::STATUS_WARNING;

        return [DiagnosticResult::singleResult(
            $this->getLabel(),
            $status,
            Piwik::translate("DiagnosticsExtended_CurlVersionCheckVulnerable", [htmlspecialchars($version)])
            . " " . $list
        )];
    }

    /**
     * Parses https://curl.se/docs/vuln.pm which contains lines like
     * "CVE-2024-9681.html|7.74.0|8.10.1|HSTS subdomain overwrites parent cache entry|CVE-2024-9681|...",
     *
     * @return array<array{page: string, first: string, last: string, cve: string}>
     */
    public function parseVulnerabilities(string $response): array
    {
        $vulnerabilities = [];
        foreach (preg_split('/\R/', $response) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '"') {
                // comments, "@vuln = (", ");", "1;" ...
                continue;
            }
            $cols = explode("|", trim($line, "\",; \t"));
            if (count($cols) < 5) {
                continue;
            }
            $page = trim($cols[0]);
            $first = trim($cols[1]);
            $last = trim($cols[2]);
            $cve = trim($cols[4]);
            if (
                !preg_match('/^[A-Za-z0-9._-]+$/', $page)
                || !preg_match('/^\d+\.\d+(\.\d+)?$/', $first)
                || !preg_match('/^\d+\.\d+(\.\d+)?$/', $last)
            ) {
                continue;
            }
            $vulnerabilities[] = [
                'page' => $page,
                'first' => $first,
                'last' => $last,
                'cve' => $cve !== '' && $cve !== '-' ? $cve : $page,
            ];
        }
        return $vulnerabilities;
    }

    private function failedResult(): DiagnosticResult
    {
        return DiagnosticResult::singleResult(
            $this->getLabel(),
            DiagnosticResult::STATUS_INFORMATIONAL,
            Piwik::translate("DiagnosticsExtended_CurlVersionCheckFailed")
        );
    }
}
