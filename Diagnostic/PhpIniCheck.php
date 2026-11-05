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
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResultItem;
use Piwik\Plugins\DiagnosticsExtended\Diagnostic\IniSettings\IniSetting;

class PhpIniCheck extends AbstractCheck
{
    /**
     * @var IniSetting[]
     */
    private $iniSettings;

    /**
     * @param IniSetting[] $iniSettings
     */
    public function __construct(array $iniSettings, LoggerInterface $logger)
    {
        parent::__construct($logger);
        $this->iniSettings = $iniSettings;
    }

    protected function getLabel(): string
    {
        return self::LABEL_PREFIX . Piwik::translate("DiagnosticsExtended_PhpIniCheckLabel");
    }

    protected function doExecute(): array
    {
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        $result = new DiagnosticResult($this->getLabel());
        foreach ($this->iniSettings as $setting) {
            if (!$setting instanceof IniSetting) {
                continue;
            }
            $key = $setting->getFormattedKey();
            $targetValue = $setting->getTargetValue();
            if ($setting->getCurrentValue() === $targetValue) {
                $status = DiagnosticResult::STATUS_OK;
                $message = $targetValue
                    ? Piwik::translate("DiagnosticsExtended_PhpIniCheckIsEnabled", [$key])
                    : Piwik::translate("DiagnosticsExtended_PhpIniCheckIsDisabled", [$key]);
            } else {
                if ($isCli && !$setting->isRelevantForCli()) {
                    // the value used by the webserver can't be determined from the command line
                    $status = DiagnosticResult::STATUS_INFORMATIONAL;
                } else {
                    $status = $setting->isSevere() ? DiagnosticResult::STATUS_ERROR : DiagnosticResult::STATUS_WARNING;
                }
                $message = $targetValue
                    ? Piwik::translate("DiagnosticsExtended_PhpIniCheckShouldBeEnabled", [$key])
                    : Piwik::translate("DiagnosticsExtended_PhpIniCheckShouldBeDisabled", [$key]);
            }
            $result->addItem(new DiagnosticResultItem($status, $message));
        }
        return [$result];
    }
}
