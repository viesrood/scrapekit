<?php

declare(strict_types=1);

namespace viesrood\scrapekit\dom;

use Craft;
use Symfony\Component\DomCrawler\Crawler as SymfonyCrawler;

/**
 * A single matched DOM node.
 *
 * Readable from Twig via methods (`node.text`, `node.html`, `node.parent`, ...)
 * and via magic HTML-attribute access (`node.href`, `node.src`, `node.content`).
 */
class Node
{
    public function __construct(
        private readonly \DOMNode $node,
    ) {
    }

    /**
     * The plain text content of this node.
     */
    public function text(): string
    {
        return $this->node->textContent ?? '';
    }

    /**
     * The inner HTML of this node.
     */
    public function html(): string
    {
        $html = '';
        foreach ($this->node->childNodes as $child) {
            $html .= $child->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    /**
     * The outer HTML of this node (the node itself included).
     */
    public function outerHtml(): string
    {
        return $this->node->ownerDocument?->saveHTML($this->node) ?: '';
    }

    /**
     * The value of an HTML attribute ('' when absent).
     */
    public function attr(string $name): string
    {
        return $this->node instanceof \DOMElement ? $this->node->getAttribute($name) : '';
    }

    /**
     * The lowercase tag name of this node.
     */
    public function nodeName(): string
    {
        return strtolower($this->node->nodeName);
    }

    /**
     * The CSS classes on this node.
     *
     * @return string[]
     */
    public function classes(): array
    {
        return preg_split('/\s+/', trim($this->attr('class')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Whether this node has the given CSS class.
     */
    public function hasClass(string $name): bool
    {
        return in_array($name, $this->classes(), true);
    }

    /**
     * The parent element, or null at the document root.
     */
    public function parent(): ?Node
    {
        $parent = $this->node->parentNode;

        return $parent instanceof \DOMElement ? new Node($parent) : null;
    }

    /**
     * The direct child elements, optionally filtered by a CSS selector.
     */
    public function children(?string $selector = null): NodeList
    {
        $children = [];
        foreach ($this->node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $children[] = $child;
            }
        }

        if ($selector !== null) {
            $matches = $this->matchDescendants($selector);
            $children = array_values(array_filter(
                $children,
                static fn(\DOMElement $child): bool => in_array($child, $matches, true),
            ));
        }

        return new NodeList(array_map(static fn(\DOMNode $child): Node => new Node($child), $children));
    }

    /**
     * Find descendant elements matching a CSS selector, scoped to this node.
     */
    public function find(string $selector): NodeList
    {
        $nodes = [];
        foreach ($this->matchDescendants($selector) as $domNode) {
            $nodes[] = new Node($domNode);
        }

        return new NodeList($nodes);
    }

    /**
     * Run a scoped CSS filter and return matched DOM nodes, excluding this node itself.
     *
     * @return \DOMNode[]
     */
    private function matchDescendants(string $selector): array
    {
        try {
            $crawler = new SymfonyCrawler($this->node);
            $matched = $crawler->filter($selector);
        } catch (\Throwable $e) {
            Craft::error("ScrapeKit scoped selector failed '{$selector}': " . $e->getMessage(), __METHOD__);
            return [];
        }

        $results = [];
        foreach ($matched as $domNode) {
            // Only descendants: never return the scope node itself.
            if ($domNode !== $this->node) {
                $results[] = $domNode;
            }
        }

        return $results;
    }

    /**
     * Magic getter for HTML attributes: `node.src`, `node.href`, `node.content`.
     */
    public function __get(string $name): string
    {
        return $this->attr($name);
    }

    /**
     * Magic isset, limited to real HTML attributes so Twig falls through to the
     * method with the same name otherwise (`node.text` -> text(), etc.).
     */
    public function __isset(string $name): bool
    {
        return $this->node instanceof \DOMElement && $this->node->hasAttribute($name);
    }
}
