<?php

declare(strict_types=1);

namespace viesrood\scrapekit\models;

use craft\base\Model;

/**
 * ScrapeKit settings.
 *
 * All values can be overridden per environment via config/scrapekit.php
 * (Craft applies that file to these settings automatically).
 */
class Settings extends Model
{
    /** How long (in seconds) a fetched document is cached; 0 disables caching. */
    public int $cacheDuration = 3600;

    /** HTTP request timeout in seconds. */
    public int $timeout = 15;

    /** Connection timeout in seconds. */
    public int $connectTimeout = 5;

    /** The User-Agent header sent with every request. */
    public string $userAgent = 'Mozilla/5.0 (compatible; CraftCMS ScrapeKit)';

    /** Maximum decoded response body size in bytes. */
    public int $maxResponseBytes = 5242880;

    /** Maximum number of redirects to follow. */
    public int $maxRedirects = 3;

    /** Number of retries for transient failures. */
    public int $retryCount = 1;

    /** Maximum number of concurrent requests in getMany(). */
    public int $concurrency = 5;

    /** Newline- or comma-separated exact hosts and *.example.com patterns. */
    public string $allowedHosts = '';

    /** Comma-separated destination ports. */
    public string $allowedPorts = '80,443';

    /** Whether localhost, private and reserved networks may be requested. */
    public bool $allowPrivateNetworks = false;

    public function rules(): array
    {
        return [
            [['cacheDuration'], 'integer', 'min' => 0, 'max' => 31536000],
            [['timeout', 'connectTimeout'], 'integer', 'min' => 1, 'max' => 300],
            [['maxResponseBytes'], 'integer', 'min' => 1024, 'max' => 104857600],
            [['maxRedirects'], 'integer', 'min' => 0, 'max' => 10],
            [['retryCount'], 'integer', 'min' => 0, 'max' => 3],
            [['concurrency'], 'integer', 'min' => 1, 'max' => 20],
            [['userAgent'], 'string'],
            [['userAgent'], 'required'],
            [['allowedHosts', 'allowedPorts'], 'string'],
            [['allowedPorts'], 'validateAllowedPorts'],
            [['allowPrivateNetworks'], 'boolean'],
        ];
    }

    public function validateAllowedPorts(string $attribute): void
    {
        if ($this->getAllowedPorts() === []) {
            $this->addError($attribute, 'Enter at least one valid port between 1 and 65535.');
        }
    }

    /**
     * @return string[]
     */
    public function getAllowedHosts(): array
    {
        $hosts = preg_split('/[\s,]+/', strtolower(trim($this->allowedHosts)), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($hosts ?: []));
    }

    /**
     * @return int[]
     */
    public function getAllowedPorts(): array
    {
        $ports = [];
        foreach (preg_split('/[\s,]+/', trim($this->allowedPorts), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $port) {
            if (ctype_digit($port) && (int)$port >= 1 && (int)$port <= 65535) {
                $ports[] = (int)$port;
            }
        }

        return array_values(array_unique($ports));
    }
}
