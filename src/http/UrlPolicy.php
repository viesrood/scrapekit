<?php

declare(strict_types=1);

namespace viesrood\scrapekit\http;

use viesrood\scrapekit\models\Settings;

final class UrlPolicy
{
    public function __construct(
        private readonly Settings $settings,
    ) {
    }

    /**
     * Validate a URL against the configured policy.
     *
     * Returns the resolved public IP addresses that the request may connect to,
     * so the caller can pin the connection to exactly those addresses (defeating
     * DNS rebinding). Returns an empty array when no pinning is needed/possible:
     * private networks are trusted, or the host is already an IP literal.
     *
     * @return string[]
     * @throws \InvalidArgumentException
     */
    public function assertAllowed(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('invalid_url');
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('unsupported_scheme');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \InvalidArgumentException('url_credentials_not_allowed');
        }

        $host = $this->normalizeHost($parts['host']);
        $port = (int)($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (!in_array($port, $this->settings->getAllowedPorts(), true)) {
            throw new \InvalidArgumentException('port_not_allowed');
        }

        if (!$this->hostMatchesAllowlist($host)) {
            throw new \InvalidArgumentException('host_not_allowed');
        }

        if ($this->settings->allowPrivateNetworks) {
            return [];
        }

        // An IP-literal host: validate it directly; curl connects to it as-is,
        // so there is nothing to pin.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!$this->isPublicIp($host)) {
                throw new \InvalidArgumentException('private_network_not_allowed');
            }

            return [];
        }

        $addresses = $this->resolve($host);
        if ($addresses === []) {
            throw new \InvalidArgumentException('dns_resolution_failed');
        }

        foreach ($addresses as $address) {
            if (!$this->isPublicIp($address)) {
                throw new \InvalidArgumentException('private_network_not_allowed');
            }
        }

        return $addresses;
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(rtrim(trim($host, '[]'), '.'));
        if (function_exists('idn_to_ascii') && !filter_var($host, FILTER_VALIDATE_IP)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $host = strtolower($ascii);
            }
        }

        return $host;
    }

    private function hostMatchesAllowlist(string $host): bool
    {
        $allowedHosts = $this->settings->getAllowedHosts();
        if ($allowedHosts === []) {
            return true;
        }

        foreach ($allowedHosts as $pattern) {
            $pattern = $this->normalizeHost($pattern);
            if ($host === $pattern) {
                return true;
            }

            if (str_starts_with($pattern, '*.') && str_ends_with($host, substr($pattern, 1))) {
                $suffix = substr($pattern, 2);
                if ($host !== $suffix) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        foreach ($records as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($address)) {
                $addresses[] = $address;
            }
        }

        return array_values(array_unique($addresses));
    }

    private function isPublicIp(string $address): bool
    {
        // Unwrap IPv4-mapped IPv6 (::ffff:a.b.c.d) and re-check as IPv4.
        if (stripos($address, '::ffff:') === 0) {
            $mapped = substr($address, (int)strrpos($address, ':') + 1);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $this->isPublicIp($mapped);
            }
        }

        if (filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false) {
            return false;
        }

        // Ranges PHP's filter flags do not cover.
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            // 100.64.0.0/10 - carrier-grade NAT (RFC 6598).
            if ($this->ipv4InCidr($address, '100.64.0.0', 10)) {
                return false;
            }

            return true;
        }

        // fe80::/10 - IPv6 link-local.
        $binary = @inet_pton($address);
        if ($binary !== false && strlen($binary) === 16
            && ord($binary[0]) === 0xfe && (ord($binary[1]) & 0xc0) === 0x80) {
            return false;
        }

        return true;
    }

    private function ipv4InCidr(string $ip, string $subnet, int $bits): bool
    {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
