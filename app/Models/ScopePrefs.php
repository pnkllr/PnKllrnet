<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/Twitch/helpers.php';  // <-- add this

final class ScopePrefs {
  public static function getDesired(int $userId): array {
    $db = $GLOBALS['_db'];
    $raw = $db->query(
      "SELECT scope_str FROM user_desired_scopes WHERE user_id=?",
      [$userId]
    )->fetchColumn();
    return $raw ? parse_scope_str((string)$raw) : [];
  }

  public static function saveDesired(int $userId, array $scopes): void {
    $db = $GLOBALS['_db'];
    $raw = scope_str($scopes);  // uses helper
    $exists = $db->query(
      "SELECT 1 FROM user_desired_scopes WHERE user_id=?",
      [$userId]
    )->fetchColumn();

    if ($exists) {
      $db->query(
        "UPDATE user_desired_scopes SET scope_str=?, updated_at=NOW() WHERE user_id=?",
        [$raw, $userId]
      );
    } else {
      $db->insert(
        "INSERT INTO user_desired_scopes (user_id, scope_str) VALUES (?,?)",
        [$userId, $raw]
      );
    }
  }
}
