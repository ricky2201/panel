<?php
session_start();
require_once '../config/db_panel.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['userid'])) {
    header("Location: ../");
    exit;
}

$userid = $_SESSION['userid'];

// Filter
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// WHERE clause
$where = "WHERE ReferrerUserID = ?";
$params = [$userid];

if (!empty($status_filter)) {
    $where .= " AND Status = ?";
    $params[] = $status_filter;
}

// Total data
$sql_count = "SELECT COUNT(*) AS total FROM ReferralRewardHistory $where";
$stmt_count = sqlsrv_query($connPanel, $sql_count, $params);
$total_data = 0;
if ($stmt_count && $row = sqlsrv_fetch_array($stmt_count, SQLSRV_FETCH_ASSOC)) {
    $total_data = $row['total'];
}
$total_pages = ceil($total_data / $per_page);

// Data fetch
$sql = "SELECT * FROM ReferralRewardHistory $where 
        ORDER BY CreatedAt DESC 
        OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = $per_page;
$stmt = sqlsrv_query($connPanel, $sql, $params);

function sensor_userid($userid) {
    $length = strlen($userid);

    // Tentukan berapa karakter terakhir yang ingin disensor
    $mask = match (true) {
        $length >= 16 => 8,
        $length >= 12 => 6,
        $length >= 8  => 4,
        $length >= 6  => 3,
        default       => 2,
    };

    $visible = $length - $mask;
    if ($visible <= 0) {
        return str_repeat('*', $length);
    }

    return substr($userid, 0, $visible) . str_repeat('*', $mask);
}

function selected($val1, $val2) {
    return $val1 === $val2 ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Referral</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col">

<?php include '../components/navbar.php'; ?>

<main class="flex-grow container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6 text-center">Riwayat Referral</h1>

    <!-- Filter -->
    <form method="GET" class="mb-6 flex flex-wrap gap-4 justify-center">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <select name="status" class="p-2 rounded bg-gray-800">
            <option value="">Semua Status</option>
            <option value="pending" <?= selected($status_filter, 'pending') ?>>Pending</option>
            <option value="approved" <?= selected($status_filter, 'approved') ?>>Approved</option>
            <option value="rejected" <?= selected($status_filter, 'rejected') ?>>Rejected</option>
        </select>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white">Terapkan</button>
    </form>

    <!-- Tabel -->
    <div class="overflow-x-auto">
        <table class="min-w-full bg-gray-800 text-sm rounded-lg text-center">
            <thead class="bg-gray-700">
                <tr>
                    <th class="py-2 px-4">No</th>
                    <th class="py-2 px-4">ID Pengguna Referral</th>
                    <th class="py-2 px-4">Jumlah (Rp)</th>
                    <th class="py-2 px-4">Reward Streamer</th>
                    <th class="py-2 px-4">Tanggal</th>
                    <th class="py-2 px-4">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($stmt && sqlsrv_has_rows($stmt)) {
                    $no = $offset + 1;
                    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $createdAt = ($row['CreatedAt'] instanceof DateTime)
                            ? $row['CreatedAt']->format('d-m-Y H:i')
                            : '-';

                        $status = strtolower($row['Status']);
                        $status_color = match ($status) {
                            'approved' => 'text-green-400',
                            'rejected' => 'text-red-400',
                            'pending' => 'text-yellow-400',
                            default => 'text-white'
                        };

                        echo "<tr class='border-b border-gray-700'>
                                <td class='py-2 px-4'>{$no}</td>
                                <td class='py-2 px-4'>" . htmlspecialchars(sensor_userid($row['ReferredUserID'])) . "</td>
                                <td class='py-2 px-4'>Rp" . number_format((int)$row['TopupAmount'], 0, ',', '.') . "</td>
                                <td class='py-2 px-4'>" . number_format((int)$row['RewardEPoint']) . " EP</td>
                                <td class='py-2 px-4'>{$createdAt}</td>
                                <td class='py-2 px-4 font-bold {$status_color}'>" . ucfirst($status) . "</td>
                              </tr>";
                        $no++;
                    }
                } else {
                    echo "<tr><td colspan='6' class='py-4 text-gray-400'>Tidak ada riwayat referral ditemukan.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-6 space-x-2">
            <?php
            $max_links = 5;
            $start = max(1, $page - floor($max_links / 2));
            $end = min($total_pages, $start + $max_links - 1);
            $start = max(1, $end - $max_links + 1);

            if ($page > 1) {
                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $page - 1])) . '" class="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 text-white">&laquo;</a>';
            }

            for ($i = $start; $i <= $end; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                   class="px-3 py-1 rounded <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-700 text-white hover:bg-gray-600' ?>">
                   <?= $i ?>
                </a>
            <?php endfor;

            if ($page < $total_pages) {
                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $page + 1])) . '" class="px-3 py-1 rounded bg-gray-700 hover:bg-gray-600 text-white">&raquo;</a>';
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="mt-6 text-center">
        <a href="./" class="text-blue-400 hover:underline">Kembali ke Dashboard</a>
    </div>
</main>

<?php include '../components/footer.php'; ?>

</body>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</html>
