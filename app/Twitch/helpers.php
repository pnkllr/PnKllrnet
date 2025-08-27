<?php
function scopes_normalize(array $scopes): array {
  $out = [];
  foreach ($scopes as $s) {
    $s = trim((string)$s);
    if ($s !== '') $out[$s] = true;
  }
  return array_keys($out);
}
function parse_scope_str(string $s): array {
  $s = trim($s);
  if ($s === '') return [];
  return scopes_normalize(preg_split('/\s+/', $s));
}
function scope_str(array $scopes): string {
  return implode(' ', scopes_normalize($scopes));
}
