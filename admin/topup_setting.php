<?php
session_start();

require_once '../config/db.php';        // Koneksi ke RG1User
require_once '../config/db_game.php';   // Koneksi ke RG1Game
require_once '../config/db_panel.php';  // koneksi ke NRSPanel

// Proteksi akses hanya untuk admin UserType 30
if (!isset($_SESSION['userid']) || !isset($_SESSION['UserType']) || $_SESSION['UserType'] != 30) {
  header("Location: ../");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bulan = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('m');
    $tahun = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');

    // Cek apakah data sudah ada
    $stmt = sqlsrv_query($connPanel, "SELECT COUNT(*) AS total FROM TopupSetting");
    $exists = 0;
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $exists = (int)$row['total'];
    }

    if ($exists > 0) {
        // update
        $stmtUpdate = sqlsrv_query($connPanel, "
            UPDATE TopupSetting SET bulan = ?, tahun = ?, updated_at = GETDATE()
        ", [$bulan, $tahun]);
    } else {
        // insert
        $stmtInsert = sqlsrv_query($connPanel, "
            INSERT INTO TopupSetting (bulan, tahun) VALUES (?, ?)
        ", [$bulan, $tahun]);
    }

    // Redirect kembali ke index
    header("Location: index");
    exit;
}
?>
