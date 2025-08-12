<?php
include 'config.php';

$APIKEY_SERVER = 'iotflood1903';
$apikey = $_GET['apikey'] ?? '';
if ($apikey !== $APIKEY_SERVER) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

$sql = "SELECT status FROM control_status WHERE id=1";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
echo $row['status'];
$conn->close();
?>
