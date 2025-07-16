<?php
// FILE: ajax/get_purchases_for_retur.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekAdmin(); // Proteksi halaman

$supplier_id = isset($_GET['supplier_id']) ? intval($_GET['supplier_id']) : 0;

if ($supplier_id === 0) {
    echo json_encode([]);
    exit;
}

// Ambil pembelian yang statusnya sudah 'Lunas' dari supplier yang dipilih
$query = $koneksi->prepare(
    "SELECT id, no_pembelian, tanggal 
     FROM pembelian 
     WHERE supplier_id = ? AND status = 'Lunas' 
     ORDER BY tanggal DESC"
);
$query->bind_param("i", $supplier_id);
$query->execute();
$result = $query->get_result();

$purchases = [];
while ($row = $result->fetch_assoc()) {
    $row['tanggal'] = formatTanggal($row['tanggal']); // Menggunakan helper untuk format tanggal
    $purchases[] = $row;
}

header('Content-Type: application/json');
echo json_encode($purchases);
?>