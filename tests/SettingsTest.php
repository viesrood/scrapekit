<?php

declare(strict_types=1);

namespace viesrood\scrapekit\tests;

use PHPUnit\Framework\TestCase;
use viesrood\scrapekit\models\Settings;

final class SettingsTest extends TestCase
{
    public function testDefaultsAreValid(): void
    {
        $settings = new Settings();

        self::assertTrue($settings->validate(), json_encode($settings->getErrors()) ?: '');
        self::assertSame([80, 443], $settings->getAllowedPorts());
        self::assertSame([], $settings->getAllowedHosts());
        self::assertFalse($settings->allowPrivateNetworks);
    }

    public function testHostAndPortListsAreNormalized(): void
    {
        $settings = new Settings([
            'allowedHosts' => "Example.com\n*.Example.org, example.com",
            'allowedPorts' => '443, 8443,443',
        ]);

        self::assertSame(['example.com', '*.example.org'], $settings->getAllowedHosts());
        self::assertSame([443, 8443], $settings->getAllowedPorts());
    }

    public function testInvalidLimitsAreRejected(): void
    {
        $settings = new Settings([
            'timeout' => 0,
            'maxResponseBytes' => 100,
            'allowedPorts' => 'invalid',
        ]);

        self::assertFalse($settings->validate());
        self::assertArrayHasKey('timeout', $settings->getErrors());
        self::assertArrayHasKey('maxResponseBytes', $settings->getErrors());
        self::assertArrayHasKey('allowedPorts', $settings->getErrors());
    }
}
