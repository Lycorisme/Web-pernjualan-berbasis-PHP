<?php
// FILE: ajax/get_products.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');

$search = isset($_GET['search']) ? '%' . trim($_GET['search']) . '%' : '';

// PERBAIKAN: Tambahkan "WHERE supplier_id IS NULL" untuk hanya mengambil barang inventaris utama
if (!empty(trim($search, '%'))) {
    $query = "SELECT * FROM barang WHERE supplier_id IS NULL AND (nama_barang LIKE ? OR kode_barang LIKE ?)";
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("ss", $search, $search);
} else {
    $query = "SELECT * FROM barang WHERE supplier_id IS NULL";
    $stmt = $koneksi->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

echo json_encode($products);
?>