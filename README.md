# PnKllr Tools (Twitch Utilities Dashboard)

Small PHP app that lets streamers connect their Twitch, choose scopes, mint/refresh tokens, and use simple **tools** (e.g. create clips) from a clean dashboard. Includes an **admin** view for users/tokens.

> ⚡️ Tech: PHP 8.x, MySQL/MariaDB, cURL. No framework. Public endpoints live under `/public`.

---

## Features

- **Twitch OAuth**: connect, re-authorize with selected scopes, store access/refresh tokens.
- **Dashboard**: profile, token display (copy/show/hide), scope picker, and tool cards.
- **Tools**: each tool declares `required_scopes`; UI shows URL (copyable) and missing scopes.
- **ClipIt**: public endpoint to create a clip for a channel that has `clips:edit`.
- **Admin**: KPIs, user list (ban/unban), token list (refresh/delete), scope viewer.
- **Security**: CSP-friendly (no inline JS), rate-limited tools, HTTPS clipboard support.

---

## Quick start

### 1) Requirements
- PHP **8.1+** (with cURL)
- MySQL/MariaDB
- HTTPS recommended (Clipboard API needs it)

### 2) Configure environment
Create `.env` (or export env vars via your panel):

```bash
APP_NAME="PnKllr Tools"
APP_URL="https://tools.example.com"   # used for absolute redirects

DB_HOST="127.0.0.1"
DB_NAME="pnkllr"
DB_USER="pnkllr"
DB_PASS="secret"

TWITCH_CLIENT_ID="xxxxxxxxxxxxxxxxxxxxxx"
TWITCH_CLIENT_SECRET="yyyyyyyyyyyyyyyyyy"
# Must match your Twitch Console redirect URL exactly:
TWITCH_REDIRECT_URI="https://tools.example.com/auth/callback.php"

# Optional
SESSION_NAME="pnkllr_sess"
```

> In PHP, the app reads via `getenv()`; you can also set these in your hosting control panel.

### 3) Create database tables

```sql
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NULL,
  display VARCHAR(255) NULL,
  login   VARCHAR(255) NULL,
  avatar  VARCHAR(1024) NULL,
  twitch_id    VARCHAR(32) UNIQUE,
  twitch_login VARCHAR(64) UNIQUE,
  role ENUM('user','admin') DEFAULT 'user',
  is_banned TINYINT(1) DEFAULT 0,
  banned_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE oauth_tokens (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  provider VARCHAR(32) NOT NULL DEFAULT 'twitch',
  access_token  TEXT,
  refresh_token TEXT,
  scopes TEXT,                -- space-separated scopes ("clips:edit channel:read:subscriptions")
  expires_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_desired_scopes (
  user_id INT UNSIGNED NOT NULL,
  scope   VARCHAR(128) NOT NULL,
  PRIMARY KEY (user_id, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4) Twitch application (developer console)
- Set **OAuth Redirect URL** to `https://your-domain/auth/callback.php`.
- Copy the **Client ID/Secret** to your env.
- Make sure any scopes you want are enabled in the dashboard UI.

### 5) Serve the app
Point your web root to **`/public`**.

**Apache** (.htaccess already routes to `public/index.php` if present), or:

**PHP built-in server (dev)**:
```bash
php -S localhost:8080 -t public
```

---

## Project structure (essentials)

```
public/
  index.php             # front controller
  tools/clipit.php      # public tool: create clip
  assets/css/...        # style.css (theme), header.css (optional)
  assets/js/...         # app.js, tools.js (copy handlers; CSP-safe)
app/
  Controllers/...       # Dashboard/Admin controllers
  Tools.php             # Tool definitions (name, desc, method, required_scopes, url)
  ToolsHelper.php       # Helper: scopes_missing(), build_grant_url(), etc.
  Twitch/...            # OAuth helpers + scopes list
core/
  init.php, bootstrap.php, db wrapper, Auth, CSRF, etc.
ui/
  dashboard/...         # views
  admin/...             # views
```

---

## Scopes, tokens, and tools

