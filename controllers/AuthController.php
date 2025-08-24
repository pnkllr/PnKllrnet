<?php
// controllers/AuthController.php

require_once __DIR__ . '/../app/Config.php';
require_once __DIR__ . '/../app/DB.php';
require_once __DIR__ . '/../app/Auth.php';
require_once __DIR__ . '/../app/Twitch.php';

class AuthController {
  /**
   * GET /login
   * Renders a simple login page with a "Login with Twitch" button to /auth/start.
   */
  public static function showLogin() {
    // If already logged in, go straight to dashboard
    if (!empty($_SESSION['user_id'])) {
      header('Location: /'); exit;
    }
    // Minimal view: create views/login.php or inline here if you prefer
    if (function_exists('view')) {
      view('login');
    } else {
      // Fallback inline (only if you haven't created views/login.php)
      echo '<!doctype html><meta charset="utf-8"><title>Login</title>
            <body style="font-family:system-ui;margin:2rem;max-width:720px">
              <h1>Welcome</h1>
              <p>Sign in to access your dashboard.</p>
              <p><a href="/auth/start" style="display:inline-block;padding:.6rem 1rem;border-radius:10px;background:#5b61ff;color:#fff;text-decoration:none">Login with Twitch</a></p>
            </body>';
    }
  }

  /**
   * GET /auth/start
   * Starts the Twitch OAuth login (identification only).
   */
  public static function login() {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    header('Location: ' . Twitch::loginUrl($state));
    exit;
  }

  /**
   * GET /auth/callback
   * Handles the OAuth callback, creates/updates the user, and starts the session.
   */
  public static function loginCallback() {
    $stateOk = isset($_GET['state'], $_SESSION['oauth_state']) && hash_equals($_SESSION['oauth_state'], $_GET['state']);
    $code    = $_GET['code'] ?? null;

    if (!$stateOk || !$code) {
      error_log('[auth] Invalid login attempt: state or code mismatch');
      http_response_code(400);
      echo 'Invalid login attempt.';
      return;
    }

    // Exchange code -> short-lived access token (we do NOT store this token)
    [$tok, $err] = Twitch::exchange($code, Config::LOGIN_REDIRECT_URI);
    if ($err || empty($tok['access_token'])) {
      error_log('[auth] Token exchange failed: ' . ($err ?: 'no access_token'));
      http_response_code(400);
      echo 'Login failed (token exchange).';
      return;
    }

    // Fetch identity
    [$u, $uErr] = Twitch::users($tok['access_token']);
    $user = $u['data'][0] ?? null;
    if ($uErr || !$user) {
      error_log('[auth] /users failed: ' . ($uErr ?: 'no data'));
      http_response_code(400);
      echo 'Login failed (identity).';
      return;
    }

    // Upsert user and start app session
    try {
      $pdo = DB::pdo();
      $stmt = $pdo->prepare(
        "INSERT INTO users (twitch_user_id, display_name, email, avatar_url)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           display_name = VALUES(display_name),
           email        = VALUES(email),
           avatar_url   = VALUES(avatar_url)"
      );
      $stmt->execute([
        (string)$user['id'],
        (string)($user['display_name'] ?? $user['login'] ?? ''),
        isset($user['email']) ? (string)$user['email'] : null,
        (string)($user['profile_image_url'] ?? ''),
      ]);

      // Resolve local user id
      $id = $pdo->lastInsertId();
      if (!$id) {
        $id = $pdo->query("SELECT id FROM users WHERE twitch_user_id=" . $pdo->quote((string)$user['id']))->fetchColumn();
      }
      Auth::login((int)$id);

      // Done — go to dashboard
      header('Location: /');
      exit;

    } catch (Throwable $e) {
      error_log('[auth] DB upsert failed: ' . $e->getMessage());
      http_response_code(500);
      echo 'Server error during login.';
    }
  }

  /**
   * GET /logout
   * Clears session + remember-me and sends the user to the login page (no auto-OAuth).
   */
  public static function logout() {
    Auth::logout();
    header('Location: /login'); // important: do NOT send to "/" which requires login
    exit;
  }

  /**
   * GET /health
   * Simple readiness probe.
   */
  public static function health() {
    header('Content-Type: application/json');
    try {
      DB::pdo()->query('SELECT 1');
      echo json_encode(['ok' => true, 'base_url' => Config::BASE_URL], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
      error_log('[health] ' . $e->getMessage());
      http_response_code(500);
      echo json_encode(['ok' => false, 'err' => $e->getMessage()]);
    }
  }
}
