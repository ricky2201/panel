<?php
require_once __DIR__ . '/config/db_game.php';
require_once __DIR__ . '/config/db.php'; // Perbaikan: Seharusnya db_panel.php jika Anda mengikuti setup sebelumnya
require_once __DIR__ . '/config/db_panel.php'; // Pastikan ini ada jika diperlukan
require_once __DIR__ . '/path.php';

// Menentukan kategori utama, default ke 'topkill'
$mainCategory = $_GET['category'] ?? 'topkill';
$school = $_GET['school'] ?? 'all';
$class = $_GET['class'] ?? 'all'; // Default tetap 'all' untuk fleksibilitas

$schools = [
    'all' => 'All',
    0 => 'Sacred Gate',
    1 => 'Mystic Peak',
    2 => 'Phoenix'
];

$classGroups = [
    'all' => 'All',
    'brawler' => 'Brawler',
    'swordsman' => 'Swordsman',
    'archer' => 'Archer',
    'shaman' => 'Shaman', // Tambahkan Shaman di sini
    'extreme' => 'Extreme',
    'scientist' => 'Scientist'
];

function mapChaClass($classValue) {
    $mapping = [
        1 => 'Fighter (M)',
        64 => 'Fighter (F)',
        2 => 'Swordsman (M)',
        128 => 'Swordsman (F)',
        4 => 'Archer (F)',
        256 => 'Archer (M)',
        8 => 'Shaman (F)',
        512 => 'Shaman (M)',
        16 => 'Extreme (M)',
        32 => 'Extreme (F)',
        1024 => 'Scientist (M)',
        2048 => 'Scientist (F)',
        4096 => 'Assassin (M)',
        8192 => 'Assassin (F)',
        16384 => 'Magician (M)',
        32768 => 'Magician (F)',
        262144 => 'Shaper (M)',
        524288 => 'Shaper (F)'
    ];
    return $mapping[(int)$classValue] ?? 'Unknown';
}

function getChaClassIcon($classValue) {
    $validClasses = [
        1, 2, 4, 8, 16, 32, 64, 128, 256, 512,
        1024, 2048, 4096, 8192, 16384, 32768, 262144, 524288
    ];
    return in_array((int)$classValue, $validClasses) ? (int)$classValue : 'unknown';
}

$whereClauses = [];
$joinCombatRecord = false; // Flag untuk menentukan apakah perlu JOIN ke ChaCombatRecord

// Filter Sekolah
if (is_numeric($school)) {
    $whereClauses[] = "CI.ChaSchool = $school"; // Gunakan alias CI untuk ChaInfo
}

// Filter Kelas (Shaman Khusus untuk Top Ress)
$classFilterMap = [
    'brawler' => [1, 64],
    'swordsman' => [2, 128],
    'archer' => [4, 256],
    'shaman' => [8, 512],
    'extreme' => [16, 32],
    'scientist' => [1024, 2048]
];

