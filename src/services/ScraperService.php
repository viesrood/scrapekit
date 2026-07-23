<?php

declare(strict_types=1);

namespace viesrood\scrapekit\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\Exception\RequestException;
use viesrood\scrapekit\dom\Crawler;
use viesrood\scrapekit\Plugin;
use yii\caching\TagDependency;

/**
 * The `craft.scrapekit` Twig service.
 *
 * Fetches external documents (cached, tagged) and hands them back as a
 * queryable Crawler, or as decoded JSON.
 */
class ScraperService extends Component
{
    /**
     * Fetch a URL and return a queryable Crawler.
     *
     * Options (each overrides the plugin setting for this request only):
     * - cacheDuration: seconds to cache the response; 0 disables caching
     * - timeout: request timeout in seconds
     * - userAgent: User-Agent header value
     * - headers: extra request headers (assoc array)
     */
    public function get(string $url, array $options = []): Crawler
    {
        $response = $this->fetch($url, $options);

        return new Crawler($response['body'], $response['ok'], $response['status']);
    }

    /**
     * Fetch a URL and return its decoded JSON (null on failure or invalid JSON).
     */
    public function json(string $url, array $options = []): ?array
    {
        $response = $this->fetch($url, $options);

        if (!$response['ok']) {
            return null;
        }

        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            Craft::error("ScrapeKit got non-JSON response from {$url}", __METHOD__);
            return null;
        }

        return $decoded;
    }

    /**
     * Fetch a URL through the cache.
     *
     * Only successful responses are cached, tagged so they can be invalidated
     * as a group (Craft's Caches utility, `php craft scrapekit/cache/clear`).
     *
     * @return array{ok: bool, status: int, body: string}
     */
    private function fetch(string $url, array $options = []): array
    {
        $settings = Plugin::getInstance()->getSettings();

        $cacheDuration = (int)($options['cacheDuration'] ?? $settings->cacheDuration);
        $cacheKey = 'scrapekit:' . md5($url);

        if ($cacheDuration > 0) {
            $cached = Craft::$app->getCache()->get($cacheKey);
            if (is_array($cached)) {
                return ['ok' => true, 'status' => $cached['status'], 'body' => $cached['body']];
            }
        }

        $headers = ['User-Agent' => $options['userAgent'] ?? $settings->userAgent];
        foreach ($options['headers'] ?? [] as $name => $value) {
            $headers[$name] = $value;
        }

        try {
            $response = Craft::createGuzzleClient()->get($url, [
                'timeout' => (int)($options['timeout'] ?? $settings->timeout),
                'headers' => $headers,
            ]);

            $status = $response->getStatusCode();
            $body = (string)$response->getBody();

            if ($cacheDuration > 0) {
                Craft::$app->getCache()->set(
                    $cacheKey,
                    ['status' => $status, 'body' => $body],
                    $cacheDuration,
                    new TagDependency(['tags' => Plugin::CACHE_TAG]),
                );
            }

            return ['ok' => true, 'status' => $status, 'body' => $body];
        } catch (\Throwable $e) {
            $status = $e instanceof RequestException && $e->getResponse() !== null
                ? $e->getResponse()->getStatusCode()
                : 0;

            Craft::error("ScrapeKit failed to fetch {$url}: " . $e->getMessage(), __METHOD__);

            return ['ok' => false, 'status' => $status, 'body' => ''];
        }
    }
}
