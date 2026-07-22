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
    public int $timeout = 30;

    /** The User-Agent header sent with every request. */
    public string $userAgent = 'Mozilla/5.0 (compatible; CraftCMS ScrapeKit)';

    public function rules(): array
    {
        return [
            [['cacheDuration', 'timeout'], 'integer', 'min' => 0],
            [['userAgent'], 'string'],
            [['userAgent'], 'required'],
        ];
    }
}
