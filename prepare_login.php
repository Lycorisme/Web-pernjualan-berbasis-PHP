<?php
require_once __DIR__ . '/config/koneksi.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_GET['supplier_id']) || !is_numeric($_GET['supplier_id'])) {
    die("Akses tidak valid.");
}

$supplier_id = (int)$_GET['supplier_id'];

$stmt = $koneksi->prepare("SELECT password FROM supplier WHERE id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $supplier = $result->fetch_assoc();
    $_SESSION['prefilled_password'] = $supplier['password'];
}

// PERBAIKAN: Memastikan data sesi tersimpan sepenuhnya sebelum beralih halaman.
session_write_close();

header("Location: login.php");
exit();
?>