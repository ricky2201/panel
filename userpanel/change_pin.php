<?php
session_start();
include '../auth/cek_session.php';
include '../config/db.php';
date_default_timezone_set('Asia/Jakarta');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userid = $_SESSION['userid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldpin = $_POST['old_pin'];
    $newpin = $_POST['new_pin'];
    $confirm = $_POST['confirm_pin'];
    $password = $_POST['password'];
    $email = $_POST['email'];
    $csrf_token = $_POST['csrf_token'];

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $_SESSION['error'] = "Token tidak valid.";
        header("Location: change_pin"); exit;
    }

    if (!ctype_digit($oldpin) || !ctype_digit($newpin) || strlen($newpin) > 6) {
        $_SESSION['error'] = "PIN hanya boleh angka dan maksimal 6 digit.";
    } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $password)) {
        $_SESSION['error'] = "Password hanya boleh huruf dan angka.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid.";
    } elseif ($newpin !== $confirm) {
        $_SESSION['error'] = "Konfirmasi PIN baru tidak cocok.";
    } else {
        $query = "SELECT UserPass2, UserPass, UserEmail FROM dbo.UserInfo WHERE UserID = ?";
        $stmt = sqlsrv_query($conn, $query, [$userid]);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

        if ($row && $row['UserEmail'] === $email && $row['UserPass2'] === $oldpin && $row['UserPass'] === $password) {
            $update = "UPDATE dbo.UserInfo SET UserPass2 = ? WHERE UserID = ?";
            $update_stmt = sqlsrv_query($conn, $update, [$newpin, $userid]);

            if ($update_stmt) {
                $log = "UserID: $userid | PIN Lama: $oldpin | PIN Baru: $newpin | " . date("d-m-Y H:i:s") . PHP_EOL;
                file_put_contents(__DIR__ . '/../log/log_change_pin.txt', $log, FILE_APPEND);
                $_SESSION['success'] = "PIN berhasil diubah.";
            } else {
                $_SESSION['error'] = "Gagal mengubah PIN.";
            }
        } else {
            $_SESSION['error'] = "Email, PIN lama, atau password salah.";
        }
    }

    header("Location: change_pin");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white flex justify-center items-center min-h-screen">
  <form method="POST" class="bg-gray-800 p-6 rounded shadow w-80">
    <h2 class="text-2xl font-bold mb-2 text-center">UBAH PIN</h2>
    <div class="flex justify-center mb-2">
      <img src="../assets/logo.png" alt="Logo" class="h-22 w-auto">
    </div>

    <!-- Notifikasi -->
    <?php if (isset($_SESSION['success'])): ?>
      <p class="text-green-500 text-center mb-2"><?= $_SESSION['success']; unset($_SESSION['success']); ?></p>
    <?php elseif (isset($_SESSION['error'])): ?>
      <p class="text-red-500 text-center mb-2"><?= $_SESSION['error']; unset($_SESSION['error']); ?></p>
    <?php endif; ?>

    <input name="old_pin" type="password" maxlength="6" class="w-full p-2 border mb-2 text-black" placeholder="PIN Lama" required>
    <input name="new_pin" type="password" maxlength="6" class="w-full p-2 border mb-2 text-black" placeholder="PIN Baru" required>
    <input name="confirm_pin" type="password" maxlength="6" class="w-full p-2 border mb-2 text-black" placeholder="Konfirmasi PIN Baru" required>
    <input name="password" type="password" class="w-full p-2 border mb-2 text-black" placeholder="Password Sekarang" required>
    <input name="email" type="email" class="w-full p-2 border mb-4 text-black" placeholder="Email Akun" required>

    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <button class="bg-blue-600 text-white font-bold py-2 px-4 w-full rounded hover:bg-blue-700">SIMPAN</button>
    <a href="./" class="block mt-3 text-center bg-gray-600 text-white font-bold py-2 px-4 w-full rounded hover:bg-gray-700">KEMBALI</a>
  </form>

  <script>
    setTimeout(() => {
      const notif = document.querySelector('form p.text-green-500, form p.text-red-500');
      if (notif) notif.remove();
    }, 3000);
  </script>
</body>
</html>
