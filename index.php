<?php
session_start();

require_once __DIR__ . '/config/db_game.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/db_panel.php';
require_once __DIR__ . '/path.php';

$username = '';
$logoutSuccess = isset($_GET['logout']);
$timeout = isset($_GET['timeout']);
$invalidSession = isset($_GET['invalid_session']);

if (isset($_SESSION['userid'])) {
  $userid = $_SESSION['userid'];
  $stmtUser = sqlsrv_query($conn, "SELECT UserID FROM dbo.UserInfo WHERE UserID = ?", [$userid]);
  if ($stmtUser && $rowUser = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
    $username = $rowUser['UserID'];
  }
}

$page = isset($_GET['page']) ? max((int)$_GET['page'], 1) : 1;
$limit = 4;
$offset = ($page - 1) * $limit;

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
    $userID = $row['UserID'];
    $chaName = $userID;
    $chaSchool = null;
    $chaClass = null;

    // Ambil UserNum dan ChaName dari UserInfo
    $stmtUser = sqlsrv_query($conn, "SELECT UserNum, ChaName FROM dbo.UserInfo WHERE UserID = ?", [$userID]);
    if ($stmtUser && $rowUser = sqlsrv_fetch_array($stmtUser, SQLSRV_FETCH_ASSOC)) {
      $chaName = $rowUser['ChaName'] ?: $userID;
      $userNum = $rowUser['UserNum'];

      // Ambil Campus & Job dari ChaInfo berdasarkan UserNum
      $stmtChar = sqlsrv_query($connGame, "SELECT TOP 1 ChaSchool, ChaClass FROM ChaInfo WHERE UserNum = ? ORDER BY ChaReborn DESC", [$userNum]);
      if ($stmtChar && $rowChar = sqlsrv_fetch_array($stmtChar, SQLSRV_FETCH_ASSOC)) {
        $chaSchool = $rowChar['ChaSchool'];
        $chaClass = $rowChar['ChaClass'];
      }
    }

    $topSpender[] = [
      'UserID' => $userID,
      'ChaName' => $chaName,
      'ChaSchool' => $chaSchool,
      'ChaClass' => $chaClass,
    ];
  }
}

$total = 0;
$stmtCount = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM NRSPanel.dbo.News");
if ($stmtCount && $row = sqlsrv_fetch_array($stmtCount, SQLSRV_FETCH_ASSOC)) {
  $total = (int)$row['total'];
}
$totalPages = ceil($total / $limit);

$news = [];
$stmtNews = sqlsrv_query($conn, "SELECT * FROM NRSPanel.dbo.News ORDER BY created_at DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY", [$offset, $limit]);
if ($stmtNews) {
  while ($row = sqlsrv_fetch_array($stmtNews, SQLSRV_FETCH_ASSOC)) {
    $news[] = $row;
  }
}