if (array_key_exists($class, $classFilterMap)) {
    $allowed = implode(', ', $classFilterMap[$class]);
    $whereClauses[] = "CI.ChaClass IN ($allowed)";
} elseif (is_numeric($class)) { // Jika class dipilih secara spesifik
    $whereClauses[] = "CI.ChaClass = $class";
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

switch ($mainCategory) {
    case 'toplevel':
        $query = "
            SELECT TOP 50 CI.ChaClass, CI.ChaSchool, CI.ChaName, CI.ChaLevel, CI.ChaExp, CI.ChaOnline
            FROM RG1Game.dbo.ChaInfo CI
            $whereSql
            ORDER BY CI.ChaLevel DESC, CI.ChaExp DESC
        ";
        break;
    case 'toprich':
        $query = "
            SELECT TOP 50 CI.ChaClass, CI.ChaSchool, CI.ChaName, CI.ChaMoney, CI.ChaOnline
            FROM RG1Game.dbo.ChaInfo CI
            $whereSql
            ORDER BY CI.ChaMoney DESC
        ";
        break;
    case 'topress': // Kategori baru untuk Top Ress
        $joinCombatRecord = true;
        // Hanya untuk shaman (ChaClass 8 dan 512)
        // Jika filter kelas sudah ada, tambahkan ke dalamnya, jika tidak, buat baru
        if (empty($class) || $class === 'all' || !array_key_exists($class, $classFilterMap) || ($class !== 'shaman' && !in_array((int)$class, [8, 512]))) {
             // Jika bukan shaman atau 'all' atau filter lain yang aktif, paksakan ke shaman
            $whereClauses[] = "CI.ChaClass IN (8, 512)";
            // Set class ke 'shaman' agar tombol filter shaman otomatis terpilih
            $class = 'shaman'; 
        } else if ($class === 'shaman' || in_array((int)$class, [8, 512])) {
            // Jika shaman sudah dipilih, tidak perlu menambahkan lagi
            // Pastikan ChaClass IN (8, 512) sudah ada di $whereClauses dari filter umum
        } else {
             // Jika ada filter kelas lain yang dipilih tapi bukan shaman, override atau beri pesan error.
             // Untuk saat ini, kita akan menambahkan ChaClass In (8,512)
             // Atau bisa juga redirect user kembali dengan error.
             // Untuk kesederhanaan, kita hanya menambahkan filter shaman.
             $whereClauses[] = "CI.ChaClass IN (8, 512)";
             $class = 'shaman'; // Set class ke shaman untuk UI
        }
        $whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        $query = "
            SELECT TOP 50
                CI.ChaClass, CI.ChaSchool, CI.ChaName, CI.ChaOnline,
                CR.PVPResu
            FROM RG1Game.dbo.ChaInfo CI
            JOIN RG1Game.dbo.ChaCombatRecord CR ON CI.ChaNum = CR.ChaNum
            $whereSql
            ORDER BY CR.PVPResu DESC
        ";
        break;
    default: // Default ke Top Kill
        $mainCategory = 'topkill';
        $joinCombatRecord = true;
        $query = "
            SELECT TOP 50
                CI.ChaClass, CI.ChaSchool, CI.ChaName, CI.ChaOnline,
                CR.PVPKills, CR.PVPDeaths
            FROM RG1Game.dbo.ChaInfo CI
            JOIN RG1Game.dbo.ChaCombatRecord CR ON CI.ChaNum = CR.ChaNum
            $whereSql
            ORDER BY CR.PVPKills DESC
        ";
}

$result = sqlsrv_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Leaderboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white flex flex-col min-h-screen">
<?php include_once __DIR__ . '/components/navbar.php'; ?>

<div class="max-w-7xl mx-auto p-6 flex-grow">
    <h1 class="text-3xl font-bold mb-6 text-center">LEADERBOARD</h1>

    <div class="flex flex-wrap justify-center gap-2 mb-4">
        <?php
        $mainCategories = [
            'topkill' => 'Top Kill',
            'toplevel' => 'Top Level',
            'toprich' => 'Top Rich',
            'topress' => 'Top Ress' // Tambahkan kategori Top Ress di sini
        ];
        foreach ($mainCategories as $key => $label):
        ?>
            <a href="?category=<?= $key ?>&school=<?= $school ?>&class=<?= ($key == 'topress') ? 'shaman' : $class ?>"
               class="px-4 py-2 rounded <?= $mainCategory == $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-800 border' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap justify-center gap-2 mb-2">
        <?php foreach ($schools as $key => $label): ?>
            <a href="?category=<?= $mainCategory ?>&school=<?= $key ?>&class=<?= $class ?>"
               class="px-3 py-1 rounded <?= $school == $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-800 border' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap justify-center gap-2 mb-6">
        <?php foreach ($classGroups as $key => $label): ?>
            <a href="?category=<?= $mainCategory ?>&school=<?= $school ?>&class=<?= $key ?>"
               class="px-3 py-1 rounded <?= $class == $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-800 border' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 bg-black bg-opacity-50 rounded shadow overflow-x-auto text-sm">
        <table class="table-fixed w-full text-md">
            <thead class="bg-gray-800 text-white">
            <tr>
                <th class="w-[40px] px-1 py-1 text-center">#</th>
                <th class="w-[100px] px-1 py-1 text-left">Name</th>
                <th class="w-[60px] px-1 py-1 text-center">School</th>
                <th class="w-[60px] px-1 py-1 text-center">Class</th>
                <?php if ($mainCategory === 'topkill'): ?>
                    <th class="w-[80px] px-1 py-1 text-center">PK Score</th>
                    <th class="w-[80px] px-1 py-1 text-center">PK Death</th>
                <?php elseif ($mainCategory === 'toplevel'): ?>
                    <th class="w-[80px] px-1 py-1 text-center">Level</th>
                    <th class="w-[80px] px-1 py-1 text-center">Exp</th>
                <?php elseif ($mainCategory === 'toprich'): ?>
                    <th class="w-[80px] px-1 py-1 text-center">Money</th>
                <?php elseif ($mainCategory === 'topress'): // Kolom baru untuk Top Ress ?>
                    <th class="w-[80px] px-1 py-1 text-center">Resurrection</th>
                <?php endif; ?>
                <th class="w-[80px] px-1 py-1 text-center">Status</th>
            </tr>
            </thead>
            <tbody>
            <?php
                $rank = 1;
                if ($result) { // Pastikan query berhasil
                    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)):
                        $chaClass = mapChaClass($row['ChaClass']);
                        $chaSchool = $schools[$row['ChaSchool']] ?? 'Unknown';
                        $classIcon = getChaClassIcon($row['ChaClass']);

                        // Warna highlight baris
                        $rowClass = '';
                        if ($rank == 1) {
                            $rowClass = 'bg-yellow-500 bg-opacity-30 font-semibold shadow-lg';
                        } elseif ($rank == 2) {
                            $rowClass = 'bg-blue-400 bg-opacity-20 font-semibold shadow-lg';
                        } elseif ($rank == 3) {
                            $rowClass = 'bg-orange-400 bg-opacity-20 font-medium shadow-lg';
                        }
                        
                        // Medali jika TOP 1-3
                        $medal = '';
                        if ($rank == 1) $medal = '🥇';
                        elseif ($rank == 2) $medal = '🥈';
                        elseif ($rank == 3) $medal = '🥉';
                ?>
                <tr class="border-b border-gray-700 text-center <?= $rowClass ?>">
                    <td class="px-1 py-1"><?= $medal ?: $rank ?></td>
                    <td class="px-1 py-1 text-left"><?= htmlspecialchars($row['ChaName']) ?></td>
                    <td class="px-1 py-1">
                        <img src="assets/icons/school/<?= (int)$row['ChaSchool'] ?>.png" class="h-8 w-8 mx-auto" title="<?= $chaSchool ?>">
                    </td>
                    <td class="px-1 py-1">
                        <img src="assets/icons/job/<?= $classIcon ?>.png" class="h-8 w-8 mx-auto" title="<?= $chaClass ?>">
                    </td>
                    <?php if ($mainCategory === 'topkill'): ?>
                        <td class="px-1 py-1"><?= $row['PVPKills'] ?></td>
                        <td class="px-1 py-1"><?= $row['PVPDeaths'] ?></td>
                    <?php elseif ($mainCategory === 'toplevel'): ?>
                        <td class="px-1 py-1"><?= $row['ChaLevel'] ?></td>
                        <td class="px-1 py-1"><?= $row['ChaExp'] ?></td>
                    <?php elseif ($mainCategory === 'toprich'): ?>
                        <td class="px-1 py-1"><?= number_format($row['ChaMoney']) ?></td>
                    <?php elseif ($mainCategory === 'topress'): ?>
                        <td class="px-1 py-1"><?= $row['PVPResu'] ?></td>
                    <?php endif; ?>
                    <td class="px-1 py-1">
                        <span class="<?= $row['ChaOnline'] == 1 ? 'text-green-500' : 'text-red-500' ?>">
                            <?= $row['ChaOnline'] == 1 ? 'Online' : 'Offline' ?>
                        </span>
                    </td>
                </tr>
                <?php
                        $rank++;
                    endwhile;
                } else {
                    // Penanganan error jika query gagal
                    echo "<tr><td colspan='7' class='px-4 py-2 text-red-500 text-center'>Gagal mengambil data leaderboard. Silakan coba lagi.</td></tr>";
                    // Untuk debugging, bisa tambahkan: print_r(sqlsrv_errors(), true);
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/components/footer.php'; ?>
</body>
</html>