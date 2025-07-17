<?php
// FILE: ajax/confirm_shipment.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Hanya supplier yang bisa konfirmasi pengiriman

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pesanan tidak valid.']);
    exit;
}

$koneksi->begin_transaction();

try {
    // Ambil detail pesanan untuk validasi dan email
    $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, o.total_order_price, o.payment_type, o.buyer_address, o.receiving_warehouse, u.email AS admin_email, oc.payment_terms_description, oc.payment_due_date FROM orders o JOIN users u ON o.admin_user_id = u.id LEFT JOIN order_contracts oc ON o.order_id = oc.order_id WHERE o.order_id = ? AND o.supplier_id = ?");
    $stmt_order->bind_param("ii", $order_id, $_SESSION['supplier_id']);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
    }
    // Status yang diizinkan untuk konfirmasi pengiriman
    $allowed_statuses = ['Kontrak Diunggah', 'Menunggu Pembayaran', 'Lunas'];
    if (!in_array($order['order_status'], $allowed_statuses)) {
        throw new Exception('Pesanan ini tidak dalam status yang dapat dikonfirmasi pengirimannya.');
    }

    // Update status pesanan menjadi 'Di Antar'
    $stmt_update_status = $koneksi->prepare("UPDATE orders SET order_status = 'Di Antar' WHERE order_id = ?");
    $stmt_update_status->bind_param("i", $order_id);
    if (!$stmt_update_status->execute()) {
        throw new Exception('Gagal memperbarui status pesanan.');
    }

    // Kirim email notifikasi ke admin
    send_order_shipment_email_to_admin(
        $order['admin_email'],
        $order['order_no'],
        $order['buyer_address'],
        $order['receiving_warehouse'],
        $order['payment_type'],
        $order['payment_due_date'], // Bisa null jika tunai
        $order['total_order_price'],
        $order['payment_terms_description'] // Ketentuan pembayaran dari kontrak
    );

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Pengiriman pesanan berhasil dikonfirmasi. Notifikasi telah dikirim ke admin.']);

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in ajax/confirm_shipment.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_update_status)) $stmt_update_status->close();
    $koneksi->close();
}
