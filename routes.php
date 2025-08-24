<?php
return [
  'GET /'                 => ['DashboardController','index'],
  'GET /login'            => ['AuthController','login'],
  'GET /auth/callback'    => ['AuthController','loginCallback'],
  'POST /token/create'    => ['DashboardController','createToken'],
  'GET /auth/token-callback' => ['DashboardController','tokenCallback'],
  'GET /logout'           => ['AuthController','logout'],
];
