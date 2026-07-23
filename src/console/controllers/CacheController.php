<?php

declare(strict_types=1);

namespace viesrood\scrapekit\console\controllers;

use Craft;
use craft\console\Controller;
use viesrood\scrapekit\Plugin;
use yii\caching\TagDependency;
use yii\console\ExitCode;

/**
 * Manages the ScrapeKit response cache.
 */
class CacheController extends Controller
{
    public $defaultAction = 'clear';

    /**
     * Invalidates all cached ScrapeKit responses.
     */
    public function actionClear(): int
    {
        TagDependency::invalidate(Craft::$app->getCache(), Plugin::CACHE_TAG);
        $this->stdout("Cleared all cached ScrapeKit responses.\n");

        return ExitCode::OK;
    }
}
