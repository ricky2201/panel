<?php
session_start();
require_once '../config/db.php';
require_once '../config/db_panel.php';
require_once '../includes/csrf.php';

date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['userid'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userid = $_SESSION['userid'];
$card_id = $_POST['card_id'] ?? null;
$referral_code = trim($_POST['referral_code'] ?? '');
$bonus_event = 0;
$bonus_referral = 0;
$total_epoint = 0;
$status = 'pending';

if (!$card_id) die("\u274C Tidak ada kartu yang dipilih.");

// Ambil data kartu
$stmt = sqlsrv_query($connPanel, "SELECT * FROM TopupCard WHERE id = ? AND status = 1", [$card_id]);
if (!$stmt || !($card = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))) {
    die("\u274C Kartu topup tidak ditemukan.");
}

$amount = (int)$card['amount'];
$epoint = (int)$card['epoint'];
if (!empty($card['bonus_active']) && $card['bonus_active']) {
    $bonus_event = (int)$card['Bonus'];
}

// Validasi kode referral
$referral_valid = false;
$referrer_userid = null;
if (!empty($referral_code)) {
    $stmt_ref = sqlsrv_query($conn, "SELECT UserNum, UserID FROM UserInfo WHERE LOWER(Referral) = LOWER(?)", [$referral_code]);
    if ($stmt_ref && $row_ref = sqlsrv_fetch_array($stmt_ref, SQLSRV_FETCH_ASSOC)) {
        $referral_valid = true;
        $referrer_userid = $row_ref['UserID'];
        $bonus_referral = floor($epoint * 0.10);
    }
}

