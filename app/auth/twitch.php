<?php
require_once __DIR__ . '/../init.php';

/**
 * Handles login, callback, and logout.
 * Routes by ?action=login|callback|logout (default: login).
 * Redirect URI must be:
 *   https://pnkllr.net/core/twitch_auth.php?action=callback
 */
function handle_twitch_auth(): void {
    // Bring globals from init.php into scope
    global $config, $db;

    $action = $_GET['action'] ?? 'login';

    if ($action === 'logout') {
        // Destroy session and send home
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: ' . abs_url('/'));
        return;
    }

    if ($action === 'callback') {
        // Ensure Twitch client config exists to avoid warnings (and header issues)
        if (empty($config['twitch_client_id']) || empty($config['twitch_client_secret'])) {
            http_response_code(500);
            echo 'Twitch client configuration is missing.';
            return;
        }

        // CSRF/state check
        $stateGiven = $_GET['state'] ?? '';
        if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $stateGiven)) {
            http_response_code(400);
            echo 'Invalid OAuth state.';
            return;
        }
        unset($_SESSION['oauth_state']);

        $code = $_GET['code'] ?? null;
        if (!$code) {
            http_response_code(400);
            echo 'Missing code.';
            return;
        }

        // Exchange code for tokens
        [$tok, $err] = http_post_form('https://id.twitch.tv/oauth2/token', [
            'client_id'     => $config['twitch_client_id'],
            'client_secret' => $config['twitch_client_secret'],
            'code'          => $code,
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => abs_url('/core/twitch_auth.php?action=callback'),
        ]);
        if ($err || empty($tok['access_token'])) {
            http_response_code(500);
            echo 'Token exchange failed.';
            return;
        }

        $accessToken  = $tok['access_token'];
        $refreshToken = $tok['refresh_token'] ?? null;
        $expiresIn    = (int)($tok['expires_in'] ?? 3600);
        $expiresAt    = time() + $expiresIn;

        // Prefer scopes returned by Twitch; fall back to config
        $scopeStr = '';
        if (!empty($tok['scope']) && is_array($tok['scope'])) {
            $scopeStr = implode(' ', $tok['scope']);
        } else {
            $scopeStr = implode(' ', $config['twitch_scopes'] ?? []);
        }

        // Fetch Twitch user info
        [$me, $err2] = http_get_json('https://api.twitch.tv/helix/users', [
            'Authorization' => 'Bearer ' . $accessToken,
            'Client-Id'     => $config['twitch_client_id'],
        ]);
        if ($err2 || empty($me['data'][0])) {
            http_response_code(500);
            echo 'Failed to fetch user.';
            return;
        }

        $tuser = $me['data'][0]; // id, login, display_name, email, profile_image_url, ...

        // --- Persist user + tokens to DB ---
        try {
            $db->beginTransaction();

            // Upsert user
            $stmtUser = $db->prepare("
                INSERT INTO users (twitch_id, login, display_name, email, profile_image_url, created_at, updated_at)
                VALUES (:id, :login, :display_name, :email, :avatar, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    login = VALUES(login),
                    display_name = VALUES(display_name),
                    email = VALUES(email),
                    profile_image_url = VALUES(profile_image_url),
                    updated_at = NOW()
            ");
            $stmtUser->execute([
                ':id'           => (string)$tuser['id'],
                ':login'        => (string)$tuser['login'],
                ':display_name' => (string)($tuser['display_name'] ?? $tuser['login']),
                ':email'        => isset($tuser['email']) ? (string)$tuser['email'] : null,
                ':avatar'       => isset($tuser['profile_image_url']) ? (string)$tuser['profile_image_url'] : null,
            ]);

            // Upsert tokens
            $stmtTok = $db->prepare("
                INSERT INTO user_tokens (twitch_id, access_token, refresh_token, scope, expires_at, updated_at)
                VALUES (:id, :at, :rt, :scope, FROM_UNIXTIME(:exp), NOW())
                ON DUPLICATE KEY UPDATE
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    scope = VALUES(scope),
                    expires_at = VALUES(expires_at),
                    updated_at = NOW()
            ");
            $stmtTok->execute([
                ':id'    => (string)$tuser['id'],
                ':at'    => $accessToken,
                ':rt'    => $refreshToken,
                ':scope' => $scopeStr,
                ':exp'   => $expiresAt, // unix time
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            http_response_code(500);
            echo 'DB save failed.';
            return;
        }

        // --- Session cache for quick use ---
        $_SESSION['user'] = [
            'id'                   => (string)$tuser['id'],
            'login'                => (string)$tuser['login'],
            'display_name'         => (string)($tuser['display_name'] ?? $tuser['login']),
            'email'                => isset($tuser['email']) ? (string)$tuser['email'] : null,
            'twitch_access_token'  => $accessToken,
            'twitch_refresh_token' => $refreshToken,
            'twitch_expires_at'    => $expiresAt,
        ];

        header('Location: ' . abs_url('/dashboard/'));
        return;
    }

    // ---------- Default: begin login flow ----------
    if (empty($config['twitch_client_id'])) {
        http_response_code(500);
        echo 'Missing twitch_client_id in app/config.php';
        return;
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $redirectUri = abs_url('/core/twitch_auth.php?action=callback');
    $scopeStr    = implode(' ', $config['twitch_scopes'] ?? []);

    $authUrl = 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
        'client_id'     => $config['twitch_client_id'],
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => $scopeStr,
        'state'         => $state,
        //'force_verify' => 'true',
    ]);

    header('Location: ' . $authUrl);
}
