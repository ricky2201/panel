<?php
session_start();
require_once '../config/db_panel.php'; // Koneksi ke NRSPanel
require_once '../includes/csrf.php';   // ✅ Tambahkan ini

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

// Handle tambah/edit/hapus kartu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('topup_card')) {
        die('❌ CSRF token tidak valid atau sudah kedaluwarsa.');
    }

    if (isset($_POST['add_card'])) {
        $amount = (int)$_POST['amount'];
        $epoint = (int)$_POST['epoint'];
        $bonus_percent = (float)$_POST['bonus_percent'];
        $bonus_active = isset($_POST['bonus_active']) ? 1 : 0;

        $bonus = $bonus_active ? floor($epoint * $bonus_percent / 100) : 0;

        sqlsrv_query($connPanel, "INSERT INTO TopupCard (amount, epoint, Bonus, bonus_percent, bonus_active, status, created_at)
            VALUES (?, ?, ?, ?, ?, 1, GETDATE())",
            [$amount, $epoint, $bonus, $bonus_percent, $bonus_active]);
    }

    if (isset($_POST['edit_card'])) {
        $id = (int)$_POST['id'];
        $amount = (int)$_POST['amount'];
        $epoint = (int)$_POST['epoint'];
        $bonus_percent = (float)$_POST['bonus_percent'];
        $bonus_active = isset($_POST['bonus_active']) ? 1 : 0;

        $bonus = $bonus_active ? floor($epoint * $bonus_percent / 100) : 0;

        sqlsrv_query($connPanel, "UPDATE TopupCard
            SET amount = ?, epoint = ?, Bonus = ?, bonus_percent = ?, bonus_active = ?
            WHERE id = ?",
            [$amount, $epoint, $bonus, $bonus_percent, $bonus_active, $id]);
    }

    if (isset($_POST['delete_card'])) {
        $id = (int)$_POST['id'];
        sqlsrv_query($connPanel, "DELETE FROM TopupCard WHERE id = ?", [$id]);
    }

    header("Location: topup-card-manage");
    exit;
}

// Ambil semua kartu
$cards = [];
$stmt = sqlsrv_query($connPanel, "SELECT * FROM TopupCard ORDER BY amount ASC");
if ($stmt === false) {
    die("❌ Gagal mengambil data kartu topup: " . print_r(sqlsrv_errors(), true));
}
while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $cards[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Kartu Topup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen px-6 py-8">

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">💳 Kelola Kartu Topup</h1>
    <a href="./" class="inline-block bg-gray-700 hover:bg-gray-600 text-white font-bold px-4 py-2 rounded">
        Back
    </a>
</div>

<form method="POST" class="bg-gray-800 p-6 rounded mb-8 space-y-4 max-w-xl">
    <h2 class="text-lg font-bold">➕ Tambah Kartu</h2>
    <?= csrf_input('topup_card') ?>
    <input type="number" name="amount" placeholder="Harga (Rp)" class="w-full p-2 rounded text-black" required>
    <input type="number" name="epoint" placeholder="E-Point" class="w-full p-2 rounded text-black" required>
    <input type="number" name="bonus_percent" placeholder="Bonus (%)" step="0.1" class="w-full p-2 rounded text-black">
    <label class="inline-flex items-center space-x-2 text-sm">
        <input type="checkbox" name="bonus_active" class="accent-green-500">
        <span>Aktifkan Bonus</span>
    </label>
    <button name="add_card" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded font-bold">Tambah</button>
</form>

<table class="w-full bg-gray-800 rounded text-left">
    <thead>
        <tr class="bg-gray-700 text-sm uppercase">
            <th class="p-3">Harga</th>
            <th class="p-3">E-Point</th>
            <th class="p-3">Bonus</th>
            <th class="p-3">%</th>
            <th class="p-3">Status</th>
            <th class="p-3">Total</th>
            <th class="p-3">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cards as $card): ?>
        <tr class="border-b border-gray-700">
            <form method="POST">
                <?= csrf_input('topup_card') ?>
                <td class="p-3"><input type="number" name="amount" value="<?= $card['amount'] ?>" class="w-24 p-1 text-black rounded" required></td>
                <td class="p-3"><input type="number" name="epoint" value="<?= $card['epoint'] ?>" class="w-24 p-1 text-black rounded" required></td>
                <td class="p-3 text-green-400 font-bold"><?= $card['bonus_active'] ? $card['Bonus'] : 0 ?></td>
                <td class="p-3">
                    <input type="number" name="bonus_percent" value="<?= $card['bonus_percent'] ?>" step="0.1" class="w-20 p-1 text-black rounded">
                </td>
                <td class="p-3">
                    <label class="inline-flex items-center space-x-1 text-sm">
                        <input type="checkbox" name="bonus_active" <?= $card['bonus_active'] ? 'checked' : '' ?> class="accent-green-500">
                        <span><?= $card['bonus_active'] ? 'Aktif' : 'Mati' ?></span>
                    </label>
                </td>
                <td class="p-3 text-yellow-300 font-bold"><?= $card['epoint'] + ($card['bonus_active'] ? $card['Bonus'] : 0) ?></td>
                <td class="p-3 space-x-1">
                    <input type="hidden" name="id" value="<?= $card['id'] ?>">
                    <button name="edit_card" class="bg-yellow-500 hover:bg-yellow-600 px-3 py-1 rounded font-bold">Edit</button>
                    <button name="delete_card" onclick="return confirm('Yakin ingin menghapus?')" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded font-bold">Hapus</button>
                </td>
            </form>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>