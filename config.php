<?php
$host = "localhost";
$user = "u944297177_reynalda";
$pass = "Rusl119032000.";
$db = "u944297177_iotflood";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
date_default_timezone_set('Asia/Jakarta');
?>
