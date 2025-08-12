<?php
session_start();
include '../config.php';
date_default_timezone_set('Asia/Jakarta');
$now = date('Y-m-d H:i:s');

// Ambil parameter hari, default 30 hari
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;

// Validasi rentang hari
if ($days < 1 || $days > 365) {
    die("Rentang hari tidak valid. Harus antara 1 sampai 365 hari.");
}

// Proses arsip data
$conn->begin_transaction();

try {
    // Arsipkan ke tabel archive (pastikan kolom 'status' juga disertakan)
    $conn->query("
        INSERT INTO data_air_archive (tinggi, status, waktu) 
        SELECT tinggi, status, waktu FROM data_air 
        WHERE waktu < NOW() - INTERVAL $days DAY
    ");

    // Hapus dari tabel utama
    $conn->query("
        DELETE FROM data_air 
        WHERE waktu < NOW() - INTERVAL $days DAY
    ");

    // Catat ke log
    $log_user = isset($_SESSION['username']) ? ucfirst(strtolower($_SESSION['username'])) : 'SYSTEM';
    $log_msg = "Arsipkan data lama (> $days hari)";
    $conn->query("INSERT INTO user_logs (user, activity, log_time) VALUES ('$log_user', '$log_msg', '$now')");

    $conn->commit();

    // Redirect jika akses dari browser
    if (isset($_SESSION['username'])) {
        header("Location: dashboard.php?message=arsip_sukses");
        exit;
    } else {
        echo "✅ Data lebih dari $days hari berhasil diarsipkan.";
    }
} catch (Exception $e) {
    $conn->rollback();
    echo "❌ Gagal mengarsipkan data: " . $e->getMessage();
}
?>
