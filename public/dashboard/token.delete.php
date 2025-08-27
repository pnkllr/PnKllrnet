<?php
require_once dirname(__DIR__, 2) . '/core/init.php';

$u = Auth::requireUser();
CSRF::check();

// Remove this user's token row
    $GLOBALS['_db']->query("DELETE FROM oauth_tokens WHERE user_id=?", [(int)$u['id']]);
    $GLOBALS['_db']->query("DELETE FROM user_desired_scopes WHERE user_id=?", [(int)$u['id']]);
// Redirect back with a flag so we can show a success note
header('Location: ' . base_url('/?token_deleted=1'));
exit;
