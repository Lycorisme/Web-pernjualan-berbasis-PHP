<?php
// FILE: ajax/reject_order.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Hanya supplier yang bisa menolak pesanan

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pesanan tidak valid.']);
    exit;
}

$koneksi->begin_transaction();

try {
    // Ambil detail pesanan untuk validasi dan email
    $stmt_order = $koneksi->prepare("SELECT o.order_no, o.admin_user_id, o.order_status, u.email AS admin_email, s.nama_perusahaan AS supplier_company_name FROM orders o JOIN users u ON o.admin_user_id = u.id JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ? AND o.supplier_id = ?");
    $stmt_order->bind_param("ii", $order_id, $_SESSION['supplier_id']);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
    }
    if ($order['order_status'] !== 'Di Pesan') {
        throw new Exception('Pesanan ini sudah tidak dalam status "Di Pesan" dan tidak dapat ditolak.');
    }

    // Update status pesanan menjadi 'Ditolak Supplier'
    $stmt_update_status = $koneksi->prepare("UPDATE orders SET order_status = 'Ditolak Supplier' WHERE order_id = ?");
    $stmt_update_status->bind_param("i", $order_id);
    if (!$stmt_update_status->execute()) {
        throw new Exception('Gagal memperbarui status pesanan.');
    }

    // Kirim email notifikasi ke admin
    send_order_status_update_email_to_admin(
        $order['admin_email'],
        $order['order_no'],
        $order['supplier_company_name'],
        'Ditolak Supplier'
    );

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Pesanan berhasil ditolak. Notifikasi telah dikirim ke admin.']);

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in ajax/reject_order.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_update_status)) $stmt_update_status->close();
    $koneksi->close();
}
