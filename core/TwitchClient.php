<?php
declare(strict_types=1);

final class TwitchClient {
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct() {
        $this->clientId = getenv('TWITCH_CLIENT_ID') ?: '';
        $this->clientSecret = getenv('TWITCH_CLIENT_SECRET') ?: '';
        $this->redirectUri = getenv('TWITCH_REDIRECT_URI') ?: '';
    }

    public function authUrl(string $state, array $scopes): string {
        $scopeStr = implode(' ', $scopes);
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $scopeStr,
            'state' => $state,
        ]);
        return "https://id.twitch.tv/oauth2/authorize?$params";
    }

    public function exchangeCode(string $code): array {
        $body = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
        ];
        return $this->postToken($body);
    }

    public function refresh(string $refreshToken): array {
        $body = [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];
        return $this->postToken($body);
    }

    private function postToken(array $body): array {
        $ch = curl_init("https://id.twitch.tv/oauth2/token");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($body),
            CURLOPT_TIMEOUT => 20,
        ]);
        $res = curl_exec($ch);
        if ($res === false) throw new \RuntimeException('Curl error: ' . curl_error($ch));
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($res, true) ?: [];
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('Twitch token error: ' . ($data['message'] ?? 'unknown'));
        }
        return $data;
    }

    public function getUser(string $accessToken): array {
        $h = [
            'Authorization: Bearer ' . $accessToken,
            'Client-Id: ' . $this->clientId,
        ];
        $ch = curl_init("https://api.twitch.tv/helix/users");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 20,
        ]);
        $res = curl_exec($ch);
        if ($res === false) throw new \RuntimeException('Curl error: ' . curl_error($ch));
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($res, true) ?: [];
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('Twitch get user error: ' . ($data['message'] ?? 'unknown'));
        }
        return $data['data'][0] ?? [];
    }

    public function createClip(string $accessToken, string $broadcasterId): array {
        $h = [
            'Authorization: Bearer ' . $accessToken,
            'Client-Id: ' . $this->clientId,
            'Content-Type: application/json',
        ];
        $endpoint = "https://api.twitch.tv/helix/clips?broadcaster_id=" . urlencode($broadcasterId);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 20,
        ]);
        $res = curl_exec($ch);
        if ($res === false) throw new \RuntimeException('Curl error: ' . curl_error($ch));
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($res, true) ?: [];
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException('Twitch create clip error: ' . ($data['message'] ?? 'unknown'));
        }
        return $data['data'][0] ?? $data;
    }
}
