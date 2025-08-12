<?php
$host = "localhost";
$user = "";
$pass = "";
$db = "";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
date_default_timezone_set('Asia/Jakarta');
?>