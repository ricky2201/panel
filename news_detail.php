<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_game.php';
require_once __DIR__ . '/config/db_panel.php';
require_once __DIR__ . '/path.php';

if (!isset($_GET['slug'])) {
    die("Berita tidak ditemukan.");
}

$slug = $_GET['slug'];

// Ambil berita berdasarkan slug
$sql = "SELECT * FROM NRSPanel.dbo.News WHERE slug = ?";
$params = [$slug];
$stmt = sqlsrv_query($connPanel, $sql, $params);

if ($stmt === false || !sqlsrv_has_rows($stmt)) {
    die("Berita tidak ditemukan.");
}

$news = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

// Sidebar Data

// Top Spender (bulan aktif)
$activeMonth = date('n');
$activeYear = date('Y');

$stmtSetting = sqlsrv_query($connPanel, "SELECT TOP 1 bulan, tahun FROM TopupSetting ORDER BY updated_at DESC");
if ($stmtSetting && $rowSetting = sqlsrv_fetch_array($stmtSetting, SQLSRV_FETCH_ASSOC)) {
    $activeMonth = (int)$rowSetting['bulan'];
    $activeYear = (int)$rowSetting['tahun'];
}

$topSpender = [];
$stmtSpender = sqlsrv_query($connPanel, "
    SELECT tr.UserID, SUM(tr.Amount) AS total
    FROM TopupRequest tr
    WHERE tr.Status = 'approved'
      AND MONTH(tr.RequestDate) = ? AND YEAR(tr.RequestDate) = ?
    GROUP BY tr.UserID
    ORDER BY total DESC
", [$activeMonth, $activeYear]);

if ($stmtSpender) {
    while ($row = sqlsrv_fetch_array($stmtSpender, SQLSRV_FETCH_ASSOC)) {
        $topSpender[] = $row;
    }
}

// Top Kill
$leaderboard = [];
$stmtLB = sqlsrv_query($connGame, "SELECT TOP 3 ChaName, ChaPKScore, ChaSchool, ChaClass FROM ChaInfo ORDER BY ChaPKScore DESC");
if ($stmtLB) {
    while ($row = sqlsrv_fetch_array($stmtLB, SQLSRV_FETCH_ASSOC)) {
        $leaderboard[] = $row;
    }
}

// GM Online
$gmOnlineCount = 0;
$stmtGM = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.UserInfo WHERE UserLoginState = 1 AND UserType = 30");
if ($stmtGM && $rowGM = sqlsrv_fetch_array($stmtGM, SQLSRV_FETCH_ASSOC)) {
    $gmOnlineCount = (int)$rowGM['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($news['title']) ?> - Glacier Game</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
  .glowing-text {
    text-shadow:
      0 0 5px #22c55e,
      0 0 10px #22c55e,
      0 0 15px #22c55e,
      0 0 20px #22c55e;
  }
  .glowing-offline {
    text-shadow:
      0 0 5px #ef4444,
      0 0 10px #ef4444,
      0 0 15px #ef4444,
      0 0 20px #ef4444;
  }
  </style>
</head>
<body class="bg-gray-900 text-white font-sans min-h-screen">
  <?php include_once __DIR__ . '/components/navbar.php'; ?>

  <div class="max-w-6xl mx-auto py-10 px-4 grid grid-cols-1 md:grid-cols-3 gap-8">
    <!-- Konten Berita -->
    <div class="md:col-span-2">
      <div class="bg-black bg-opacity-80 p-6 rounded-lg shadow-lg text-center">
        <h1 class="text-5xl font-bold mb-4"><?= htmlspecialchars($news['title']) ?></h1>
        <p class="text-gray-400 mb-6"><?= date_format($news['created_at'], "d M Y, H:i") ?></p>

        <div class="flex justify-center">
          <?php
              $imageSrc = '';
              if (!empty($news['image'])) {
                  if (filter_var($news['image'], FILTER_VALIDATE_URL)) {
                      // Jika URL eksternal
                      $imageSrc = $news['image'];
                  } else {
                      // Jika nama file lokal
                      $imageSrc = 'uploads/' . htmlspecialchars($news['image']);
                  }
              }
            ?>
            <?php if (!empty($imageSrc)): ?>
              <img src="<?= $imageSrc ?>" alt="News Image"
                  class="w-[518px] h-[600px] object-contain bg-black rounded shadow" />
            <?php endif; ?>
        </div>

        <div class="text-lg leading-relaxed text-gray-200 whitespace-pre-line mt-6 font-bold text-center">
          <?= nl2br(htmlspecialchars($news['content'])) ?>
        </div>

        <div class="mt-8">
          <a href="<?= BASE_URL ?>" class="text-blue-400 hover:underline">&larr; Kembali ke Beranda</a>
        </div>
      </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">

      <!-- Top Spender -->
      <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-bold mb-4 text-center">💰 Top Spender</h2>
        <table class="w-full table-fixed text-sm">
          <thead>
            <tr class="border-b border-gray-600 text-left">
              <th class="w-[20px] px-1">#</th>
              <th class="w-[85px] text-center px-1">Nama</th>
              <th class="w-[60px] text-center px-1">Total (Rp)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topSpender as $i => $donor): ?>
              <?php
                $total = (int)$donor['total'];
                $formatted = number_format($total, 0, '', '');
                if (strlen($formatted) <= 2) {
                  $masked = '**';
                } else {
                  $visible = substr($formatted, 2);
                  $masked = '**' . number_format((int)$visible, 0, ',', '.');
                }
              ?>
              <tr class="border-b border-gray-800">
                <td class="py-1 px-1"><?= $i + 1 ?></td>
                <td class="py-1 px-1 text-blue-400 font-semibold truncate text-center"><?= htmlspecialchars($donor['UserID']) ?></td>
                <td class="py-1 px-1 text-center text-green-400 font-medium"><?= $masked ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Top Kill -->
      <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
        <h2 class="text-xl font-bold mb-4 text-center">🏆 Top Kill</h2>
        <table class="w-full table-fixed text-sm">
          <thead>
            <tr class="border-b border-gray-600 text-left">
              <th class="w-[20px] px-1">#</th>
              <th class="w-[85px] px-1">Nama</th>
              <th class="w-[40px] text-center px-1">Kill</th>
              <th class="w-[40px] text-center px-1">Campus</th>
              <th class="w-[40px] text-center px-1">Job</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leaderboard as $i => $char): ?>
            <tr class="border-b border-gray-800">
              <td class="py-1 px-1"><?= $i + 1 ?></td>
              <td class="py-1 px-1 truncate text-red-400 font-semibold"><?= htmlspecialchars($char['ChaName']) ?></td>
              <td class="py-1 px-1 text-center"><?= number_format($char['ChaPKScore']) ?></td>
              <td class="py-1 px-1 text-center">
                <img src="assets/icons/school/<?= (int)$char['ChaSchool'] ?>.png" class="inline-block h-8 w-8" />
              </td>
              <td class="py-1 px-1 text-center">
                <img src="assets/icons/job/<?= (int)$char['ChaClass'] ?>.png" class="inline-block h-8 w-8" />
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Status Server + GM Online -->
      <div class="grid grid-cols-1 sm:grid-cols-[6fr_2fr] gap-1">
        <!-- Status Server -->
        <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
          <h2 class="text-xl font-bold mb-3 text-center">🖥️ Status Server</h2>
          <?php
            $serverStatus = 'OFFLINE';
            $statusFile = __DIR__ . '/admin/server_status.txt';
            if (file_exists($statusFile)) {
              $serverStatus = strtoupper(trim(file_get_contents($statusFile)));
            }
            $color = $serverStatus === 'ONLINE' ? 'text-green-400 glowing-text' : 'text-red-500 glowing-offline';
          ?>
          <div class="text-5xl font-extrabold <?= $color ?> tracking-widest text-center"><?= $serverStatus ?></div>
        </div>

        <!-- GM Online -->
        <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
          <h2 class="text-xl font-bold mb-3 text-center">GM Online</h2>
          <?php
            $gmColor = $gmOnlineCount > 0 ? 'text-green-400 glowing-text' : 'text-red-500 glowing-offline';
          ?>
          <div class="text-3xl font-extrabold <?= $gmColor ?> text-center"><?= $gmOnlineCount ?></div>
        </div>
      </div>
    </div>
  </div>

  <?php include_once __DIR__ . '/components/footer.php'; ?>
</body>
</html>
