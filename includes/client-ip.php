<?php
/**
 * IntuiFy — real visitor IP (used by rate limits, login throttling and the audit log).
 *
 * Chain: visitor → Cloudflare → Traefik → Apache → PHP.
 * mod_remoteip trusts only Traefik (private Docker ranges, see Dockerfile), so REMOTE_ADDR is
 * whoever connected to Traefik: a Cloudflare edge for normal traffic, or the client itself
 * when someone reaches the server IP directly.
 * CF-Connecting-IP is trusted only when that peer is a Cloudflare edge: anyone else could forge it.
 */

declare(strict_types=1);

/** https://www.cloudflare.com/ips/ (checked 2026-10-03) — update if Cloudflare publishes changes. */
const CLOUDFLARE_IP_RANGES = [
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
];

function ipInCidr(string $ip, string $cidr): bool
{
    [$subnet, $bits] = explode('/', $cidr, 2);
    $ipBin = @inet_pton($ip);
    $subnetBin = @inet_pton($subnet);
    if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
        return false;
    }
    $bits = (int) $bits;
    $bytes = intdiv($bits, 8);
    if (strncmp($ipBin, $subnetBin, $bytes) !== 0) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest === 0) {
        return true;
    }
    $mask = (0xFF << (8 - $rest)) & 0xFF;
    return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
}

function isCloudflareIp(string $ip): bool
{
    foreach (CLOUDFLARE_IP_RANGES as $cidr) {
        if (ipInCidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function clientIp(): string
{
    $peer = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $visitor = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($visitor !== '' && filter_var($visitor, FILTER_VALIDATE_IP) !== false && isCloudflareIp($peer)) {
        return $visitor;
    }
    return $peer;
}
