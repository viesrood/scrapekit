# ScrapeKit

Fetch and parse external HTML (or XML) straight from your Twig templates. ScrapeKit is
a small, dependency-light scraper for **Craft CMS 5**, built on
[Symfony DomCrawler](https://symfony.com/doc/current/components/dom_crawler.html) and
Craft's own Guzzle client, with responses cached out of the box.

It is a spiritual successor to the excellent - but Craft 5-incompatible -
[`topshelfcraft/scraper`](https://github.com/TopShelfCraft/Scraper) plugin, and keeps a
familiar SimpleHtmlDom-style API (`find()`, `innertext()`, `plaintext()`, attribute
access, and the `<` child/descendant operator).

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installation

From your project's Composer root:

```bash
composer require viesrood/scrapekit
php craft plugin/install scrapekit
```

## Usage

ScrapeKit registers a `craft.scrapekit` variable in Twig.

```twig
{% set page = craft.scrapekit.get('https://example.com') %}

{# find() returns an array of nodes; index into it or loop over it #}
<h1>{{ page.find('h1')[0].innertext() }}</h1>

{% for item in page.find('.news-list li') %}
    <article>
        <h2>{{ item.find('h3')[0].plaintext() }}</h2>
        <a href="{{ item.find('a[href]')[0].href }}">Read more</a>
    </article>
{% endfor %}
```

### Reading a node

Each result is a node you can read in a few ways:

| Access | Returns |
|---|---|
| `node.innertext()` | The node's inner **HTML** |
| `node.plaintext()` | The node's plain text content |
| `node.href`, `node.src`, `node.content`, ... | Any HTML attribute value |
| `node.find(selector)` | Descendant nodes, scoped to this node |

### Selectors

`find()` accepts standard CSS selectors (via `symfony/css-selector`). For backwards
compatibility with SimpleHtmlDom, the `<` operator is also supported and is treated as a
descendant combinator:

```twig
{# these two are equivalent #}
{{ page.find('.gallery < img[src]') }}
{{ page.find('.gallery img[src]') }}
```

### `using()`

The old plugin's `using()` selector is supported for a drop-in feel; the client-name
argument is accepted but ignored (there is a single DomCrawler-backed engine):

```twig
{% set page = craft.scrapekit.using('simplehtmldom').get(url) %}
```

### Error handling & caching

- Fetch failures (network, timeout, bad status) are logged and degrade to an empty
  document - your template never throws.
- A selector that Symfony can't parse logs a warning and returns an empty array.
- Every response is cached for the configured duration (1 hour by default), keyed on the
  URL, using Craft's cache.

## Configuration

Defaults are editable under **Settings → Plugins → ScrapeKit**, or overridden per
environment with a `config/scrapekit.php` file (which wins over the stored settings):

```php
<?php

return [
    'cacheDuration' => 3600, // seconds; 0 disables caching
    'timeout'       => 30,   // request timeout in seconds
    'userAgent'     => 'Mozilla/5.0 (compatible; CraftCMS ScrapeKit)',
];
```

## Migrating from topshelfcraft/scraper

The Twig API is intentionally close, with one change: the variable is now
**`craft.scrapekit`** instead of `craft.scraper`. Update your templates accordingly;
`get()`, `using()`, `find()`, `innertext()`, `plaintext()`, attribute access and the `<`
operator all keep working.

## Credits

- The original [Scraper](https://github.com/TopShelfCraft/Scraper) plugin by
  Michael Rog / TopShelf Craft.
- [Symfony DomCrawler & CssSelector](https://symfony.com) by Fabien Potencier and
  contributors.
- The SimpleHtmlDom API that inspired the ergonomics, by S. C. Chen.

## License

[MIT](LICENSE.md)
