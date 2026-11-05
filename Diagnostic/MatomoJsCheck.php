<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic;

use Piwik\Http;
use Piwik\Piwik;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\SettingsPiwik;
use Piwik\Tracker\TrackerCodeGenerator;

class MatomoJsCheck extends AbstractCheck
{
    public const SOCKET_TIMEOUT = 2;

    /**
     * Part of the LibreJS license header (magnet link of the BSD-3-Clause license) contained in matomo.js
     */
    private const MATOMO_JS_MARKER = "c80d50af7d3db9be66a4d0a86db0286e4fd33292";

    private const COMPRESSION_ENCODINGS = ['gzip', 'br', 'deflate', 'zstd'];

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . "matomo.js"; # no need to make it translatable
    }

    protected function doExecute(): array
    {
        $matomoUrl = (string) SettingsPiwik::getPiwikUrl();
        if (!SettingsPiwik::isInternetEnabled() || $matomoUrl === '') {
            return [$this->unknownResult()];
        }
        $generator = new TrackerCodeGenerator();
        $checkURL = $matomoUrl . $generator->getJsTrackerEndpoint(); # something like https://example.com/matomo.js

        try {
            $response = Http::sendHttpRequest(
                $checkURL,
                self::SOCKET_TIMEOUT,
                null,
                null,
                0,
                false,
                false,
                true
            );
        } catch (\Exception $e) {
            return [$this->unknownResult()];
        }

        $status = is_array($response) && isset($response["status"]) ? (int) $response["status"] : 0;
        $headers = is_array($response) && isset($response["headers"]) && is_array($response["headers"]) ? $response["headers"] : [];
        $data = is_array($response) && isset($response["data"]) ? (string) $response["data"] : '';
        $contentType = $this->getHeader($headers, "Content-Type");

        if ($status !== 200 || strpos($data, self::MATOMO_JS_MARKER) === false || $contentType === null) {
            $result = new DiagnosticResult($this->getLabel());
            $result->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_MatomoJSCheckFailed")
            ));
            $result->setLongErrorMessage(Piwik::translate("DiagnosticsExtended_MatomoJSCheckFailedCurlTip", [
                "<code>curl -v " . htmlspecialchars($checkURL, ENT_QUOTES, 'UTF-8') . "</code>"
            ]));
            return [$result];
        }

        $results = new DiagnosticResult($this->getLabel());

        // "text/javascript" is the recommended MIME type since RFC 9239, "application/javascript" is still accepted
        if (!preg_match('#^\s*(text|application)/(x-)?javascript\b#i', $contentType)) {
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_MatomoJSCheckMIMEError", [htmlspecialchars($contentType)])
            ));
        }

        $contentEncoding = strtolower((string) $this->getHeader($headers, "Content-Encoding"));
        $vary = strtolower((string) $this->getHeader($headers, "Vary"));
        $isCompressed = false;
        foreach (self::COMPRESSION_ENCODINGS as $encoding) {
            if (strpos($contentEncoding, $encoding) !== false) {
                $isCompressed = true;
            }
        }
        if ($isCompressed || strpos($vary, 'accept-encoding') !== false) {
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_OK,
                Piwik::translate("DiagnosticsExtended_MatomoJSCheckGzipped")
            ));
        } else {
            // only a performance recommendation, not a problem
            $results->addItem(new DiagnosticResultItem(
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_MatomoJSCheckNotGzipped")
            ));
        }
        return [$results];
    }

    /**
     * HTTP header names are case-insensitive (and lowercase with HTTP/2)
     */
    private function getHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                return is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }
        return null;
    }

    private function unknownResult(): DiagnosticResult
    {
        return DiagnosticResult::singleResult(
            $this->getLabel(),
            DiagnosticResult::STATUS_INFORMATIONAL,
            Piwik::translate("DiagnosticsExtended_MatomoJSCheckUnknown")
        );
    }
}
