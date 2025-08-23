<?php

// index.php (front controller)
require_once __DIR__ . '/core/bootstrap.php';

route('/', fn() => include BASE_PATH . '/index.html');

route('/twitch/auth/login.php',    fn() => include BASE_PATH . '/app/twitch/auth/login.php');
route('/twitch/auth/callback.php', fn() => include BASE_PATH . '/app/twitch/auth/callback.php');

route('/twitch/auth/reauth.php',   fn() => (require_login() || true) && include BASE_PATH . '/app/twitch/auth/reauth.php');
route('/dashboard/scopes.save.php', fn() => (require_login() || true) && include BASE_PATH . '/dashboard/scopes.save.php');

route('/dashboard/',        fn() => (require_login() || true) && include BASE_PATH . '/dashboard/index.php');
route('/admin/',            fn() => (require_login() || true) && include BASE_PATH . '/admin/index.php');
route('/admin/actions.php', fn() => (require_login() || true) && include BASE_PATH . '/admin/actions.php');

route('/app/twitch/tool/clipit.php', fn() => include BASE_PATH . '/app/twitch/tool/clipit.php');
route('/twitch/tool/clipit.php',     fn() => include BASE_PATH . '/app/twitch/tool/clipit.php');

route('/logout', fn() => include BASE_PATH . '/logout.php');


dispatch();
