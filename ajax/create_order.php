<?php
// FILE: ajax/create_order.php

// Mengatur header sebagai JSON di baris paling atas
header('Content-Type: application/json');

// Memulai output buffering untuk menangkap semua output tak terduga (termasuk error)
ob_start();

// Memuat file-file inti
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Memulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Memastikan hanya admin yang bisa melakukan aksi ini
cekAdmin();

// Mengambil data JSON yang dikirim dari JavaScript
$data = json_decode(file_get_contents('php://input'), true);

// --- LOGGING UNTUK DEBUGGING ---
error_log("DEBUG create_order.php - Data diterima: " . json_encode($data));

// Validasi data dasar yang diterima
if (!$data || !isset($data['items']) || empty($data['items']) || !isset($data['total_order_price'])) {
    ob_end_clean(); // Bersihkan buffer sebelum mengirim JSON error
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data pesanan tidak lengkap atau format JSON salah.']);
    exit();
}

// Validasi input wajib
$required_fields = ['order_no', 'order_date', 'admin_user_id', 'supplier_id', 'total_order_price', 'buyer_name', 'buyer_address', 'buyer_contact', 'receiving_warehouse', 'payment_type'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty(trim($data[$field]))) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => "Field '{$field}' tidak boleh kosong."]);
        exit();
    }
}

$koneksi->begin_transaction();

