<?php
session_start();
require_once '../config/db.php';
require_once '../config/db_panel.php';
require_once '../includes/csrf.php'; // Tambahkan ini

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

if (!isset($_GET['id'])) {
    die("ID tidak ditemukan.");
}

$id = (int)$_GET['id'];
$msg = '';

$stmt = sqlsrv_query($conn, "SELECT * FROM NRSPanel.dbo.News WHERE id = ?", [$id]);
if (!$stmt || !sqlsrv_has_rows($stmt)) {
    die("Berita tidak ditemukan.");
}
$news = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('news_edit')) {
        $msg = "❌ CSRF token tidak valid atau sudah kadaluarsa.";
    } else {
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);
        $content = trim($_POST['content']);
        $image = trim($_POST['image']);

        if ($title && $slug && $content) {
            $sql = "UPDATE NRSPanel.dbo.News SET title = ?, slug = ?, content = ?, image = ? WHERE id = ?";
            $params = [$title, $slug, $content, $image, $id];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt) {
                header("Location: news_manage?msg=updated");
                exit;
            } else {
                $msg = "Gagal mengupdate berita.";
            }
        } else {
            $msg = "Semua field wajib diisi.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Berita</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-10">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">✏️ Edit Berita</h1>

        <?php if ($msg): ?>
            <div class="bg-red-600 p-3 rounded mb-4">❗ <?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <?= csrf_input('news_edit') ?>
            <input type="text" name="title" value="<?= htmlspecialchars($news['title']) ?>" class="w-full p-2 rounded text-black" required>
            <input type="text" name="slug" value="<?= htmlspecialchars($news['slug']) ?>" class="w-full p-2 rounded text-black" required>
            <input type="text" name="image" value="<?= htmlspecialchars($news['image']) ?>" class="w-full p-2 rounded text-black">
            <textarea name="content" class="w-full p-2 rounded text-black h-40" required><?= htmlspecialchars($news['content']) ?></textarea>
            <div class="flex justify-between">
                <a href="news_manage" class="bg-gray-600 px-4 py-2 rounded">Batal</a>
                <button type="submit" class="bg-yellow-500 px-4 py-2 rounded">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</body>
</html>