### Selecting scopes
- The **Permissions** section lets a user pick desired scopes.  
- Clicking **Save & Re-authorize** sends them to Twitch to approve.
- After OAuth, tokens are stored in `oauth_tokens` and the UI shows the active scopes.

### How tools work
- Tools are declared in **`app/Tools.php`**. Example:

```php
return [
  [
    'name'  => 'Create Clip (ClipIt)',
    'desc'  => 'Instantly create a clip for your channel.',
    'method'=> 'POST',
    'required_scopes' => ['clips:edit'],
    // {self} is replaced with the viewer's Twitch login in the UI
    'url'   => base_url('/tools/clipit.php?channel={self}&format=text')
  ],
  // ...
];
```

- In the dashboard, a tool **fades** when you’re missing required scopes and shows which ones you need.
- If you have the scopes, the **URL chip** appears with a **Copy** button (CSP-safe, external JS).

---

## ClipIt (public endpoint)

**Endpoint**
```
GET/POST /tools/clipit.php?channel={twitch_login}[&format=text]
```

**Requirements**
- The target **channel** must exist as a user in your DB **and** have an unexpired token that includes `clips:edit`.

**Responses**
- `200 OK` (JSON): `{"ok":true,"clip_id":"...","clip_url":"https://clips.twitch.tv/...","edit_url":"..."}`  
- With `&format=text`: returns the **clip URL** as plain text.  
- `403/404/429/5xx` for missing scope, unknown channel, rate limiting, or Twitch errors.

**Rate limit**
- Per channel, basic window (default **20s**) using a file lock in `/storage/clipit_{channel}.lock`.

**Examples**
```bash
# Plain text URL (great for OBS/LioranBoard/streamdeck webhooks)
curl "https://tools.example.com/tools/clipit.php?channel=YourLogin&format=text"

# JSON
curl "https://tools.example.com/tools/clipit.php?channel=YourLogin"
```

> ⚠️ The endpoint is public by design so you don’t have to log in.  
> If you’re concerned about abuse, consider adding:
> - a simple shared `?key=...` check,  
> - an `Origin`/`Referer` allow-list, or  
> - a stricter rate limit.

---

## Admin

`/admin` shows:
- KPI tiles
- **Users**: avatar, login, joined date, **ban/unban**
- **Tokens**: scopes, **expires**, **refresh**, **delete**

The colored badges (ok/warn/bad) reflect time to expiry.

---

## CSP & Clipboard

- The site uses a strict **Content-Security-Policy** (`script-src 'self'`).
- All JS that needs to run (e.g. “Copy URL”) lives in **`/public/assets/js/`**.  
  Include it once in your base layout (no inline scripts).

```html
<!-- In your base template, before </body> -->
<script src="/assets/js/app.js" defer></script>
<script src="/assets/js/tools.js" defer></script>
```

- Clipboard API requires **HTTPS**. A fallback to `document.execCommand('copy')` is provided for non-secure contexts.

---

## Token refresh (cron)

Set a cron to keep tokens fresh (adjust path/filename to your repo):

```bash
# Every 10 minutes
*/10 * * * * /usr/bin/php -d detect_unicode=0 /home/username/tools.pnkllr.net/cron/refresh_tokens.php >> /home/username/logs/refresh.log 2>&1
```

Your refresh script should:
1. Find tokens expiring soon,
2. Use the refresh token against Twitch,
3. Update `access_token`, `expires_at`, and `scopes` if Twitch returns them.

If your script lives elsewhere, update the cron path accordingly.

---

## Theming

The app ships with a **purple → blue** theme (glass UI).  
Theme tokens live in `:root` (`--violet`, `--blue`, etc.).  
Background uses a CSS “aurora” with `prefers-reduced-motion` honored.

---

## Development tips

- Web root should be **`public/`**.  
- If using cPanel, map the domain/subdomain to `/public` (or symlink).  
- Logs: check your PHP error log (e.g. `error_log` or hosting panel logs).
- To add more tools, create endpoints in `public/tools/` and add entries to `app/Tools.php`.

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Credits

Built for **PnKllr.net**. Twitch trademarks and logos belong to Twitch Interactive, Inc.
