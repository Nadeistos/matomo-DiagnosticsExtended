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
use Piwik\Plugins\DiagnosticsExtended\Utils;

class OpensslVersionCheck extends AbstractCheck
{
    /**
     * Use a rather old version as many security fixes are backported by distributions.
     * Everything older than the 1.1.1 branch hasn't been supported for years.
     */
    public const MINIMUM_VERSION = "1.1.1";

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . Piwik::translate("DiagnosticsExtended_OpensslVersionCheckLabel");
    }

    protected function doExecute(): array
    {
        $versionText = $this->getOpensslVersionText();
        if ($versionText === null || !preg_match('/OpenSSL[\/ ](\d+\.\d+\.\d+)([a-z]*)/i', $versionText, $matches)) {
            // no OpenSSL at all, or another TLS library (LibreSSL, GnuTLS, BoringSSL, Schannel, ...)
            return [DiagnosticResult::singleResult(
                $this->getLabel(),
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate("DiagnosticsExtended_OpensslVersionCheckNoOpenssl")
            )];
        }
        $version = $matches[1] . $matches[2];

        if (version_compare($matches[1], self::MINIMUM_VERSION, "<")) {
            return [DiagnosticResult::singleResult(
                $this->getLabel(),
                Utils::isDistributionPackagedPhp() ? DiagnosticResult::STATUS_INFORMATIONAL : DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_OpensslVersionCheckOutdated", [htmlspecialchars($version)])
            )];
        }
        return [DiagnosticResult::singleResult(
            $this->getLabel(),
            DiagnosticResult::STATUS_INFORMATIONAL,
            Piwik::translate("DiagnosticsExtended_OpensslVersionCheckNotOutdated", [htmlspecialchars($version)])
        )];
    }

    /**
     * The OpenSSL version used by curl (used by Matomo for outgoing HTTPS requests) or by the PHP openssl extension.
     */
    private function getOpensslVersionText(): ?string
    {
        if (extension_loaded('curl') && function_exists('curl_version')) {
            $curlInfo = curl_version();
            if (is_array($curlInfo) && !empty($curlInfo['ssl_version']) && stripos($curlInfo['ssl_version'], 'openssl') !== false) {
                return (string) $curlInfo['ssl_version'];
            }
        }
        if (extension_loaded('openssl') && defined('OPENSSL_VERSION_TEXT')) {
            return OPENSSL_VERSION_TEXT;
        }
        return null;
    }
}
