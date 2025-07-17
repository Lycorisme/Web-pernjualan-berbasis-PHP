<?php
// FILE: ajax/upload_contract.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';
require_once __DIR__ . '/../system/upload_handler.php'; // Re-use upload handler

header('Content-Type: application/json');
cekAdmin(); // Hanya admin yang bisa mengunggah kontrak

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pesanan tidak valid.']);
    exit;
}

// Validasi file upload
if (!isset($_FILES['contract_file']) || $_FILES['contract_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File kontrak tidak ditemukan atau terjadi error upload.']);
    exit;
}

$koneksi->begin_transaction();

try {
    // Ambil info pesanan untuk validasi dan email
    $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, s.nama_perusahaan AS supplier_company_name, s.email AS supplier_email FROM orders o JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ? AND o.admin_user_id = ?");
    $stmt_order->bind_param("ii", $order_id, $_SESSION['user_id']);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
    }
    if ($order['order_status'] !== 'Diterima Supplier') {
        throw new Exception('Kontrak hanya bisa diunggah untuk pesanan dengan status "Diterima Supplier".');
    }

    // Handle upload file kontrak
    $upload_dir = __DIR__ . '/../uploads/contracts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Gunakan upload_handler.php, sesuaikan untuk file kontrak
    // Fungsi handleProductPhotoUpload bisa diadaptasi atau buat fungsi baru di upload_handler.php
    // Untuk saat ini, saya akan mengadaptasi handleProductPhotoUpload untuk kontrak
    $uploadResult = handleProductPhotoUpload(
        $_FILES['contract_file'],
        $upload_dir,
        null, // Tidak ada file lama untuk dihapus saat upload kontrak baru
        ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'], // Tipe file yang diizinkan
        5 * 1024 * 1024 // Maks 5MB
    );

    if (isset($uploadResult['error'])) {
        throw new Exception($uploadResult['error']);
    }
    $contract_file_path = $uploadResult['success'];

    // Update path kontrak dan status di tabel order_contracts
    $stmt_update_contract = $koneksi->prepare("UPDATE order_contracts SET admin_contract_file_path = ? WHERE order_id = ?");
    $stmt_update_contract->bind_param("si", $contract_file_path, $order_id);
    if (!$stmt_update_contract->execute()) {
        throw new Exception('Gagal menyimpan path kontrak ke database.');
    }

    // Update status pesanan menjadi 'Kontrak Diunggah' di tabel orders
    $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Kontrak Diunggah' WHERE order_id = ?");
    $stmt_update_order_status->bind_param("i", $order_id);
    if (!$stmt_update_order_status->execute()) {
        throw new Exception('Gagal memperbarui status pesanan.');
    }

    // Kirim email notifikasi ke supplier
    send_contract_uploaded_email_to_supplier(
        $order['supplier_email'],
        $order['supplier_company_name'],
        $order['order_no'],
        BASE_URL . 'orders.php' // Link ke halaman orders supplier
    );

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Kontrak berhasil diunggah dan status pesanan diperbarui. Notifikasi telah dikirim ke supplier.']);

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in ajax/upload_contract.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_update_contract)) $stmt_update_contract->close();
    if (isset($stmt_update_order_status)) $stmt_update_order_status->close();
    $koneksi->close();
}
