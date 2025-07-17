<?php
// FILE: ajax/get_order_details.php

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
            o.order_id, o.order_no, o.order_date, o.total_order_price, o.order_status,
            s.nama_perusahaan AS supplier_name, s.email AS supplier_email, s.nama_supplier AS supplier_pic_name, s.telepon AS supplier_contact,
            u.nama_lengkap AS admin_name, u.email AS admin_email, u.alamat AS admin_address, u.telepon AS admin_contact,
            o.payment_type, o.buyer_name, o.buyer_address, o.buyer_contact, o.receiving_warehouse
        FROM orders o
        JOIN supplier s ON o.supplier_id = s.id
        JOIN users u ON o.admin_user_id = u.id
        WHERE o.order_id = ?
    ";
    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        throw new Exception("Gagal mempersiapkan query: " . $koneksi->error);
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan.');
    }

    echo json_encode(['success' => true, 'order' => $order]);

} catch (Exception $e) {
    error_log("Error in ajax/get_order_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    $koneksi->close();
}
?>
