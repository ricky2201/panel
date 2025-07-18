<?php
$serverName = "DESKTOP-2STLION\SQLEXPRESS";
$connectionOptions = [
    "Database" => "RG1Shop",
    "Uid" => "sa",
    "PWD" => "123qweasd",
    "CharacterSet" => "UTF-8"
];
$connShop = sqlsrv_connect($serverName, $connectionOptions);

if (!$connShop) {
    die(print_r(sqlsrv_errors(), true));
}
?>
