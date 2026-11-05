<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\RemoteData;

/**
 * Release and end-of-life information from https://endoflife.date
 *
 * Uses the v1 API and falls back to the legacy v0 API, so a change on one of them doesn't break the checks.
 */
class EndOfLifeData
{
    private const TTL = 86400;

    /**
     * @var RemoteDataFetcher
     */
    private $fetcher;

    public function __construct(RemoteDataFetcher $fetcher)
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @param string $product e.g. "php", "mariadb", "mysql"
     * @param string $cycle e.g. "8.3", "10.11"
     * @return array{latest: string|null, eolDate: string|null, isEol: bool|null}|null
     *         null if nothing is known about this release
     */
    public function getRelease(string $product, string $cycle): ?array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $product) || !preg_match('/^[0-9.]+$/', $cycle)) {
            return null;
        }

        $data = $this->fetcher->fetchJson(
            'https://endoflife.date/api/v1/products/' . $product . '/releases/' . $cycle,
            self::TTL
        );
        if (isset($data['result']) && is_array($data['result'])) {
            $release = $data['result'];
            $latest = null;
            if (isset($release['latest']) && is_array($release['latest']) && isset($release['latest']['name'])) {
                $latest = $release['latest']['name'];
            }
            $result = $this->normalize(
                $latest,
                $release['eolFrom'] ?? null,
                $release['isEol'] ?? null
            );
            if ($result !== null) {
                return $result;
            }
        }

        $data = $this->fetcher->fetchJson(
            'https://endoflife.date/api/' . $product . '/' . $cycle . '.json',
            self::TTL
        );
        if (is_array($data)) {
            $eol = $data['eol'] ?? null;
            return $this->normalize(
                $data['latest'] ?? null,
                is_string($eol) ? $eol : null,
                is_bool($eol) ? $eol : null
            );
        }

        return null;
    }

    /**
     * @param mixed $latest
     * @param mixed $eolDate
     * @param mixed $isEol
     */
    private function normalize($latest, $eolDate, $isEol): ?array
    {
        $latest = is_string($latest) && preg_match('/^\d+\.\d+(\.\d+)?/', $latest) ? $latest : null;
        $eolDate = is_string($eolDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eolDate) ? $eolDate : null;
        if ($eolDate !== null) {
            $isEol = $eolDate <= gmdate('Y-m-d');
        } elseif (!is_bool($isEol)) {
            $isEol = null;
        }

        if ($latest === null && $eolDate === null && $isEol === null) {
            return null;
        }

        return [
            'latest' => $latest,
            'eolDate' => $eolDate,
            'isEol' => $isEol,
        ];
    }
}
