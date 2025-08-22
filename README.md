# PnKllr.net

Twitch-connected tools and dashboard. Users sign in with **Twitch OAuth**, their **profile** and **tokens + scopes** are stored, and they land on a **mobile-friendly dashboard**. An **admin panel** lists users and tokens. Includes a **ClipIt** tool that creates clips for channels that have previously logged in.

---

## Features

- 🔐 **Sign in with Twitch** (authorization code flow)
- 🪪 **Profile storage**: `twitch_id`, `twitch_login`, `twitch_display`, `avatar_url`, `email` (if scoped)
- 🔑 **Token storage**: `access_token`, `refresh_token`, **granted scopes**, `expires_at`
- 📊 **Dashboard**: shows the current user’s info + Twitch embed; responsive UI
- 🛠️ **Admin panel**: manage users & tokens; refresh/delete tokens
- ✂️ **ClipIt tool**: creates clips for **registered** channels (`?channel=<login>`)
- 🧰 Minimal stack: **plain PHP 8**, **PDO MySQL**, **cURL** — no Composer/autoload

---

## Requirements

- PHP 8.1+ with extensions: `pdo_mysql`, `curl`, `mbstring`
- MySQL/MariaDB
- Apache (with `.htaccess`) or Nginx (rewrite rules below)
- A Twitch Developer application (Client ID & Secret)

---

## Quickstart

1) **Clone & configure**
```bash
git clone https://github.com/pnkllr/PnKllrnet.git
cd PnKllrnet
cp .env.example .env
# edit .env with your values (BASE_URL, DB creds, Twitch keys)
