<?php
// Pastikan session_start() ada di baris paling atas
session_start();

// --- PENGATURAN ERROR REPORTING UNTUK PRODUKSI ---
ini_set('display_errors', 0); // Atur ke 1 untuk debugging di lingkungan pengembangan
ini_set('display_startup_errors', 0); // Atur ke 1 untuk debugging di lingkungan pengembangan
error_reporting(E_ALL);
// --- AKHIR PENGATURAN ERROR REPORTING ---

// Memuat file konfigurasi dan fungsi
require_once '../config/db.php';
require_once '../config/db_game.php';
require_once '../config/db_panel.php';
require_once '../includes/csrf.php';

// Proteksi akses admin
if (!isset($_SESSION['userid']) || !isset($_SESSION['UserType']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

// Inisialisasi CSRF token
if (empty($_SESSION['csrf_tokens'])) {
    $_SESSION['csrf_tokens'] = [];
}

// Default tanggal untuk tampilan statistik hari ini/bulan ini
$customDate = date('Y-m-d');
$customMonth = date('m');
$customYear = date('Y');

// Variabel untuk bulan dan tahun donasi yang aktif/diatur admin
// Ini adalah nilai default awal jika belum ada pengaturan di database
$activeMonth = (int)date('m');
$activeYear = (int)date('Y');

// ⛳ Cek apakah form Bulan Donasi disubmit atau form toggle server
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['month'], $_POST['year']) && csrf_validate('donasi')) {
        $newMonth = (int)$_POST['month'];
        $newYear = (int)$_POST['year'];

        // Periksa apakah sudah ada entri di TopupSettings
        $checkStmt = sqlsrv_query($connPanel, "SELECT COUNT(*) AS total FROM NRSPanel.dbo.TopupSettings");
        $exists = 0;
        if ($checkStmt && $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
            $exists = (int)$row['total'];
        }

        // Jika sudah ada, update; jika belum, insert
        if ($exists > 0) {
            $updateQuery = "UPDATE NRSPanel.dbo.TopupSettings SET month = ?, year = ?";
            $params = [$newMonth, $newYear];
            sqlsrv_query($connPanel, $updateQuery, $params);
        } else {
            $insertQuery = "INSERT INTO NRSPanel.dbo.TopupSettings (month, year) VALUES (?, ?)";
            $params = [$newMonth, $newYear];
            sqlsrv_query($connPanel, $insertQuery, $params);
        }

        // Redirect untuk mencegah resubmission form dan memuat ulang data dengan nilai baru
        header("Location: index");
        exit;

    } elseif (isset($_POST['toggle_status']) && csrf_validate('toggle')) {
        $statusFile = __DIR__ . '/server_status.txt';
        $currentStatus = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'OFFLINE';
        $newStatus = $currentStatus === 'ONLINE' ? 'OFFLINE' : 'ONLINE';
        file_put_contents($statusFile, $newStatus);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// 🗓 Ambil pengaturan TopupSetting
// Bagian ini akan membaca nilai bulan dan tahun yang terakhir disimpan di database
// Ini akan override nilai default $activeMonth dan $activeYear di atas
$stmtSetting = sqlsrv_query($connPanel, "SELECT TOP 1 month, year FROM NRSPanel.dbo.TopupSettings ORDER BY id DESC");
if ($stmtSetting && $row = sqlsrv_fetch_array($stmtSetting, SQLSRV_FETCH_ASSOC)) {
    $activeMonth = (int)$row['month'];
    $activeYear = (int)$row['year'];
}


// --- Pengambilan Data Statistik ---

// Total berita
$totalNews = 0;
$stmtNews = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM RG1User.dbo.News");
if ($stmtNews && $row = sqlsrv_fetch_array($stmtNews, SQLSRV_FETCH_ASSOC)) {
    $totalNews = (int)$row['total'];
}

// 🏆 Top 3 Donatur Bulan yang Diatur Admin
$top3Donors = [];
$queryTop3 = "
    SELECT TOP 3 tr.UserID, SUM(tr.Amount) AS total, ui.UserNum
    FROM TopupRequest tr
    JOIN RG1User.dbo.UserInfo ui ON tr.UserID = ui.UserID
    WHERE tr.Status = 'approved'
      AND MONTH(tr.RequestDate) = ? AND YEAR(tr.RequestDate) = ?
    GROUP BY tr.UserID, ui.UserNum
    ORDER BY total DESC
";
// Query ini menggunakan $activeMonth dan $activeYear yang sudah diperbarui
$stmtTop3 = sqlsrv_query($connPanel, $queryTop3, [$activeMonth, $activeYear]);

if ($stmtTop3) {
    while ($row3 = sqlsrv_fetch_array($stmtTop3, SQLSRV_FETCH_ASSOC)) {
        $chaName = '-';
        $stmtCha = sqlsrv_query($connGame, "SELECT TOP 1 ChaName FROM ChaInfo WHERE UserNum = ?", [$row3['UserNum']]);
        if ($stmtCha && $rowCha = sqlsrv_fetch_array($stmtCha, SQLSRV_FETCH_ASSOC)) {
            $chaName = $rowCha['ChaName'];
        }
        $row3['ChaName'] = $chaName;
        $top3Donors[] = $row3;
    }
}

// Statistik tambahan
$totalGold = 0;
$stmtGold = sqlsrv_query($connGame, "SELECT SUM(ChaMoney) AS totalGold FROM ChaInfo");
if ($stmtGold && $row = sqlsrv_fetch_array($stmtGold, SQLSRV_FETCH_ASSOC)) {
    $totalGold = (int)$row['totalGold'];
}

$totalDonation = 0;
$totalTopupSukses = 0;
$stmtDonation = sqlsrv_query($connPanel, "
    SELECT SUM(Amount) AS totalDonation, COUNT(*) AS totalTopupSukses
    FROM TopupRequest WHERE Status = 'approved'
");
if ($stmtDonation && $row = sqlsrv_fetch_array($stmtDonation, SQLSRV_FETCH_ASSOC)) {
    $totalDonation = (int)$row['totalDonation'];
    $totalTopupSukses = (int)$row['totalTopupSukses'];
}

$totalDonationToday = 0;
$stmtToday = sqlsrv_query($connPanel, "
    SELECT SUM(Amount) AS totalToday
    FROM TopupRequest
    WHERE Status = 'approved' AND CONVERT(DATE, RequestDate) = ?
", [$customDate]);
if ($stmtToday && $rowToday = sqlsrv_fetch_array($stmtToday, SQLSRV_FETCH_ASSOC)) {
    $totalDonationToday = (int)$rowToday['totalToday'];
}

$topDonorToday = '-';
$stmtTopToday = sqlsrv_query($connPanel, "
    SELECT TOP 1 UserID, SUM(Amount) AS total
    FROM TopupRequest
    WHERE Status = 'approved' AND CONVERT(DATE, RequestDate) = ?
    GROUP BY UserID ORDER BY total DESC
", [$customDate]);
if ($stmtTopToday && $rowTop = sqlsrv_fetch_array($stmtTopToday, SQLSRV_FETCH_ASSOC)) {
    $topDonorToday = $rowTop['UserID'] . ' (Rp ' . number_format($rowTop['total']) . ')';
}

$topDonorMonth = '-';
$stmtTopMonth = sqlsrv_query($connPanel, "
    SELECT TOP 1 UserID, SUM(Amount) AS total
    FROM TopupRequest
    WHERE Status = 'approved'
      AND MONTH(RequestDate) = ? AND YEAR(RequestDate) = ?
    GROUP BY UserID
    ORDER BY total DESC
", [$activeMonth, $activeYear]);
if ($stmtTopMonth && $rowMonth = sqlsrv_fetch_array($stmtTopMonth, SQLSRV_FETCH_ASSOC)) {
    $topDonorMonth = $rowMonth['UserID'] . ' (Rp ' . number_format($rowMonth['total']) . ')';
}

$total_epoint = 0;
$stmt_total = sqlsrv_query($conn, "SELECT SUM(UserPoint) AS TotalEpoint FROM UserInfo");
if ($stmt_total && $row_total = sqlsrv_fetch_array($stmt_total, SQLSRV_FETCH_ASSOC)) {
    $total_epoint = (int)$row_total['TotalEpoint'];
}

$totalUser = 0;
$stmt = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM UserInfo");
if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $totalUser = (int)$row['total'];
}

$totalOnline = 0;
$stmtOnline = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM UserInfo WHERE UserLoginState = 1");
if ($stmtOnline && $rowOnline = sqlsrv_fetch_array($stmtOnline, SQLSRV_FETCH_ASSOC)) {
    $totalOnline = (int)$rowOnline['total'];
}

// Status server
$statusFile = __DIR__ . '/server_status.txt';
$currentStatus = file_exists($statusFile) ? trim(file_get_contents($statusFile)) : 'OFFLINE';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        /* Anda bisa menghapus gaya debugging yang sebelumnya ditambahkan di sini jika ada */
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen font-sans">

<div class="flex min-h-screen">
    <aside class="w-64 bg-black bg-opacity-80 p-6 space-y-6" x-data="{ openLog: false, openTopup: false }">
        <h1 class="text-2xl font-bold tracking-wide mb-8" style="font-family: 'Bebas Neue', sans-serif;">
            Hiperion Ran Online
        </h1>
        <nav class="flex flex-col space-y-4 text-lg">
            <a href="#" class="hover:text-blue-400">📊 Dashboard</a>
            <a href="user_manage" class="hover:text-blue-400">👥 Manage User</a>
            <a href="news_manage" class="hover:text-blue-400">📰 Manage News</a>         
            <a href="whitelistip-approval" class="hover:text-blue-400">⚪ Manage Whitelist IP</a>

            <button @click="openTopup = !openTopup" class="flex items-center justify-between text-left hover:text-blue-400">
                <span>💵 Manage Topup</span>
                <svg class="w-4 h-4 ml-2 transform transition-transform duration-200"
                    :class="{'rotate-180': openTopup}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="openTopup" x-transition 
                class="flex flex-col pl-4 mt-1 space-y-2 text-base text-gray-300">
                <a href="topup_manage" class="hover:text-blue-400">🛠️ Topup Approval</a>
                <a href="topup-card-manage" class="hover:text-blue-400">💳 Topup Card</a>
            </div>

            <button @click="openLog = !openLog" class="flex items-center justify-between text-left hover:text-blue-400">
                <span>📈 Log</span>
                <svg class="w-4 h-4 ml-2 transform transition-transform duration-200"
                    :class="{'rotate-180': openLog}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            <div x-show="openLog" x-transition 
            class="flex flex-col pl-4 mt-1 space-y-2 text-base text-gray-300">
            <a href="log_user" class="hover:text-blue-400">📄 Log User</a>
            <a href="log_ip" class="hover:text-blue-400">🌐 Log IP</a>
            <a href="log_itemmall" class="hover:text-blue-400">🛒 Log Item Mall</a>
            <a href="log_wars" class="hover:text-blue-400">📄 Log War</a>
            </div>

            <a href="../" class="hover:text-blue-400">🏠 Beranda</a>
            <a href="../auth/logout" class="mt-8 text-red-500 hover:text-red-400">🔓 Logout</a>
        </nav>
    </aside>

    <main class="flex-1 p-10">
        <h2 class="text-3xl font-bold mb-6">Admin Panel, Admin</h2>
        
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-10">
            <form method="POST" class="flex items-center gap-2">
                <?= csrf_input('donasi') ?>
                <label for="month" class="text-sm">Bulan Donasi:</label>
                <select name="month" id="month" class="bg-gray-800 text-white p-2 rounded">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= ($m == $activeMonth ? 'selected' : '') ?>>
                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <label for="year" class="text-sm">Tahun:</label>
                <select name="year" id="year" class="bg-gray-800 text-white p-2 rounded">
                    <?php for ($y = 2023; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= ($y == $activeYear ? 'selected' : '') ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Simpan
                </button>
            </form>

            <form method="POST">
                <?= csrf_input('toggle') ?>
                <button type="submit" name="toggle_status"
                        class="px-4 py-2 text-sm font-semibold rounded shadow text-white transition
                        <?= $currentStatus === 'ONLINE' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700' ?>">
                    <?= $currentStatus === 'ONLINE' ? '🔴 Matikan Server' : '🟢 Nyalakan Server' ?>
                </button>
            </form>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <div class="bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-2">Total User</h3>
                <p class="text-3xl font-bold text-blue-400"><?= $totalUser ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-2">Online Player</h3>
                <p class="text-3xl font-bold text-green-500"><?= $totalOnline ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-2">Berita Aktif</h3>
                <p class="text-3xl font-bold text-yellow-400"><?= $totalNews ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-2">Total Donasi</h3>
            <p class="text-3xl font-bold text-pink-400">Rp <?= number_format($totalDonation) ?></p>
            <p class="text-md text-gray-400 mt-2"><?= $totalTopupSukses ?> topup berhasil</p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
            <h3 class="text-lg font-semibold mb-2">Total Donasi Hari Ini</h3>
            <p class="text-3xl font-bold text-yellow-300">Rp <?= number_format($totalDonationToday) ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-2">Donatur Terbanyak Hari Ini</h3>
                <p class="text-lg font-bold text-green-400"><?= $topDonorToday ?></p>
                <p class="text-xs text-gray-400 mt-1">Tanggal: <?= $customDate ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-2">Total Gold Server</h3>
                <p class="text-3xl font-bold text-orange-400"><?= number_format($totalGold) ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow">
                <h3 class="text-lg font-semibold mb-2">Total E-Point Server</h3>
                <p class="text-3xl font-bold text-green-400"><?= number_format($total_epoint) ?></p>
            </div>
            <div class="bg-gray-800 p-6 rounded-lg shadow mt-6">
                <h3 class="text-lg font-semibold mb-4">Top 3 Bulan (<?= date('F Y', mktime(0, 0, 0, $activeMonth, 1, $activeYear)) ?>)</h3>
                <table class="w-full text-left text-sm border border-gray-700">
                    <thead class="bg-gray-700 text-gray-300">
                        <tr>
                            <th class="px-4 py-2 border border-gray-600">#</th>
                            <th class="px-4 py-2 border border-gray-600">UserID</th>
                            <th class="px-4 py-2 border border-gray-600">Total Donasi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-100">
                        <?php if (count($top3Donors) > 0): ?>
                            <?php $rank = 1; foreach ($top3Donors as $donor): ?>
                                <tr class="border-b border-gray-700">
                                    <td class="px-4 py-2 border border-gray-700"><?= $rank++ ?></td>
                                    <td class="px-4 py-2 border border-gray-700"><?= htmlspecialchars($donor['UserID']) ?></td>
                                    <td class="px-4 py-2 border border-gray-700">Rp <?= number_format($donor['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-4 py-2 text-red-400 text-center">Belum ada donasi bulan ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </main>
</div>

</body>
</html>