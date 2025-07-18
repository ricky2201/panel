<?php
if (!isset($_SESSION)) session_start();
require_once '../config/db_panel.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
  header("Location: ../");
  exit;
}

// Handle Approval/Blocking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
  if (!csrf_validate('whitelist')) {
    die('CSRF token tidak valid.');
  }

  $id = (int) $_POST['id'];
  $action = $_POST['action'];

  if (in_array($action, ['approve', 'block'])) {
    $status = $action === 'approve' ? 'approved' : 'blocked';
    if ($action === 'approve') {
    include_once __DIR__ . '/approve_and_add_firewall.php';
    approveAndAddToFirewall($id, $connPanel); // fungsi di file approve_and_add_firewall.php
  } else {
    $sql_update = "UPDATE NRSPanel.dbo.whitelist_ip SET status = ?, approved_at = GETDATE() WHERE id = ?";
    sqlsrv_query($connPanel, $sql_update, [$status, $id]);
  }
    }

  header("Location: whitelistip-approval.php?" . http_build_query($_GET));
  exit;
}

// Filter parameters
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
  $where .= " AND status = ?";
  $params[] = $status_filter;
}

if (!empty($search)) {
  $where .= " AND userid LIKE ?";
  $params[] = "%$search%";
}

if (!empty($date_from)) {
  $where .= " AND created_at >= ?";
  $params[] = date_create($date_from);
}

if (!empty($date_to)) {
  $where .= " AND created_at <= ?";
  $params[] = date_create($date_to . ' 23:59:59');
}

$sql_count = "SELECT COUNT(*) AS total FROM NRSPanel.dbo.whitelist_ip $where";
$stmt_count = sqlsrv_query($connPanel, $sql_count, $params);
$total_data = ($stmt_count && ($row = sqlsrv_fetch_array($stmt_count))) ? $row['total'] : 0;
$total_pages = ceil($total_data / $per_page);

$sql = "SELECT * FROM NRSPanel.dbo.whitelist_ip $where ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
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
  <title>Whitelist IP - Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="bg-gray-900 text-white min-h-screen p-6">
  <a href="index.php" class="inline-block mb-4 bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">
    Back
  </a>

  <h1 class="text-3xl font-bold mb-6 text-center">Whitelist IP Request</h1>

  <!-- Filter Form -->
  <form method="GET" class="mb-6 grid md:grid-cols-6 gap-4">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Cari User ID" class="p-2 rounded bg-gray-700 w-full">
    <select name="status" class="p-2 rounded bg-gray-700 w-full">
      <option value="">Semua Status</option>
      <option value="pending" <?= selected($status_filter, 'pending') ?>>Pending</option>
      <option value="approved" <?= selected($status_filter, 'approved') ?>>Approved</option>
      <option value="blocked" <?= selected($status_filter, 'blocked') ?>>Blocked</option>
    </select>
    <input type="date" name="from" value="<?= htmlspecialchars($date_from) ?>" class="p-2 rounded bg-gray-700 w-full">
    <input type="date" name="to" value="<?= htmlspecialchars($date_to) ?>" class="p-2 rounded bg-gray-700 w-full">
    <div></div>
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 rounded px-4 py-2">Filter</button>
  </form>

  <!-- Data Table -->
  <div class="overflow-x-auto">
    <table class="min-w-full bg-gray-800 text-sm rounded-lg overflow-hidden text-center">
      <thead class="bg-gray-700 text-white">
        <tr>
          <th class="py-3 px-4">#</th>
          <th class="py-3 px-4">User ID</th>
          <th class="py-3 px-4">IP Address</th>
          <th class="py-3 px-4">Status</th>
          <th class="py-3 px-4">Diajukan</th>
          <th class="py-3 px-4">Disetujui</th>
          <th class="py-3 px-4">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        if ($stmt && sqlsrv_has_rows($stmt)) {
          $no = $offset + 1;
          while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $createdAt = (!empty($row['created_at']) && $row['created_at'] instanceof DateTime) ? $row['created_at']->format('Y-m-d H:i') : '-';
            $approvedAt = (!empty($row['approved_at']) && $row['approved_at'] instanceof DateTime) ? $row['approved_at']->format('Y-m-d H:i') : '-';
            $statusColor = match (strtolower($row['status'] ?? '')) {
              'approved' => 'text-green-400',
              'pending' => 'text-yellow-400',
              'blocked' => 'text-red-400',
              default => 'text-white'
            };
            echo "<tr class='border-b border-gray-700'>
              <td class='py-2 px-4'>{$no}</td>
              <td class='py-2 px-4'>" . htmlspecialchars($row['userid']) . "</td>
              <td class='py-2 px-4'>" . htmlspecialchars($row['ip_address']) . "</td>
              <td class='py-2 px-4 font-bold $statusColor'>" . ucfirst($row['status']) . "</td>
              <td class='py-2 px-4'>$createdAt</td>
              <td class='py-2 px-4'>$approvedAt</td>
              <td class='py-2 px-4'>";
              if ($row['status'] === 'pending') {
                ?>
                <form method="POST" class="flex gap-1 justify-center">
                  <?= csrf_input('whitelist') ?>
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button name="action" value="approve" class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-xs">Approve</button>
                  <button name="action" value="block" class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs">Block</button>
                </form>
                <?php
              } else {
                echo '-';
              }
              echo "</td></tr>";
              $no++;
          }
        } else {
          echo "<tr><td colspan='7' class='text-center py-6 text-gray-400'>Tidak ada data ditemukan.</td></tr>";
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
</body>
</html>
