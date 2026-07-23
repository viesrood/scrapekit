<?php

declare(strict_types=1);

namespace viesrood\scrapekit\dom;

use Craft;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

/**
 * A parsed document, queryable with CSS selectors or XPath.
 *
 * Wraps Symfony's DomCrawler (spec-compliant HTML5 parsing included) and
 * carries the outcome of the fetch that produced it (`ok`, `statusCode`).
 */
class Crawler
{
    private SymfonyCrawler $crawler;

    public function __construct(
        string $html,
        public readonly bool $ok = true,
        public readonly int $statusCode = 200,
        public readonly string $url = '',
        public readonly string $contentType = '',
        public readonly bool $fromCache = false,
        public readonly ?string $errorCode = null,
        string $format = 'html',
    ) {
        $this->crawler = new SymfonyCrawler();
        if ($format === 'xml') {
            $this->crawler->addXmlContent($html, 'UTF-8', LIBXML_NONET);
        } else {
            $this->crawler->addHtmlContent($html, 'UTF-8');
        }
    }

    /**
     * Find elements matching a CSS selector.
     */
    public function find(string $selector): NodeList
    {
        try {
            $matched = $this->crawler->filter($selector);
        } catch (\Throwable $e) {
            Craft::error("ScrapeKit selector failed '{$selector}': " . $e->getMessage(), __METHOD__);
            return new NodeList();
        }

        return self::wrap($matched);
    }

    /**
     * Find elements matching an XPath expression.
     */
    public function findXPath(string $expression): NodeList
    {
        try {
            $matched = $this->crawler->filterXPath($expression);
        } catch (\Throwable $e) {
            Craft::error("ScrapeKit XPath failed '{$expression}': " . $e->getMessage(), __METHOD__);
            return new NodeList();
        }

        return self::wrap($matched);
    }

    /**
     * The first element matching a CSS selector, or null.
     */
    public function first(string $selector): ?Node
    {
        return $this->find($selector)->first();
    }

    /**
     * The text content of the first match ('' when nothing matches).
     */
    public function text(string $selector): string
    {
        return $this->first($selector)?->text() ?? '';
    }

    /**
     * The inner HTML of the first match ('' when nothing matches).
     */
    public function html(string $selector): string
    {
        return $this->first($selector)?->html() ?? '';
    }

    /**
     * An attribute of the first match ('' when nothing matches).
     */
    public function attr(string $selector, string $name): string
    {
        return $this->first($selector)?->attr($name) ?? '';
    }

    private static function wrap(SymfonyCrawler $matched): NodeList
    {
        $nodes = [];
        foreach ($matched as $domNode) {
            $nodes[] = new Node($domNode);
        }

        return new NodeList($nodes);
    }
}
