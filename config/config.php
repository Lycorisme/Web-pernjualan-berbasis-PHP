<?php
// FILE: config.php

// 1. PENGATURAN KONEKSI DATABASE
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root'); // Ganti dengan username database Anda
define('DB_PASSWORD', ''); // Ganti dengan password database Anda
define('DB_NAME', 'database_platinum_komputer_sql'); // Ganti dengan nama database Anda

// 2. PENGATURAN URL DASAR APLIKASI
// (Biasanya tidak perlu diubah jika aplikasi berada di root folder proyek)
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';
define('BASE_URL', $base_url);

// 3. PENGATURAN SMTP UNTUK PENGIRIMAN EMAIL
// (Ganti dengan kredensial SMTP Anda, contoh menggunakan GMail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'blazeee0230@gmail.com'); // Ganti dengan alamat email GMail Anda
define('SMTP_PASSWORD', 'kuai wqlo yblq rlxr'); // Ganti dengan "App Password" GMail Anda, BUKAN password login biasa
define('SMTP_PORT', 465); // Port untuk SMTPS (SSL)
define('SMTP_SECURE', 'ssl'); // atau 'tls' jika menggunakan port 587
define('SMTP_FROM_EMAIL', 'blazeee0230@gmail.com'); // Email pengirim (biasanya sama dengan SMTP_USERNAME)
define('SMTP_FROM_NAME', 'Platinum Komputer'); // Nama pengirim yang akan muncul di email

// 4. PENGATURAN EMAIL ADMIN
// (Email ini akan menerima notifikasi dari supplier)
define('ADMIN_EMAIL', 'blazeee0230@gmail.com'); // Ganti dengan email admin Anda
?>