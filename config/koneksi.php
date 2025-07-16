<?php
// Memuat file konfigurasi utama yang berisi semua kredensial
require_once __DIR__ . '/config.php';

// Memulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

try {
    // Menggunakan konstanta dari config.php untuk membuat koneksi database
    $koneksi = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Memeriksa koneksi
    if ($koneksi->connect_error) {
        // Jika database tidak ditemukan, coba buat database (berguna untuk setup awal)
        $temp_koneksi = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD);
        if (!$temp_koneksi->connect_error) {
            $create_db = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`";
            if ($temp_koneksi->query($create_db)) {
                $temp_koneksi->close();
                // Koneksi ulang ke database yang baru dibuat
                $koneksi = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
            }
        }
        // Jika masih error setelah mencoba membuat DB, hentikan proses
        if ($koneksi->connect_error) {
            die("Connection failed: " . $koneksi->connect_error);
        }
    }

    // Atur set karakter ke utf8
    $koneksi->set_charset("utf8");

} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Atur zona waktu default
date_default_timezone_set('Asia/Jakarta');
?>