<?php
require_once __DIR__.'/core/bootstrap.php';

// public pages
route('/',                  fn() => header('Location: /dashboard/'));
route('/logout',            fn() => logout());

// sso routes
route('/twitch/auth/login.php',    fn() => include BASE_PATH.'/app/twitch/auth/login.php');
route('/twitch/auth/callback.php', fn() => include BASE_PATH.'/app/twitch/auth/callback.php');

// app pages
route('/dashboard/', fn() => (require_login() || true) && include BASE_PATH.'/dashboard/index.php');
route('/admin/',     fn() => (require_login() || true) && include BASE_PATH.'/admin/index.php');
route('/admin/actions.php', fn() => (require_login() || true) && include BASE_PATH.'/admin/actions.php');

dispatch();
