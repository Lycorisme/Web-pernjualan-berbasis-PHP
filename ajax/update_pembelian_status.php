<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekAdmin(); // Hanya admin yang bisa mengubah status

// Validasi input
if (!isset($_POST['pembelian_id']) || !is_numeric($_POST['pembelian_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID Pembelian tidak valid.']);
    exit();
}

$pembelian_id = (int)$_POST['pembelian_id'];

// Update status di database
$stmt = $koneksi->prepare("UPDATE pembelian SET status = 'Lunas' WHERE id = ?");
$stmt->bind_param("i", $pembelian_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status pembelian berhasil diubah menjadi Lunas!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status pembelian.']);
}

exit();
?>