<?php
session_start();
include '../config/db.php';
require_once '../includes/csrf.php'; // 🔐 Tambahkan CSRF Helper

define('BLOCK_FILE', __DIR__ . '/blocked_ips.json');
define('MAX_ATTEMPT', 3);
define('BLOCK_MINUTES', 30);

// Ambil IP saat ini
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Fungsi logging
function log_admin_login($userid, $status) {
    $logFile = __DIR__ . '/login_log.txt';
    $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
    $time = date("Y-m-d H:i:s");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log = "[$time] UserID: $userid | Status: $status | IP: $ip | Agent: $agent\n";
    file_put_contents($logFile, $log, FILE_APPEND);
}

// Fungsi cek dan update blokir IP
function is_ip_blocked($ip) {
    if (!file_exists(BLOCK_FILE)) return false;
    $data = json_decode(file_get_contents(BLOCK_FILE), true) ?: [];
    if (isset($data[$ip])) {
        $entry = $data[$ip];
        if ($entry['blocked'] && time() < $entry['blocked_until']) {
            return true;
        }
    }
    return false;
}

function record_failed_attempt($ip) {
    $data = file_exists(BLOCK_FILE) ? json_decode(file_get_contents(BLOCK_FILE), true) : [];

    if (!isset($data[$ip])) {
        $data[$ip] = ['attempt' => 1, 'blocked' => false, 'blocked_until' => 0];
    } else {
        $data[$ip]['attempt'] += 1;
        if ($data[$ip]['attempt'] >= MAX_ATTEMPT) {
            $data[$ip]['blocked'] = true;
            $data[$ip]['blocked_until'] = time() + (BLOCK_MINUTES * 60);
        }
    }

    file_put_contents(BLOCK_FILE, json_encode($data));
}

// Cek blokir sebelum proses login
if (is_ip_blocked($ip)) {
    log_admin_login('UNKNOWN', 'DITOLAK - IP diblokir');
    die("<h2 style='color:red;text-align:center;margin-top:100px;'>Akses ditolak: IP Anda telah diblokir selama ".BLOCK_MINUTES." menit.</h2>");
}

// Proses login jika belum diblok
// Proses login (dengan validasi CSRF)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_validate('admin_login')) {
        $error = "Token CSRF tidak valid atau telah kedaluwarsa.";
        log_admin_login('UNKNOWN', 'GAGAL - CSRF Expired');
    } else {
        $userid = $_POST['userid'];
        $password = $_POST['password'];

        $query = "SELECT UserID, UserPass, UserType FROM dbo.UserInfo WHERE UserID = ?";
        $stmt = sqlsrv_query($conn, $query, [$userid]);

        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($password === $row['UserPass']) {
                if ((int)$row['UserType'] === 30) {
                    $_SESSION['userid'] = $row['UserID'];
                    $_SESSION['UserType'] = $row['UserType'];
                    $_SESSION['last_login'] = time();
                    log_admin_login($userid, 'BERHASIL');
                    header("Location: ../admin/index");
                    exit();
                } else {
                    $error = "Akun ini bukan admin.";
                    log_admin_login($userid, 'GAGAL - Bukan Admin');
                    record_failed_attempt($ip);
                }
            } else {
                $error = "Password salah.";
                log_admin_login($userid, 'GAGAL - Password Salah');
                record_failed_attempt($ip);
            }
        } else {
            $error = "Username tidak ditemukan.";
            log_admin_login($userid, 'GAGAL - Username Tidak Ada');
            record_failed_attempt($ip);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center items-center h-screen">
  <form method="POST" class="bg-white p-6 rounded shadow w-80">
    <h2 class="text-lg font-bold mb-4 text-red-600">Login Admin</h2>
    
    <?php if (isset($error)): ?>
      <p class="text-red-500 mb-2"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?= csrf_input('admin_login') ?> <!-- 🔐 Token disisipkan -->
    
    <input name="userid" class="w-full p-2 border mb-2" placeholder="Admin Username" required>
    <input name="password" type="password" class="w-full p-2 border mb-4" placeholder="Password" required>
    
    <button class="bg-red-600 text-white px-4 py-2 w-full font-bold">Login Admin</button>
  </form>
</body>
</html>
