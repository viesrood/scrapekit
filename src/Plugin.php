<?php

declare(strict_types=1);

namespace viesrood\scrapekit;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use viesrood\scrapekit\models\Settings;
use viesrood\scrapekit\services\ScraperService;
use yii\base\Event;

/**
 * ScrapeKit plugin.
 *
 * Fetch and parse external HTML (or XML) from Twig templates, on top of
 * Symfony DomCrawler + Guzzle, with a familiar SimpleHtmlDom-style API:
 * `craft.scrapekit.get(url).find(selector)`.
 *
 * A Craft 5 successor to the abandoned `topshelfcraft/scraper` plugin.
 *
 * @property-read ScraperService $scraper
 */
class Plugin extends BasePlugin
{
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

        Craft::info('ScrapeKit plugin loaded', __METHOD__);
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
        return Craft::$app->getView()->renderTemplate('scrapekit/settings', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ], View::TEMPLATE_MODE_CP);
    }
}
