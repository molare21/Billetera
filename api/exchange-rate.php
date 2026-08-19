<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$cacheFile = sys_get_temp_dir() . '/aldia-usd-cop-rate.json';
$cacheSeconds = 900;

function emitRate(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheSeconds) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['rate'])) {
        $cached['cached'] = true;
        emitRate($cached);
    }
}

$url = 'https://api.frankfurter.dev/v2/rate/USD/COP';
$body = false;

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'AL-DIA/1.0'
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) $body = false;
}

if ($body === false) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 8,
            'header' => "Accept: application/json\r\nUser-Agent: AL-DIA/1.0\r\n"
        ]
    ]);
    $body = @file_get_contents($url, false, $context);
}

if ($body !== false) {
    $json = json_decode($body, true);

    if (is_array($json) && isset($json['rate']) && is_numeric($json['rate'])) {
        $payload = [
            'success' => true,
            'base' => 'USD',
            'quote' => 'COP',
            'rate' => (float)$json['rate'],
            'date' => $json['date'] ?? null,
            'source' => 'Frankfurter',
            'cached' => false,
            'checked_at' => gmdate('c')
        ];

        @file_put_contents($cacheFile, json_encode($payload));
        emitRate($payload);
    }
}

if (is_file($cacheFile)) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached) && isset($cached['rate'])) {
        $cached['cached'] = true;
        $cached['stale'] = true;
        emitRate($cached);
    }
}

http_response_code(503);
emitRate([
    'success' => false,
    'message' => 'No se pudo consultar la tasa USD/COP en este momento.'
]);
