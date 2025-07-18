<?php
session_start();
require_once '../config/db_panel.php'; // koneksi ke NRSPanel
require_once __DIR__ . '/../components/navbar.php';
require_once __DIR__ . '/../path.php';

// Cek apakah user sudah login
if (!isset($_SESSION['userid'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userid = $_SESSION['userid'];
$ip_address = $_SERVER['REMOTE_ADDR'];
$status = 'blocked';

// Cek status IP di whitelist_ip
$query = sqlsrv_query($connPanel, "SELECT status FROM NRSPanel.dbo.whitelist_ip WHERE userid = ? AND ip_address = ?", [$userid, $ip_address]);
if ($query && $row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
    $status = $row['status'];
}

// Proses request IP whitelist
$success = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_whitelist'])) {
    // Cek apakah IP sudah pernah diajukan
    $check = sqlsrv_query($connPanel, "SELECT * FROM NRSPanel.dbo.whitelist_ip WHERE userid = ? AND ip_address = ?", [$userid, $ip_address]);
    if ($check && sqlsrv_has_rows($check)) {
        $error = "Permintaan whitelist IP sudah diajukan sebelumnya.";
    } else {
        $insert = sqlsrv_query($connPanel, "INSERT INTO NRSPanel.dbo.whitelist_ip (userid, ip_address, status, created_at) VALUES (?, ?, 'pending', GETDATE())", [$userid, $ip_address]);
        if ($insert) {
            $success = "Permintaan whitelist IP berhasil dikirim. Mohon tunggu 5–10 menit.";
            $status = 'pending';
        } else {
            $error = "Gagal mengirim permintaan whitelist.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Whitelist IP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">

    <!-- Konten utama -->
    <main class="flex-grow">
        <div class="max-w-xl mx-auto p-6 mt-12 bg-gray-800 rounded-xl shadow-lg border border-gray-700">
            <h2 class="text-2xl font-bold mb-4 text-center text-blue-400">Whitelist IP</h2>
            
            <div class="mb-6 text-center">
                <p class="text-lg">
                    IP Anda saat ini:
                    <span class="font-mono bg-gray-700 px-2 py-1 rounded">
                        <?= htmlspecialchars($ip_address) ?>
                    </span>
                </p>
                <p class="mt-2 text-xl font-semibold">
                    Status IP Anda: 
                    <?php if ($status === 'approved'): ?>
                        <span class="text-green-400">Approved</span>
                    <?php elseif ($status === 'pending'): ?>
                        <span class="text-yellow-400">Menunggu Approval</span>
                    <?php else: ?>
                        <span class="text-red-500">Blocked</span>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-700 p-3 rounded mb-4 text-white text-sm text-center">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php elseif ($error): ?>
                <div class="bg-red-600 p-3 rounded mb-4 text-white text-sm text-center">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="text-center">
                <?php if ($status === 'blocked'): ?>
                    <button type="submit" name="request_whitelist"
                        class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded-full font-semibold text-white shadow transition">
                        Request Whitelist
                    </button>
                <?php else: ?>
                    <button type="button" disabled
                        class="bg-gray-600 px-6 py-2 rounded-full font-semibold text-white opacity-60 cursor-not-allowed">
                        Permintaan Sudah Dikirim
                    </button>
                <?php endif; ?>
            </form>

            <p class="mt-6 text-center text-gray-400 text-sm">
                Permintaan whitelist IP akan diproses oleh admin dalam 5–10 menit.
            </p>

            <div class="mt-6 text-center">
                <a href="./" class="text-blue-400 hover:underline">Kembali ke Dashboard</a>
            </div>
        </div>
    </main>

    <!-- Footer tetap di bawah -->
    <?php include_once __DIR__ . '/../components/footer.php'; ?>
</body>
</html>
