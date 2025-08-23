<?php
// index.php (front controller)
require_once __DIR__ . '/core/bootstrap.php';

route('/', fn() => include BASE_PATH . '/index.html'); // ← your old homepage here

route('/twitch/auth/login.php',    fn() => include BASE_PATH . '/app/twitch/auth/login.php');
route('/twitch/auth/callback.php', fn() => include BASE_PATH . '/app/twitch/auth/callback.php');

route('/dashboard/',        fn() => (require_login() || true) && include BASE_PATH . '/dashboard/index.php');
route('/admin/',            fn() => (require_login() || true) && include BASE_PATH . '/admin/index.php');
route('/admin/actions.php', fn() => (require_login() || true) && include BASE_PATH . '/admin/actions.php');

route('/app/twitch/tool/clipit.php', fn() => include BASE_PATH . '/app/twitch/tool/clipit.php');
route('/twitch/tool/clipit.php',     fn() => include BASE_PATH . '/app/twitch/tool/clipit.php');

route('/logout', fn() => logout());

dispatch();
