<?php

declare(strict_types=1);

namespace viesrood\scrapekit\models;

/**
 * Detailed result returned by `craft.scrapekit.jsonResult()`.
 */
final class JsonResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly mixed $data = null,
        public readonly int $statusCode = 0,
        public readonly string $url = '',
        public readonly string $contentType = '',
        public readonly bool $fromCache = false,
        public readonly ?string $errorCode = null,
    ) {
    }
}
