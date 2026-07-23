# ScrapeKit

Fetch and query external HTML, XML-ish markup, or JSON straight from your Twig
templates. ScrapeKit is a small, dependency-light toolkit for **Craft CMS 5**:
CSS selectors, XPath, node traversal, spec-compliant HTML5 parsing, and cached
HTTP fetching - all behind one Twig variable.

```twig
{% set page = craft.scrapekit.get('https://example.com') %}

<h1>{{ page.text('h1') }}</h1>
<img src="{{ page.attr('meta[property="og:image"]', 'content') }}">

{% for item in page.find('.news li') %}
    <article>
        <h2>{{ item.find('h3').first().text() }}</h2>
        <a href="{{ item.find('a').first().href }}">Read more</a>
    </article>
{% endfor %}
```

## Highlights

- **One-liners for the common cases** - `page.text('h1')`, `page.html('.intro')`,
  `page.attr('a.cta', 'href')`, `page.first('h2')`
- **Full node API** - `text()`, `html()`, `outerHtml()`, `attr()`, `nodeName()`,
  `classes()`, `hasClass()`, `parent()`, `children()`, scoped `find()`, and magic
  attribute access (`node.href`, `node.src`)
- **Smart lists** - `find()` returns a `NodeList` that works like an array in Twig
  (`|length`, `[0]`, `for`, `|slice`) and adds `first()`, `last()`, `eq(i)`,
  `texts()`, `attrs('href')`
- **CSS and XPath** - `find('div.item > a')` or `findXPath('//item/title')`
- **JSON too** - `craft.scrapekit.json(url)` fetches and decodes JSON APIs
- **HTML5 parsing** - documents are parsed with a spec-compliant HTML5 parser
  (via Symfony DomCrawler), so real-world markup behaves like it does in a browser
- **Cached and polite** - responses are cached (1 hour by default) with a dedicated
  cache tag; clear them from Craft's Caches utility or `php craft scrapekit/cache/clear`
- **Per-request options** - override cache duration, timeout, user agent, or add
  headers for a single call
- **Never breaks your page** - fetch and selector errors are logged and degrade to
  empty results; check `page.ok` / `page.statusCode` when you want to know

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

### Fetching

```twig
{% set page = craft.scrapekit.get('https://example.com') %}

{# per-request options #}
{% set page = craft.scrapekit.get('https://example.com', {
    cacheDuration: 600,          {# seconds; 0 disables caching for this call #}
    timeout: 10,
    userAgent: 'MyBot/1.0',
    headers: { 'Accept-Language': 'nl' },
}) %}

{% if page.ok %} ... {% else %} status: {{ page.statusCode }} {% endif %}
```

### Querying

```twig
{# quick single-value reads (first match, '' when nothing matches) #}
{{ page.text('h1') }}
{{ page.html('.article-body') }}
{{ page.attr('link[rel="canonical"]', 'href') }}

{# lists #}
{% set rows = page.find('table.prices tr') %}
{{ rows | length }} rows, first: {{ rows.first().text() }}
{% for row in rows | slice(1, 10) %}
    {{ row.find('td').texts() | join(' | ') }}
{% endfor %}
{{ page.find('a.download').attrs('href') | join(', ') }}

{# XPath #}
{% for title in page.findXPath('//rss/channel/item/title') %}
    {{ title.text() }}
{% endfor %}
```

### Traversing

```twig
{% set button = page.first('button.buy') %}
{{ button.nodeName() }}          {# 'button' #}
{{ button.hasClass('primary') }}
{{ button.parent().attr('id') }}
{% for child in button.parent().children('span') %}
    {{ child.text() }}
{% endfor %}
```

### JSON

```twig
{% set data = craft.scrapekit.json('https://api.example.com/items') %}
{% if data %}
    {% for item in data.items %} {{ item.name }} {% endfor %}
{% endif %}
```

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

## Cache

Responses are cached in Craft's cache with the `scrapekit` tag. Invalidate them via:

- **Utilities → Caches** → "ScrapeKit responses"
- `php craft scrapekit/cache/clear`
- per call: `craft.scrapekit.get(url, { cacheDuration: 0 })`

## Credits

Built on [Symfony DomCrawler and CssSelector](https://symfony.com) by Fabien
Potencier and contributors.

## License

[MIT](LICENSE.md)
