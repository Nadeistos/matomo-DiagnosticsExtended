<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\DiagnosticsExtended\RemoteData;

use Piwik\Cache;
use Piwik\Http;
use Piwik\Log\LoggerInterface;
use Piwik\SettingsPiwik;

/**
 * Fetches (and caches) data from external services.
 *
 * - nothing is fetched when internet features are disabled in Matomo
 * - only successful (HTTP 200) responses are used
 * - failures are cached too (for a shorter time) so that an unreachable service doesn't slow down every
 *   page that runs the system check (e.g. the "System check" widget on the administration home page)
 */
class RemoteDataFetcher
{
    public const TIMEOUT = 3;
    public const FAILURE_TTL = 3600;

    /**
     * The largest expected response (curl's vuln.pm) is a few hundred KB
     */
    public const MAX_RESPONSE_SIZE = 5 * 1024 * 1024;

    /**
     * Hosts that couldn't be reached during the current request (e.g. blocked by a firewall),
     * so that we don't wait for the timeout again for every following request to the same host.
     *
     * @var array<string, bool>
     */
    private static $unreachableHosts = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var \Matomo\Cache\Lazy|null
     */
    private $cache;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function isEnabled(): bool
    {
        return SettingsPiwik::isInternetEnabled();
    }

    /**
     * @return string|null the response body or null if the request failed
     */
    public function fetch(string $url, int $ttl): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $cacheId = 'DiagnosticsExtended_Remote_' . md5($url);
        $cache = $this->getCache();
        if ($cache !== null) {
            $cached = $cache->fetch($cacheId);
            if (is_array($cached) && array_key_exists('body', $cached)) {
                return is_string($cached['body']) ? $cached['body'] : null;
            }
        }

        $body = null;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (isset(self::$unreachableHosts[$host])) {
            return null;
        }
        try {
            $response = Http::sendHttpRequest(
                $url,
                self::TIMEOUT,
                null,
                null,
                0,
                false,
                false,
                true
            );
            $status = is_array($response) && isset($response['status']) ? (int) $response['status'] : 0;
            if (
                $status === 200
                && isset($response['data'])
                && is_string($response['data'])
                && $response['data'] !== ''
                && strlen($response['data']) <= self::MAX_RESPONSE_SIZE
            ) {
                $body = $response['data'];
            } else {
                $this->logger->debug('DiagnosticsExtended: unexpected HTTP status {status} for {url}', [
                    'status' => $status,
                    'url' => $url,
                ]);
            }
        } catch (\Throwable $e) {
            self::$unreachableHosts[$host] = true;
            $this->logger->debug('DiagnosticsExtended: request to {url} failed: {message}', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
        }

        if ($cache !== null) {
            try {
                $cache->save($cacheId, ['body' => $body], $body === null ? self::FAILURE_TTL : $ttl);
            } catch (\Throwable $e) {
                // caching is only an optimisation
            }
        }

        return $body;
    }

    /**
     * @return array|null the decoded JSON object or null if the request failed or didn't return valid JSON
     */
    public function fetchJson(string $url, int $ttl): ?array
    {
        $body = $this->fetch($url, $ttl);
        if ($body === null) {
            return null;
        }
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * @return \Matomo\Cache\Lazy|null
     */
    private function getCache()
    {
        if ($this->cache === null) {
            try {
                $this->cache = Cache::getLazyCache();
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $this->cache;
    }
}
