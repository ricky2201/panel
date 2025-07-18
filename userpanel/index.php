<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['userid']) || $_SESSION['UserAgent'] !== $_SERVER['HTTP_USER_AGENT'] || $_SESSION['UserIP'] !== $_SERVER['REMOTE_ADDR']) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login?invalid_session=1");
    exit;
}

require_once __DIR__ . '/../config/db_game.php'; // koneksi ke RG1User
require_once __DIR__ . '/../config/db.php';      // koneksi ke NRSPanel
require_once __DIR__ . '/../path.php';
require_once __DIR__ . '/../components/navbar.php';

$userid = $_SESSION['userid'];

// Ambil data user dari RG1User (data utama user)
$stmt = sqlsrv_query($conn, "SELECT UserID, UserName, UserEmail, UserPoint, VotePoint, PlayTime, UserLoginState, Referral FROM dbo.UserInfo WHERE UserID = ?", [$userid]);
$userData = ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) ? $row : [];
$hasReferral = !empty($userData['Referral']); // cek apakah user punya kode referral

function generateCaptcha($length = 6) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz123456789';
    return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['captcha'] = generateCaptcha();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_convert'])) {
    $captchaInput = $_POST['captcha'] ?? '';
    if ($captchaInput !== ($_SESSION['captcha'] ?? '')) {
        $_SESSION['convert_message'] = "❌ Captcha salah!";
        header("Location: ./"); exit;
    }
    $playtime = (int)$userData['PlayTime'];
    if ((int)$userData['UserLoginState'] === 1) {
        $_SESSION['convert_message'] = "❌ Harap logout dari game dulu.";
        header("Location: ./"); exit;
    }
    if ($playtime < 60) {
        $_SESSION['convert_message'] = "❌ Minimal 60 menit PlayTime dibutuhkan!";
        header("Location: ./"); exit;
    }
    $claimableVP = floor($playtime / 60) * 2;
    $usedPlaytime = floor($playtime / 60) * 60;
    $update = sqlsrv_query($conn, "UPDATE dbo.UserInfo SET VotePoint = VotePoint + ?, PlayTime = PlayTime - ? WHERE UserID = ?", [$claimableVP, $usedPlaytime, $userid]);
    if ($update) {
        file_put_contents(__DIR__ . '/../log/log_convert_vp.txt', date("Y-m-d H:i:s") . " | $userid | +$claimableVP VP | -$usedPlaytime PlayTime\n", FILE_APPEND);
        unset($_SESSION['captcha']);
        $_SESSION['success'] = true;
        header("Location: ./"); exit;
    }
    $_SESSION['convert_message'] = "❌ Gagal menyimpan ke database.";
}

function maskEmail($email) {
    $at = strpos($email, '@');
    return $at !== false ? substr($email, 0, 4) . str_repeat('*', $at - 4) . substr($email, $at) : str_repeat('*', strlen($email));
}

