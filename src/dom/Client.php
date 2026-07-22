<?php

declare(strict_types=1);

namespace viesrood\scrapekit\dom;

use Craft;
use viesrood\scrapekit\Plugin;

/**
 * Fetches a URL (cached) and wraps the response HTML in a Crawler.
 */
class Client
{
    /**
     * Fetch a URL and return a Crawler wrapping the HTML.
     *
     * The response is cached for the configured duration; fetch failures are
     * logged and degrade to an empty document rather than throwing.
     */
    public function get(string $url): Crawler
    {
        $settings = Plugin::getInstance()->getSettings();

        $cacheKey = 'scrapekit_' . md5($url);
        $html = Craft::$app->cache->get($cacheKey);

        if ($html === false) {
            try {
                $client = Craft::createGuzzleClient();
                $response = $client->get($url, [
                    'timeout' => $settings->timeout,
                    'headers' => [
                        'User-Agent' => $settings->userAgent,
                    ],
                ]);
                $html = (string)$response->getBody();

                if ($settings->cacheDuration > 0) {
                    Craft::$app->cache->set($cacheKey, $html, $settings->cacheDuration);
                }
            } catch (\Throwable $e) {
                Craft::error("ScrapeKit failed to fetch {$url}: " . $e->getMessage(), __METHOD__);
                $html = '';
            }
        }

        return new Crawler($html);
    }
}
