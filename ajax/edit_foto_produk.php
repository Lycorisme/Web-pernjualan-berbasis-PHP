<?php
// ajax/edit_foto_produk.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';
require_once __DIR__ . '/../system/upload_handler.php';

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
    // Ambil data barang existing
    $sql = "SELECT foto_produk FROM barang WHERE id = ? AND supplier_id IS NULL";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param("i", $barang_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Barang tidak ditemukan atau bukan milik admin.');
    }

    $barang = $result->fetch_assoc();
    $foto_lama = $barang['foto_produk'];

    // Validasi file upload
    if (!isset($_FILES['foto_produk']) || $_FILES['foto_produk']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('Tidak ada file yang diupload.');
    }

    // Handle upload foto baru
    $uploadResult = handleProductPhotoUpload(
        $_FILES['foto_produk'], 
        __DIR__ . '/../uploads/produk/', 
        $foto_lama // Foto lama akan diganti/dihapus
    );
    
    if (isset($uploadResult['error'])) {
        throw new Exception($uploadResult['error']);
    }

    $foto_baru = $uploadResult['success'];

    // Update database dengan foto baru
    $update_stmt = $koneksi->prepare("UPDATE barang SET foto_produk = ? WHERE id = ? AND supplier_id IS NULL");
    $update_stmt->bind_param("si", $foto_baru, $barang_id);
    
    if (!$update_stmt->execute()) {
        // Jika gagal update database, hapus foto baru yang sudah diupload
        $path_foto_baru = __DIR__ . '/../uploads/produk/' . $foto_baru;
        if (is_file($path_foto_baru)) {
            unlink($path_foto_baru);
        }
        throw new Exception('Gagal memperbarui database.');
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Foto produk berhasil diperbarui.',
        'new_filename' => $foto_baru
    ]);

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>