<?php
session_start();
include '../auth/cek_session.php';
include '../config/db.php';

date_default_timezone_set('Asia/Jakarta');

// CSRF token generate jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['notif_message'] = "Permintaan tidak valid (CSRF).";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    $userid   = $_SESSION['userid'];
    $oldpass  = $_POST['old_password'];
    $newpass  = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];
    $pinInput = $_POST['pin'];

    // Validasi PIN: angka maksimal 6 digit
    if (!ctype_digit($pinInput) || strlen($pinInput) > 6) {
        $_SESSION['notif_message'] = "PIN hanya angka dan maksimal 6 digit.";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    // Validasi password: hanya huruf dan angka
    if (!preg_match('/^[a-zA-Z0-9]+$/', $oldpass) || !preg_match('/^[a-zA-Z0-9]+$/', $newpass) || !preg_match('/^[a-zA-Z0-9]+$/', $confirm)) {
        $_SESSION['notif_message'] = "Password hanya boleh huruf dan angka.";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    // Cek password & PIN lama dari DB
    $stmt = sqlsrv_query($conn, "SELECT UserPass, UserPass2, UserName FROM dbo.UserInfo WHERE UserID = ?", [$userid]);
    $data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if (!$data) {
        $_SESSION['notif_message'] = "Data user tidak ditemukan.";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    $dbOldPass = $data['UserPass'];
    $dbPin = $data['UserPass2'];
    $username = $data['UserName'];

    if ($oldpass !== $dbOldPass) {
        $_SESSION['notif_message'] = "Password lama salah.";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    if ($pinInput !== $dbPin) {
        $_SESSION['notif_message'] = "PIN salah.";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    if ($newpass !== $confirm) {
        $_SESSION['notif_message'] = "Konfirmasi password tidak cocok.";
        $_SESSION['notif_color'] = "red";
        header("Location: change-password");
        exit;
    }

    // Update password tanpa mengubah PIN
    $update = sqlsrv_query($conn, "UPDATE dbo.UserInfo SET UserPass = ? WHERE UserID = ?", [$newpass, $userid]);

    if ($update) {
        // Tulis log
        $logPath = realpath(__DIR__ . '/../log') . '/log_change_pw.txt';
        $waktu = date('d-m-Y H:i:s');
        $logText = "UserName: $username | Password Lama: $oldpass | Password Baru: $newpass | $waktu\n";
        file_put_contents($logPath, $logText, FILE_APPEND);

        $_SESSION['notif_message'] = "Password berhasil diubah.";
        $_SESSION['notif_color'] = "green";
    } else {
        $_SESSION['notif_message'] = "Gagal menyimpan ke database.";
        $_SESSION['notif_color'] = "red";
    }

    header("Location: change-password");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Change Password</title>
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
    <h2 class="text-2xl font-bold mb-3 text-center">Change Password</h2>
    <div class="flex justify-center mb-4">
      <img src="../assets/logo.png" alt="Logo" class="h-22 w-auto">
    </div>

    <?php if (isset($_SESSION['notif_message'])): ?>
      <div id="notif" class="text-center text-<?= $_SESSION['notif_color'] ?? 'red' ?>-500 font-semibold mb-3">
        <?= $_SESSION['notif_message']; unset($_SESSION['notif_message'], $_SESSION['notif_color']); ?>
      </div>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div class="relative mb-3">
      <input name="old_password" id="old_password" type="password" class="w-full p-2 border text-black rounded" placeholder="Current Password" required>
      <svg onclick="togglePassword('old_password')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="eye-icon h-5 w-5 text-gray-600 absolute">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.478 0-8.268-2.943-9.542-7z" />
      </svg>
    </div>

    <div class="relative mb-3">
      <input name="new_password" id="new_password" type="password" class="w-full p-2 border text-black rounded" placeholder="New Password" required>
      <svg onclick="togglePassword('new_password')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="eye-icon h-5 w-5 text-gray-600 absolute">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.478 0-8.268-2.943-9.542-7z" />
      </svg>
    </div>

    <div class="relative mb-3">
      <input name="confirm_password" id="confirm_password" type="password" class="w-full p-2 border text-black rounded" placeholder="Confirm New Password" required>
      <svg onclick="togglePassword('confirm_password')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="eye-icon h-5 w-5 text-gray-600 absolute">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.478 0-8.268-2.943-9.542-7z" />
      </svg>
    </div>

    <div class="relative mb-4">
      <input name="pin" id="pin" maxlength="6" pattern="\d*" inputmode="numeric" type="password" class="w-full p-2 border text-black rounded" placeholder="PIN (max 6digit)" required>
      <svg onclick="togglePassword('pin')" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="eye-icon h-5 w-5 text-gray-600 absolute">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.522 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.478 0-8.268-2.943-9.542-7z" />
      </svg>
    </div>

    <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 w-full rounded hover:bg-blue-700">Confirm</button>
    <a href="./" class="block mt-3 text-center bg-gray-600 text-white font-bold py-2 px-4 w-full rounded hover:bg-gray-700">Back</a>
  </form>

  <script>
    function togglePassword(id) {
      const input = document.getElementById(id);
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    const notif = document.getElementById('notif');
    if (notif) {
      setTimeout(() => notif.style.display = 'none', 3000);
    }

    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  </script>
</body>
</html>
