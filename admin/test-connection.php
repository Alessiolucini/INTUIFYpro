<?php
/**
 * IntuiFy — Supabase Connection Test
 * DELETE THIS FILE AFTER TESTING!
 * Access: https://intuify.net/admin/test-connection.php
 */

header('Content-Type: application/json');

$config = require dirname(__DIR__) . '/config.php';

$results = [];

// 1. Check Supabase URL resolution
$url = $config['supabase_url'];
$results['supabase_url'] = $url;

// 2. Test DNS resolution
$host = parse_url($url, PHP_URL_HOST);
$ip = gethostbyname($host);
$results['dns_resolution'] = [
    'host' => $host,
    'ip' => $ip,
    'resolved' => ($ip !== $host),
];

// 3. Test basic REST API connection
$testUrl = rtrim($url, '/') . '/rest/v1/leads?select=count&limit=1';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $config['supabase_service_key'],
        'Authorization: Bearer ' . $config['supabase_service_key'],
        'Content-Type: application/json',
        'Prefer: count=exact',
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_VERBOSE => true,
]);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);
curl_close($ch);

rewind($verbose);
$verboseLog = stream_get_contents($verbose);

$results['rest_api_test'] = [
    'url' => $testUrl,
    'http_code' => $httpCode,
    'response' => json_decode($response, true) ?? $response,
    'curl_error' => $error,
    'curl_errno' => $errno,
];

// 4. Test INSERT (write)
$testUrl2 = rtrim($url, '/') . '/rest/v1/leads';
$testData = [
    'name' => 'TEST CONNECTION',
    'email' => 'test@test.com',
    'company' => 'Test Company',
    'message' => 'Test di connessione - eliminare',
    'source' => 'other',
    'status' => 'new',
];

$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $testUrl2,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => [
        'apikey: ' . $config['supabase_service_key'],
        'Authorization: Bearer ' . $config['supabase_service_key'],
        'Content-Type: application/json',
        'Prefer: return=representation',
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$error2 = curl_error($ch2);
curl_close($ch2);

$results['insert_test'] = [
    'http_code' => $httpCode2,
    'response' => json_decode($response2, true) ?? $response2,
    'curl_error' => $error2,
    'success' => ($httpCode2 >= 200 && $httpCode2 < 300),
];

// 5. SSL test without verification (for comparison)
if (!empty($error)) {
    $ch3 = curl_init();
    curl_setopt_array($ch3, [
        CURLOPT_URL => rtrim($url, '/') . '/rest/v1/leads?limit=1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $config['supabase_service_key'],
            'Authorization: Bearer ' . $config['supabase_service_key'],
        ],
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response3 = curl_exec($ch3);
    $httpCode3 = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    $error3 = curl_error($ch3);
    curl_close($ch3);

    $results['ssl_bypass_test'] = [
        'http_code' => $httpCode3,
        'curl_error' => $error3,
        'works_without_ssl_verify' => ($httpCode3 >= 200 && $httpCode3 < 300),
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
