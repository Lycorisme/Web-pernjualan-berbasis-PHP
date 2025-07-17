<?php
// FILE: ajax/get_order_items.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekLogin(); // Bisa diakses Admin atau Supplier

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pesanan tidak valid.']);
    exit;
}

try {
    $query = "
        SELECT 
            oi.kode_barang,
            oi.nama_barang,
            oi.quantity,
            oi.price_per_item,
            oi.subtotal_item_price
        FROM order_items oi
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id ASC
    ";
    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        throw new Exception("Gagal mempersiapkan query: " . $koneksi->error);
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    echo json_encode(['success' => true, 'items' => $items]);

} catch (Exception $e) {
    error_log("Error in ajax/get_order_items.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    $koneksi->close();
}
?>
