<?php
$serverName = "DESKTOP-2STLION\SQLEXPRESS";
$connectionOptions = array(
    "Database" => "RG1User",
    "Uid" => "sa",
    "PWD" => "123qweasd"
);
$conn = sqlsrv_connect($serverName, $connectionOptions);
if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}
?>
