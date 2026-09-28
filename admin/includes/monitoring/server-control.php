<?php
/**
 * IntuiFy — Server Control Center service layer (read-only MVP).
 *
 * Builds the JSON the admin UI renders, from Prometheus + Node Exporter + cAdvisor
 * + Blackbox. Metrics are never copied to Supabase (spec §62).
 * Every public function returns plain arrays with a `generatedAt` timestamp.
 */

declare(strict_types=1);

require_once __DIR__ . '/prometheus.php';

// Scrape job names — must match monitoring/prometheus/prometheus.yml
const SCC_NODE      = 'job="node"';
const SCC_CADVISOR  = 'job="cadvisor"';
const SCC_BLACKBOX  = 'job="blackbox-http"';

// Host traffic only: skip loopback and the virtual interfaces Docker creates per container
const SCC_NET_DEVICES = 'device!~"lo|veth.*|docker.*|br-.*|virbr.*|cali.*|flannel.*|vxlan.*"';
// Real filesystems only
const SCC_REAL_FS = 'fstype!~"tmpfs|overlay|squashfs|nsfs|ramfs|devtmpfs|autofs|fuse.*|proc|sysfs"';

/** A container counts as running if cAdvisor saw it in the last N seconds. */
const SCC_CONTAINER_FRESH_SECONDS = 60;

/** Initial thresholds (spec §6). Durations are enforced by Prometheus alert rules. */
const SCC_THRESHOLDS = [
    'cpu'             => ['warning' => 75,  'critical' => 90],
    'memory'          => ['warning' => 80,  'critical' => 92],
    'disk'            => ['warning' => 75,  'critical' => 90],
    'inode'           => ['warning' => 75,  'critical' => 90],
    'latency_seconds' => ['warning' => 2.0, 'critical' => 5.0],
];
/** SSL: fewer days left = worse. */
const SCC_SSL_DAYS = ['warning' => 30, 'critical' => 7];

/** Chart ranges: [seconds, step]. ~120–180 points each (spec §14 selector). */
const SCC_RANGES = [
    '1h'  => [3600, 30],
    '6h'  => [21600, 180],
    '24h' => [86400, 600],
    '7d'  => [604800, 3600],
    '30d' => [2592000, 14400],
];

// =============================================================================
// Helpers
// =============================================================================

