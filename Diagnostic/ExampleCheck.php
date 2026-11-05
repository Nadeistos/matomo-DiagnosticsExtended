<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\Diagnostic;

use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;

/**
 * Example showing all possible result states (not registered by default, see config/config.php)
 */
class ExampleCheck extends AbstractCheck
{
    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . "label";
    }

    protected function doExecute(): array
    {
        $result = new DiagnosticResult($this->getLabel());
        $result->addItem(new DiagnosticResultItem(DiagnosticResult::STATUS_ERROR, "error"));
        $result->addItem(new DiagnosticResultItem(DiagnosticResult::STATUS_WARNING, "warning"));
        $result->addItem(new DiagnosticResultItem(DiagnosticResult::STATUS_OK, "okay"));
        $result->addItem(new DiagnosticResultItem(DiagnosticResult::STATUS_INFORMATIONAL, "info"));
        return [$result];
    }
}
