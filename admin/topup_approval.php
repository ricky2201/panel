<?php
session_start();
require_once '../config/db.php';         // RG1User
require_once '../config/db_panel.php';   // NRSPanel
require_once '../includes/csrf.php';     // ✅ CSRF tools

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

date_default_timezone_set('Asia/Jakarta');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    if (!csrf_validate('topup_approval')) {
        $_SESSION['msg'] = "<span class='text-red-500'>❌ Permintaan tidak valid (CSRF token tidak cocok).</span>";
        header("Location: topup_manage");
        exit;
    }

    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];

    // Ambil data topup
    $stmt = sqlsrv_query($connPanel, "SELECT * FROM TopupRequest WHERE ID = ?", [$requestId]);
    if (!$stmt) {
        $_SESSION['msg'] = "<span class='text-red-500'>❌ Gagal mengambil data topup.</span>";
        header("Location: topup_manage");
        exit;
    }

    $request = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    if (!$request || $request['Status'] !== 'pending') {
        $_SESSION['msg'] = "<span class='text-red-500'>❌ Topup tidak ditemukan atau sudah diproses.</span>";
        header("Location: topup_manage");
        exit;
    }

    $userID       = $request['UserID'];
    $amount       = (int)$request['Amount'];
    $epointTotal  = (int)$request['EPoint'];
    $method       = $request['Method'];
    $referral     = $request['Referral'];
    $card_id      = isset($request['CardID']) ? (int)$request['CardID'] : 0;

    if ($action === 'approve') {
        // Ambil epoint dasar dari kartu
        $epointDasar   = 0;
        $bonusPercent  = 0;
        if ($card_id > 0) {
            $stmtCard = sqlsrv_query($connPanel, "SELECT epoint, bonus_percent FROM TopupCard WHERE id = ?", [$card_id]);
            if ($stmtCard && ($card = sqlsrv_fetch_array($stmtCard, SQLSRV_FETCH_ASSOC))) {
                $epointDasar  = (int)$card['epoint'];
                $bonusPercent = floatval($card['bonus_percent']);
            }
        }

        // Tambahkan EPoint ke akun user
        sqlsrv_query($conn, "UPDATE UserInfo SET UserPoint = UserPoint + ? WHERE UserID = ?", [$epointTotal, $userID]);

        // Periksa dan eksekusi reward referral
        $stmtReward = sqlsrv_query($connPanel, "SELECT * FROM ReferralRewardHistory WHERE TopupRequestID = ? AND Status = 'pending'", [$requestId]);
        if ($stmtReward && $reward = sqlsrv_fetch_array($stmtReward, SQLSRV_FETCH_ASSOC)) {
            $referrerUserID = $reward['ReferrerUserID'];
            $rewardEPoint   = (int)$reward['RewardEPoint'];

            // Tambah point ke referrer
            sqlsrv_query($conn, "UPDATE UserInfo SET UserPoint = UserPoint + ? WHERE UserID = ?", [$rewardEPoint, $referrerUserID]);

            // Update reward menjadi approved
            sqlsrv_query($connPanel, "UPDATE ReferralRewardHistory SET Status = 'approved' WHERE ID = ?", [$reward['ID']]);
        }

        $result = sqlsrv_query($connPanel, "UPDATE TopupRequest SET Status = 'approved', ResponseDate = GETDATE() WHERE ID = ?", [$requestId]);
        $_SESSION['msg'] = $result
            ? "<span class='text-green-500'>✅ Topup berhasil disetujui.</span>"
            : "<span class='text-red-500'>❌ Gagal memperbarui status.</span>";

    } elseif ($action === 'reject') {
        $result = sqlsrv_query($connPanel, "UPDATE TopupRequest SET Status = 'rejected', ResponseDate = GETDATE() WHERE ID = ?", [$requestId]);
        $_SESSION['msg'] = $result
            ? "<span class='text-red-500'>❌ Topup ditolak.</span>"
            : "<span class='text-red-500'>❌ Gagal memperbarui status.</span>";
    }

    header("Location: topup_manage");
    exit;
}

// Ambil detail topup jika ada ID
$detail = null;
if (isset($_GET['id'])) {
    $requestId = (int)$_GET['id'];
    $stmt = sqlsrv_query($connPanel, "SELECT * FROM TopupRequest WHERE ID = ?", [$requestId]);
    if ($stmt) {
        $detail = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Detail Topup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-6 min-h-screen">
    <div class="max-w-xl mx-auto bg-gray-800 rounded shadow p-6">
        <h1 class="text-xl font-bold mb-4 text-white">Detail Topup</h1>

        <?php if (!$detail): ?>
            <p class="text-red-500 font-bold">❌ Data topup tidak ditemukan.</p>
        <?php else: ?>
            <table class="w-full text-sm mb-4">
                <tr><td class="font-semibold py-1 w-32">User ID</td><td>: <?= htmlspecialchars($detail['UserID']) ?></td></tr>
                <tr><td class="font-semibold py-1">Amount</td><td>: Rp<?= number_format($detail['Amount'], 0, ',', '.') ?></td></tr>
                <tr><td class="font-semibold py-1">Total E-Point</td><td>: <?= number_format($detail['EPoint'], 0, ',', '.') ?> EP</td></tr>
                <tr><td class="font-semibold py-1">Metode</td><td>: <?= htmlspecialchars($detail['Method'] ?? '-') ?></td></tr>
                <tr><td class="font-semibold py-1">Status</td><td>: <span class="font-bold text-yellow-400"><?= ucfirst($detail['Status']) ?></span></td></tr>
                <tr><td class="font-semibold py-1">Referral</td><td>: <?= $detail['Referral'] ?: '-' ?></td></tr>
            </table>

            <?php if ($detail['Status'] === 'pending'): ?>
                <form method="POST" class="space-x-2 mb-4" onsubmit="return confirmAction(event)">
                <?= csrf_input('topup_approval') ?>
                <input type="hidden" name="request_id" value="<?= (int)$detail['ID'] ?>">
                <button type="submit" name="action" value="approve" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">✅ Setujui</button>
                <button type="submit" name="action" value="reject" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">❌ Tolak</button>
                </form>
            <?php else: ?>
                <p class="text-green-400 font-semibold mb-4">✅ Sudah diproses.</p>
            <?php endif; ?>

            <a href="topup_manage" class="inline-block bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded">⬅️ Kembali</a>
        <?php endif; ?>
    </div>

    <script>
    function confirmAction(event) {
        const form = event.target;
        const action = event.submitter.value;

        let message = '';
        if (action === 'approve') {
            message = 'Apakah kamu yakin ingin menyetujui topup ini?';
        } else if (action === 'reject') {
            message = 'Apakah kamu yakin ingin menolak topup ini?';
        }

        if (!confirm(message)) {
            event.preventDefault();
            return false;
        }
        return true;
    }
    </script>
</body>
</html>