function sccNow(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

/** healthy | warning | critical | unknown for "higher is worse" metrics. */
function sccLevel(?float $value, array $t): string
{
    if ($value === null) {
        return 'unknown';
    }
    if ($value >= $t['critical']) {
        return 'critical';
    }
    return $value >= $t['warning'] ? 'warning' : 'healthy';
}

function sccSslLevel(?float $days): string
{
    if ($days === null) {
        return 'unknown';
    }
    if ($days < SCC_SSL_DAYS['critical']) {
        return 'critical';
    }
    return $days < SCC_SSL_DAYS['warning'] ? 'warning' : 'healthy';
}

/** Worst of the given levels; `unknown` only wins if nothing else is known. */
function sccWorst(array $levels): string
{
    $rank = ['critical' => 3, 'warning' => 2, 'healthy' => 1, 'unknown' => 0];
    $worst = 'unknown';
    foreach ($levels as $l) {
        if (($rank[$l] ?? 0) > $rank[$worst]) {
            $worst = $l;
        }
    }
    return $worst;
}

function sccPercent(?float $part, ?float $total): ?float
{
    if ($part === null || $total === null || $total <= 0) {
        return null;
    }
    return round($part / $total * 100, 1);
}

/** Index an instant-query result by one label. */
function sccByLabel(array $rows, string $label): array
{
    $out = [];
    foreach ($rows as $r) {
        $key = (string) ($r['metric'][$label] ?? '');
        $out[$key] = $r['value'];
    }
    return $out;
}

// =============================================================================
// Scrape targets ("monitoring del monitoring", spec §38)
// =============================================================================

/** @return array<string, array{up:int,total:int,level:string,lastError:string}> keyed by job */
function sccTargets(PrometheusClient $p): array
{
    $jobs = [];
    foreach ($p->targets() as $t) {
        $job = (string) ($t['labels']['job'] ?? $t['scrapePool'] ?? 'unknown');
        $jobs[$job] ??= ['up' => 0, 'total' => 0, 'lastError' => ''];
        $jobs[$job]['total']++;
        if (($t['health'] ?? '') === 'up') {
            $jobs[$job]['up']++;
        } elseif (!empty($t['lastError']) && $jobs[$job]['lastError'] === '') {
            // Keep only the error text, not the internal scrape URL
            $jobs[$job]['lastError'] = mb_substr((string) $t['lastError'], 0, 160);
        }
    }
    foreach ($jobs as $job => &$j) {
        $j['level'] = $j['up'] === $j['total'] ? 'healthy' : ($j['up'] === 0 ? 'critical' : 'warning');
    }
    unset($j);
    ksort($jobs);
    return $jobs;
}

// =============================================================================
// Alerts (spec §22)
// =============================================================================

function sccAlerts(PrometheusClient $p): array
{
    $items = [];
    $summary = ['critical' => 0, 'warning' => 0, 'pending' => 0];

    foreach ($p->alerts() as $a) {
        $severity = strtolower((string) ($a['labels']['severity'] ?? 'warning'));
        $state = (string) ($a['state'] ?? 'firing');
        if ($state === 'firing') {
            if ($severity === 'critical') {
                $summary['critical']++;
            } else {
                $summary['warning']++;
            }
        } else {
            $summary['pending']++;
        }
        $items[] = [
            'name'     => (string) ($a['labels']['alertname'] ?? 'Alert'),
            'severity' => $severity === 'critical' ? 'critical' : 'warning',
            'state'    => $state,
            'service'  => (string) ($a['labels']['name'] ?? $a['labels']['instance'] ?? $a['labels']['job'] ?? ''),
            'summary'  => (string) ($a['annotations']['summary'] ?? $a['annotations']['description'] ?? ''),
            'since'    => (string) ($a['activeAt'] ?? ''),
        ];
    }

    // Critical first, then oldest first
    usort($items, fn($x, $y) => [$x['severity'] !== 'critical', $x['since']] <=> [$y['severity'] !== 'critical', $y['since']]);

    return ['generatedAt' => sccNow(), 'summary' => $summary, 'items' => $items];
}

// =============================================================================
// Containers (cAdvisor, spec §16)
// =============================================================================

function sccContainers(PrometheusClient $p): array
{
    $sel = SCC_CADVISOR . ',name!=""';
    $groupLabels = 'name, image, container_label_com_docker_swarm_service_name, '
        . 'container_label_com_docker_compose_project, container_label_com_docker_compose_service, '
        . 'container_label_com_docker_stack_namespace';

    // Inventory: every container seen in the last 24h, with its last-seen time
    $seen = $p->query("max by ({$groupLabels}) (max_over_time(container_last_seen{{$sel}}[24h]))");

    $cpu   = sccByLabel($p->query("sum by (name) (rate(container_cpu_usage_seconds_total{{$sel}}[5m])) * 100"), 'name');
    $mem   = sccByLabel($p->query("sum by (name) (container_memory_working_set_bytes{{$sel}})"), 'name');
    $rx    = sccByLabel($p->query("sum by (name) (rate(container_network_receive_bytes_total{{$sel}}[5m]))"), 'name');
    $tx    = sccByLabel($p->query("sum by (name) (rate(container_network_transmit_bytes_total{{$sel}}[5m]))"), 'name');
    $start = sccByLabel($p->query("max by (name) (container_start_time_seconds{{$sel}})"), 'name');

    $now = time();
    $services = [];
    foreach ($seen as $row) {
        $m = $row['metric'];
        $name = (string) ($m['name'] ?? '');
        $lastSeen = $row['value'];
        $running = $lastSeen !== null && ($now - $lastSeen) < SCC_CONTAINER_FRESH_SECONDS;

        // Swarm replaces task containers on every deploy: group by service so an
        // old replica does not look like a stopped service.
        $service = (string) ($m['container_label_com_docker_swarm_service_name'] ?? '')
            ?: (string) ($m['container_label_com_docker_compose_service'] ?? '')
            ?: $name;
        $project = (string) ($m['container_label_com_docker_stack_namespace'] ?? '')
            ?: (string) ($m['container_label_com_docker_compose_project'] ?? '')
            ?: (preg_match('/^(dokploy|traefik)/i', $name) ? 'System' : '—');

        $entry = [
            'name'       => $name,
            'service'    => $service,
            'project'    => $project,
            'image'      => (string) ($m['image'] ?? ''),
            'status'     => $running ? 'running' : 'stopped',
            'health'     => null, // not exposed by cAdvisor — needs the Server Agent (Fase 3)
            'cpuPercent' => $running && isset($cpu[$name]) ? round($cpu[$name], 2) : null,
            'memoryBytes' => $running ? ($mem[$name] ?? null) : null,
            'rxBytesPerSecond' => $running ? ($rx[$name] ?? null) : null,
            'txBytesPerSecond' => $running ? ($tx[$name] ?? null) : null,
            'uptimeSeconds' => $running && isset($start[$name]) ? max(0, (int) ($now - $start[$name])) : null,
            'lastSeen'   => $lastSeen !== null ? gmdate('Y-m-d\TH:i:s\Z', (int) $lastSeen) : null,
        ];

        $key = $project . '/' . $service;
        // Keep the running replica; if none is running keep the most recent one
        if (!isset($services[$key])
            || ($entry['status'] === 'running' && $services[$key]['status'] !== 'running')
            || ($entry['status'] === $services[$key]['status'] && $entry['lastSeen'] > $services[$key]['lastSeen'])) {
            $services[$key] = $entry;
        }
    }

    $items = array_values($services);
    usort($items, fn($a, $b) => [$a['status'] !== 'stopped', -($a['cpuPercent'] ?? 0)] <=> [$b['status'] !== 'stopped', -($b['cpuPercent'] ?? 0)]);

    $running = count(array_filter($items, fn($c) => $c['status'] === 'running'));
    $critical = sccCriticalContainerPattern();
    $stoppedCritical = count(array_filter(
        $items,
        fn($c) => $c['status'] === 'stopped' && $critical !== '' && preg_match($critical, $c['service'] . ' ' . $c['name'])
    ));

    return [
        'generatedAt' => sccNow(),
        'summary' => [
            'running'         => $running,
            'stopped'         => count($items) - $running,
            'stoppedCritical' => $stoppedCritical,
            'unhealthy'       => null, // see 'health' above
        ],
        'items' => $items,
    ];
}

/** Regex for containers whose absence is critical (env MONITORING_CRITICAL_CONTAINERS). */
function sccCriticalContainerPattern(): string
{
    static $pattern = null;
    if ($pattern === null) {
        $config = require dirname(__DIR__, 3) . '/config.php';
        $raw = (string) ($config['monitoring_critical_containers'] ?? '');
        $pattern = $raw !== '' ? '~(' . str_replace('~', '\~', $raw) . ')~i' : '';
    }
    return $pattern;
}

// =============================================================================
// Domains & SSL (Blackbox, spec §20)
// =============================================================================

function sccDomains(PrometheusClient $p): array
{
    $sel = SCC_BLACKBOX;
    $success = $p->query("max by (instance, project, critical) (probe_success{{$sel}})");
    $status  = sccByLabel($p->query("max by (instance) (probe_http_status_code{{$sel}})"), 'instance');
    $latency = sccByLabel($p->query("max by (instance) (probe_duration_seconds{{$sel}})"), 'instance');
    $expiry  = sccByLabel($p->query("min by (instance) (probe_ssl_earliest_cert_expiry{{$sel}})"), 'instance');

    $now = time();
    $items = [];
    foreach ($success as $row) {
        $url = (string) ($row['metric']['instance'] ?? '');
        $up = $row['value'] === 1.0;
        $sslDays = isset($expiry[$url]) ? round(($expiry[$url] - $now) / 86400, 1) : null;
        $lat = $latency[$url] ?? null;

        $httpLevel = $up ? 'healthy' : (($row['metric']['critical'] ?? '') === 'true' ? 'critical' : 'warning');
        $items[] = [
            'url'        => $url,
            'host'       => (string) (parse_url($url, PHP_URL_HOST) ?: $url),
            'project'    => (string) ($row['metric']['project'] ?? '—'),
            'online'     => $up,
            'httpStatus' => isset($status[$url]) ? (int) $status[$url] : null,
            'latencyMs'  => $lat !== null ? (int) round($lat * 1000) : null,
            'sslDaysLeft' => $sslDays,
            'level'      => sccWorst([
                $httpLevel,
                str_starts_with($url, 'https://') ? sccSslLevel($sslDays) : 'unknown',
                $up ? sccLevel($lat, SCC_THRESHOLDS['latency_seconds']) : 'unknown',
            ]),
        ];
    }
    usort($items, fn($a, $b) => [$a['online'], $a['host']] <=> [$b['online'], $b['host']]);

    return [
        'generatedAt' => sccNow(),
        'summary' => [
            'total'       => count($items),
            'online'      => count(array_filter($items, fn($d) => $d['online'])),
            'sslExpiring' => count(array_filter($items, fn($d) => $d['sslDaysLeft'] !== null && $d['sslDaysLeft'] < SCC_SSL_DAYS['warning'])),
        ],
        'items' => $items,
    ];
}

// =============================================================================
// Host metrics (Node Exporter)
// =============================================================================

function sccHost(PrometheusClient $p): array
{
    $n = SCC_NODE;
    $cpu      = $p->scalar("100 - (avg(rate(node_cpu_seconds_total{{$n},mode=\"idle\"}[5m])) * 100)");
    $cores    = $p->scalar("count(node_cpu_seconds_total{{$n},mode=\"idle\"})");
    $memTotal = $p->scalar("sum(node_memory_MemTotal_bytes{{$n}})");
    $memAvail = $p->scalar("sum(node_memory_MemAvailable_bytes{{$n}})");
    $swapTot  = $p->scalar("sum(node_memory_SwapTotal_bytes{{$n}})");
    $swapFree = $p->scalar("sum(node_memory_SwapFree_bytes{{$n}})");
    $diskSize = $p->scalar("max(node_filesystem_size_bytes{{$n},mountpoint=\"/\"," . SCC_REAL_FS . "})");
    $diskAvail = $p->scalar("max(node_filesystem_avail_bytes{{$n},mountpoint=\"/\"," . SCC_REAL_FS . "})");
    $rx       = $p->scalar("sum(rate(node_network_receive_bytes_total{{$n}," . SCC_NET_DEVICES . "}[5m]))");
    $tx       = $p->scalar("sum(rate(node_network_transmit_bytes_total{{$n}," . SCC_NET_DEVICES . "}[5m]))");
    $boot     = $p->scalar("max(node_boot_time_seconds{{$n}})");
    $load     = sccByLabel($p->query("label_replace(node_load1{{$n}}, \"w\", \"1\", \"\", \"\") or label_replace(node_load5{{$n}}, \"w\", \"5\", \"\", \"\") or label_replace(node_load15{{$n}}, \"w\", \"15\", \"\", \"\")"), 'w');

    $memUsed  = ($memTotal !== null && $memAvail !== null) ? $memTotal - $memAvail : null;
    $diskUsed = ($diskSize !== null && $diskAvail !== null) ? $diskSize - $diskAvail : null;
    $loadPerCore = ($cores && isset($load['1'])) ? round($load['1'] / $cores, 2) : null;

    return [
        'cpu' => [
            'usagePercent' => $cpu !== null ? round($cpu, 1) : null,
            'cores'        => $cores !== null ? (int) $cores : null,
            'level'        => sccLevel($cpu, SCC_THRESHOLDS['cpu']),
        ],
        'memory' => [
            'usedBytes'    => $memUsed,
            'totalBytes'   => $memTotal,
            'usagePercent' => sccPercent($memUsed, $memTotal),
            'level'        => sccLevel(sccPercent($memUsed, $memTotal), SCC_THRESHOLDS['memory']),
            'swapUsedBytes'  => ($swapTot !== null && $swapFree !== null) ? $swapTot - $swapFree : null,
            'swapTotalBytes' => $swapTot,
        ],
        'disk' => [
            'mount'        => '/',
            'usedBytes'    => $diskUsed,
            'freeBytes'    => $diskAvail,
            'totalBytes'   => $diskSize,
            'usagePercent' => sccPercent($diskUsed, $diskSize),
            'level'        => sccLevel(sccPercent($diskUsed, $diskSize), SCC_THRESHOLDS['disk']),
        ],
        'network' => [
            'rxBytesPerSecond' => $rx,
            'txBytesPerSecond' => $tx,
        ],
        'load' => [
            'load1'  => $load['1'] ?? null,
            'load5'  => $load['5'] ?? null,
            'load15' => $load['15'] ?? null,
            'perCore' => $loadPerCore,
            // spec §6: warning > cores × 0.8, critical > cores × 1.2
            'level'  => sccLevel($loadPerCore, ['warning' => 0.8, 'critical' => 1.2]),
        ],
        'uptimeSeconds' => $boot !== null ? max(0, time() - (int) $boot) : null,
        'bootTime'      => $boot !== null ? gmdate('Y-m-d\TH:i:s\Z', (int) $boot) : null,
    ];
}

// =============================================================================
// Overview (spec §14, §44, §54)
// =============================================================================

function sccOverview(PrometheusClient $p): array
{
    $targets    = sccTargets($p);
    $host       = sccHost($p);
    $containers = sccContainers($p);
    $domains    = sccDomains($p);
    $alerts     = sccAlerts($p);
    $filesystems = sccFilesystems($p);

    $nodeUp = ($targets['node']['up'] ?? 0) > 0 && $host['cpu']['usagePercent'] !== null;
    $cadvisorUp = ($targets['cadvisor']['up'] ?? 0) > 0;

    // Critical services (spec §14). Dokploy/Traefik are recognised from running containers.
    $findRunning = function (string $regex) use ($containers): string {
        $match = array_filter($containers['items'], fn($c) => preg_match($regex, $c['service'] . ' ' . $c['name']));
        if (!$match) {
            return 'unknown';
        }
        return array_filter($match, fn($c) => $c['status'] === 'running') ? 'healthy' : 'critical';
    };
    $supabase = array_values(array_filter($domains['items'], fn($d) => str_contains($d['host'], 'supabase.intuify.net')));

    $services = [
        ['name' => 'Dokploy',        'level' => $cadvisorUp ? $findRunning('~(^|\s)dokploy($|[\s.])~i') : 'unknown'],
        ['name' => 'Traefik',        'level' => $cadvisorUp ? $findRunning('~traefik~i') : 'unknown'],
        ['name' => 'Docker',         'level' => $cadvisorUp ? ($containers['summary']['running'] > 0 ? 'healthy' : 'critical') : 'unknown'],
        ['name' => 'Prometheus',     'level' => 'healthy'], // we just queried it
        ['name' => 'Supabase IntuiFy', 'level' => $supabase ? ($supabase[0]['online'] ? 'healthy' : 'critical') : 'unknown'],
        ['name' => 'Backups',        'level' => 'unknown', 'note' => 'Fase 2'],
    ];

    $attention = sccNeedsAttention($host, $filesystems, $containers, $domains, $alerts, $targets);

    // Server status (spec §7): unknown if node-exporter is not answering
    if (!$nodeUp) {
        $status = 'unknown';
    } else {
        $levels = [$host['cpu']['level'], $host['memory']['level'], $host['disk']['level'], $host['load']['level']];
        foreach ($filesystems as $fs) {
            $levels[] = $fs['level'];
        }
        if ($alerts['summary']['critical'] > 0 || $containers['summary']['stoppedCritical'] > 0) {
            $levels[] = 'critical';
        } elseif ($alerts['summary']['warning'] > 0) {
            $levels[] = 'warning';
        }
        foreach ($domains['items'] as $d) {
            $levels[] = $d['level'];
        }
        $status = sccWorst($levels);
    }

    return [
        'generatedAt' => sccNow(),
        'status'      => $status,
        'host'        => $host,
        'containers'  => $containers['summary'],
        'domains'     => $domains['summary'],
        'alerts'      => $alerts['summary'],
        'services'    => $services,
        'monitoring'  => array_map(fn($j) => ['up' => $j['up'], 'total' => $j['total'], 'level' => $j['level']], $targets),
        'needsAttention' => $attention,
    ];
}

/**
 * "Needs Attention" (spec §32): ordered list of what to look at first.
 */
function sccNeedsAttention(array $host, array $filesystems, array $containers, array $domains, array $alerts, array $targets): array
{
    $items = [];
    $add = function (int $priority, string $level, string $title, string $detail, string $view) use (&$items) {
        $items[] = compact('priority', 'level', 'title', 'detail', 'view');
    };

    // Resources already covered by a firing critical alert are not listed twice
    $alerted = [];
    foreach ($alerts['items'] as $a) {
        if ($a['state'] === 'firing' && $a['severity'] === 'critical') {
            $add(1, 'critical', $a['summary'] ?: $a['name'], $a['name'] . ($a['service'] ? ' · ' . $a['service'] : ''), 'alerts');
            $alerted[$a['service']] = true;
        }
    }
    foreach ($targets as $job => $t) {
        if ($t['level'] !== 'healthy') {
            $add(1, 'critical', "Monitoring: {$job}", "{$t['up']}/{$t['total']} target attivi", 'server');
        }
    }
    foreach ($filesystems as $fs) {
        if (in_array($fs['level'], ['warning', 'critical'], true)) {
            $inodes = $fs['inodePercent'] !== null ? " · inode {$fs['inodePercent']}%" : '';
            $add(2, $fs['level'], "Spazio disco {$fs['mount']}", "Al {$fs['usagePercent']}% · liberi " . round(($fs['freeBytes'] ?? 0) / 1e9) . " GB{$inodes}", 'server');
        }
    }
    foreach ($containers['items'] as $c) {
        if ($c['status'] === 'stopped') {
            $add(3, 'warning', "Container fermo: {$c['service']}", 'Ultimo segnale ' . ($c['lastSeen'] ?? 'n/d'), 'containers');
        }
    }
    foreach ($domains['items'] as $d) {
        if (!$d['online'] && !isset($alerted[$d['url']])) {
            $add(3, $d['level'], "{$d['host']} non risponde", 'HTTP ' . ($d['httpStatus'] ?: 'nessuna risposta'), 'domains');
        } elseif ($d['online'] && $d['sslDaysLeft'] !== null && $d['sslDaysLeft'] < SCC_SSL_DAYS['warning']) {
            $add(6, sccSslLevel($d['sslDaysLeft']), "SSL in scadenza: {$d['host']}", "{$d['sslDaysLeft']} giorni", 'domains');
        }
    }
    foreach (['cpu' => 'CPU', 'memory' => 'RAM'] as $key => $label) {
        if (in_array($host[$key]['level'], ['warning', 'critical'], true)) {
            $add(7, $host[$key]['level'], "{$label} alta", "{$host[$key]['usagePercent']}%", 'server');
        }
    }

    usort($items, fn($a, $b) => [$a['priority'], $a['level'] !== 'critical'] <=> [$b['priority'], $b['level'] !== 'critical']);
    return array_map(fn($i) => array_diff_key($i, ['priority' => 1]), $items);
}

// =============================================================================
// Server detail (spec §15)
// =============================================================================

/**
 * Every real filesystem with space and inode usage (spec §15 table, §32 disk capacity).
 */
function sccFilesystems(PrometheusClient $p): array
{
    $n = SCC_NODE;
    $size   = $p->query("max by (mountpoint, device, fstype) (node_filesystem_size_bytes{{$n}," . SCC_REAL_FS . "})");
    $avail  = sccByLabel($p->query("max by (mountpoint) (node_filesystem_avail_bytes{{$n}," . SCC_REAL_FS . "})"), 'mountpoint');
    $files  = sccByLabel($p->query("max by (mountpoint) (node_filesystem_files{{$n}," . SCC_REAL_FS . "})"), 'mountpoint');
    $ffree  = sccByLabel($p->query("max by (mountpoint) (node_filesystem_files_free{{$n}," . SCC_REAL_FS . "})"), 'mountpoint');

    $filesystems = [];
    foreach ($size as $row) {
        $mount = (string) ($row['metric']['mountpoint'] ?? '');
        if ($mount === '' || isset($filesystems[$mount])) {
            continue;
        }
        $total = $row['value'];
        $free = $avail[$mount] ?? null;
        $used = ($total !== null && $free !== null) ? $total - $free : null;
        $inodePct = (isset($files[$mount], $ffree[$mount]) && $files[$mount] > 0)
            ? round((1 - $ffree[$mount] / $files[$mount]) * 100, 1) : null;
        $pct = sccPercent($used, $total);
        $filesystems[$mount] = [
            'mount'        => $mount,
            'device'       => (string) ($row['metric']['device'] ?? ''),
            'fstype'       => (string) ($row['metric']['fstype'] ?? ''),
            'totalBytes'   => $total,
            'usedBytes'    => $used,
            'freeBytes'    => $free,
            'usagePercent' => $pct,
            'inodePercent' => $inodePct,
            'level'        => sccWorst([sccLevel($pct, SCC_THRESHOLDS['disk']), sccLevel($inodePct, SCC_THRESHOLDS['inode'])]),
        ];
    }
    ksort($filesystems);
    return array_values($filesystems);
}

function sccServerDetail(PrometheusClient $p): array
{
    $n = SCC_NODE;
    $uname  = $p->query("node_uname_info{{$n}}")[0]['metric'] ?? [];
    $os     = $p->query("node_os_info{{$n}}")[0]['metric'] ?? [];
    $docker = $p->query('cadvisor_version_info{' . SCC_CADVISOR . '}')[0]['metric'] ?? [];

    return [
        'generatedAt' => sccNow(),
        'info' => [
            'hostname'      => (string) ($uname['nodename'] ?? ''),
            'os'            => (string) ($os['pretty_name'] ?? $docker['osVersion'] ?? ''),
            'kernel'        => (string) ($uname['release'] ?? $docker['kernelVersion'] ?? ''),
            'arch'          => (string) ($uname['machine'] ?? ''),
            'dockerVersion' => (string) ($docker['dockerVersion'] ?? ''),
            'cadvisorVersion' => (string) ($docker['cadvisorVersion'] ?? ''),
        ],
        'host'        => sccHost($p),
        'filesystems' => sccFilesystems($p),
        'monitoring'  => sccTargets($p),
    ];
}

// =============================================================================
// Charts (spec §14 time selector, §28 PromQL)
// =============================================================================

/**
 * @return array{metric:string, range:string, unit:string, series:array}
 */
function sccChart(PrometheusClient $p, string $metric, string $range): array
{
    if (!isset(SCC_RANGES[$range])) {
        throw new InvalidArgumentException('range');
    }
    [$span, $step] = SCC_RANGES[$range];
    $w = max(300, $step) . 's'; // rate window never shorter than the step
    $n = SCC_NODE;

    $defs = [
        'cpu' => ['percent', [
            'CPU' => "100 - (avg(rate(node_cpu_seconds_total{{$n},mode=\"idle\"}[{$w}])) * 100)",
        ]],
        'memory' => ['percent', [
            'RAM' => "100 * (1 - sum(node_memory_MemAvailable_bytes{{$n}}) / sum(node_memory_MemTotal_bytes{{$n}}))",
        ]],
        'network' => ['bytesPerSecond', [
            'Download (RX)' => "sum(rate(node_network_receive_bytes_total{{$n}," . SCC_NET_DEVICES . "}[{$w}]))",
            'Upload (TX)'   => "sum(rate(node_network_transmit_bytes_total{{$n}," . SCC_NET_DEVICES . "}[{$w}]))",
        ]],
        'disk_io' => ['bytesPerSecond', [
            'Lettura'   => "sum(rate(node_disk_read_bytes_total{{$n},device!~\"loop.*|ram.*|sr.*\"}[{$w}]))",
            'Scrittura' => "sum(rate(node_disk_written_bytes_total{{$n},device!~\"loop.*|ram.*|sr.*\"}[{$w}]))",
        ]],
        'load' => ['load', [
            '1m'  => "max(node_load1{{$n}})",
            '5m'  => "max(node_load5{{$n}})",
            '15m' => "max(node_load15{{$n}})",
        ]],
    ];
    if (!isset($defs[$metric])) {
        throw new InvalidArgumentException('metric');
    }

    [$unit, $queries] = $defs[$metric];
    $end = time();
    $start = $end - $span;
    $series = [];
    foreach ($queries as $label => $q) {
        $rows = $p->queryRange($q, $start, $end, $step);
        $series[] = ['label' => $label, 'points' => $rows[0]['values'] ?? []];
    }

    return ['generatedAt' => sccNow(), 'metric' => $metric, 'range' => $range, 'unit' => $unit, 'series' => $series];
}
