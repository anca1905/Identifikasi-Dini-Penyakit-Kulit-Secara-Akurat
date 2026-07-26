<?php
// includes/koneksi.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "isdila_kulit";

$koneksi = mysqli_connect($host, $user, $pass, $db);

if (!$koneksi) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}
?>
