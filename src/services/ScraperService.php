<?php

declare(strict_types=1);

namespace viesrood\scrapekit\services;

use Craft;
use craft\base\Component;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Pool;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use viesrood\scrapekit\dom\Crawler;
use viesrood\scrapekit\http\FetchResult;
use viesrood\scrapekit\http\ResponseTooLargeException;
use viesrood\scrapekit\http\UrlPolicy;
use viesrood\scrapekit\models\JsonResult;
use viesrood\scrapekit\models\Settings;
use viesrood\scrapekit\Plugin;
use yii\caching\TagDependency;

/**
 * Fetches and queries external HTML, XML and JSON from Twig.
 *
 * @phpstan-type RequestContext array{
 *   url: string,
 *   headers: array<string, string|array<int, string>>,
 *   timeout: int,
 *   connectTimeout: int,
 *   maxResponseBytes: int,
 *   maxRedirects: int,
 *   cacheDuration: int,
 *   cacheKey: string,
 *   policy: UrlPolicy,
 *   resolve: array<int, string>
 * }
 */
class ScraperService extends Component
{
    private const UNSAFE_HEADERS = [
        'connection',
        'content-length',
        'host',
        'keep-alive',
        'proxy-authenticate',
        'proxy-authorization',
        'proxy-connection',
        'te',
        'trailer',
        'transfer-encoding',
        'upgrade',
    ];

