<?php
include 'config.php';
date_default_timezone_set('Asia/Jakarta');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Query untuk mengambil data terbaru dari tabel data_air
$sql = "SELECT id, tinggi, status, waktu FROM data_air ORDER BY waktu DESC LIMIT 20";
$result = $conn->query($sql);

$data = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
} else {
    echo json_encode(["error" => "Tidak ada data sensor"]);
    exit;
}

echo json_encode($data);
$conn->close();
?>
