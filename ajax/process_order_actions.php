<?php
// FILE: ajax/process_order_actions.php
// INI ADALAH FILE YANG DIPERBAIKI
// Mengkonsolidasi semua aksi terkait pesanan

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';
require_once __DIR__ . '/../system/upload_handler.php';

header('Content-Type: application/json');

// Mulai output buffering untuk menangkap error tak terduga
ob_start();

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? ($_SESSION['supplier_id'] ?? null);

$action = $_POST['action'] ?? '';
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0 || empty($action)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid: ID Pesanan atau Aksi tidak ditemukan.']);
    exit;
}

$koneksi->begin_transaction();

try {
    switch ($action) {
        case 'accept_order':
            if ($role !== 'supplier') throw new Exception('Akses ditolak: Hanya supplier yang dapat menerima pesanan.');

            $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, o.payment_type, u.email AS admin_email FROM orders o JOIN users u ON o.admin_user_id = u.id WHERE o.order_id = ? AND o.supplier_id = ?");
            $stmt_order->bind_param("ii", $order_id, $user_id);
            $stmt_order->execute();
            $order = $stmt_order->get_result()->fetch_assoc();

            if (!$order) throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
            if ($order['order_status'] !== 'Di Pesan') throw new Exception('Pesanan ini sudah tidak dalam status "Di Pesan".');

            // Ambil data dari form modal
            $supplier_company_name_contract = sanitize($_POST['supplier_company_name_contract']);
            $supplier_pic_name_contract = sanitize($_POST['supplier_pic_name_contract']);
            $supplier_contact_contract = sanitize($_POST['supplier_contact_contract']);
            $amount_to_pay = (float)($_POST['amount_to_pay']);
            $payment_terms_description = sanitize($_POST['payment_terms_description']);
            $payment_due_date = empty($_POST['payment_due_date']) ? null : sanitize($_POST['payment_due_date']);

            // Validasi input
            if (empty($supplier_company_name_contract) || empty($supplier_pic_name_contract) || empty($supplier_contact_contract) || empty($amount_to_pay) || empty($payment_terms_description)) {
                throw new Exception('Semua detail serah terima wajib diisi.');
            }
            if ($order['payment_type'] === 'kredit' && (empty($payment_due_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_due_date))) {
                throw new Exception('Format Tanggal Jatuh Tempo tidak valid atau kosong untuk pembayaran Kredit.');
            }

            // Update status pesanan
            $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Diterima Supplier' WHERE order_id = ?");
            $stmt_update_order_status->bind_param("i", $order_id);
            if (!$stmt_update_order_status->execute()) throw new Exception('Gagal memperbarui status pesanan.');

            // Simpan detail kontrak ke tabel order_contracts
            $stmt_insert_contract = $koneksi->prepare(
                "INSERT INTO order_contracts (order_id, supplier_company_name_contract, supplier_pic_name_contract, supplier_contact_contract, payment_due_date, amount_to_pay, payment_terms_description) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            // FIX: Mengubah tipe data 'payment_due_date' dari 'i' (integer) menjadi 's' (string)
            $stmt_insert_contract->bind_param(
                "issssds",
                $order_id,
                $supplier_company_name_contract,
                $supplier_pic_name_contract,
                $supplier_contact_contract,
                $payment_due_date,
                $amount_to_pay,
                $payment_terms_description
            );
            if (!$stmt_insert_contract->execute()) throw new Exception('Gagal menyimpan detail kontrak: ' . $stmt_insert_contract->error);

            // Kirim email
            send_order_accepted_with_contract_email_to_admin(
                $order['admin_email'],
                $_POST['order_no'],
                $supplier_company_name_contract,
                $amount_to_pay,
                $payment_terms_description,
                $payment_due_date,
                BASE_URL . 'orders.php'
            );
            $message = 'Pesanan berhasil diterima dan detail kontrak disimpan. Notifikasi telah dikirim ke admin.';
            break;

        case 'reject_order':
            if ($role !== 'supplier') throw new Exception('Akses ditolak: Hanya supplier yang dapat menolak pesanan.');

            $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, u.email AS admin_email, s.nama_perusahaan AS supplier_company_name FROM orders o JOIN users u ON o.admin_user_id = u.id JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ? AND o.supplier_id = ?");
            $stmt_order->bind_param("ii", $order_id, $user_id);
            $stmt_order->execute();
            $order = $stmt_order->get_result()->fetch_assoc();

            if (!$order) throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
            if ($order['order_status'] !== 'Di Pesan') throw new Exception('Pesanan ini tidak dapat ditolak.');

            $stmt_update_status = $koneksi->prepare("UPDATE orders SET order_status = 'Ditolak Supplier' WHERE order_id = ?");
            $stmt_update_status->bind_param("i", $order_id);
            if (!$stmt_update_status->execute()) throw new Exception('Gagal memperbarui status pesanan.');

            send_order_status_update_email_to_admin(
                $order['admin_email'],
                $order['order_no'],
                $order['supplier_company_name'],
                'Ditolak Supplier'
            );
            $message = 'Pesanan berhasil ditolak. Notifikasi telah dikirim ke admin.';
            break;

        case 'upload_contract':
            if ($role !== 'admin') throw new Exception('Akses ditolak: Hanya admin yang dapat mengunggah kontrak.');

            $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, s.nama_perusahaan AS supplier_company_name, s.email AS supplier_email FROM orders o JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ?");
            $stmt_order->bind_param("i", $order_id);
            $stmt_order->execute();
            $order = $stmt_order->get_result()->fetch_assoc();

            if (!$order) throw new Exception('Pesanan tidak ditemukan.');
            if ($order['order_status'] !== 'Diterima Supplier') throw new Exception('Kontrak hanya bisa diunggah untuk pesanan berstatus "Diterima Supplier".');

            if (!isset($_FILES['contract_file']) || $_FILES['contract_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File kontrak tidak ditemukan atau terjadi error upload.');
            }

            $upload_dir = __DIR__ . '/../uploads/contracts/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $uploadResult = handleProductPhotoUpload(
                $_FILES['contract_file'],
                $upload_dir,
                null,
                ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'],
                5 * 1024 * 1024
            );

            if (isset($uploadResult['error'])) throw new Exception($uploadResult['error']);
            $contract_file_path = $uploadResult['success'];

            $stmt_update_contract = $koneksi->prepare("UPDATE order_contracts SET admin_contract_file_path = ? WHERE order_id = ?");
            $stmt_update_contract->bind_param("si", $contract_file_path, $order_id);
            if (!$stmt_update_contract->execute()) throw new Exception('Gagal menyimpan path kontrak.');

            $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Kontrak Diunggah' WHERE order_id = ?");
            $stmt_update_order_status->bind_param("i", $order_id);
            if (!$stmt_update_order_status->execute()) throw new Exception('Gagal memperbarui status pesanan.');

            send_contract_uploaded_email_to_supplier(
                $order['supplier_email'],
                $order['supplier_company_name'],
                $order['order_no'],
                BASE_URL . 'orders.php'
            );
            $message = 'Kontrak berhasil diunggah. Notifikasi telah dikirim ke supplier.';
            break;

        case 'confirm_shipment':
            if ($role !== 'supplier') throw new Exception('Akses ditolak: Hanya supplier yang dapat mengkonfirmasi pengiriman.');

            $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, u.email AS admin_email FROM orders o JOIN users u ON o.admin_user_id = u.id WHERE o.order_id = ? AND o.supplier_id = ?");
            $stmt_order->bind_param("ii", $order_id, $user_id);
            $stmt_order->execute();
            $order = $stmt_order->get_result()->fetch_assoc();

            if (!$order) throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
            $allowed_statuses = ['Kontrak Diunggah', 'Menunggu Pembayaran', 'Lunas'];
            if (!in_array($order['order_status'], $allowed_statuses)) throw new Exception('Status pesanan tidak memungkinkan untuk pengiriman.');

            $stmt_update_status = $koneksi->prepare("UPDATE orders SET order_status = 'Di Antar' WHERE order_id = ?");
            $stmt_update_status->bind_param("i", $order_id);
            if (!$stmt_update_status->execute()) throw new Exception('Gagal memperbarui status pesanan.');

            send_order_shipment_email_to_admin(
                $order['admin_email'],
                $_POST['order_no'],
                $_POST['admin_address'],
                $_POST['receiving_warehouse'],
                $_POST['payment_type'],
                $_POST['payment_due_date'],
                $_POST['total_order_price'],
                $_POST['payment_terms_description']
            );
            $message = 'Pengiriman pesanan berhasil dikonfirmasi. Notifikasi telah dikirim ke admin.';
            break;

        case 'confirm_receipt':
            if ($role !== 'admin') throw new Exception('Akses ditolak: Hanya admin yang dapat mengkonfirmasi penerimaan.');

            $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, s.email AS supplier_email, s.nama_perusahaan AS supplier_company_name FROM orders o JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ?");
            $stmt_order->bind_param("i", $order_id);
            $stmt_order->execute();
            $order = $stmt_order->get_result()->fetch_assoc();

            if (!$order) throw new Exception('Pesanan tidak ditemukan.');
            if ($order['order_status'] !== 'Di Antar') throw new Exception('Pesanan ini tidak dalam status "Di Antar".');

            $stmt_order_items = $koneksi->prepare("SELECT oi.quantity, oi.kode_barang, oi.nama_barang, oi.price_per_item, b.kategori_id, b.satuan_id, b.harga_jual, b.foto_produk FROM order_items oi JOIN barang b ON oi.barang_id_supplier_original = b.id WHERE oi.order_id = ?");
            $stmt_order_items->bind_param("i", $order_id);
            $stmt_order_items->execute();
            $order_items_result = $stmt_order_items->get_result();

            while ($item = $order_items_result->fetch_assoc()) {
                $stmt_check_admin_item = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ? AND supplier_id IS NULL");
                $stmt_check_admin_item->bind_param("s", $item['kode_barang']);
                $stmt_check_admin_item->execute();
                $admin_item_exists = $stmt_check_admin_item->get_result()->fetch_assoc();

                if ($admin_item_exists) {
                    $stmt_update_existing = $koneksi->prepare("UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE id = ?");
                    $stmt_update_existing->bind_param("idi", $item['quantity'], $item['price_per_item'], $admin_item_exists['id']);
                    if (!$stmt_update_existing->execute()) throw new Exception("Gagal update stok: " . $stmt_update_existing->error);
                } else {
                    $stmt_insert_new_item = $koneksi->prepare(
                        "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id, foto_produk) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)"
                    );
                    $stmt_insert_new_item->bind_param("ssiiddis", $item['kode_barang'], $item['nama_barang'], $item['kategori_id'], $item['satuan_id'], $item['price_per_item'], $item['harga_jual'], $item['quantity'], $item['foto_produk']);
                    if (!$stmt_insert_new_item->execute()) throw new Exception("Gagal menambah barang baru: " . $stmt_insert_new_item->error);
                }
            }

            $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Selesai' WHERE order_id = ?");
            $stmt_update_order_status->bind_param("i", $order_id);
            if (!$stmt_update_order_status->execute()) throw new Exception('Gagal memperbarui status pesanan menjadi Selesai.');

            send_order_received_email_to_supplier(
                $order['supplier_email'],
                $order['supplier_company_name'],
                $order['order_no'],
                $_SESSION['nama_lengkap']
            );
            $message = 'Penerimaan barang berhasil dikonfirmasi dan stok diperbarui.';
            break;

        default:
            throw new Exception('Aksi tidak dikenal.');
    }

    $koneksi->commit();
    $output = ob_get_clean();
    if (!empty($output)) { throw new Exception("Terjadi output tak terduga: " . $output); }
    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $koneksi->rollback();
    $error_message = $e->getMessage();
    $output = ob_get_clean(); // Bersihkan buffer jika ada output sebelum error
    http_response_code(500);
    error_log("Error in ajax/process_order_actions.php (Action: {$action}): " . $error_message . " | Output Buffer: " . $output);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $error_message]);
} finally {
    // Pastikan semua statement ditutup
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_update_order_status)) $stmt_update_order_status->close();
    if (isset($stmt_insert_contract)) $stmt_insert_contract->close();
    if (isset($stmt_update_status)) $stmt_update_status->close();
    if (isset($stmt_update_contract)) $stmt_update_contract->close();
    if (isset($stmt_order_items)) $stmt_order_items->close();
    if (isset($stmt_check_admin_item)) $stmt_check_admin_item->close();
    if (isset($stmt_update_existing)) $stmt_update_existing->close();
    if (isset($stmt_insert_new_item)) $stmt_insert_new_item->close();
    $koneksi->close();
}
?>