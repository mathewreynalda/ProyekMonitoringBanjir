<?php
include '../config.php';
include '../includes/status_level.php';
date_default_timezone_set('Asia/Jakarta');

// === KONFIG TELEGRAM ===
define('TELEGRAM_TOKEN', '8347890114:AAFkjPoz7yuW5uHh5RbinMfjydGjboiGllg');
define('TELEGRAM_CHAT_ID', '6181039632');

function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $post = ['chat_id' => TELEGRAM_CHAT_ID, 'text' => $message, 'parse_mode' => 'HTML'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("Telegram Error: $error");
        return false;
    }

    $decoded = json_decode($response, true);
    return isset($decoded['ok']) && $decoded['ok'] === true;
}

// === STATUS SENSOR SAAT INI ===
$statusRow = $conn->query("SELECT status FROM control_status WHERE id=1");
if (!$statusRow || $statusRow->num_rows === 0) exit;
$currentSensorStatus = $statusRow->fetch_assoc()['status'] ?? 'OFF';

// === STATUS NOTIFY SEBELUMNYA ===
$res = $conn->query("SELECT * FROM notify_status WHERE id=1");
if (!$res || $res->num_rows === 0) exit;
$notify = $res->fetch_assoc();

$last_status = $notify['value'];
$last_sensor_status = $notify['sensor_status'] ?? 'OFF';
$last_notif_time = strtotime($notify['last_notif_sent_at'] ?? '1970-01-01');
$is_fresh_boot = $notify['is_fresh_boot'] ?? 0;

// === DATA SENSOR TERKINI ===
$response = @file_get_contents("https://lightgoldenrodyellow-elk-308282.hostingersite.com/get_data.php");
$data = json_decode($response, true);
if (!is_array($data) || !isset($data[0]['tinggi'], $data[0]['status'], $data[0]['waktu'])) exit;

$latest = $data[0];
$tinggi = floatval($latest['tinggi']);
$status_now = $latest['status'];
$waktu = $latest['waktu'];
$now = time();

// === 1. SENSOR ON ===
if ($last_sensor_status === 'OFF' && $currentSensorStatus === 'ON') {
    sendTelegramMessage("✅ Sensor telah dinyalakan");
    $conn->query("UPDATE notify_status SET sensor_status='ON', is_fresh_boot=1, updated_at=NOW() WHERE id=1");
    exit;
}

// === 2. SENSOR OFF ===
if ($last_sensor_status === 'ON' && $currentSensorStatus === 'OFF') {
    sendTelegramMessage("⚠️ Sensor telah dimatikan");
    $conn->query("UPDATE notify_status SET sensor_status='OFF', is_fresh_boot=0, updated_at=NOW() WHERE id=1");
    exit;
}

// === 3. FRESH BOOT
if ($is_fresh_boot == 1 && $currentSensorStatus === 'ON') {
    if (($now - strtotime($notify['updated_at'])) >= 5) {
        $msg = "ℹ️ Status saat sensor aktif:\nStatus: $status_now\nKetinggian air: {$tinggi} cm\nPukul: {$waktu}";
        sendTelegramMessage($msg);
        $conn->query("UPDATE notify_status SET value='$status_now', is_fresh_boot=0, updated_at='$waktu', last_notif_sent_at=NOW() WHERE id=1");
    }
    exit;
}

// === 4. STATUS BERUBAH
if ($status_now !== $last_status) {
    if (($now - $last_notif_time) >= 2) {
        $msg = ($status_now === 'Normal')
            ? "✅ Status kembali Normal.\nKetinggian air: {$tinggi} cm (pukul {$waktu})."
            : "⚠️ Peringatan Banjir: Status {$status_now}!\nKetinggian air: {$tinggi} cm (pukul {$waktu}).";
        sendTelegramMessage($msg);
        $conn->query("UPDATE notify_status SET value='$status_now', updated_at='$waktu', last_notif_sent_at=NOW(), is_fresh_boot=0 WHERE id=1");
    }
}
?>
