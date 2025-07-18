<?php
require_once __DIR__ . '/../config/db_game.php'; // Koneksi ke RG1Game
require_once __DIR__ . '/../config/db.php';      // Koneksi ke NRSPanel
require_once __DIR__ . '/../path.php';           // Untuk BASE_URL
// require_once __DIR__ . '/../config/db_panel.php'; // Opsional, jika hanya db_game dan db yang cukup

// Inisialisasi kategori default
$mainCategory = $_GET['category'] ?? 'cw_kill'; // Default ke Club War Kills
$school = $_GET['school'] ?? 'all';
$class = $_GET['class'] ?? 'all';

// Definisi Sekolah/Campus
$schools = [
    'all' => 'All Campus',
    0 => 'Sacred Gate',
    1 => 'Mystic Peak',
    2 => 'Phoenix'
];

// Definisi Kelas/Job
$classGroups = [
    'all' => 'All Jobs',
    'brawler' => 'Brawler',
    'swordsman' => 'Swordsman',
    'archer' => 'Archer',
    'shaman' => 'Shaman',
    'extreme' => 'Extreme',
    'scientist' => 'Scientist'
];

// Mapping ChaClass ke nama Class yang lebih mudah dibaca
function mapChaClass($classValue) {
    $mapping = [
        1 => 'Brawler (M)',    64 => 'Brawler (F)',
        2 => 'Swordsman (M)', 128 => 'Swordsman (F)',
        4 => 'Archer (F)',    256 => 'Archer (M)',
        8 => 'Shaman (F)',    512 => 'Shaman (M)',
        16 => 'Extreme (M)',   32 => 'Extreme (F)',
        1024 => 'Scientist (M)', 2048 => 'Scientist (F)'
    ];
    return $mapping[(int)$classValue] ?? 'Unknown';
}

// Fungsi untuk mendapatkan icon class
function getChaClassIcon($classValue) {
    $validClasses = [
        1, 2, 4, 8, 16, 32, 64, 128, 256, 512,
        1024, 2048, 4096, 8192, 16384, 32768, 262144, 524288
    ];
    return in_array((int)$classValue, $validClasses) ? (int)$classValue : 'unknown';
}

$whereClauses = [];
$queryParams = [];

// Filter Sekolah/Campus
if (is_numeric($school)) {
    $whereClauses[] = "CI.ChaSchool = ?";
    $queryParams[] = (int)$school;
}

// Filter Kelas/Job
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
} elseif (is_numeric($class)) { // Jika class diset langsung dengan nilai numerik
    $whereClauses[] = "CI.ChaClass = ?";
    $queryParams[] = (int)$class;
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$orderBy = '';
$selectColumn = '';
$title = '';

switch ($mainCategory) {
    case 'cw_kill':
        $selectColumn = 'CCR.ClubWarKills';
        $orderBy = 'ClubWarKills DESC';
        $title = 'Top Kill (Club War)';
        break;
    case 'cw_resu':
        $selectColumn = 'CCR.ClubWarResu';
        $orderBy = 'ClubWarResu DESC';
        $title = 'Top Resurrection (Club War)';
        break;
    case 'tw_kill':
        $selectColumn = 'CCR.TyrannyKill';
        $orderBy = 'TyrannyKill DESC';
        $title = 'Top Kill (Tyranny War)';
        break;
    case 'tw_resu':
        $selectColumn = 'CCR.TyrannyResu';
        $orderBy = 'TyrannyResu DESC';
        $title = 'Top Resurrection (Tyranny War)';
        break;
    default:
        $mainCategory = 'cw_kill';
        $selectColumn = 'CCR.ClubWarKills';
        $orderBy = 'ClubWarKills DESC';
        $title = 'Top Kill (Club War)';
        break;
}

$query = "
    SELECT TOP 50
        CI.ChaName, CI.ChaSchool, CI.ChaClass, CI.ChaOnline,
        $selectColumn AS Score
    FROM RG1Game.dbo.ChaInfo CI
    JOIN RG1Game.dbo.ChaCombatRecord CCR ON CI.ChaNum = CCR.ChaNum
    $whereSql
    ORDER BY Score DESC, CI.ChaLevel DESC
";

$stmt = sqlsrv_query($conn, $query, $queryParams);
$results = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
} else {
    // Penanganan error jika query gagal
    // error_log("SQL Error: " . print_r(sqlsrv_errors(), true)); // Untuk debugging ke error log
}

