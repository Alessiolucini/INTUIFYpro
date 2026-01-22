<?php
/**
 * Test script for n8n webhook integration
 * This file helps debug webhook connectivity issues
 */

// Test data
$testData = [
    'nombre_completo' => 'Test User',
    'empresa' => 'Test Company',
    'email' => 'test@example.com',
    'mensaje' => 'This is a test message from the webhook test script',
    'lang' => 'it',
    'timestamp' => date('c')
];

$webhookUrl = 'https://n8n-n8n-60a08c-72-60-34-31.traefik.me/webhook/73129db0-a899-412d-b0f3-0a32fac8b692';

echo "Testing webhook connection...\n";
echo "URL: $webhookUrl\n";
echo "Payload: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Initialize cURL
$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_VERBOSE => true
]);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlInfo = curl_getinfo($ch);

curl_close($ch);

// Display results
echo "=== RESULTS ===\n";
echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($curlError) {
    echo "cURL Error: $curlError\n";
}

echo "\n=== cURL Info ===\n";
echo "Total Time: " . $curlInfo['total_time'] . " seconds\n";
echo "Connect Time: " . $curlInfo['connect_time'] . " seconds\n";
echo "Size Download: " . $curlInfo['size_download'] . " bytes\n";

if ($httpCode >= 200 && $httpCode < 300) {
    echo "\n✅ SUCCESS! Webhook is working correctly.\n";
} else {
    echo "\n❌ FAILED! HTTP Code: $httpCode\n";
}
