<?php
if (!function_exists('http_post_form')) {
    function http_post_form(string $url, array $fields): array {
        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($fields),
                'timeout' => 15,
            ],
        ];
        $ctx  = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) return [null, 'HTTP request failed'];
        $data = json_decode($resp, true);
        if (!is_array($data)) return [null, 'Invalid JSON'];
        return [$data, null];
    }
}

if (!function_exists('http_get_json')) {
    function http_get_json(string $url, array $headers = []): array {
        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        $opts = [
            'http' => [
                'method'  => 'GET',
                'header'  => implode("\r\n", $headerLines) . "\r\n",
                'timeout' => 15,
            ],
        ];
        $ctx  = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp === false) return [null, 'HTTP request failed'];
        $data = json_decode($resp, true);
        if (!is_array($data)) return [null, 'Invalid JSON'];
        return [$data, null];
    }
}
