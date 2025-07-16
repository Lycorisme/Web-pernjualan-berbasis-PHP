<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Pastikan hanya supplier yang login bisa akses

$supplier_id = $_SESSION['supplier_id'];

// PERUBAHAN: Klausa "OR supplier_id IS NULL" dihapus.
// Query sekarang HANYA mengambil kategori yang dimiliki oleh supplier ini.
$stmt = $koneksi->prepare("
    SELECT id, nama_kategori, supplier_id 
    FROM kategori 
    WHERE supplier_id = ?
    ORDER BY nama_kategori ASC
");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

echo json_encode($categories);
?>