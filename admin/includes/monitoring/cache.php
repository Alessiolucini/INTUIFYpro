<?php
/**
 * IntuiFy — tiny file cache for monitoring responses (spec §29).
 * Keeps Prometheus load constant no matter how many admin tabs are open.
 */

declare(strict_types=1);

/**
 * Return the cached value for $key if younger than $ttl seconds, otherwise compute and store it.
 * Exceptions from $compute are not cached.
 */
function monitoringCache(string $key, int $ttl, callable $compute): mixed
{
    $file = sys_get_temp_dir() . '/intuify_scc_' . md5($key) . '.json';

    if ($ttl > 0 && is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && array_key_exists('v', $cached)) {
            return $cached['v'];
        }
    }

    $value = $compute();
    if ($ttl > 0) {
        file_put_contents($file, json_encode(['v' => $value]), LOCK_EX);
    }
    return $value;
}
