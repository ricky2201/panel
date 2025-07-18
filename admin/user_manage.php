<?php
session_start();
require_once '../config/db.php';
require_once '../includes/csrf.php';

// Proteksi akses hanya untuk admin
if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Ambil data user dari database
$users = [];
$filter_sql = "SELECT * FROM (
  SELECT UserNum, UserName, UserPass, UserPass2, UserType, UserPoint, VotePoint, UserEmail, Referral, CreateDate, LastLoginDate,
         ROW_NUMBER() OVER (ORDER BY UserNum ASC) AS RowNum
  FROM dbo.UserInfo";
$params = [];

// Filtering dan pengurutan
$orderBy = isset($_GET['sort']) && $_GET['sort'] === 'epoint' ? " ORDER BY UserPoint DESC" : "";

if (isset($_GET['filter_by'], $_GET['keyword']) && $_GET['keyword'] !== '') {
    $allowed = ['UserNum', 'UserName', 'UserType'];
    $filter_by = in_array($_GET['filter_by'], $allowed) ? $_GET['filter_by'] : 'UserName';
    $keyword = $_GET['keyword'];

    if ($filter_by === 'UserNum' || $filter_by === 'UserType') {
        $filter_sql .= " WHERE $filter_by = ?";
        $params[] = (int)$keyword;
    } else {
        $filter_sql .= " WHERE $filter_by LIKE ?";
        $params[] = '%' . $keyword . '%';
    }
}

$filter_sql .= ") AS Temp WHERE Temp.RowNum BETWEEN ? AND ?";
$params[] = $offset + 1;
$params[] = $offset + $perPage;

$stmt = sqlsrv_query($conn, $filter_sql, $params);
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $users[] = $row;
    }
}

// Hitung total data
$count_sql = "SELECT COUNT(*) AS total FROM dbo.UserInfo";
$count_stmt = sqlsrv_query($conn, $count_sql);
$totalRows = ($count_stmt && $r = sqlsrv_fetch_array($count_stmt, SQLSRV_FETCH_ASSOC)) ? (int)$r['total'] : 0;
$totalPages = ceil($totalRows / $perPage);

function formatTanggal($datetime) {
    if (!$datetime instanceof DateTime) return '-';
    $bulanIndonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    $tgl = $datetime->format('d');
    $bln = $bulanIndonesia[(int)$datetime->format('m')];
    $thn = $datetime->format('Y');
    $jam = $datetime->format('H:i');
    return "$tgl $bln $thn $jam";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Manajemen User</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
  <div class="max-w-7xl mx-auto p-6">
    <div class="flex justify-between items-start mb-4">
      <a href="./" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">Back</a>

      <form method="GET" class="flex flex-wrap items-center gap-2" id="searchForm">
          <label class="text-sm text-gray-300">Cari berdasarkan:</label>
          <select name="filter_by" class="p-2 rounded bg-gray-800 text-white">
              <option value="UserNum" <?= (isset($_GET['filter_by']) && $_GET['filter_by'] == 'UserNum') ? 'selected' : '' ?>>UserNum</option>
              <option value="UserName" <?= (isset($_GET['filter_by']) && $_GET['filter_by'] == 'UserName') ? 'selected' : '' ?>>UserName</option>
              <option value="UserType" <?= (isset($_GET['filter_by']) && $_GET['filter_by'] == 'UserType') ? 'selected' : '' ?>>UserType</option>
          </select>

          <input type="text" name="keyword" placeholder="Kata kunci..." class="p-2 rounded bg-gray-800 text-white"
              value="<?= isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : '' ?>">

          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded">🔍 Cari</button>
          <a href="user_manage?sort=epoint" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold px-2 py-2 rounded">E-Point</a>
          <a href="user_manage" class="bg-gray-600 hover:bg-gray-700 text-white font-bold px-2 py-2 rounded">🔄 Reset Filter</a>
      </form>
    </div>

    <div class="rounded shadow border border-gray-700 overflow-auto">
      <table class="min-w-full divide-y divide-gray-600 whitespace-nowrap text-sm">
        <thead class="bg-gray-800 text-sm uppercase text-gray-300">
          <tr>
              <th class="px-4 py-2 text-center">UserNum</th>
              <th class="px-4 py-2 text-center">UserName</th>
              <th class="px-4 py-2 text-center">Password</th>
              <th class="px-4 py-2 text-center">Pin</th>
              <th class="px-4 py-2 text-center">User Type</th>
              <th class="px-4 py-2 text-center">E-POINT</th>
              <th class="px-4 py-2 text-center">Email</th>
              <th class="px-4 py-2 text-center">Referral</th>
              <th class="px-4 py-2 text-center">Tanggal Buat</th>
              <th class="px-4 py-2 text-center">Terakhir Login</th>
              <th class="px-4 py-2 text-center">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-700 text-center">
          <?php foreach ($users as $user): ?>
          <tr class="hover:bg-gray-800">
              <td class="px-4 py-2"><?= $user['UserNum'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($user['UserName']) ?></td>
              <td class="px-4 py-2">••••••</td>
              <td class="px-4 py-2">••••••</td>
              <td class="px-4 py-2">
                <?php
                  if ($user['UserType'] == 30) {
                    echo '<span class="text-green-400 font-bold">Admin</span>';
                  } elseif ($user['UserType'] == 1) {
                    echo '<span class="text-gray-300">Player</span>';
                  } else {
                    echo $user['UserType'];
                  }
                ?>
              </td>
              <td class="px-4 py-2"><?= $user['UserPoint'] ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($user['UserEmail']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($user['Referral']) ?></td>
              <td class="px-4 py-2"><?= isset($user['CreateDate']) ? formatTanggal($user['CreateDate']) : '-' ?></td>
              <td class="px-4 py-2"><?= isset($user['LastLoginDate']) ? formatTanggal($user['LastLoginDate']) : '-' ?></td>
              <td class="px-4 py-2">
                  <a href="user_edit?UserNum=<?= $user['UserNum'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-1 px-3 rounded text-xs">Edit</a>
              </td>
          </tr>
          <?php endforeach; ?>
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
