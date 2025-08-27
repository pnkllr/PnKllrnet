<?php
declare(strict_types=1);

final class AuthController {
    /**
     * First-time login:
     * - Minimal scopes (or none) just to identify the user.
     * - Do NOT store tokens here.
     * - Redirect to dashboard after user row is upserted in callback.
     */
    public static function login(): void {
        Session::regenerate();
        Session::set('auth_mode', 'login'); // mark this as initial login

        $state = bin2hex(random_bytes(16));
        Session::set('state', $state);

        // Minimal scopes: identity works with 0 scopes; add 'user:read:email' if you want email.
        $scopes = []; // or ['user:read:email']

        $tw = new TwitchClient();
        header('Location: ' . $tw->authUrl($state, $scopes));
        exit;
    }

    /**
     * Common callback for both modes:
     * - "login"  -> store user profile only, no tokens
     * - "reauth" -> store tokens + scopes in oauth_tokens
     */
    public static function callback(): void {
        $state = $_GET['state'] ?? '';
        $code  = $_GET['code'] ?? '';
        if (!$state || !hash_equals((string)Session::get('state'), (string)$state)) {
            abort(400, 'Invalid state');
        }
        if (!$code) abort(400, 'Missing code');

        $tw  = new TwitchClient();
        $tok = $tw->exchangeCode($code);           // access+refresh (in memory for now)
        $tu  = $tw->getUser($tok['access_token']); // fetch Twitch user

        if (!$tu || empty($tu['id'])) abort(400, 'Failed to fetch user');

        // Create/update local user
        $appUser = User::upsertFromTwitch($tu);

        // Put user in session
        Session::set('user', [
            'id'        => (int)$appUser['id'],
            'twitch_id' => $appUser['twitch_id'],
            'login'     => $appUser['twitch_login'],
            'display'   => $appUser['twitch_display'],
            'avatar'    => $appUser['avatar_url'],
            'role'      => $appUser['role'],
        ]);

        // Decide what to do based on mode
        $mode = (string)(Session::get('auth_mode') ?? 'login');
        if ($mode === 'reauth') {
            // Save tokens + chosen scopes on re-auth only
            $requestedScopes = (array)(Session::get('requested_scopes') ?? []);
            if (empty($requestedScopes)) {
                $requestedScopes = preg_split('/\s+/', getenv('TWITCH_SCOPES') ?: '') ?: [];
            }
            // This stores refresh token + scopes (and can be adapted to also store access token if desired)
            User::setTokens((int)$appUser['id'], $tok, $requestedScopes);
        }

        // Clean up ephemeral session keys
        $next = (string)(Session::get('next_after_auth') ?? '/');
        Session::forget('state');
        Session::forget('auth_mode');
        Session::forget('requested_scopes');
        Session::forget('next_after_auth');

        header('Location: ' . base_url($next));
        exit;
    }

    public static function logout(): void {
        Session::destroy();
        header('Location: ' . base_url('/'));
        exit;
    }
}
