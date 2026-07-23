<?php

declare(strict_types=1);

namespace viesrood\scrapekit\tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use viesrood\scrapekit\http\ResponseTooLargeException;
use viesrood\scrapekit\services\ScraperService;

final class ScraperServiceTest extends TestCase
{
    public function testDecodeBodyInflatesGzipUnderCap(): void
    {
        $service = new ScraperService();
        $method = new \ReflectionMethod($service, 'decodeBody');
        $payload = str_repeat('groep', 200);
        $response = new Response(200, ['Content-Encoding' => 'gzip'], gzencode($payload));

        self::assertSame($payload, $method->invoke($service, $response, 1_000_000));
    }

    public function testDecodeBodyRejectsDecompressionBomb(): void
    {
        $service = new ScraperService();
        $method = new \ReflectionMethod($service, 'decodeBody');
        // ~1 MB of zeros compresses tiny but must be rejected against a 1 KB cap.
        $response = new Response(200, ['Content-Encoding' => 'gzip'], gzencode(str_repeat('0', 1_000_000)));

        $this->expectException(ResponseTooLargeException::class);
        $method->invoke($service, $response, 1024);
    }

    public function testResolveEntriesPinsHostToValidatedIps(): void
    {
        $service = new ScraperService();
        $method = new \ReflectionMethod($service, 'resolveEntries');

        self::assertSame(
            ['forms.example.com:443:203.0.113.10,203.0.113.11'],
            $method->invoke($service, 'https://forms.example.com/feed', ['203.0.113.10', '203.0.113.11']),
        );
        // IP-literal hosts and empty address lists produce no pin entries.
        self::assertSame([], $method->invoke($service, 'https://8.8.8.8/', ['8.8.8.8']));
        self::assertSame([], $method->invoke($service, 'https://forms.example.com/feed', []));
    }

    public function testCacheFingerprintIncludesHeaders(): void
    {
        $service = new ScraperService();
        $method = new \ReflectionMethod($service, 'cacheKey');

        $first = $method->invoke($service, 'https://example.com/data', [
            'user-agent' => 'ScrapeKit',
            'authorization' => 'Bearer first',
        ]);
        $second = $method->invoke($service, 'https://example.com/data', [
            'user-agent' => 'ScrapeKit',
            'authorization' => 'Bearer second',
        ]);

        self::assertNotSame($first, $second);
        self::assertStringStartsWith('scrapekit:v1.1:', $first);
        self::assertStringNotContainsString('first', $first);
    }

    public function testSafeUrlRemovesSecrets(): void
    {
        $service = new ScraperService();
        $method = new \ReflectionMethod($service, 'safeUrl');

        $safe = $method->invoke(
            $service,
            'https://user:secret@example.com/path?token=sensitive#fragment',
        );

        self::assertSame('https://example.com/path', $safe);
    }
}
