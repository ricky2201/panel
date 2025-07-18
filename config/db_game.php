<?php
$serverName = "DESKTOP-2STLION\SQLEXPRESS"; // atau IP SQL Server
$connectionOptions = [
    "Database" => "RG1Game",
    "Uid" => "sa",           // Sesuaikan
    "PWD" => "123qweasd",     // Sesuaikan
    "CharacterSet" => "UTF-8"
];

$connGame = sqlsrv_connect($serverName, $connectionOptions);

if ($connGame === false) {
    die("Koneksi ke RG1Game gagal: " . print_r(sqlsrv_errors(), true));
}
?>
