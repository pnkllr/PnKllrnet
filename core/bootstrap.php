<?php
require_once __DIR__.'/env.php';
require_once __DIR__.'/config.php';
load_env(BASE_PATH.'/.env');

require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/router.php';

start_secure_session();

if (APP_DEBUG){ ini_set('display_errors','1'); error_reporting(E_ALL); }
else { ini_set('display_errors','0'); error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED); }
