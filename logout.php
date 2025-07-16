<?php
// FILE: logout.php

// Mulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi
session_unset();

// Hancurkan sesi
session_destroy();

// Alihkan ke halaman login setelah sesi dihancurkan
header("Location: login.php");
exit();
?><?php
// FILE: logout.php

// Mulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua variabel sesi
session_unset();

// Hancurkan sesi
session_destroy();

// Alihkan ke halaman login setelah sesi dihancurkan
header("Location: login.php");
exit();
?>