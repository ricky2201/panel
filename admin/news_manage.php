<?php
session_start();
require_once '../config/db.php';
require_once '../config/db_panel.php';

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

// Ambil semua berita dari database
$query = "SELECT * FROM NRSPanel.dbo.News ORDER BY created_at DESC";
$stmt = sqlsrv_query($conn, $query);

$news_list = [];
if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $news_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kelola Berita</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-10">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">📰 Kelola Berita</h1>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
            <div class="bg-green-600 p-4 mb-4 rounded text-center">
                ✅ Berita berhasil dihapus.
            </div>
        <?php endif; ?>

        <div class="mb-6 flex justify-between items-center">
            <a href="./" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back
            </a>
            <a href="news_add" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                ➕ Tambah Berita
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full table-auto bg-gray-800 rounded shadow">
                <thead class="bg-gray-700 text-gray-300 text-sm uppercase">
                    <tr>
                        <th class="px-4 py-2 text-center">ID</th>
                        <th class="px-4 py-2 text-left">Judul</th>
                        <th class="px-4 py-2 text-left">Slug</th>
                        <th class="px-4 py-2 text-left">Gambar</th>
                        <th class="px-4 py-2 text-left">Konten</th>
                        <th class="px-4 py-2 text-center">Tanggal</th>
                        <th class="px-4 py-2 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-700">
                    <?php foreach ($news_list as $news): ?>
                    <tr class="hover:bg-gray-700">
                        <td class="px-4 py-2 text-center"><?= $news['id'] ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($news['title']) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($news['slug']) ?></td>
                        <td class="px-4 py-2">
                            <?php if (!empty($news['image'])): ?>
                                <?php
                                    $image = htmlspecialchars($news['image']);
                                    $imageSrc = (filter_var($image, FILTER_VALIDATE_URL)) ? $image : '../uploads/' . $image;
                                    ?>
                                    <img src="<?= $imageSrc ?>" alt="Gambar" class="w-20 rounded">
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-2 max-w-xs truncate"><?= strip_tags($news['content']) ?></td>
                        <td class="px-4 py-2 text-center"><?= $news['created_at']->format('Y-m-d H:i') ?></td>
                        <td class="px-4 py-2 text-center space-x-2">
                            <a href="news_edit.php?id=<?= $news['id'] ?>" class="bg-yellow-500 hover:bg-yellow-600 text-white py-1 px-3 rounded text-xs">Edit</a>
                            <a href="#" onclick="confirmDelete('news_delete.php?id=<?= $news['id'] ?>')" class="bg-red-600 hover:bg-red-700 text-white py-1 px-3 rounded text-xs">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (count($news_list) === 0): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400">Belum ada berita.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function confirmDelete(url) {
            if (confirm("Apakah Anda yakin ingin menghapus berita ini?")) {
                window.location.href = url;
            }
        }
    </script>
</body>
</html>
