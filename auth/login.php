<?php
session_start();
require_once '../path.php';
require_once '../config/db.php';
require_once '../includes/csrf.php'; // Fungsi CSRF milikmu

$error = '';

// Pesan error dari URL
if (isset($_GET['timeout'])) {
    $error = "⏱️ Sesi berakhir karena tidak ada aktivitas. Silakan login ulang.";
} elseif (isset($_GET['invalid_session'])) {
    $error = "⚠️ Sesi tidak valid, Silakan login ulang.";
}

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('login')) {
        $error = "⚠️ Permintaan tidak valid (CSRF).";
    } else {
        $userid = $_POST['userid'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = sqlsrv_query($conn, "SELECT UserID, UserPass, UserType FROM dbo.UserInfo WHERE UserID = ?", [$userid]);

        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            if ($password === $row['UserPass']) {
                $_SESSION['userid'] = $row['UserID'];
                $_SESSION['UserType'] = $row['UserType'];
                $_SESSION['UserAgent'] = $_SERVER['HTTP_USER_AGENT'];
                $_SESSION['UserIP'] = $_SERVER['REMOTE_ADDR'];
                $_SESSION['last_login'] = time();
                $_SESSION['last_activity'] = time();

                header("Location: ../userpanel/");
                exit;
            } else {
                $error = "❌ Login gagal: Password salah.";
            }
        } else {
            $error = "❌ Login gagal: Username tidak ditemukan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Hiperion RAN - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-900 flex justify-center items-center h-screen">

  <form method="POST" class="bg-gray-800 text-white p-6 rounded-lg shadow-md w-80 border border-gray-700">
    <h2 class="text-2xl font-bold mb-4 text-center">Login Akun</h2>

    <div class="flex justify-center mb-4">
      <img src="<?= ASSETS_PATH ?>logo.png" alt="Logo" class="h-22 w-auto">
    </div>

    <?php if (!empty($error)): ?>
      <div class="bg-red-500 bg-opacity-10 border border-red-500 text-red-400 p-2 rounded mb-3 text-sm">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <!-- ✅ CSRF token -->
    <?= csrf_input('login') ?>

    <input type="text" name="userid" placeholder="Username" required
           class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded mb-3 focus:outline-none focus:ring-2 focus:ring-blue-600 text-white placeholder-gray-400">

    <input type="password" name="password" placeholder="Password" required
           class="w-full px-3 py-2 bg-gray-900 border border-gray-700 rounded mb-4 focus:outline-none focus:ring-2 focus:ring-blue-600 text-white placeholder-gray-400">

    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white w-full font-bold py-2 rounded transition">
      LOGIN
    </button>

    <div class="mt-4 flex flex-col gap-2 text-sm text-center">
      <a href="forgot_password" class="text-blue-400 hover:underline">Lupa Password?</a>
      <a href="../" class="text-gray-400 hover:text-white">← Kembali ke Beranda</a>
    </div>
  </form>

</body>
</html>
