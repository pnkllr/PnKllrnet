-- Users created via Twitch SSO
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  twitch_id VARCHAR(50) UNIQUE NOT NULL,
  twitch_login VARCHAR(100) NOT NULL,
  twitch_display VARCHAR(100) NOT NULL,
  avatar_url TEXT NULL,
  email VARCHAR(190) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tokens per user (Twitch)
CREATE TABLE IF NOT EXISTS oauth_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider ENUM('twitch') NOT NULL,
  channel VARCHAR(100) NOT NULL,           -- same as twitch_login
  access_token TEXT NOT NULL,
  refresh_token TEXT,
  scope TEXT,                               -- space-separated granted scopes
  expires_at DATETIME NULL,
  UNIQUE KEY uniq_provider_user (provider, user_id),
  CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
