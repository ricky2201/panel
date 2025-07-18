<?php
if (!isset($_SESSION)) session_start();

function csrf_token($key = 'global', $expire_seconds = 600) {
    $now = time();

    if (empty($_SESSION['csrf_tokens'][$key]['value']) || ($now - $_SESSION['csrf_tokens'][$key]['time']) > $expire_seconds) {
        $_SESSION['csrf_tokens'][$key] = [
            'value' => bin2hex(random_bytes(32)),
            'time'  => $now
        ];
    }

    return $_SESSION['csrf_tokens'][$key]['value'];
}

function csrf_input($key = 'global') {
    $token = csrf_token($key);
    return '<input type="hidden" name="csrf_token_' . $key . '" value="' . htmlspecialchars($token) . '">';
}

function csrf_validate($key = 'global', $expire_seconds = 600) {
    $session = $_SESSION['csrf_tokens'][$key] ?? null;
    $posted = $_POST['csrf_token_' . $key] ?? null;

    if (!$session || !$posted) return false;

    $expired = (time() - $session['time']) > $expire_seconds;
    $valid   = hash_equals($session['value'], $posted);

    return !$expired && $valid;
}
