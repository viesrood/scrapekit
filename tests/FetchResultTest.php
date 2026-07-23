<?php

declare(strict_types=1);

namespace viesrood\scrapekit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\scrapekit\http\FetchResult;

final class FetchResultTest extends TestCase
{
    public function testCacheRoundTripPreservesMetadata(): void
    {
        $result = new FetchResult(true, 200, 'body', 'https://example.com/final', 'text/html');
        $cached = FetchResult::fromCacheValue($result->toCacheValue());

        self::assertNotNull($cached);
        self::assertTrue($cached->ok);
        self::assertTrue($cached->fromCache);
        self::assertSame('https://example.com/final', $cached->url);
        self::assertSame('text/html', $cached->contentType);
    }
}