    private const POLICY_ERROR_CODES = [
        'dns_resolution_failed',
        'host_not_allowed',
        'invalid_headers',
        'invalid_url',
        'port_not_allowed',
        'private_network_not_allowed',
        'unsafe_header',
        'unsupported_scheme',
        'url_credentials_not_allowed',
    ];

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $url, array $options = []): Crawler
    {
        $result = $this->fetch($url, $options);

        return $this->crawler($result, $this->requestedFormat($options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function xml(string $url, array $options = []): Crawler
    {
        $options['format'] = 'xml';

        return $this->get($url, $options);
    }

    /**
     * Fetch multiple documents concurrently while preserving input keys.
     *
     * @param array<array-key, mixed> $urls
     * @param array<string, mixed> $options
     * @return array<array-key, Crawler>
     */
    public function getMany(array $urls, array $options = []): array
    {
        if ($urls === []) {
            return [];
        }

        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        $format = $this->requestedFormat($options);
        // An out-of-range concurrency option should not fail the whole batch;
        // fall back to the configured default instead.
        try {
            $concurrency = $this->boundedInt(
                $options['concurrency'] ?? $settings->concurrency,
                1,
                $settings->concurrency,
                'concurrency',
            );
        } catch (\InvalidArgumentException) {
            $concurrency = $settings->concurrency;
        }

        $results = [];
        $contexts = [];
        $keysByFingerprint = [];

        foreach ($urls as $key => $url) {
            if (!is_string($url) || trim($url) === '') {
                $results[$key] = new FetchResult(false, errorCode: 'invalid_url');
                continue;
            }

            $prepared = $this->prepare($url, $options, $settings);
            if ($prepared instanceof FetchResult) {
                $results[$key] = $prepared;
                continue;
            }

            $cached = $this->cached($prepared);
            if ($cached !== null) {
                $results[$key] = $cached;
                continue;
            }

            $fingerprint = $prepared['cacheKey'];
            $contexts[$fingerprint] = $prepared;
            $keysByFingerprint[$fingerprint][] = $key;
        }

        if ($contexts !== []) {
            $client = Craft::createGuzzleClient();
            $fetched = [];
            $requests = function () use ($contexts, $client): \Generator {
                foreach ($contexts as $fingerprint => $context) {
                    yield $fingerprint => fn() => $client->requestAsync(
                        'GET',
                        $context['url'],
                        $this->requestOptions($context),
                    );
                }
            };

            $pool = new Pool($client, $requests(), [
                'concurrency' => $concurrency,
                'fulfilled' => function (ResponseInterface $response, string $fingerprint) use (&$fetched, $contexts): void {
                    $fetched[$fingerprint] = $this->responseResult($response, $contexts[$fingerprint]);
                },
                'rejected' => function (mixed $reason, string $fingerprint) use (&$fetched, $contexts): void {
                    $throwable = $reason instanceof \Throwable ? $reason : new \RuntimeException('Request failed.');
                    $fetched[$fingerprint] = $this->exceptionResult($throwable, $contexts[$fingerprint]);
                },
            ]);
            $pool->promise()->wait();

            foreach ($contexts as $fingerprint => $context) {
                $result = $fetched[$fingerprint] ?? new FetchResult(
                    false,
                    url: $context['url'],
                    errorCode: 'network_error',
                );

                for ($attempt = 0; $attempt < $settings->retryCount && $this->shouldRetry($result); $attempt++) {
                    $result = $this->requestOnce($client, $context);
                }

                $this->store($context, $result);
                foreach ($keysByFingerprint[$fingerprint] as $key) {
                    $results[$key] = $result;
                }
            }
        }

        $crawlers = [];
        foreach ($urls as $key => $_url) {
            $crawlers[$key] = $this->crawler(
                $results[$key] ?? new FetchResult(false, errorCode: 'network_error'),
                $format,
            );
        }

        return $crawlers;
    }

    /**
     * Backwards-compatible JSON helper: returns associative arrays or null.
     *
     * @param array<string, mixed> $options
     * @return array<mixed>|null
     */
    public function json(string $url, array $options = []): ?array
    {
        $result = $this->jsonResult($url, $options);

        return $result->ok && is_array($result->data) ? $result->data : null;
    }

    /**
     * Fetch and decode any valid JSON value with detailed response metadata.
     */
    /**
     * @param array<string, mixed> $options
     */
    public function jsonResult(string $url, array $options = []): JsonResult
    {
        $result = $this->fetch($url, $options);
        if (!$result->ok) {
            return new JsonResult(
                false,
                statusCode: $result->statusCode,
                url: $result->url,
                contentType: $result->contentType,
                fromCache: $result->fromCache,
                errorCode: $result->errorCode,
            );
        }

        try {
            $data = json_decode($result->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            Craft::warning(sprintf(
                'ScrapeKit received invalid JSON from %s.',
                $this->safeUrl($result->url ?: $url),
            ), __METHOD__);

            return new JsonResult(
                false,
                statusCode: $result->statusCode,
                url: $result->url,
                contentType: $result->contentType,
                fromCache: $result->fromCache,
                errorCode: 'invalid_json',
            );
        }

        return new JsonResult(
            true,
            $data,
            $result->statusCode,
            $result->url,
            $result->contentType,
            $result->fromCache,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function fetch(string $url, array $options): FetchResult
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();
        $prepared = $this->prepare($url, $options, $settings);
        if ($prepared instanceof FetchResult) {
            return $prepared;
        }

        $cached = $this->cached($prepared);
        if ($cached !== null) {
            return $cached;
        }

        $client = Craft::createGuzzleClient();
        $result = $this->requestOnce($client, $prepared);
        for ($attempt = 0; $attempt < $settings->retryCount && $this->shouldRetry($result); $attempt++) {
            $result = $this->requestOnce($client, $prepared);
        }

        $this->store($prepared, $result);

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     * @return RequestContext|FetchResult
     */
    private function prepare(string $url, array $options, Settings $settings): array|FetchResult
    {
        try {
            $allowedOptions = ['cacheDuration', 'timeout', 'userAgent', 'headers', 'format', 'concurrency'];
            $unknown = array_diff(array_keys($options), $allowedOptions);
            if ($unknown !== []) {
                throw new \InvalidArgumentException('invalid_options');
            }

            $policy = new UrlPolicy($settings);
            $addresses = $policy->assertAllowed($url);

            $headers = ['user-agent' => $this->headerValue($options['userAgent'] ?? $settings->userAgent)];
            $extraHeaders = $options['headers'] ?? [];
            if (!is_array($extraHeaders)) {
                throw new \InvalidArgumentException('invalid_headers');
            }

            foreach ($extraHeaders as $name => $value) {
                $normalizedName = is_string($name) ? strtolower($name) : '';
                if (
                    $normalizedName === '' ||
                    preg_match('/^[!#$%&\'*+.^_`|~0-9a-z-]+$/', $normalizedName) !== 1 ||
                    in_array($normalizedName, self::UNSAFE_HEADERS, true) ||
                    str_starts_with($normalizedName, 'proxy-')
                ) {
                    throw new \InvalidArgumentException('unsafe_header');
                }
                $headers[$normalizedName] = is_array($value)
                    ? array_map(fn(mixed $item): string => $this->headerValue($item), $value)
                    : $this->headerValue($value);
            }

            $timeout = $this->boundedInt($options['timeout'] ?? $settings->timeout, 1, $settings->timeout, 'timeout');
            $cacheDuration = $this->boundedInt(
                $options['cacheDuration'] ?? $settings->cacheDuration,
                0,
                31536000,
                'cacheDuration',
            );
            $cacheKey = $this->cacheKey($url, $headers);

            return [
                'url' => $url,
                'headers' => $headers,
                'timeout' => $timeout,
                'connectTimeout' => min($settings->connectTimeout, $timeout),
                'maxResponseBytes' => $settings->maxResponseBytes,
                'maxRedirects' => $settings->maxRedirects,
                'cacheDuration' => $cacheDuration,
                'cacheKey' => $cacheKey,
                'policy' => $policy,
                'resolve' => $this->resolveEntries($url, $addresses),
            ];
        } catch (\InvalidArgumentException $e) {
            $errorCode = $e->getMessage();
            Craft::warning(sprintf(
                'ScrapeKit rejected %s [%s].',
                $this->safeUrl($url),
                $errorCode,
            ), __METHOD__);

            return new FetchResult(false, url: $url, errorCode: $errorCode);
        }
    }

    /**
     * @param RequestContext $context
     */
    private function requestOnce(ClientInterface $client, array $context): FetchResult
    {
        try {
            $response = $client->request('GET', $context['url'], $this->requestOptions($context));

            return $this->responseResult($response, $context);
        } catch (\Throwable $e) {
            return $this->exceptionResult($e, $context);
        }
    }

    /**
     * @param RequestContext $context
     * @return array<string, mixed>
     */
    private function requestOptions(array $context): array
    {
        $maxBytes = $context['maxResponseBytes'];
        $policy = $context['policy'];

        $options = [
            'allow_redirects' => $context['maxRedirects'] === 0 ? false : [
                'max' => $context['maxRedirects'],
                'strict' => true,
                'referer' => false,
                'protocols' => ['http', 'https'],
                'track_redirects' => true,
                'on_redirect' => static function (
                    \Psr\Http\Message\RequestInterface $_request,
                    ResponseInterface $_response,
                    UriInterface $uri,
                ) use ($policy): void {
                    $policy->assertAllowed((string)$uri);
                },
            ],
            'connect_timeout' => $context['connectTimeout'],
            // Do not let curl transparently decompress: we cap the decoded size
            // ourselves (see decodeBody) to stop decompression bombs. Without an
            // Accept-Encoding header servers normally return identity anyway.
            'decode_content' => false,
            'headers' => $context['headers'],
            'http_errors' => false,
            'on_headers' => static function (ResponseInterface $response) use ($maxBytes): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && ctype_digit($length) && (int)$length > $maxBytes) {
                    throw new ResponseTooLargeException('Response exceeds the configured maximum size.');
                }
            },
            'progress' => static function (
                int|float $_downloadTotal,
                int|float $downloadedBytes,
            ) use ($maxBytes): void {
                if ($downloadedBytes > $maxBytes) {
                    throw new ResponseTooLargeException('Response exceeds the configured maximum size.');
                }
            },
            'timeout' => $context['timeout'],
        ];

        // Pin the connection to the exact IP(s) the policy validated, so curl
        // cannot re-resolve the host to a different (private) address between
        // validation and connect (DNS rebinding).
        if ($context['resolve'] !== []) {
            $options['curl'] = [CURLOPT_RESOLVE => $context['resolve']];
        }

        return $options;
    }

    /**
     * Build curl CURLOPT_RESOLVE entries pinning a host:port to validated IPs.
     *
     * @param string[] $addresses
     * @return array<int, string>
     */
    private function resolveEntries(string $url, array $addresses): array
    {
        if ($addresses === []) {
            return [];
        }

        $parts = parse_url($url);
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return [sprintf('%s:%d:%s', $host, $port, implode(',', $addresses))];
    }

    /**
     * @param RequestContext $context
     */
    private function responseResult(ResponseInterface $response, array $context): FetchResult
    {
        try {
            $body = $this->decodeBody($response, $context['maxResponseBytes']);
        } catch (ResponseTooLargeException) {
            return $this->loggedFailure($context['url'], 'response_too_large');
        }

        if (strlen($body) > $context['maxResponseBytes']) {
            return $this->loggedFailure($context['url'], 'response_too_large');
        }

        $statusCode = $response->getStatusCode();
        $ok = $statusCode >= 200 && $statusCode < 300;
        $url = $context['url'];
        $history = $response->getHeader('X-Guzzle-Redirect-History');
        if ($history !== []) {
            $url = (string)end($history);
        }

        return new FetchResult(
            $ok,
            $statusCode,
            $body,
            $url,
            $response->getHeaderLine('Content-Type'),
            false,
            $ok ? null : 'http_status',
        );
    }

    /**
     * @param RequestContext $context
     */
    private function exceptionResult(\Throwable $e, array $context): FetchResult
    {
        $errorCode = 'network_error';
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ResponseTooLargeException) {
                $errorCode = 'response_too_large';
                break;
            }
            if ($current instanceof \InvalidArgumentException) {
                $candidate = $current->getMessage();
                $errorCode = in_array($candidate, self::POLICY_ERROR_CODES, true)
                    ? $candidate
                    : 'network_error';
                break;
            }
        }

        return $this->loggedFailure($context['url'], $errorCode);
    }

    /**
     * Return the response body, decompressing gzip/deflate under a hard size cap.
     *
     * With decode_content disabled the body is normally identity-encoded; if a
     * server compressed it unsolicited we inflate incrementally and abort past
     * the cap, so a small payload cannot inflate into a memory/disk bomb.
     *
     * @throws ResponseTooLargeException
     */
    private function decodeBody(ResponseInterface $response, int $maxBytes): string
    {
        $body = (string)$response->getBody();
        $encoding = strtolower(trim($response->getHeaderLine('Content-Encoding')));

        if ($encoding === '' || $encoding === 'identity' || $body === '') {
            return $body;
        }

        if (!in_array($encoding, ['gzip', 'x-gzip', 'deflate'], true)) {
            // Unknown encoding (e.g. br): we can't safely decode it. The wire
            // size was already capped, so returning it raw just yields an empty
            // parse rather than a crash.
            return $body;
        }

        $mode = $encoding === 'deflate' ? ZLIB_ENCODING_DEFLATE : ZLIB_ENCODING_GZIP;
        $resource = inflate_init($mode);
        if ($resource === false) {
            return '';
        }

        $out = '';
        $length = strlen($body);
        for ($offset = 0; $offset < $length; $offset += 65536) {
            $decoded = inflate_add($resource, substr($body, $offset, 65536));
            if ($decoded === false) {
                return '';
            }
            $out .= $decoded;
            if (strlen($out) > $maxBytes) {
                throw new ResponseTooLargeException('Decoded response exceeds the configured maximum size.');
            }
        }

        return $out;
    }

    private function loggedFailure(string $url, string $errorCode): FetchResult
    {
        Craft::warning(sprintf(
            'ScrapeKit request failed for %s [%s].',
            $this->safeUrl($url),
            $errorCode,
        ), __METHOD__);

        return new FetchResult(false, url: $url, errorCode: $errorCode);
    }

    private function shouldRetry(FetchResult $result): bool
    {
        if ($result->ok) {
            return trim($result->body) === '';
        }

        if ($result->errorCode === 'network_error') {
            return true;
        }

        return in_array($result->statusCode, [408, 425, 429], true) || $result->statusCode >= 500;
    }

    /**
     * @param RequestContext $context
     */
    private function cached(array $context): ?FetchResult
    {
        if ($context['cacheDuration'] <= 0) {
            return null;
        }

        $value = Craft::$app->getCache()->get($context['cacheKey']);

        return is_array($value) ? FetchResult::fromCacheValue($value) : null;
    }

    /**
     * @param RequestContext $context
     */
    private function store(array $context, FetchResult $result): void
    {
        if (
            $context['cacheDuration'] <= 0 ||
            !$result->ok ||
            trim($result->body) === ''
        ) {
            return;
        }

        Craft::$app->getCache()->set(
            $context['cacheKey'],
            $result->toCacheValue(),
            $context['cacheDuration'],
            new TagDependency(['tags' => Plugin::CACHE_TAG]),
        );
    }

    private function crawler(FetchResult $result, string $format): Crawler
    {
        if ($format === 'auto') {
            $format = $this->detectFormat($result);
        }

        return new Crawler(
            $result->body,
            $result->ok,
            $result->statusCode,
            $result->url,
            $result->contentType,
            $result->fromCache,
            $result->errorCode,
            $format,
        );
    }

    private function detectFormat(FetchResult $result): string
    {
        $contentType = strtolower($result->contentType);
        if (
            str_contains($contentType, '/xml') ||
            str_contains($contentType, '+xml') ||
            preg_match('/^\s*<\?xml\b/i', $result->body) === 1
        ) {
            return 'xml';
        }

        return 'html';
    }

    /**
     * @param array<string, mixed> $options
     */
    private function requestedFormat(array $options): string
    {
        // Default to HTML (lenient) parsing. XML/auto are opt-in: `xml()` or an
        // explicit `format` option. Auto-switching to strict XML on a stray
        // content-type could silently empty a lenient HTML/custom-tag feed.
        $format = $options['format'] ?? 'html';
        if (!is_string($format) || !in_array($format, ['auto', 'html', 'xml'], true)) {
            return 'html';
        }

        return $format;
    }

    /**
     * @param array<string, string|array<int, string>> $headers
     */
    private function cacheKey(string $url, array $headers): string
    {
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            $normalizedHeaders[strtolower($name)] = (array)$value;
        }
        ksort($normalizedHeaders);

        $fingerprint = json_encode(
            ['url' => $url, 'headers' => $normalizedHeaders],
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );

        return 'scrapekit:v1.1:' . hash('sha256', $fingerprint);
    }

    private function headerValue(mixed $value): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('invalid_header_value');
        }

        $value = (string)$value;
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new \InvalidArgumentException('invalid_header_value');
        }

        return $value;
    }

    private function boundedInt(mixed $value, int $minimum, int $maximum, string $name): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException("invalid_{$name}");
        }

        $value = (int)$value;
        if ($value < $minimum || $value > $maximum) {
            throw new \InvalidArgumentException("invalid_{$name}");
        }

        return $value;
    }

    private function safeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return '[invalid URL]';
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) . '://' : '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . strtolower($parts['host']) . $port . ($parts['path'] ?? '');
    }
}
