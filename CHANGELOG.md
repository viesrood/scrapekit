# Release Notes for ScrapeKit

## 1.1.0 - 2026-07-23

### Added
- SSRF protection for schemes, credentials, ports, DNS results, private/reserved networks, and redirects.
- Configurable response-size, connection-timeout, redirect, retry, concurrency, host, and port limits.
- `craft.scrapekit.getMany(urls, options)` for concurrent cached document fetching.
- `craft.scrapekit.xml(url, options)` for XML, plus opt-in format detection via `get(url, {format: 'auto'})`.
- `craft.scrapekit.jsonResult(url, options)` for all valid JSON types plus response metadata.
- PHPUnit coverage, PHPStan analysis, and a PHP 8.2–8.4 CI matrix.

### Changed
- `get()` still parses as HTML by default (unchanged from 1.0.x); XML parsing is opt-in via `xml()` or `{format: 'auto'|'xml'}`.
- Cache keys now include all effective request headers and use SHA-256 fingerprints.
- Only transient network and server failures are retried.
- Responses expose their final URL, content type, cache status, and stable error code.
- Request errors are logged without query strings or credentials.
- Plugin settings are grouped, validated, and correctly namespaced by Craft.

### Security
- SSRF protection for schemes, credentials, ports, DNS results, and private/reserved networks, re-validated on every redirect.
- Connections are pinned to the validated IP address(es), so a host cannot be re-resolved to a private address between validation and connect (DNS rebinding).
- Private-network detection additionally rejects IPv6 link-local (`fe80::/10`), CGNAT (`100.64.0.0/10`), and IPv4-mapped IPv6 addresses.
- Responses are capped at 5 MiB by default; gzip/deflate is inflated under that cap so a small payload cannot decompress into a memory/disk bomb.
- Unsafe request headers are rejected.

## 1.0.1 - 2026-07-23

### Changed
- Fetching is now more resilient: a network error or an empty response is retried once, and empty or failed responses are never cached - so a transient glitch can no longer poison the cache for the full cache duration.

## 1.0.0 - 2026-07-23

### Added
- Initial release.
- `craft.scrapekit.get(url, options)` - fetch and parse external HTML/XML with per-request options (cacheDuration, timeout, userAgent, headers).
- `craft.scrapekit.json(url, options)` - fetch and decode JSON APIs.
- Querying via CSS selectors (`find()`, `first()`, `text()`, `html()`, `attr()`) and XPath (`findXPath()`).
- `NodeList` results: array-like in Twig plus `first()`, `last()`, `eq()`, `texts()`, `attrs()`.
- Node traversal and inspection: `text()`, `html()`, `outerHtml()`, `attr()`, `nodeName()`, `classes()`, `hasClass()`, `parent()`, `children()`, scoped `find()`, and magic attribute access.
- Fetch outcome on the document: `ok` and `statusCode`.
- Tagged response cache: clearable from Craft's Caches utility and via `php craft scrapekit/cache/clear`.
- Control panel settings + `config/scrapekit.php` for cache duration, timeout, and User-Agent.
