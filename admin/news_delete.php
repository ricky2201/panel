<?php
session_start();
require_once '../config/db.php';
require_once '../config/db_panel.php';

if (!isset($_SESSION['userid']) || $_SESSION['UserType'] != 30) {
  header("Location: ../");
  exit;
}

if (!isset($_GET['id'])) {
  die("ID tidak ditemukan.");
}

$id = (int)$_GET['id'];

// Ambil nama gambar untuk dihapus dari folder
$stmt = sqlsrv_query($conn, "SELECT image FROM NRSPanel.dbo.News WHERE id = ?", [$id]);
$row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

if (!$row) {
  die("Berita tidak ditemukan.");
}

// Hapus gambar jika ada
if (!empty($row['image'])) {
  $image_path = '../uploads/' . $row['image'];
  if (file_exists($image_path)) {
    unlink($image_path);
  }
}

// Hapus berita dari database
$stmt = sqlsrv_query($conn, "DELETE FROM NRSPanel.dbo.News WHERE id = ?", [$id]);

if ($stmt) {
  header("Location: news_manage?msg=deleted");
  exit;
} else {
  die("Gagal menghapus berita.");
}
?>
