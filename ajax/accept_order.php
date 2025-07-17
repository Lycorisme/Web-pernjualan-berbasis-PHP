<?php
// FILE: ajax/accept_order.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Hanya supplier yang bisa menerima pesanan

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pesanan tidak valid.']);
    exit;
}

$koneksi->begin_transaction();

try {
    // Ambil detail pesanan untuk validasi dan email
    $stmt_order = $koneksi->prepare("SELECT o.order_no, o.admin_user_id, o.order_status, o.total_order_price, o.payment_type, u.email AS admin_email, s.nama_perusahaan AS supplier_company_name, s.nama_supplier AS supplier_pic_name, s.telepon AS supplier_contact_number FROM orders o JOIN users u ON o.admin_user_id = u.id JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ? AND o.supplier_id = ?");
    $stmt_order->bind_param("ii", $order_id, $_SESSION['supplier_id']);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
    }
    if ($order['order_status'] !== 'Di Pesan') {
        throw new Exception('Pesanan ini sudah tidak dalam status "Di Pesan" dan tidak dapat diterima.');
    }

    // Cek apakah ini adalah permintaan penerimaan awal atau penyimpanan detail kontrak
    if (isset($_POST['supplier_company_name_contract'])) {
        // Ini adalah langkah penyimpanan detail kontrak setelah supplier menerima pesanan (untuk tunai)
        $supplier_company_name_contract = sanitize($_POST['supplier_company_name_contract']);
        $supplier_pic_name_contract = sanitize($_POST['supplier_pic_name_contract']);
        $supplier_contact_contract = sanitize($_POST['supplier_contact_contract']);
        $amount_to_pay = (float)($_POST['amount_to_pay']); // Sudah di-parse di JS
        $payment_terms_description = sanitize($_POST['payment_terms_description']);
        $payment_due_date = empty($_POST['payment_due_date']) ? null : sanitize($_POST['payment_due_date']);

        // Validasi input detail kontrak
        if (empty($supplier_company_name_contract) || empty($supplier_pic_name_contract) || empty($supplier_contact_contract) || empty($amount_to_pay) || empty($payment_terms_description)) {
            throw new Exception('Semua detail serah terima wajib diisi.');
        }
        if ($order['payment_type'] === 'kredit' && empty($payment_due_date)) {
            throw new Exception('Tanggal Jatuh Tempo wajib diisi untuk pembayaran Kredit.');
        }

        // Update status pesanan menjadi 'Diterima Supplier'
        $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Diterima Supplier' WHERE order_id = ?");
        $stmt_update_order_status->bind_param("i", $order_id);
        if (!$stmt_update_order_status->execute()) {
            throw new Exception('Gagal memperbarui status pesanan.');
        }

        // Simpan detail kontrak ke tabel order_contracts
        $stmt_insert_contract = $koneksi->prepare(
            "INSERT INTO order_contracts (order_id, supplier_company_name_contract, supplier_pic_name_contract, supplier_contact_contract, payment_due_date, amount_to_pay, payment_terms_description) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt_insert_contract->bind_param(
            "isssids",
            $order_id,
            $supplier_company_name_contract,
            $supplier_pic_name_contract,
            $supplier_contact_contract,
            $payment_due_date,
            $amount_to_pay,
            $payment_terms_description
        );
        if (!$stmt_insert_contract->execute()) {
            throw new Exception('Gagal menyimpan detail kontrak: ' . $stmt_insert_contract->error);
        }

        // Kirim email notifikasi ke admin (pesanan diterima + detail kontrak)
        send_order_accepted_with_contract_email_to_admin(
            $order['admin_email'],
            $order['order_no'],
            $supplier_company_name_contract,
            $amount_to_pay,
            $payment_terms_description,
            $payment_due_date,
            BASE_URL . 'orders.php' // Link ke halaman orders admin
        );

        $koneksi->commit();
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil diterima dan detail kontrak disimpan. Notifikasi telah dikirim ke admin.']);

    } else {
        // Ini adalah permintaan penerimaan awal (jika payment_type adalah kredit)
        // Update status pesanan menjadi 'Diterima Supplier'
        $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Diterima Supplier' WHERE order_id = ?");
        $stmt_update_order_status->bind_param("i", $order_id);
        if (!$stmt_update_order_status->execute()) {
            throw new Exception('Gagal memperbarui status pesanan.');
        }

        // Simpan detail kontrak awal (placeholder) untuk pesanan kredit
        // Admin akan mengupload bukti transfer, jadi tidak ada detail kontrak dari supplier di awal
        $stmt_insert_contract_placeholder = $koneksi->prepare(
            "INSERT INTO order_contracts (order_id, supplier_company_name_contract, supplier_pic_name_contract, supplier_contact_contract, amount_to_pay, payment_terms_description) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $default_terms = "Pembayaran via transfer bank.";
        $stmt_insert_contract_placeholder->bind_param(
            "isssds",
            $order_id,
            $order['supplier_company_name'],
            $order['supplier_pic_name'],
            $order['supplier_contact_number'],
            $order['total_order_price'],
            $default_terms
        );
        if (!$stmt_insert_contract_placeholder->execute()) {
            throw new Exception('Gagal menyimpan detail kontrak placeholder: ' . $stmt_insert_contract_placeholder->error);
        }


        // Kirim email notifikasi ke admin (pesanan diterima)
        send_order_status_update_email_to_admin(
            $order['admin_email'],
            $order['order_no'],
            $order['supplier_company_name'],
            'Diterima Supplier'
        );

        $koneksi->commit();
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil diterima. Notifikasi telah dikirim ke admin.']);
    }

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in ajax/accept_order.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_update_order_status)) $stmt_update_order_status->close();
    if (isset($stmt_insert_contract)) $stmt_insert_contract->close();
    if (isset($stmt_insert_contract_placeholder)) $stmt_insert_contract_placeholder->close();
    $koneksi->close();
}
