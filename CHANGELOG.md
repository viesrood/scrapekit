# Release Notes for ScrapeKit

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
