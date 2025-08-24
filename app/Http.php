<?php
class Http {
  public static function getJSON(string $url, array $headers=[]): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>20,
      CURLOPT_HTTPHEADER=>$headers ?: ['Accept: application/json']
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    return [$res ? json_decode($res, true) : null, $err ?: null];
  }
  public static function postForm(string $url, array $fields, array $headers=[]): array {
    $ch = curl_init($url);
    $def = ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'];
    $hh  = $headers ? array_merge($def, $headers) : $def;
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_TIMEOUT=>20,
      CURLOPT_POST=>true,
      CURLOPT_POSTFIELDS=>http_build_query($fields),
      CURLOPT_HTTPHEADER=>$hh
    ]);
    $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    return [$res ? json_decode($res, true) : null, $err ?: null];
  }
}
