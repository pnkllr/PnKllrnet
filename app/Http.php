<?php
class Http {
  static function getJSON(string $url, array $headers=[]): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_HTTPHEADER=>$headers,
      CURLOPT_TIMEOUT=>15
    ]);
    $out = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return [$out ? json_decode($out, true) : null, $err ?: null];
  }
  static function postForm(string $url, array $data, array $headers=[]): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_POST=>true,
      CURLOPT_POSTFIELDS=>http_build_query($data),
      CURLOPT_HTTPHEADER=>$headers,
      CURLOPT_TIMEOUT=>15
    ]);
    $out = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return [$out ? json_decode($out, true) : null, $err ?: null];
  }
}
