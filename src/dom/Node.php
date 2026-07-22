<?php

declare(strict_types=1);

namespace viesrood\scrapekit\dom;

use Craft;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

/**
 * A single matched DOM node, exposing inner text/HTML and attribute access to
 * Twig (`node.innertext()`, `node.plaintext()`, `node.src`, `node.href`, ...).
 */
class Node
{
    private \DOMNode $node;

    public function __construct(\DOMNode $node)
    {
        $this->node = $node;
    }

    /**
     * Return the inner HTML of this node.
     */
    public function innertext(): string
    {
        $innerHTML = '';
        foreach ($this->node->childNodes as $child) {
            $innerHTML .= $child->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }

    /**
     * Return the plain text content of this node.
     */
    public function plaintext(): string
    {
        return $this->node->textContent ?? '';
    }

    /**
     * Find descendant elements matching a CSS selector, scoped to this node.
     *
     * @return Node[]
     */
    public function find(string $selector): array
    {
        $selector = Crawler::translateSelector($selector);

        try {
            $crawler = new SymfonyCrawler($this->node);
            $nodes = $crawler->filter($selector);
        } catch (\Throwable $e) {
            Craft::error("ScrapeKit child selector failed '{$selector}': " . $e->getMessage(), __METHOD__);
            return [];
        }

        $results = [];
        foreach ($nodes as $domNode) {
            // Skip the root node itself - only return descendants.
            if ($domNode === $this->node) {
                continue;
            }
            $results[] = new Node($domNode);
        }

        return $results;
    }

    /**
     * Magic getter for HTML attributes and special properties.
     * Enables Twig access like `node.src`, `node.content`, `node.href`,
     * `node.innertext`, `node.plaintext`.
     */
    public function __get(string $name): string
    {
        if ($name === 'innertext') {
            return $this->innertext();
        }
        if ($name === 'plaintext') {
            return $this->plaintext();
        }

        if ($this->node instanceof \DOMElement) {
            return $this->node->getAttribute($name) ?? '';
        }

        return '';
    }

    /**
     * Magic isset - required for Twig property access to resolve.
     */
    public function __isset(string $name): bool
    {
        if (in_array($name, ['innertext', 'plaintext'], true)) {
            return true;
        }

        if ($this->node instanceof \DOMElement) {
            return $this->node->hasAttribute($name);
        }

        return false;
    }
}
