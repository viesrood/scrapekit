<?php

declare(strict_types=1);

namespace viesrood\scrapekit\services;

use craft\base\Component;
use viesrood\scrapekit\dom\Client;
use viesrood\scrapekit\dom\Crawler;

/**
 * The `craft.scrapekit` Twig service.
 */
class ScraperService extends Component
{
    /**
     * Mirrors the original plugin's using() method.
     *
     * The client name is accepted for backwards compatibility with the old
     * `topshelfcraft/scraper` API (e.g. `using('simplehtmldom')`) but is
     * ignored - a single Symfony-DomCrawler-backed client is always returned.
     */
    public function using(string $client = 'simplehtmldom'): Client
    {
        return new Client();
    }

    /**
     * Shortcut: fetch a URL directly without calling using() first.
     */
    public function get(string $url): Crawler
    {
        return $this->using()->get($url);
    }
}