$claimableVP = floor(($userData['PlayTime'] ?? 0) / 60) * 2;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard - Glacier</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col" x-data="{ open: false }">

  <?php include_once __DIR__ . '/../components/navbar.php'; ?>

  <header class="text-center mt-8">
    <h1 class="text-4xl font-bold text-white tracking-widest">User Panel</h1>
  </header>

  <main class="flex-grow max-w-6xl mx-auto py-10 px-4 grid grid-cols-1 md:grid-cols-3 gap-8">
    <div class="md:col-span-2 space-y-6">
      <h2 class="text-xl font-bold">
      Welcome, <span class="text-yellow-400"><?= htmlspecialchars($userid) ?></span>!
      </h2>
      <div class="bg-gray-800 p-6 rounded shadow space-y-2">
        <div class="flex justify-between"><span class="font-semibold">USERNAME:</span><span><?= htmlspecialchars($userData['UserName']) ?></span></div>
        <div class="flex justify-between"><span class="font-semibold">EMAIL:</span><span><?= htmlspecialchars(maskEmail($userData['UserEmail'])) ?></span></div>
        <div class="flex justify-between"><span class="font-semibold">USER POINT:</span><span><?= number_format($userData['UserPoint'], 0, ',', '.') ?></span></div>
        <div class="flex justify-between"><span class="font-semibold">VOTE POINT:</span><span><?= number_format($userData['VotePoint'], 0, ',', '.') ?></span></div>
        <div class="flex justify-between"><span class="font-semibold">PLAYTIME:</span><span><?= number_format($userData['PlayTime'], 0, ',', '.') ?> menit</span></div>
      </div>

      <?php if (isset($_SESSION['convert_message'])): ?>
      <div id="convert-message" class="text-red-500 text-center"><?= $_SESSION['convert_message']; ?></div>
      <script>
        setTimeout(() => {
          const el = document.getElementById('convert-message');
          if (el) el.remove();
        }, 2500);
      </script>
      <?php unset($_SESSION['convert_message']); ?>
    <?php endif; ?>
      <?php if (isset($_SESSION['success'])): ?>
      <div id="convert-success" class="text-green-500 text-center">Konversi berhasil!</div>
      <script>
        setTimeout(() => {
          const el = document.getElementById('convert-success');
          if (el) el.remove();
        }, 2500);
      </script>
      <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

      <div class="bg-gray-800 p-6 rounded shadow space-y-4">
        <h3 class="text-lg font-bold text-center">Convert PlayTime to V-Point</h3>
        <p class="text-sm text-gray-300 text-center">60 minutes = 2 VotePoint</p>
        <p class="text-center">VP can be claimed: <span class="text-green-400 font-bold"><?= $claimableVP ?> V-Point</span></p>
        <div class="flex justify-center">
          <button @click="open = true" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded text-white font-bold">
            Convert
          </button>
        </div>
      </div>
    </div>

    <div class="space-y-6">
     <div class="flex flex-col space-y-3">
        <a href=" <?= BASE_URL ?>topup/" class="block bg-green-600 text-white py-2 rounded font-bold hover:bg-green-700 text-center uppercase" style="width: 150%;">Topup</a>
        <a href="<?= BASE_URL ?>userpanel/topup-history" class="block bg-yellow-600 text-white py-2 rounded font-bold hover:bg-yellow-700 text-center uppercase" style="width: 150%;">Topup History</a>

        <?php if ($hasReferral): ?>
        <a href="<?= BASE_URL ?>userpanel/referral-history" class="block bg-purple-600 text-white py-2 rounded font-bold hover:bg-purple-700 text-center uppercase" style="width: 150%;">Referral History</a>
        <?php endif; ?>

        <a href="whitelist-ip" class="block bg-purple-600 text-white py-2 rounded font-bold hover:bg-purple-700 text-center uppercase" style="width: 150%;">
            Request Whitelist IP
        </a>

        <a href="change-password" class="block bg-blue-600 text-white py-2 rounded hover:bg-blue-700 text-center font-bold uppercase" style="width: 150%;">Change Password</a>
        <a href="change_email" class="block bg-blue-600 text-white py-2 rounded hover:bg-blue-700 text-center font-bold uppercase" style="width: 150%;">Change Email</a>
        <a href="change_pin" class="block bg-blue-600 text-white py-2 rounded hover:bg-blue-700 text-center font-bold uppercase" style="width: 150%;">Change Pin</a>
      </div>
    </div>
  </main>

  <div x-show="open" class="fixed inset-0 bg-black bg-opacity-60 flex justify-center items-center z-50">
    <div class="bg-white text-black rounded-lg shadow-lg w-96 p-6">
      <h2 class="text-xl font-bold mb-4 text-center">Captcha Verification</h2>
      <form method="POST">
        <input type="hidden" name="confirm_convert" value="1">
        <p class="mb-3 text-center">Code: <span class="font-mono bg-gray-800 text-white px-3 py-1 rounded text-lg tracking-widest select-none"><?= $_SESSION['captcha'] ?></span></p>
        <input type="text" name="captcha" maxlength="6" required class="w-full px-3 py-2 border rounded mb-4" oncopy="return false;" onpaste="return false;" oncontextmenu="return false;" onselectstart="return false;">
        <div class="flex justify-between gap-3">
          <button type="submit" class="bg-blue-600 text-white w-full py-2 rounded hover:bg-blue-700 font-bold">Confirm</button>
          <button type="button" @click="window.location.reload()" class="bg-gray-400 w-full text-black font-bold py-2 rounded hover:bg-gray-500">Close</button>
        </div>
      </form>
    </div>
  </div>

  <?php include_once __DIR__ . '/../components/footer.php'; ?>

</body>
</html>