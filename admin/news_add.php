<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/db.php';
require_once '../config/db_panel.php';
require_once '../includes/csrf.php'; // Tambahkan ini untuk CSRF

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
    header("Location: ../");
    exit;
}

$msg = '';

// Validasi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate('news_add')) {
        $msg = '⚠️ Permintaan tidak valid atau session expired.';
    } else {
        $title   = trim($_POST['title']);
        $slug    = trim($_POST['slug']);
        $content = trim($_POST['content']);
        $image   = trim($_POST['image']); // bisa URL atau nama file lokal

        if ($title && $slug && $content) {
            $sql = "INSERT INTO NRSPanel.dbo.News (title, slug, content, image, created_at) VALUES (?, ?, ?, ?, GETDATE())";
            $params = [$title, $slug, $content, $image];
            $stmt = sqlsrv_query($conn, $sql, $params);
            if ($stmt) {
                header("Location: news_manage?msg=added");
                exit;
            } else {
                $msg = "❌ Gagal menambahkan berita.";
            }
        } else {
            $msg = "⚠️ Semua field wajib diisi.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Berita</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen p-10">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-3xl font-bold mb-6">➕ Tambah Berita</h1>

        <?php if ($msg): ?>
            <div class="bg-red-600 p-3 rounded mb-4"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <?= csrf_input('news_add') ?>
            <input type="text" name="title" placeholder="Judul Berita" class="w-full p-2 rounded text-black" required>
            <input type="text" name="slug" placeholder="Slug (tanpa spasi)" class="w-full p-2 rounded text-black" required>
            <input type="text" name="image" placeholder="Link Gambar atau Nama File" class="w-full p-2 rounded text-black">
            <textarea name="content" placeholder="Isi berita..." class="w-full p-2 rounded text-black h-40" required></textarea>
            <div class="flex justify-between">
                <a href="news_manage" class="bg-gray-600 px-4 py-2 rounded">Kembali</a>
                <button type="submit" class="bg-blue-600 px-4 py-2 rounded">Simpan</button>
            </div>
        </form>
    </div>
</body>
</html>
