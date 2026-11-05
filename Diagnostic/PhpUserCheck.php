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
use Piwik\SettingsServer;

class PhpUserCheck extends AbstractCheck
{
    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . Piwik::translate("DiagnosticsExtended_PhpUserCheckLabel");
    }

    protected function doExecute(): array
    {
        if (SettingsServer::isWindows() || !function_exists("posix_geteuid")) {
            return [];
        }
        if (posix_geteuid() === 0) {
            return [DiagnosticResult::singleResult(
                $this->getLabel(),
                DiagnosticResult::STATUS_WARNING,
                Piwik::translate("DiagnosticsExtended_PhpUserCheckWarning")
            )];
        }
        return [];
    }
}
