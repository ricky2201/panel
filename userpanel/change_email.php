<?php
session_start();
include '../auth/cek_session.php';
include '../config/db.php';
date_default_timezone_set('Asia/Jakarta');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$notif = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>CSRF token tidak valid.</p>";
        header("Location: change_email"); exit;
    }

    $userid   = $_SESSION['userid'];
    $oldemail = trim($_POST['old_email']);
    $newemail = trim($_POST['new_email']);
    $confirm  = trim($_POST['confirm_email']);
    $password = trim($_POST['password']);
    $pin      = trim($_POST['pin']);

    if (!filter_var($oldemail, FILTER_VALIDATE_EMAIL) || !filter_var($newemail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>Format email tidak valid.</p>";
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $password)) {
        $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>Password hanya huruf dan angka.</p>";
    } elseif (!ctype_digit($pin) || strlen($pin) > 6) {
        $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>PIN hanya angka dan maksimal 6 digit.</p>";
    } elseif ($newemail !== $confirm) {
        $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>Konfirmasi email tidak cocok.</p>";
    } else {
        $stmt = sqlsrv_query($conn, "SELECT UserEmail, UserPass, UserPass2, UserName FROM dbo.UserInfo WHERE UserID = ?", [$userid]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row && $row['UserEmail'] === $oldemail && $row['UserPass'] === $password && $row['UserPass2'] === $pin) {
            $update = sqlsrv_query($conn, "UPDATE dbo.UserInfo SET UserEmail = ? WHERE UserID = ?", [$newemail, $userid]);
            if ($update) {
                $log = "UserName: {$row['UserName']} | Email Lama: $oldemail | Email Baru: $newemail | " . date("d-m-Y H:i:s") . "\n";
                file_put_contents(__DIR__ . '/../log/log_change_email.txt', $log, FILE_APPEND);
                $_SESSION['notif'] = "<p class='text-green-500 text-sm text-center' id='notif'>Email berhasil diubah.</p>";
            } else {
                $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>Gagal mengubah email.</p>";
            }
        } else {
            $_SESSION['notif'] = "<p class='text-red-500 text-sm text-center' id='notif'>Data tidak cocok. Periksa kembali.</p>";
        }
    }
    header("Location: change_email");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
  <title>Ubah Email</title>
</head>
<body class="bg-gray-900 text-white flex justify-center items-center min-h-screen">
  <form method="POST" class="bg-gray-800 p-6 rounded shadow w-80 relative">
    <h2 class="text-2xl font-bold mb-4 text-center">UBAH EMAIL</h2>

    <div class="flex justify-center mb-2">
      <img src="../assets/logo.png" alt="Logo" class="h-20 w-auto">
    </div>

    <!-- Notifikasi tepat di bawah logo -->
    <?php if (isset($_SESSION['notif'])): ?>
      <?= $_SESSION['notif']; unset($_SESSION['notif']); ?>
    <?php endif; ?>

    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

    <input name="old_email" type="email" class="w-full p-2 border mb-2 text-black" placeholder="Email Lama" required>
    <input name="new_email" type="email" class="w-full p-2 border mb-2 text-black" placeholder="Email Baru" required>
    <input name="confirm_email" type="email" class="w-full p-2 border mb-2 text-black" placeholder="Konfirmasi Email Baru" required>

    <!-- Password -->
    <div class="relative mb-2">
      <input name="password" id="password" type="password" class="w-full p-2 border text-black pr-10" placeholder="Password Sekarang" required>
      <button type="button" onclick="toggle('password')" class="absolute right-2 top-2 text-black">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7s-8.268-2.943-9.542-7z" />
        </svg>
      </button>
    </div>

    <!-- PIN -->
    <div class="relative mb-4">
      <input name="pin" id="pin" type="password" maxlength="6" class="w-full p-2 border text-black pr-10" placeholder="PIN (6 digit)" required>
      <button type="button" onclick="toggle('pin')" class="absolute right-2 top-2 text-black">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.522 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7s-8.268-2.943-9.542-7z" />
        </svg>
      </button>
    </div>

    <button class="bg-blue-600 text-white font-bold py-2 px-4 w-full rounded hover:bg-blue-700">SIMPAN</button>
    <a href="./" class="block mt-3 text-center bg-gray-600 text-white font-bold py-2 px-4 w-full rounded hover:bg-gray-700">KEMBALI</a>
  </form>

  <script>
    function toggle(id) {
      const field = document.getElementById(id);
      field.type = field.type === 'password' ? 'text' : 'password';
    }

    setTimeout(() => {
      const notif = document.getElementById('notif');
      if (notif) notif.remove();
    }, 3000);

    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
  </script>
</body>
</html>
