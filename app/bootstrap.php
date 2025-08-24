<?php
require_once __DIR__.'/Config.php';
require_once __DIR__.'/DB.php';
require_once __DIR__.'/Http.php';

$ttl = 60*60*24*Config::REMEMBER_DAYS;
session_set_cookie_params([
  'lifetime'=>$ttl, 'path'=>'/', 'domain'=>'',
  'secure'=>Config::COOKIE_SECURE, 'httponly'=>true, 'samesite'=>Config::COOKIE_SAMESITE
]);
session_start();

function enc($s){ return $s; }     // replace with OpenSSL/libsodium if you like
function dec($s){ return $s; }

function view($tpl, $vars=[]){
  extract($vars, EXTR_SKIP);
  include __DIR__.'/../views/layout.php';
}
