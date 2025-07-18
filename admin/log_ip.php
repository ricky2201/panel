<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

// Ambil parameter
$search = isset($_GET['search']) ? trim(substr($_GET['search'], 0, 50)) : '';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$maxData = 1000;

// Hitung total data
$sqlCount = "SELECT COUNT(*) AS total FROM RG1User.dbo.LogLogin";
$params = [];
if ($search !== '') {
    $sqlCount .= " WHERE UserID LIKE ?";
    $params[] = "%$search%";
}
$stmtCount = sqlsrv_query($conn, $sqlCount, $params);
$totalRows = 0;
if ($stmtCount && $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
    $totalRows = $row['total'];
}
$totalRowsLimited = min($totalRows, $maxData);
$totalPages = ceil($totalRowsLimited / $limit);
$page = min($page, $totalPages > 0 ? $totalPages : 1);
$offset = ($page - 1) * $limit;

// Query utama
$sql = "SELECT UserID, LogIpAddress, LogHWID, LogInOut, LogDate FROM RG1User.dbo.LogLogin";
if ($search !== '') {
    $sql .= " WHERE UserID LIKE ?";
}
$sql .= " ORDER BY LogDate $order OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
if ($search !== '') {
    $params[] = $offset;
    $params[] = $limit;
} else {
    $params = [$offset, $limit];
}
$query = sqlsrv_query($conn, $sql, $params);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Log IP Player</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6 text-white">Catatan Login & Logout Player</h1>
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
        <a href="./" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded shadow">
            Back
        </a>
        <form method="GET" class="flex flex-col md:flex-row gap-2 md:items-center">
            <input type="text" name="search" placeholder="Cari UserID..." value="<?= htmlspecialchars($search) ?>"
                   class="w-full md:w-64 px-4 py-2 rounded bg-gray-700 text-white border border-gray-600 focus:outline-none" />

            <select name="order" class="px-4 py-2 rounded bg-gray-700 text-white border border-gray-600">
                <option value="desc" <?= $order === 'DESC' ? 'selected' : '' ?>>Tanggal Terbaru</option>
                <option value="asc" <?= $order === 'ASC' ? 'selected' : '' ?>>Tanggal Terlama</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                Cari
            </button>
        </form>
    </div>

    <div class="overflow-x-auto bg-gray-800 rounded-lg shadow p-4">
        <table class="min-w-full text-sm text-left border border-gray-700">
            <thead class="bg-gray-700 text-gray-300 font-semibold">
                <tr>
                    <th class="px-4 py-2 border border-gray-600">#</th>
                    <th class="px-4 py-2 border border-gray-600">UserID</th>
                    <th class="px-4 py-2 border border-gray-600">IP Address</th>
                    <th class="px-4 py-2 border border-gray-600">HWID</th>
                    <th class="px-4 py-2 border border-gray-600">Status</th>
                    <th class="px-4 py-2 border border-gray-600">Tanggal</th>
                </tr>
            </thead>
            <tbody class="text-gray-200 divide-y divide-gray-700">
<?php
if ($query === false) {
    echo '<tr><td colspan="6" class="px-4 py-2 text-red-400">Gagal mengambil data log.</td></tr>';
} else {
    $no = $offset + 1;
    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)) {
        $userid = htmlspecialchars($row['UserID'] ?? '-');
        $ip     = htmlspecialchars($row['LogIpAddress'] ?? '-');
        $hwid   = htmlspecialchars($row['LogHWID'] ?? '-');
        $logInOut = ($row['LogInOut'] == 1) ? '<span class="text-green-400 font-semibold">Online</span>' : '<span class="text-red-400">Offline</span>';
        $logDate = $row['LogDate'] instanceof DateTime ? htmlspecialchars($row['LogDate']->format('Y-m-d H:i:s')) : '-';

        echo '<tr>';
        echo "<td class='px-4 py-2 border border-gray-700'>" . $no . "</td>";
        echo "<td class='px-4 py-2 border border-gray-700'>" . $userid . "</td>";
        echo "<td class='px-4 py-2 border border-gray-700'>" . $ip . "</td>";
        echo "<td class='px-4 py-2 border border-gray-700'>" . $hwid . "</td>";
        echo "<td class='px-4 py-2 border border-gray-700'>" . $logInOut . "</td>";
        echo "<td class='px-4 py-2 border border-gray-700'>" . $logDate . "</td>";
        echo '</tr>';
        $no++;
    }
}
?>
            </tbody>
        </table>
    </div>

    <div class="flex justify-center mt-6 space-x-2">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?search=<?= htmlspecialchars(urlencode($search)) ?>&order=<?= htmlspecialchars(strtolower($order)) ?>&page=<?= $i ?>"
               class="px-3 py-1 rounded <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>
</div>
</body>
</html>