$leaderboard = [];
$stmtLB = sqlsrv_query($connGame, "
  SELECT TOP 3 ChaName, ChaPKScore, ChaSchool, ChaClass 
  FROM ChaInfo 
  ORDER BY ChaPKScore DESC
");

if ($stmtLB) {
  while ($row = sqlsrv_fetch_array($stmtLB, SQLSRV_FETCH_ASSOC)) {
    $leaderboard[] = $row;
  }
}

$gmOnlineCount = 0;
$stmtGM = sqlsrv_query($conn, "SELECT COUNT(*) AS total FROM dbo.UserInfo WHERE UserLoginState = 1 AND UserType = 30");
if ($stmtGM && $rowGM = sqlsrv_fetch_array($stmtGM, SQLSRV_FETCH_ASSOC)) {
  $gmOnlineCount = (int)$rowGM['total'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Hiperion RAN - Home</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">

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
<body class="bg-gray-900 text-white min-h-screen flex flex-col">

  <div class="flex-grow flex flex-col">

    <?php include_once __DIR__ . '/components/navbar.php'; ?>

    <div x-data="carousel()" x-init="start()" class="w-full max-w-[985px] mx-auto mt-6 relative overflow-hidden rounded-lg shadow-lg aspect-[985/325]">
      <template x-for="(image, index) in images" :key="index">
        <div x-show="current === index" x-transition:enter="transition-opacity duration-1000" x-transition:leave="transition-opacity duration-1000" class="absolute inset-0">
          <img :src="image" class="w-full h-full object-cover" />
        </div>
      </template>
      <button @click="prev()" class="absolute left-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-40 text-white px-3 py-1 rounded hover:bg-opacity-60">❮</button>
      <button @click="next()" class="absolute right-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-40 text-white px-3 py-1 rounded hover:bg-opacity-60">❯</button>
    </div>

    <div class="w-full max-w-[985px] mx-auto mt-4">
      <div class="bg-red-600 text-center py-2 text-xl font-semibold rounded shadow">
        🔥 Event Top Up Double Point s/d 20 Juli 2025!
      </div>
    </div>

    <main class="max-w-[985px] mx-auto py-10 px-4 grid grid-cols-1 md:grid-cols-10 gap-8">

      <div class="md:col-span-6">
        <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
          <h2 class="text-xl font-bold mb-4">📰 Berita Terbaru</h2>
          <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach($news as $item): ?>
              <div class="bg-white text-black rounded shadow overflow-hidden hover:scale-105 transition">
                <a href="news/<?= urlencode($item['slug']) ?>">
                  <div class="relative">
                    <?php
                      $imageSrc = '';
                      if (!empty($item['image'])) {
                        if (filter_var($item['image'], FILTER_VALIDATE_URL)) {
                          $imageSrc = $item['image']; // URL eksternal
                        } else {
                          $imageSrc = 'uploads/' . htmlspecialchars($item['image']); // File lokal
                        }
                      }
                    ?>
                    <img src="<?= $imageSrc ?>" class="w-full h-[250px] object-cover bg-black" />
                    <div class="absolute bottom-0 left-0 right-0 flex justify-center">
                      <div class="bg-black bg-opacity-90 text-white text-xl font-bold px-4 py-2 text-center w-full">
                        <?= htmlspecialchars($item['title']) ?>
                      </div>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="flex justify-center space-x-2 mt-6">
            <?php if($page > 1): ?>
              <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-700 rounded hover:bg-gray-600">«</a>
            <?php endif; ?>
            <?php for($i = 1; $i <= $totalPages; $i++): ?>
              <a href="?page=<?= $i ?>" class="px-3 py-1 <?= $i == $page ? 'bg-blue-600' : 'bg-gray-700' ?> rounded"><?= $i ?></a>
            <?php endfor; ?>
            <?php if($page < $totalPages): ?>
              <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-700 rounded hover:bg-gray-600">»</a>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="md:col-span-4 space-y-6">

        <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
          <h2 class="text-xl font-bold mb-4 text-center">💰 Top Spender</h2>
          <table class="w-full table-fixed text-sm">
            <thead>
              <tr class="border-b border-gray-600 text-left">
                <th class="w-[20px] px-1">No</th>
                <th class="w-[85px] px-1">Nama</th>
                <th class="w-[40px] text-center px-1">Campus</th>
                <th class="w-[40px] text-center px-1">Job</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topSpender as $i => $donor): ?>
              <tr class="border-b border-gray-800">
                <td class="py-1 px-1"><?= $i + 1 ?></td>
                <td class="py-1 px-1 text-blue-400 font-semibold truncate"><?= htmlspecialchars($donor['ChaName']) ?></td>
                <td class="py-1 px-1 text-center">
                  <?php if ($donor['ChaSchool'] !== null): ?>
                    <img src="assets/icons/school/<?= (int)$donor['ChaSchool'] ?>.png" class="inline-block h-8 w-8" />
                  <?php endif; ?>
                </td>
                <td class="py-1 px-1 text-center">
                  <?php if ($donor['ChaClass'] !== null): ?>
                    <img src="assets/icons/job/<?= (int)$donor['ChaClass'] ?>.png" class="inline-block h-8 w-8" />
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg">
          <h2 class="text-xl font-bold mb-4 text-center">🏆 Top Kill</h2>
          <table class="w-full table-fixed text-sm">
            <thead>
              <tr class="border-b border-gray-600 text-left">
                <th class="w-[20px] px-1">No</th>
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

            <!--    <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg text-center">
          <h2 class="text-xl font-bold mb-3">🖥️ Status Server</h2>
          <?php
            $serverStatus = 'OFFLINE';
            $statusFile = __DIR__ . '/admin/server_status.txt';
            if (file_exists($statusFile)) {
              $serverStatus = strtoupper(trim(file_get_contents($statusFile)));
            }
            $color = $serverStatus === 'ONLINE' ? 'text-green-400 glowing-text' : 'text-red-500 glowing-offline';
          ?>
          <div class="text-5xl font-extrabold <?= $color ?> tracking-widest"><?= $serverStatus ?></div>
        </div> -->

              <!--   <div class="bg-black bg-opacity-60 p-6 rounded-lg shadow-lg text-center">
          <h2 class="text-xl font-bold mb-3">GM Online</h2>
          <?php
            $gmColor = $gmOnlineCount > 0 ? 'text-green-400 glowing-text' : 'text-red-500 glowing-offline';
          ?>
          <div class="text-3xl font-extrabold <?= $gmColor ?>"><?= $gmOnlineCount ?></div>
        </div> -->

      </div> <!-- End Sidebar -->

    </main>
  </div>

  <?php include_once __DIR__ . '/components/footer.php'; ?>
 

  <script>
  function carousel() {
    return {
      current: 0,
      images: [
        'assets/slide1.jpg',
        'assets/slide2.jpg',
        'assets/slide3.jpg'
      ],
      start() {
        setInterval(() => this.next(), 5000);
      },
      next() {
        this.current = (this.current + 1) % this.images.length;
      },
      prev() {
        this.current = (this.current - 1 + this.images.length) % this.images.length;
      }
    }
  }
  </script>
</body>
</html>
