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
        // Start from an empty crawler (no document attached), then load the HTML.
        // addHtmlContent tolerates malformed/custom (XML-ish) markup gracefully.
        // Note: passing a string to the constructor would attach a first document,
        // and addHtmlContent() a second - which DomCrawler 7.x forbids - so we
        // construct with no argument and attach exactly one document here.
        $this->crawler = new SymfonyCrawler();
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
