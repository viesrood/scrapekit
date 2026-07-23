<?php

declare(strict_types=1);

namespace viesrood\scrapekit;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterCacheOptionsEvent;
use craft\utilities\ClearCaches;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use viesrood\scrapekit\models\Settings;
use viesrood\scrapekit\services\ScraperService;
use yii\base\Event;

/**
 * ScrapeKit plugin.
 *
 * Fetch and query external HTML, XML-ish markup, or JSON straight from Twig:
 * `craft.scrapekit.get(url)` returns a queryable document (CSS selectors,
 * XPath, node traversal), `craft.scrapekit.json(url)` returns decoded JSON.
 * Responses are cached with a dedicated cache tag.
 *
 * @property-read ScraperService $scraper
 */
class Plugin extends BasePlugin
{
    /**
     * Cache tag applied to every cached response.
     */
    public const CACHE_TAG = 'scrapekit';

    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    /**
     * Register the service as a component so Plugin::getInstance()->scraper works.
     *
     * @return array{components: array<string, mixed>}
     */
    public static function config(): array
    {
        return [
            'components' => [
                'scraper' => ScraperService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Expose `craft.scrapekit` in Twig, resolving to the scraper service.
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event): void {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('scrapekit', $this->getScraper());
            }
        );

        // Make the response cache clearable from Craft's Caches utility.
        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_TAG_OPTIONS,
            static function (RegisterCacheOptionsEvent $event): void {
                $event->options[] = [
                    'tag' => self::CACHE_TAG,
                    'label' => Craft::t('scrapekit', 'ScrapeKit responses'),
                ];
            }
        );
    }

    /**
     * Shortcut to the scraper service.
     */
    public function getScraper(): ScraperService
    {
        /** @var ScraperService $service */
        $service = $this->get('scraper');

        return $service;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        $view = Craft::$app->getView();
        if (!$view instanceof View) {
            throw new \RuntimeException('ScrapeKit settings require Craft web view.');
        }

        return $view->renderTemplate('scrapekit/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ], View::TEMPLATE_MODE_CP);
    }
}
