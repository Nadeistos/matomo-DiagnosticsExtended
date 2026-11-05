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
use Piwik\Plugins\Diagnostics\Diagnostic\Diagnostic;
use Piwik\Plugins\Diagnostics\Diagnostic\DiagnosticResult;

/**
 * Base class for all checks of this plugin.
 *
 * Matomo's DiagnosticService doesn't catch exceptions thrown by a single diagnostic, so an unexpected error in an
 * experimental check would break the whole system check page (and the "System check" widget). This class makes
 * sure a failing check only results in an informational message.
 */
abstract class AbstractCheck implements Diagnostic
{
    public const LABEL_PREFIX = "🧪 ";

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return DiagnosticResult[]
     */
    public function execute()
    {
        try {
            return $this->doExecute();
        } catch (\Throwable $e) {
            $this->logger->warning('DiagnosticsExtended: {check} failed: {message}', [
                'check' => static::class,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return [DiagnosticResult::singleResult(
                $this->getLabel(),
                DiagnosticResult::STATUS_INFORMATIONAL,
                Piwik::translate('DiagnosticsExtended_CheckFailed')
            )];
        }
    }

    /**
     * @return DiagnosticResult[]
     */
    abstract protected function doExecute(): array;

    abstract protected function getLabel(): string;
}
