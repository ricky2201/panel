<?php
session_start();
require_once '../config/db_panel.php';

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
  header("Location: ../");
  exit;
}

$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where = "WHERE 1=1";
$params = [];

if (!empty($status_filter)) {
  $where .= " AND Status = ?";
  $params[] = $status_filter;
}

if (!empty($search)) {
  $where .= " AND UserID LIKE ?";
  $params[] = "%$search%";
}

if (!empty($date_from)) {
  $where .= " AND RequestDate >= ?";
  $params[] = date_create($date_from);
}

if (!empty($date_to)) {
  $where .= " AND RequestDate <= ?";
  $params[] = date_create($date_to . ' 23:59:59');
}

$sql_count = "SELECT COUNT(*) AS total FROM TopupRequest $where";
$stmt_count = sqlsrv_query($connPanel, $sql_count, $params);
$total_data = ($stmt_count && ($row = sqlsrv_fetch_array($stmt_count))) ? $row['total'] : 0;
$total_pages = ceil($total_data / $per_page);

$sql = "SELECT * FROM TopupRequest $where ORDER BY RequestDate DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = $per_page;
$stmt = sqlsrv_query($connPanel, $sql, $params);

function selected($val1, $val2) {
  return $val1 === $val2 ? 'selected' : '';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Topup Management - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="bg-gray-900 text-white min-h-screen p-6">
  <a href="index" class="inline-block mb-4 bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
    Back
  </a>
  <h1 class="text-3xl font-bold mb-6 text-center">Topup Management</h1>

  <?php if (isset($_SESSION['msg'])): ?>
    <p id="notifikasi" class="font-semibold text-center mb-4">
      <?= $_SESSION['msg']; unset($_SESSION['msg']); ?>
    </p>
    <script>
      setTimeout(() => {
        const notif = document.getElementById('notifikasi');
        if (notif) notif.style.display = 'none';
      }, 3000);
    </script>
  <?php endif; ?>

  <form method="GET" class="mb-6 grid md:grid-cols-6 gap-4">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari User ID" class="p-2 rounded bg-gray-700 w-full">
    <select name="status" class="p-2 rounded bg-gray-700 w-full">
      <option value="">Semua Status</option>
      <option value="pending" <?= selected($status_filter, 'pending') ?>>Pending</option>
      <option value="approved" <?= selected($status_filter, 'approved') ?>>Approved</option>
      <option value="rejected" <?= selected($status_filter, 'rejected') ?>>Rejected</option>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" class="p-2 rounded bg-gray-700 w-full">
    <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" class="p-2 rounded bg-gray-700 w-full">
    <div></div>
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Filter</button>
  </form>

  <div class="overflow-x-auto">
    <table class="min-w-full bg-gray-800 text-sm rounded-lg overflow-hidden text-center">
      <thead class="bg-gray-700 text-white">
        <tr>
          <th class="py-3 px-4">#</th>
          <th class="py-3 px-4">User ID</th>
          <th class="py-3 px-4">Amount (Rp)</th>
          <th class="py-3 px-4">E-Point</th>
          <th class="py-3 px-4">Metode</th>
          <th class="py-3 px-4">Status</th>
          <th class="py-3 px-4">Tanggal Request</th>
          <th class="py-3 px-4">Tanggal Response</th>
          <th class="py-3 px-4">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt && sqlsrv_has_rows($stmt)) {
          $no = $offset + 1;
          while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $requestDate = (!empty($row['RequestDate']) && $row['RequestDate'] instanceof DateTime) ? $row['RequestDate']->format('Y-m-d H:i') : '-';
            $responseDate = (!empty($row['ResponseDate']) && $row['ResponseDate'] instanceof DateTime) ? $row['ResponseDate']->format('Y-m-d H:i') : '-';
            $statusColor = match (strtolower($row['Status'] ?? '')) {
              'approved' => 'text-green-400',
              'rejected' => 'text-red-400',
              'pending' => 'text-yellow-400',
              default => 'text-white'
            };
            echo "<tr class='border-b border-gray-700'>
              <td class='py-2 px-4'>{$no}</td>
              <td class='py-2 px-4'>" . htmlspecialchars($row['UserID'] ?? '-') . "</td>
              <td class='py-2 px-4'>Rp" . number_format((int)($row['Amount'] ?? 0)) . "</td>
              <td class='py-2 px-4'>" . number_format((int)($row['EPoint'] ?? 0)) . "</td>
              <td class='py-2 px-4'>" . htmlspecialchars($row['Method'] ?? '-') . "</td>
              <td class='py-2 px-4 font-bold $statusColor'>" . ucfirst(htmlspecialchars($row['Status'] ?? '-')) . "</td>
              <td class='py-2 px-4'>$requestDate</td>
              <td class='py-2 px-4'>$responseDate</td>
              <td class='py-2 px-4'>
                <a href='topup_approval.php?id=" . (int)($row['ID'] ?? 0) . "' class='bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm inline-block'>Kelola</a>
              </td>
            </tr>";
            $no++;
          }
        } else {
          echo "<tr><td colspan='9' class='text-center py-6 text-gray-400'>Tidak ada data ditemukan.</td></tr>";
        }
        ?>
      </tbody>
    </table>
  </div>

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
</body>
</html>
