<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Hanya supplier yang bisa edit

$supplier_id = $_SESSION['supplier_id'];

// Validasi input
if (!isset($_POST['id']) || !is_numeric($_POST['id']) || !isset($_POST['nama']) || empty(trim($_POST['nama']))) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap.']);
    exit();
}

$kategori_id = (int)$_POST['id'];
$nama_kategori_baru = sanitize($_POST['nama']);

// Update kategori di database, pastikan hanya kategori milik supplier ini yang bisa diubah
$stmt = $koneksi->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ? AND supplier_id = ?");
$stmt->bind_param("sii", $nama_kategori_baru, $kategori_id, $supplier_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Nama kategori berhasil diperbarui!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tidak ada perubahan atau kategori tidak ditemukan.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui kategori.']);
}

exit();     