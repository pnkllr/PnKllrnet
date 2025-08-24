<?php
return [
  // Dashboard
  'GET /'                    => ['DashboardController','index'],

  // Login UX
  'GET /login'               => ['AuthController','showLogin'],
  'GET /auth/start'          => ['AuthController','login'],
  'GET /auth/callback'       => ['AuthController','loginCallback'],

  // Token (single-token flow)
  'GET  /token/create'       => ['DashboardController','createToken'],
  'POST /token/create'       => ['DashboardController','createToken'],
  'GET  /auth/token-callback'=> ['DashboardController','tokenCallback'],

  // Delete token (used by the dashboard Delete button)
  'POST /token/delete'       => ['DashboardController','deleteToken'],
  'GET  /token/delete'       => ['DashboardController','deleteToken'], // optional while testing

  // Health + logout
  'GET /logout'              => ['AuthController','logout'],
  'GET /health'              => ['AuthController','health'],

  // Admin (serves your admin template)
  'GET /admin'               => ['AdminController','index'],

  // ClipIt tool
  'GET /tool/clipit'         => ['ToolController','clipit'],
];
