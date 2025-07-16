<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Hanya supplier yang bisa hapus

$supplier_id = $_SESSION['supplier_id'];

// Validasi input
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID Kategori tidak valid.']);
    exit();
}

$kategori_id = (int)$_POST['id'];

// PENTING: Cek dulu apakah kategori ini sedang digunakan oleh barang
$stmt_check = $koneksi->prepare("SELECT COUNT(*) as count FROM barang WHERE kategori_id = ?");
$stmt_check->bind_param("i", $kategori_id);
$stmt_check->execute();
$count = $stmt_check->get_result()->fetch_assoc()['count'];

if ($count > 0) {
    echo json_encode(['success' => false, 'message' => "Kategori tidak dapat dihapus karena sedang digunakan oleh {$count} barang."]);
    exit();
}

// Jika tidak digunakan, lanjutkan proses hapus
$stmt_delete = $koneksi->prepare("DELETE FROM kategori WHERE id = ? AND supplier_id = ?");
$stmt_delete->bind_param("ii", $kategori_id, $supplier_id);

if ($stmt_delete->execute()) {
    if ($stmt_delete->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Kategori berhasil dihapus!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus atau kategori tidak ditemukan.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mengeksekusi perintah hapus.']);
}

exit();