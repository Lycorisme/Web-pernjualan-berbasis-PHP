<?php
// FILE: ajax/get_items_from_purchase.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekAdmin(); // Proteksi halaman

$pembelian_id = isset($_GET['pembelian_id']) ? intval($_GET['pembelian_id']) : 0;

if ($pembelian_id === 0) {
    echo json_encode([]);
    exit;
}

// Ambil item barang dari detail pembelian yang dipilih
$query = $koneksi->prepare(
    "SELECT pd.barang_id, b.nama_barang, pd.jumlah
     FROM pembelian_detail pd
     JOIN barang b ON pd.barang_id = b.id
     WHERE pd.pembelian_id = ?"
);
$query->bind_param("i", $pembelian_id);
$query->execute();
$result = $query->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

header('Content-Type: application/json');
echo json_encode($items);
?>