<?php
define('TELEGRAM_TOKEN', '8347890114:AAFkjPoz7yuW5uHh5RbinMfjydGjboiGllg'); // Ganti dengan token bot kamu
define('TELEGRAM_CHAT_ID', '6181039632'); // Ganti dengan chat ID kamu

function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
    $post = [
        'chat_id' => TELEGRAM_CHAT_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];

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
?>
