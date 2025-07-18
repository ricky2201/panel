<?php
session_start();
require_once '../config/db_shop.php';
require_once '../config/db.php'; // RG1User
require_once '../includes/csrf.php';

// Proteksi admin
if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

// CSRF validasi untuk GET form pencarian jika diperlukan (tidak wajib, karena GET bersifat idempotent)

// Pagination
$perPage = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Filter
$filterUserID = isset($_GET['userid']) ? trim($_GET['userid']) : '';
$orderBy = isset($_GET['sort']) && $_GET['sort'] === 'desc' ? 'DESC' : 'ASC';

// Query utama dengan join dan filter
$params = [];
$sql = "
    SELECT TOP $perPage *
    FROM (
        SELECT 
            gl.idx,
            gl.ProductNum,
            gi.ItemName,
            gi.ShopType,
            gl.ItemMoney,
            gl.Date,
            gl.UserID,
            ROW_NUMBER() OVER (ORDER BY gl.Date $orderBy) AS RowNum
        FROM RG1Shop.dbo.GISPurchaseLog gl
        LEFT JOIN RG1Shop.dbo.GameItemShop gi ON gi.ProductNum = gl.ProductNum
        WHERE 1=1
";

if ($filterUserID !== '') {
    $sql .= " AND gl.UserID LIKE ?";
    $params[] = "%$filterUserID%";
}

$sql .= ") AS Temp WHERE Temp.RowNum BETWEEN ? AND ?";
$params[] = $offset + 1;
$params[] = $offset + $perPage;

$stmt = sqlsrv_query($connShop, $sql, $params);

$logs = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $logs[] = $row;
    }
}

// Hitung total untuk pagination
$totalSql = "SELECT COUNT(*) AS total FROM RG1Shop.dbo.GISPurchaseLog";
$totalParams = [];

if ($filterUserID !== '') {
    $totalSql .= " WHERE UserID LIKE ?";
    $totalParams[] = "%$filterUserID%";
}

$totalStmt = sqlsrv_query($connShop, $totalSql, $totalParams);
$totalRows = ($totalStmt && $row = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC)) ? (int)$row['total'] : 0;
$totalPages = ceil($totalRows / $perPage);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log Item Mall</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-3xl font-bold mb-4">🛒 Log Item Mall</h1>

        <!-- Baris pencarian + tombol kembali -->
        <div class="flex flex-wrap justify-between items-end mb-4">
            <div>
                <a href="./" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded h-[42px]">
                    Back
                </a>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div>
                    <label class="text-sm block mb-1">UserID:</label>
                    <input type="text" name="userid" value="<?= htmlspecialchars($filterUserID) ?>" class="p-2 rounded bg-gray-800 text-white" placeholder="UserID...">
                </div>
                <div>
                    <label class="text-sm block mb-1">Tanggal:</label>
                    <select name="sort" class="p-2 rounded bg-gray-800 text-white">
                        <option value="desc" <?= $orderBy == 'DESC' ? 'selected' : '' ?>>Terbaru</option>
                        <option value="asc" <?= $orderBy == 'ASC' ? 'selected' : '' ?>>Terlama</option>
                    </select>
                </div>
                <div class="flex gap-2 mt-6">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded font-bold">🔍 Cari</button>
                    <a href="log_itemmall" class="bg-gray-600 hover:bg-gray-700 px-4 py-2 rounded font-bold">🔄 Reset</a>
                </div>
            </form>
        </div>

        <!-- Tabel -->
        <div class="relative overflow-auto border border-gray-700 rounded-lg">
            <table class="min-w-full text-sm text-center divide-y divide-gray-700">
                <thead class="bg-gray-800 text-gray-300 uppercase">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">UserID</th>
                        <th class="px-4 py-2">ProductNum</th>
                        <th class="px-4 py-2">Item Name</th>
                        <th class="px-4 py-2">Item Money</th>
                        <th class="px-4 py-2">Tanggal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="px-4 py-2"><?= $log['idx'] ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($log['UserID']) ?></td>
                            <td class="px-4 py-2"><?= $log['ProductNum'] ?></td>
                            <td class="px-4 py-2"><?= htmlspecialchars($log['ItemName'] ?? '-') ?></td>
                            <td class="px-4 py-2">
                                <?= $log['ItemMoney'] ?>
                                <?php if ($log['ShopType'] == 1): ?>
                                    <span class="text-green-400 font-bold">EP</span>
                                <?php elseif ($log['ShopType'] == 2): ?>
                                    <span class="text-yellow-400 font-bold">VP</span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2">
                                <?= isset($log['Date']) ? $log['Date']->format('d-m-Y H:i') : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="py-4 text-gray-400">Tidak ada data.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-6 flex justify-center items-center space-x-2">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="px-3 py-1 rounded <?= $i === $page ? 'bg-blue-600 text-white font-bold' : 'bg-gray-700 text-white' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
</body>
</html>
