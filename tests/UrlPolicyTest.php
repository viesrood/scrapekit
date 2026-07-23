<?php

declare(strict_types=1);

namespace viesrood\scrapekit\tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use viesrood\scrapekit\http\UrlPolicy;
use viesrood\scrapekit\models\Settings;

final class UrlPolicyTest extends TestCase
{
    #[DataProvider('blockedUrls')]
    public function testUnsafeUrlsAreBlocked(string $url, string $errorCode): void
    {
        $policy = new UrlPolicy(new Settings());

        try {
            $policy->assertAllowed($url);
            self::fail('Expected URL to be rejected.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame($errorCode, $e->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function blockedUrls(): iterable
    {
        yield 'unsupported scheme' => ['file:///etc/passwd', 'invalid_url'];
        yield 'credentials' => ['https://user:secret@example.com', 'url_credentials_not_allowed'];
        yield 'custom port' => ['https://8.8.8.8:8443/', 'port_not_allowed'];
        yield 'IPv4 loopback' => ['http://127.0.0.1/', 'private_network_not_allowed'];
        yield 'cloud metadata' => ['http://169.254.169.254/', 'private_network_not_allowed'];
        yield 'IPv6 loopback' => ['http://[::1]/', 'private_network_not_allowed'];
        yield 'mapped loopback' => ['http://[::ffff:127.0.0.1]/', 'private_network_not_allowed'];
        yield 'IPv6 link-local' => ['http://[fe80::1]/', 'private_network_not_allowed'];
        yield 'CGNAT' => ['http://100.64.0.1/', 'private_network_not_allowed'];
    }

    public function testPublicIpLiteralIsAllowedAndNeedsNoPinning(): void
    {
        // An IP-literal host validates but returns no pin list (curl connects to
        // the literal address directly).
        $addresses = (new UrlPolicy(new Settings()))->assertAllowed('https://8.8.8.8/');
        self::assertSame([], $addresses);
    }

    public function testAllowlistAndPrivateNetworkOverride(): void
    {
        $settings = new Settings([
            'allowedHosts' => '127.0.0.1',
            'allowPrivateNetworks' => true,
        ]);

        // Private networks trusted: allowed, and no pinning is applied.
        $addresses = (new UrlPolicy($settings))->assertAllowed('http://127.0.0.1/');
        self::assertSame([], $addresses);
    }
}
