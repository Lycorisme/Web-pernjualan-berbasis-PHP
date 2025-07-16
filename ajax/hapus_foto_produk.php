<?php
// ajax/hapus_foto_produk.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');

// Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

// Pastikan user sudah login sebagai admin
cekAdmin();

// Validasi input
if (!isset($_POST['barang_id']) || !is_numeric($_POST['barang_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'ID barang tidak valid.']);
    exit;
}

$barang_id = (int)$_POST['barang_id'];

try {
    // Ambil nama file foto dari database
    // PERBAIKAN: Pastikan hanya barang milik admin yang bisa dihapus fotonya
    $sql = "SELECT foto_produk FROM barang WHERE id = ? AND supplier_id IS NULL";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("i", $barang_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Barang tidak ditemukan atau bukan milik admin.');
    }

    $barang = $result->fetch_assoc();
    $filename = $barang['foto_produk'];

    if (empty($filename)) {
        throw new Exception('Produk ini tidak memiliki foto.');
    }

    // Update database terlebih dahulu
    $update_stmt = $koneksi->prepare("UPDATE barang SET foto_produk = NULL WHERE id = ? AND supplier_id IS NULL");
    $update_stmt->bind_param("i", $barang_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('Gagal memperbarui database.');
    }

    // Jika database berhasil diupdate, hapus file fisik
    $filePath = __DIR__ . '/../uploads/produk/' . $filename;
    if (is_file($filePath)) {
        if (!unlink($filePath)) {
            // Jika gagal hapus file, log error tapi tetap return success
            error_log("Gagal menghapus file: " . $filePath);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Foto produk berhasil dihapus.']);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>