$total_epoint = $epoint + $bonus_event + $bonus_referral;
$show_popup = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_konfirmasi'])) {
    $cek = sqlsrv_query($connPanel, "SELECT COUNT(*) AS total FROM TopupRequest WHERE UserID = ? AND Status = 'pending'", [$userid]);
    if ($cek && $row = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC)) {
        if ((int)$row['total'] >= 3) {
            echo '<script>
                alert("\u274C Anda terlalu banyak mencoba topup. Tunggu admin memproses yang sebelumnya atau contact admin melalui discord.");
                window.location.href = "./";
            </script>';
            exit;
        }
    }

    // Simpan TopupRequest dan ambil ID yang dihasilkan
    $insert_topup = sqlsrv_query($connPanel, "
        INSERT INTO NRSPanel.dbo.TopupRequest (UserID, Amount, EPoint, Status, RequestDate, Method, Referral, CardID)
        OUTPUT INSERTED.ID
        VALUES (?, ?, ?, ?, GETDATE(), ?, ?, ?)
    ", [$userid, $amount, $total_epoint, $status, 'QRIS', $referral_code, $card_id]);

    $row_inserted = sqlsrv_fetch_array($insert_topup, SQLSRV_FETCH_ASSOC);
    $topup_id = $row_inserted['ID'] ?? null;

    if (!$topup_id) {
        die("\u274C Gagal menyimpan data topup: " . print_r(sqlsrv_errors(), true));
    }

    // Jika referral valid, simpan riwayat reward (status pending)
    if ($referral_valid && $referrer_userid !== $userid) {
        sqlsrv_query($connPanel, "
            INSERT INTO NRSPanel.dbo.ReferralRewardHistory (ReferrerUserID, ReferredUserID, TopupAmount, RewardEPoint, CreatedAt, Status, TopupRequestID)
            VALUES (?, ?, ?, ?, GETDATE(), 'pending', ?)
        ", [$referrer_userid, $userid, $amount, $bonus_referral, $topup_id]);
    }

    // Kirim notifikasi ke Telegram
    $botToken = '8055892366:AAHU8VxaKk4CoMhvZYeDEnWhpteXfF7lPw8';
    $chatID = '-4876101228';

    $message = "*TOPUP BARU MASUK*\n\n"
        . "UserID: $userid\n"
        . "Jumlah: Rp " . number_format($amount, 0, ',', '.') . "\n"
        . "EPoint: $total_epoint\n"
        . "Tanggal: " . date('Y-m-d H:i:s');

    $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage?chat_id={$chatID}&text=" . urlencode($message) . "&parse_mode=Markdown";

    if (!empty($botToken) && !empty($chatID) && !empty($message)) {
        @file_get_contents($sendUrl);
    }

    $show_popup = true;
}
?>

<!-- HTML Dimulai -->
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Konfirmasi Topup</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen px-4 py-8">
<div class="max-w-xl mx-auto bg-gray-800 p-6 rounded-lg shadow text-center">
  <h1 class="text-2xl font-bold mb-6 text-yellow-400">Konfirmasi Topup</h1>

  <form method="POST">
    <input type="hidden" name="card_id" value="<?= htmlspecialchars($card_id) ?>">
    <input type="hidden" name="referral_code" value="<?= htmlspecialchars($referral_code) ?>">

    <div class="space-y-3 text-left text-sm sm:text-base">
      <div class="flex justify-between"><span class="font-medium">User ID:</span><span><?= htmlspecialchars($userid) ?></span></div>
      <div class="flex justify-between"><span class="font-medium">Nominal:</span><span>Rp<?= number_format($amount, 0, ',', '.') ?></span></div>
      <div class="flex justify-between"><span class="font-medium">E-Point:</span><span><?= number_format($epoint) ?> EP</span></div>

      <?php if ($bonus_event): ?>
      <div class="flex justify-between text-pink-400"><span class="font-medium">Bonus Event:</span><span>+<?= number_format($bonus_event) ?> EP</span></div>
      <?php endif; ?>

      <?php if (!empty($referral_code)): ?>
        <?php if ($referral_valid): ?>
          <div class="flex justify-between text-green-400"><span class="font-medium">Kode Referral:</span><span><?= htmlspecialchars($referral_code) ?></span></div>
          <div class="flex justify-between text-green-400"><span class="font-medium">Bonus Referral:</span><span>+<?= number_format($bonus_referral) ?> EP</span></div>
        <?php else: ?>
          <p class="text-red-500 font-bold">\u274C Kode Referral tidak valid.</p>
        <?php endif; ?>
      <?php endif; ?>

      <hr class="border-gray-600 my-3">
      <div class="flex justify-between text-blue-400 text-lg font-bold">
        <span>Total E-Point:</span>
        <span><?= number_format($total_epoint) ?> EP</span>
      </div>
    </div>

    <div class="mt-8 text-center" x-data="{ showQRIS: true }">
      <h2 class="text-xl font-semibold mb-3">Metode Pembayaran</h2>
      <h3 class="text-md mb-3">QRIS ( Manual Verifikasi )</h3>
      <div class="mt-4" x-show="showQRIS" x-transition>
        <img src="../assets/qris.png" alt="QRIS" class="max-w-xs mx-auto rounded-lg border border-gray-500">
      </div>
    </div>

    <div class="mt-8">
      <button type="submit" name="submit_konfirmasi" class="bg-green-600 hover:bg-green-700 px-6 py-3 rounded text-white font-bold">
        Submit Topup
      </button>
    </div>
  </form>

  <div class="mt-4">
    <a href="./" class="text-sm text-blue-400 hover:underline">Kembali</a>
  </div>
</div>

<?php if ($show_popup): ?>
<div class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50">
  <div class="bg-white text-black p-6 rounded-lg shadow-lg max-w-sm w-full text-center">
    <h2 class="text-lg font-bold text-green-600 mb-2">REQUEST TOPUP SUDAH TERKIRIM</h2>
    <p class="mb-2">SILAHKAN HUBUNGI ADMIN DAN WAJIB KIRIMKAN BUKTI TRANSFER MELALUI:</p>
    <ul class="mb-4 space-y-1">
      <li>• <a href="https://discord.gg/yourdiscordlink" class="text-blue-600 underline" target="_blank">DISCORD</a> (FAST RESPON JIKA SEDANG ONLINE)</li>
      <li>• <a href="https://facebook.com/yourpage" class="text-blue-600 underline" target="_blank">FACEBOOK</a></li>
    </ul>
    <button onclick="window.location.href='../userpanel/topup-history'" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded">Tutup</button>
  </div>
</div>
<?php endif; ?>

<!-- Popup saat halaman diload -->
<div class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-40">
  <div class="bg-white text-black p-6 rounded-lg shadow-lg max-w-sm w-full text-center">
    <h2 class="text-lg font-bold text-red-600 mb-2">PENTING!</h2>
    <p class="mb-2">SETELAH MELAKUKAN TRANSFER, WAJIB KIRIMKAN FOTO BUKTI TRANSFER MELALUI:</p>
    <ul class="mb-4 space-y-1">
      <li>• <a href="https://discord.gg/yourdiscordlink" class="text-blue-600 underline" target="_blank">DISCORD</a> (FAST RESPON JIKA SEDANG ONLINE)</li>
      <li>• <a href="https://facebook.com/yourpage" class="text-blue-600 underline" target="_blank">FACEBOOK</a></li>
    </ul>
    <button onclick="this.parentElement.parentElement.remove()" class="mt-2 px-4 py-2 bg-blue-600 text-white rounded">Tutup</button>
  </div>
</div>

</body>
</html>