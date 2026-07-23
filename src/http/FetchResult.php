<?php

declare(strict_types=1);

namespace viesrood\scrapekit\http;

final class FetchResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $statusCode = 0,
        public readonly string $body = '',
        public readonly string $url = '',
        public readonly string $contentType = '',
        public readonly bool $fromCache = false,
        public readonly ?string $errorCode = null,
    ) {
    }

    public function cached(): self
    {
        return new self(
            $this->ok,
            $this->statusCode,
            $this->body,
            $this->url,
            $this->contentType,
            true,
            $this->errorCode,
        );
    }

    /**
     * @return array{statusCode: int, body: string, url: string, contentType: string}
     */
    public function toCacheValue(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'body' => $this->body,
            'url' => $this->url,
            'contentType' => $this->contentType,
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    public static function fromCacheValue(array $value): ?self
    {
        if (
            !isset($value['statusCode'], $value['body']) ||
            !is_int($value['statusCode']) ||
            !is_string($value['body'])
        ) {
            return null;
        }

        return new self(
            true,
            $value['statusCode'],
            $value['body'],
            is_string($value['url'] ?? null) ? $value['url'] : '',
            is_string($value['contentType'] ?? null) ? $value['contentType'] : '',
            true,
        );
    }
}
