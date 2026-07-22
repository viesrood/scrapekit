# Release Notes for ScrapeKit

## 1.0.0 - 2026-07-22

### Added
- Initial release.
- `craft.scrapekit` Twig service to fetch and parse external HTML/XML.
- `get()` and `using()` methods, `find()` with CSS selectors, and node access via `innertext()`, `plaintext()`, and HTML attributes (`node.src`, `node.href`, ...).
- Backwards-compatible SimpleHtmlDom `<` child/descendant operator.
- Configurable cache duration, request timeout, and User-Agent (control panel settings + `config/scrapekit.php`).
