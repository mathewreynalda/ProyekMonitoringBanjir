<?php
include 'config.php';
include 'includes/status_level.php'; // untuk getStatusLevel()
date_default_timezone_set('Asia/Jakarta');

$APIKEY_SERVER = 'iotflood1903';
$apikey = $_POST['apikey'] ?? '';
if ($apikey !== $APIKEY_SERVER) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$tinggi = isset($_POST['tinggi']) ? floatval($_POST['tinggi']) : 0;
$waktu  = isset($_POST['waktu']) ? $_POST['waktu'] : date('Y-m-d H:i:s');

// Hitung status berdasarkan tinggi
$status = getStatusLevel($tinggi);

$sql = "INSERT INTO data_air (tinggi, status, waktu) VALUES ($tinggi, '$status', '$waktu')";
if ($conn->query($sql) === TRUE) {
    echo "Data berhasil disimpan";
} else {
    echo "Error: " . $conn->error;
}

$conn->close();
?>