// Menyertakan navbar dari folder components
include_once __DIR__ . '/../components/navbar.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>War Leaderboard - Glacier</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <style>
        /* Gaya tambahan jika diperlukan */
    </style>
</head>
<body class="bg-gray-900 text-white flex flex-col min-h-screen">

<?php /* Navbar sudah disertakan di awal file PHP */ ?>

<div class="max-w-7xl mx-auto p-6 flex-grow">
    <a href="./" class="bg-gray-700 hover:bg-gray-800 text-white font-bold py-2 px-4 rounded">Back</a>
    <h1 class="text-3xl font-bold mb-6 text-center">WAR LEADERBOARD</h1>
    <h2 class="text-2xl font-bold mb-6 text-center text-yellow-400"><?= $title ?></h2>

    <div class="flex flex-wrap justify-center gap-2 mb-4">
        <?php
        $warCategories = [
            'cw_kill' => 'Top Kill CW',
            'cw_resu' => 'Top Resu CW',
            'tw_kill' => 'Top Kill TW',
            'tw_resu' => 'Top Resu TW'
        ];
        foreach ($warCategories as $key => $label):
        ?>
            <a href="?category=<?= $key ?>&school=<?= $school ?>&class=<?= $class ?>"
               class="px-4 py-2 rounded <?= $mainCategory == $key ? 'bg-blue-600 text-white' : 'bg-white text-gray-800 border' ?>">
                <?= $label ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="flex flex-wrap justify-center gap-2 mb-2">
        <?php foreach ($schools as $key => $label): ?>
            <a href="?category=<?= $mainCategory ?>&school=<?= $key ?>&class=<?= $class ?>"
               class="px-3 py-1 rounded <?= (string)$school === (string)$key ? 'bg-blue-600 text-white' : 'bg-white text-gray-800 border' ?>">
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
                    <th class="w-[60px] px-1 py-1 text-center">Campus</th>
                    <th class="w-[60px] px-1 py-1 text-center">Job</th>
                    <th class="w-[80px] px-1 py-1 text-center">Score</th>
                    <th class="w-[80px] px-1 py-1 text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $rank = 1;
                    if (count($results) > 0) {
                        foreach ($results as $row):
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
                        <img src="<?= BASE_URL ?>assets/icons/school/<?= (int)$row['ChaSchool'] ?>.png" class="h-8 w-8 mx-auto" title="<?= $chaSchool ?>">
                    </td>
                    <td class="px-1 py-1">
                        <img src="<?= BASE_URL ?>assets/icons/job/<?= $classIcon ?>.png" class="h-8 w-8 mx-auto" title="<?= $chaClass ?>">
                    </td>
                    <td class="px-1 py-1"><?= number_format($row['Score']) ?></td>
                    <td class="px-1 py-1">
                        <span class="<?= $row['ChaOnline'] == 1 ? 'text-green-500' : 'text-red-500' ?>">
                            <?= $row['ChaOnline'] == 1 ? 'Online' : 'Offline' ?>
                        </span>
                    </td>
                </tr>
                <?php
                        $rank++;
                        endforeach;
                    } else {
                        echo "<tr><td colspan='6' class='px-4 py-2 text-red-500 text-center'>Tidak ada data untuk kategori ini.</td></tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once __DIR__ . '/../components/footer.php'; ?>

</body>
</html>