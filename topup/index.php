<?php
session_start();
require_once __DIR__ . '/../config/db_panel.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php'; // ✅ Tambahkan ini

if (!isset($_SESSION['userid'])) {
  header("Location: ../");
  exit;
}

// Notifikasi dari session
$notif_message = $_SESSION['notif'] ?? '';
$notif_type = $_SESSION['notif_type'] ?? '';
unset($_SESSION['notif'], $_SESSION['notif_type']);

// Ambil kartu
$cards = [];
$stmt = sqlsrv_query($connPanel, "SELECT * FROM TopupCard WHERE status = 1 ORDER BY amount ASC");
if ($stmt) {
  while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $cards[] = $row;
  }
} else {
  die("❌ Gagal mengambil data kartu: " . print_r(sqlsrv_errors(), true));
}

// ✅ Validasi CSRF saat POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_validate('topup')) {
    $_SESSION['notif'] = 'Token CSRF tidak valid.';
    $_SESSION['notif_type'] = 'error';
    header("Location: index.php");
    exit;
  }

  $card_id = $_POST['card_id'] ?? null;
  $referralCode = trim($_POST['referral_code'] ?? '');

  if (!$card_id) {
    $_SESSION['notif'] = 'Pilih nominal topup terlebih dahulu.';
    $_SESSION['notif_type'] = 'error';
    header("Location: index.php");
    exit;
  }

  if (!empty($referralCode)) {
    $stmtReferral = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM dbo.UserInfo WHERE Referral = ?", [$referralCode]);
    if ($stmtReferral && $row = sqlsrv_fetch_array($stmtReferral, SQLSRV_FETCH_ASSOC)) {
      if ((int)$row['total'] === 0) {
        $_SESSION['notif'] = 'Kode Referral tidak terdaftar!';
        $_SESSION['notif_type'] = 'error';
        header("Location: index.php");
        exit;
      }
    }
  }

  // Lanjut ke payment
  echo '
    <form id="redirectForm" method="POST" action="topup-payment">
      <input type="hidden" name="card_id" value="' . htmlspecialchars($card_id) . '">
      <input type="hidden" name="referral_code" value="' . htmlspecialchars($referralCode) . '">
      ' . csrf_input('topup') . '
    </form>
    <script>document.getElementById("redirectForm").submit();</script>
  ';
  exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Topup E-Point</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col relative">

<?php include '../components/navbar.php'; ?>

<main class="flex-grow px-4 py-8 max-w-6xl mx-auto">
  <h1 class="text-3xl font-bold mb-6 text-center">Topup E-Point</h1>

  <?php if (!empty($notif_message)): ?>
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show"
    class="transition-all duration-500 ease-in-out <?= $notif_type === 'error' ? 'bg-red-600' : 'bg-green-600' ?> text-white px-4 py-3 rounded-lg shadow mb-6 text-center">
    <?= htmlspecialchars($notif_message) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="index.php">
    <!-- ✅ CSRF token input -->
    <?= csrf_input('topup') ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
      <?php foreach ($cards as $card): 
        $bonus_active = isset($card['bonus_active']) && $card['bonus_active'];
        $bonus = $bonus_active ? (int)$card['Bonus'] : 0;
        $bonus_percent = isset($card['bonus_percent']) ? (int)$card['bonus_percent'] : 0;
        $total_ep = (int)$card['epoint'] + $bonus;
      ?>
      <label class="relative block cursor-pointer transition-all">
        <input type="radio" name="card_id" value="<?= $card['id'] ?>" class="peer hidden">
        <div class="p-4 rounded-xl bg-gray-800 border-2 border-transparent peer-checked:border-blue-500 peer-checked:ring-2 peer-checked:ring-blue-400 hover:border-blue-400 transition-all space-y-2 h-full">
          <?php if ($bonus_active): ?>
            <div class="absolute top-0 left-0 bg-pink-600 text-[11px] px-3 py-1 rounded-br-lg text-white font-bold z-10">EVENT</div>
          <?php endif; ?>
          <h2 class="text-center text-yellow-300 font-bold text-lg">Rp<?= number_format($card['amount'], 0, ',', '.') ?></h2>
          <p class="text-center text-green-400 text-base font-medium"><?= number_format($card['epoint'], 0, ',', '.') ?> E-Point</p>
          <?php if ($bonus_active): ?>
            <p class="text-center text-pink-400 text-sm">+<?= number_format($bonus, 0, ',', '.') ?> Bonus (<?= $bonus_percent ?>%)</p>
          <?php endif; ?>
          <p class="text-center font-bold text-white text-base">Total: <?= number_format($total_ep, 0, ',', '.') ?> EP</p>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="mt-8 max-w-sm mx-auto">
      <label for="referral" class="block mb-2 font-medium text-white text-center">Code Referral Bonus Topup 10% (Opsional)</label>
      <input type="text" name="referral_code" id="referral" placeholder="Masukkan kode referral"
             class="w-full p-3 rounded text-black bg-white text-center">
    </div>

    <div class="text-center mt-8">
      <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg font-bold text-xl">
        Submit
      </button>
    </div>
  </form>
</main>

<?php include '../components/footer.php'; ?>

</body>
</html>