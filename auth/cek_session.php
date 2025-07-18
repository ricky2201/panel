<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect jika belum login
if (!isset($_SESSION['userid'])) {
    header("Location: /auth/login");
    exit;
}

// Durasi timeout: 2 menit (120 detik)
$timeout_duration = 600;

// Validasi session IP dan User Agent agar tidak dimanipulasi
if (
    !isset($_SESSION['UserAgent']) || $_SESSION['UserAgent'] !== $_SERVER['HTTP_USER_AGENT'] ||
    !isset($_SESSION['UserIP']) || $_SESSION['UserIP'] !== $_SERVER['REMOTE_ADDR']
) {
    session_unset();
    session_destroy();
    header("Location: /auth/login?invalid_session=1");
    exit;
}

// Cek dan atur waktu aktivitas terakhir
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: /auth/login?timeout=1");
        exit;
    }
}

// Perbarui waktu aktivitas
$_SESSION['last_activity'] = time();
?>
