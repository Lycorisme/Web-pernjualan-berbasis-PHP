<?php
// FILE: ajax/get_product.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if (empty($search)) {
    echo json_encode(['error' => 'Search parameter is required']);
    exit;
}

// PERBAIKAN: Tambahkan "AND supplier_id IS NULL"
$query = "SELECT * FROM barang WHERE kode_barang = ? AND supplier_id IS NULL";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // PERBAIKAN: Tambahkan "AND supplier_id IS NULL"
    $query = "SELECT * FROM barang WHERE nama_barang LIKE ? AND supplier_id IS NULL LIMIT 1";
    $search_term = '%' . $search . '%';
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();
}

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    echo json_encode($product);
} else {
    echo json_encode(['error' => 'Product not found']);
}
?>