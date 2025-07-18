<?php
// config/db_panel.php

$serverName = "DESKTOP-2STLION\SQLEXPRESS"; // atau bisa diganti dengan nama server SQL Anda
$connectionOptions = array(
    "Database" => "NRSPanel",       // Pastikan ini sesuai
    "Uid" => "sa",                  // Ganti dengan username SQL Server Anda
    "PWD" => "123qweasd",  // Ganti dengan password SQL Server Anda
    "CharacterSet" => "UTF-8"
);

// Buat koneksi
$connPanel = sqlsrv_connect($serverName, $connectionOptions);

// Cek koneksi
if ($connPanel === false) {
    die(print_r(sqlsrv_errors(), true));
}
?>
