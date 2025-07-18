<?php
session_start();
include '../auth/cek_session.php';
include '../config/db.php';

$userid = $_SESSION['userid'];

$query = "SELECT Amount, EPoint, Status, RequestDate FROM TopupRequest WHERE UserID = ? ORDER BY RequestDate DESC";
$stmt = sqlsrv_query($conn, $query, [$userid]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Riwayat Topup</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex flex-col items-center py-10">
  <h1 class="text-3xl font-bold mb-6">Riwayat Topup Anda</h1>
  <div class="w-full max-w-4xl bg-gray-800 rounded shadow p-4">
    <table class="min-w-full text-sm text-left">
      <thead>
        <tr class="text-gray-300 border-b border-gray-600">
          <th class="py-2">Tanggal</th>
          <th class="py-2">Nominal</th>
          <th class="py-2">E-Point</th>
          <th class="py-2">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)): ?>
          <tr class="border-b border-gray-700">
            <td class="py-2"><?= $row['RequestDate']->format('Y-m-d H:i') ?></td>
            <td class="py-2">Rp<?= number_format($row['Amount'], 0, ',', '.') ?></td>
            <td class="py-2"><?= $row['EPoint'] ?></td>
            <td class="py-2">
              <?php
                switch ($row['Status']) {
                  case 'pending': echo '<span class="text-yellow-400">Menunggu</span>'; break;
                  case 'approved': echo '<span class="text-green-500">Disetujui</span>'; break;
                  case 'rejected': echo '<span class="text-red-500">Ditolak</span>'; break;
                  default: echo '<span class="text-gray-400">Tidak Diketahui</span>';
                }
              ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <a href="" class="mt-6 inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded font-bold">Kembali ke Dashboard</a>
</body>
</html>
