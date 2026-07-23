<?php

declare(strict_types=1);

namespace viesrood\scrapekit\tests;

use PHPUnit\Framework\TestCase;

final class SettingsTemplateTest extends TestCase
{
    public function testCraftAppliesTheSettingsNamespaceOnce(): void
    {
        $template = file_get_contents(dirname(__DIR__) . '/src/templates/settings.twig');

        self::assertIsString($template);
        self::assertStringContainsString("name: 'cacheDuration'", $template);
        self::assertDoesNotMatchRegularExpression("/name:\\s*'settings\\[/", $template);
    }
}
