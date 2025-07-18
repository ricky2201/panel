<?php
session_start();
require_once '../path.php'; // Tambahkan ini setelah session_start
require_once '../config/db.php';

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['keep'])) {
    unset($_SESSION['verified_forgot'], $_SESSION['verified_username']);
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$step = $_SESSION['verified_forgot'] ?? false;
$username_verified = $_SESSION['verified_username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validasi CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['notif_message'] = "Permintaan tidak valid (CSRF)";
    $_SESSION['notif_color'] = "red";
    header("Location: forgot_password");
    exit;
  }

  if (isset($_POST['check_user'])) {
    // Tahap 1 - Verifikasi User
    $username = $_POST['username'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $email = $_POST['email'] ?? '';

    $stmt = sqlsrv_query($conn, "SELECT UserID FROM dbo.UserInfo WHERE UserName = ? AND UserPass2 = ? AND UserEmail = ?", [$username, $pin, $email]);
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($data) {
      $_SESSION['verified_forgot'] = true;
      $_SESSION['verified_username'] = $username;
    } else {
      $_SESSION['notif_message'] = "Data tidak cocok. Pastikan UserName, PIN, dan Email benar.";
      $_SESSION['notif_color'] = "red";
    }

    header("Location: forgot_password");
    exit;
  }

  if (isset($_POST['reset_password']) && $step && $username_verified) {
    $newpass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!preg_match('/^[a-zA-Z0-9]+$/', $newpass) || !preg_match('/^[a-zA-Z0-9]+$/', $confirm)) {
      $_SESSION['notif_message'] = "Password hanya boleh huruf dan angka.";
      $_SESSION['notif_color'] = "red";
      header("Location: forgot_password");
      exit;
    }

    if ($newpass !== $confirm) {
      $_SESSION['notif_message'] = "Konfirmasi password tidak cocok.";
      $_SESSION['notif_color'] = "red";
      header("Location: forgot_password");
      exit;
    }

    // Ambil UserID berdasarkan UserName
    $stmt = sqlsrv_query($conn, "SELECT UserID FROM dbo.UserInfo WHERE UserName = ?", [$username_verified]);
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $userid = $data['UserID'] ?? '';

    if ($userid) {
      $update = sqlsrv_query($conn, "UPDATE dbo.UserInfo SET UserPass = ? WHERE UserID = ?", [$newpass, $userid]);

      if ($update) {
        // Tulis log
        $logPath = realpath(__DIR__ . '/../log') . '/log_forgot_pw.txt';
        $waktu = date('d-m-Y H:i:s');
        $logText = "UserID: $userid | NewPass: $newpass | $waktu\n";
        file_put_contents($logPath, $logText, FILE_APPEND);

        $_SESSION['notif_message'] = "Password berhasil direset.";
        $_SESSION['notif_color'] = "green";
        unset($_SESSION['verified_forgot'], $_SESSION['verified_username']);
      } else {
        $_SESSION['notif_message'] = "Gagal update password.";
        $_SESSION['notif_color'] = "red";
      }
    } else {
      $_SESSION['notif_message'] = "User tidak ditemukan.";
      $_SESSION['notif_color'] = "red";
    }

    header("Location: forgot_password");
    exit;
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .eye-icon {
      cursor: pointer;
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
    }
  </style>
</head>
<body class="bg-gray-900 text-white flex justify-center items-center min-h-screen">
  <form method="POST" class="bg-gray-800 p-6 rounded shadow w-80 relative" autocomplete="off">
    
    <h2 class="text-2xl font-bold mb-4 text-center">Reset Password</h2>

    <div class="flex justify-center mb-4">
      <img src="<?= ASSETS_PATH ?>logo.png" alt="Logo" class="h-22 w-auto">
    </div>

    <?php if (isset($_SESSION['notif_message'])): ?>
      <div id="notif" class="text-center text-<?= $_SESSION['notif_color'] ?? 'red' ?>-500 font-semibold mb-3">
        <?= $_SESSION['notif_message']; unset($_SESSION['notif_message'], $_SESSION['notif_color']); ?>
      </div>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <?php if (!$step): ?>
      <!-- Form Tahap 1 -->
      <input name="username" type="text" placeholder="UserName" required class="w-full mb-3 p-2 text-black rounded border">
      <input name="pin" type="password" placeholder="PIN (max 6 digit)" maxlength="6" pattern="\d*" inputmode="numeric" required class="w-full mb-3 p-2 text-black rounded border">
      <input name="email" type="email" placeholder="Email" required class="w-full mb-4 p-2 text-black rounded border">

      <button type="submit" name="check_user" class="bg-blue-600 hover:bg-blue-700 text-white w-full font-bold py-2 rounded">Verifikasi</button>
      <a href="../auth/login" class="block mt-3 text-center bg-gray-600 text-white py-2 rounded hover:bg-gray-700">← Kembali ke Login</a>
    <?php else: ?>
      <!-- Form Tahap 2 -->
      <div class="text-center mb-2 text-sm text-blue-300">User terverifikasi: <b><?= htmlspecialchars($username_verified) ?></b></div>

      <div class="relative mb-3">
        <input name="new_password" id="new_password" type="password" placeholder="Password Baru" required class="w-full p-2 text-black rounded border">
        <svg onclick="togglePassword('new_password')" xmlns="http://www.w3.org/2000/svg" class="eye-icon h-5 w-5 text-gray-600 absolute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.522 5 12 5s8.268 2.943 9.542 7-5.064 7-9.542 7S3.732 16.057 2.458 12z" />
        </svg>
      </div>

      <div class="relative mb-4">
        <input name="confirm_password" id="confirm_password" type="password" placeholder="Konfirmasi Password" required class="w-full p-2 text-black rounded border">
        <svg onclick="togglePassword('confirm_password')" xmlns="http://www.w3.org/2000/svg" class="eye-icon h-5 w-5 text-gray-600 absolute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.522 5 12 5s8.268 2.943 9.542 7-5.064 7-9.542 7S3.732 16.057 2.458 12z" />
        </svg>
      </div>

      <button type="submit" name="reset_password" class="bg-blue-600 hover:bg-blue-700 text-white w-full font-bold py-2 rounded">Reset Password</button>
      <a href="../" class="block mt-3 text-center bg-gray-600 text-white py-2 rounded hover:bg-gray-700">Batal</a>
    <?php endif; ?>
  </form>

  <script>
    function togglePassword(id) {
      const input = document.getElementById(id);
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    const notif = document.getElementById('notif');
    if (notif) setTimeout(() => notif.style.display = 'none', 3000);
  </script>
</body>
</html>