try {
    // 1. Simpan data utama ke tabel 'orders'
    $stmt_order = $koneksi->prepare(
        "INSERT INTO orders (order_no, order_date, admin_user_id, supplier_id, total_order_price, buyer_name, buyer_address, buyer_contact, receiving_warehouse, payment_type, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Di Pesan')"
    );
    if ($stmt_order === false) {
        throw new Exception("Gagal mempersiapkan query order: " . $koneksi->error);
    }

    $stmt_order->bind_param(
        "ssiidsssss",
        $data['order_no'],
        $data['order_date'],
        $data['admin_user_id'],
        $data['supplier_id'],
        $data['total_order_price'],
        $data['buyer_name'],
        $data['buyer_address'],
        $data['buyer_contact'],
        $data['receiving_warehouse'],
        $data['payment_type']
    );

    if (!$stmt_order->execute()) {
        throw new Exception("Gagal menyimpan data pesanan utama: " . $stmt_order->error);
    }
    
    $order_id = $koneksi->insert_id;
    if ($order_id === 0) {
        throw new Exception("Gagal mendapatkan ID pesanan baru.");
    }

    // Siapkan statement untuk menyimpan detail pesanan dan mengupdate stok supplier
    $stmt_order_item = $koneksi->prepare(
        "INSERT INTO order_items (order_id, barang_id_supplier_original, kode_barang, nama_barang, quantity, price_per_item, subtotal_item_price) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if ($stmt_order_item === false) {
        throw new Exception("Gagal mempersiapkan query detail pesanan: " . $koneksi->error);
    }
    
    $stmt_update_supplier_stok = $koneksi->prepare(
        "UPDATE barang SET stok = stok - ? WHERE id = ?"
    );
    if ($stmt_update_supplier_stok === false) {
        throw new Exception("Gagal mempersiapkan query update stok supplier: " . $koneksi->error);
    }

    $stmt_check_supplier_stok = $koneksi->prepare(
        "SELECT stok, nama_barang FROM barang WHERE id = ? AND supplier_id = ?"
    );
    if ($stmt_check_supplier_stok === false) {
        throw new Exception("Gagal mempersiapkan query check stok supplier: " . $koneksi->error);
    }

    // 2. Loop setiap barang di keranjang untuk disimpan ke 'order_items' dan mengurangi stok supplier
    foreach ($data['items'] as $item) {
        // Casting eksplisit untuk memastikan tipe data
        $barang_id_supplier_original = (int)$item['barang_id_supplier_original'];
        $kode_barang = $item['kode_barang'];
        $nama_barang = $item['nama_barang'];
        $quantity = (int)$item['quantity'];
        $price_per_item = (float)$item['price_per_item'];
        $subtotal_item_price = (float)$item['subtotal_item_price'];

        // --- LOGGING ITEM YANG SEDANG DIPROSES ---
        error_log("DEBUG create_order.php - Memproses item: ID {$barang_id_supplier_original}, Kode {$kode_barang}, Qty {$quantity}");

        // Validasi stok supplier sebelum mengurangi
        $stmt_check_supplier_stok->bind_param("ii", $barang_id_supplier_original, $data['supplier_id']);
        $stmt_check_supplier_stok->execute();
        $stok_result = $stmt_check_supplier_stok->get_result();
        
        if ($stok_result->num_rows === 0) {
            throw new Exception("Barang dengan ID {$barang_id_supplier_original} tidak ditemukan pada supplier ini.");
        }
        
        $supplier_barang_data = $stok_result->fetch_assoc();
        
        // --- LOGGING STOK AWAL SUPPLIER ---
        error_log("DEBUG create_order.php - Stok awal supplier untuk {$supplier_barang_data['nama_barang']} (ID: {$barang_id_supplier_original}): {$supplier_barang_data['stok']}");

        if ($supplier_barang_data['stok'] < $quantity) {
            throw new Exception("Stok barang '{$supplier_barang_data['nama_barang']}' tidak mencukupi. Stok tersedia: {$supplier_barang_data['stok']}, diminta: {$quantity}");
        }

        // Simpan ke 'order_items'
        $stmt_order_item->bind_param(
            "iissidd",
            $order_id,
            $barang_id_supplier_original,
            $kode_barang,
            $nama_barang,
            $quantity,
            $price_per_item,
            $subtotal_item_price
        );
        if (!$stmt_order_item->execute()) {
            throw new Exception("Gagal menyimpan detail pesanan untuk barang '{$nama_barang}': " . $stmt_order_item->error);
        }

        // Kurangi stok supplier di tabel 'barang'
        $stmt_update_supplier_stok->bind_param(
            "ii",
            $quantity,
            $barang_id_supplier_original
        );
        if (!$stmt_update_supplier_stok->execute()) {
            throw new Exception("Gagal mengurangi stok supplier untuk barang '{$nama_barang}': " . $stmt_update_supplier_stok->error);
        }

        // --- LOGGING STOK AKHIR SUPPLIER (opsional, bisa re-fetch stok seperti di save_purchase.php sebelumnya) ---
        // Untuk tujuan debugging ini, log langsung dari operasi update
        error_log("DEBUG create_order.php - Stok supplier untuk {$supplier_barang_data['nama_barang']} (ID: {$barang_id_supplier_original}) berhasil dikurangi sebesar {$quantity}.");
    }
    
    // Kirim email notifikasi ke supplier
    $supplier_info = $koneksi->query("SELECT nama_supplier, email FROM supplier WHERE id = {$data['supplier_id']}")->fetch_assoc();
    if ($supplier_info) {
        $loginLink = BASE_URL . 'prepare_login.php?supplier_id=' . $data['supplier_id']; // Gunakan ID supplier untuk login
        send_new_order_email_to_supplier($supplier_info['email'], $supplier_info['nama_supplier'], $data['order_no'], $data['buyer_name'], $data['total_order_price'], $loginLink);
    }

    $koneksi->commit();

    // Bersihkan buffer sebelum mengirim JSON sukses
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibuat dan notifikasi telah dikirim ke supplier!', 'order_id' => $order_id]);

} catch (Exception $e) {
    // Jika terjadi error di salah satu langkah, batalkan semua perubahan
    $koneksi->rollback();

    // Bersihkan buffer sebelum mengirim JSON error
    ob_end_clean();
    http_response_code(500); // Set status code error server
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Tutup statement dan koneksi
if (isset($stmt_order) && $stmt_order) $stmt_order->close();
if (isset($stmt_order_item) && $stmt_order_item) $stmt_order_item->close();
if (isset($stmt_update_supplier_stok) && $stmt_update_supplier_stok) $stmt_update_supplier_stok->close();
if (isset($stmt_check_supplier_stok) && $stmt_check_supplier_stok) $stmt_check_supplier_stok->close();
$koneksi->close();

exit();
?>