<?php

declare(strict_types=1);

namespace viesrood\scrapekit\dom;

use Craft;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

/**
 * A thin wrapper around Symfony's DomCrawler that returns Node objects and
 * understands the legacy SimpleHtmlDom `<` operator.
 */
class Crawler
{
    private SymfonyCrawler $crawler;

    public function __construct(string $html)
    {
        // addHtmlContent tolerates malformed/custom (XML-ish) markup gracefully.
        $this->crawler = new SymfonyCrawler('');
        $this->crawler->addHtmlContent($html, 'UTF-8');
    }

    /**
     * Find elements matching a CSS selector.
     *
     * SimpleHtmlDom's `<` operator is translated to the CSS descendant
     * combinator (a space) for backwards compatibility.
     *
     * @return Node[]
     */
    public function find(string $selector): array
    {
        $selector = self::translateSelector($selector);

        try {
            $nodes = $this->crawler->filter($selector);
        } catch (\Throwable $e) {
            Craft::error("ScrapeKit selector failed '{$selector}': " . $e->getMessage(), __METHOD__);
            return [];
        }

        $results = [];
        foreach ($nodes as $domNode) {
            $results[] = new Node($domNode);
        }

        return $results;
    }

    /**
     * Translate SimpleHtmlDom's `<` (child/descendant) operator to a CSS space
     * (descendant combinator).
     */
    public static function translateSelector(string $selector): string
    {
        return preg_replace('/\s*<\s*/', ' ', $selector);
    }
}
