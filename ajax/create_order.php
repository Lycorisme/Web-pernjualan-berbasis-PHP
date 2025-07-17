<?php
// FILE: ajax/create_order.php (Final - Tanpa Batasan Stok & Penanganan Error JSON)

// Mulai output buffering untuk menangkap semua output tak terduga
ob_start();

// Selalu atur header JSON di bagian paling atas
header('Content-Type: application/json');

try {
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

    // Validasi data dasar yang diterima
    if (!$data || !isset($data['items']) || empty($data['items']) || !isset($data['total_order_price'])) {
        throw new Exception('Data pesanan tidak lengkap atau format JSON salah.');
    }

    // Validasi input wajib
    $required_fields = ['order_no', 'order_date', 'admin_user_id', 'supplier_id', 'total_order_price', 'buyer_name', 'buyer_address', 'buyer_contact', 'receiving_warehouse', 'payment_type'];
    foreach ($required_fields as $field) {
        if (empty(trim($data[$field]))) {
            throw new Exception("Field wajib '{$field}' tidak boleh kosong.");
        }
    }

    $koneksi->begin_transaction();

    // 1. Simpan data utama ke tabel 'orders'
    $stmt_order = $koneksi->prepare(
        "INSERT INTO orders (order_no, order_date, admin_user_id, supplier_id, total_order_price, buyer_name, buyer_address, buyer_contact, receiving_warehouse, payment_type, order_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Di Pesan')"
    );
    if ($stmt_order === false) throw new Exception("Gagal mempersiapkan query order: " . $koneksi->error);
    
    $stmt_order->bind_param("ssiidsssss", $data['order_no'], $data['order_date'], $data['admin_user_id'], $data['supplier_id'], $data['total_order_price'], $data['buyer_name'], $data['buyer_address'], $data['buyer_contact'], $data['receiving_warehouse'], $data['payment_type']);
    if (!$stmt_order->execute()) throw new Exception("Gagal menyimpan data pesanan utama: " . $stmt_order->error);
    
    $order_id = $koneksi->insert_id;
    if ($order_id === 0) throw new Exception("Gagal mendapatkan ID pesanan baru.");

    // Siapkan statement untuk menyimpan detail dan update stok
    $stmt_order_item = $koneksi->prepare("INSERT INTO order_items (order_id, barang_id_supplier_original, kode_barang, nama_barang, quantity, price_per_item, subtotal_item_price) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_update_stock = $koneksi->prepare("UPDATE barang SET stok = stok - ? WHERE id = ?");

    if ($stmt_order_item === false || $stmt_update_stock === false) {
        throw new Exception("Gagal mempersiapkan query untuk item atau update stok: " . $koneksi->error);
    }

    // 2. Loop setiap barang, kurangi stok, dan simpan detail
    foreach ($data['items'] as $item) {
        $barang_id_supplier = (int)$item['barang_id_supplier_original'];
        $quantity = (int)$item['quantity'];
        
        // --- PERBAIKAN: Pengecekan stok dihapus sesuai permintaan ---
        // Logika ini mengizinkan stok menjadi negatif.
        // Supplier akan bertanggung jawab untuk menolak pesanan jika stok tidak dapat dipenuhi.

        // Langsung kurangi stok
        $stmt_update_stock->bind_param("ii", $quantity, $barang_id_supplier);
        if (!$stmt_update_stock->execute()) {
            throw new Exception("Gagal mengurangi stok untuk barang '" . htmlspecialchars($item['nama_barang']) . "': " . $stmt_update_stock->error);
        }

        // Hitung subtotal di backend untuk keamanan
        $subtotal = (float)$item['price_per_item'] * $quantity;

        // Simpan ke 'order_items'
        $stmt_order_item->bind_param("iissidd", $order_id, $barang_id_supplier, $item['kode_barang'], $item['nama_barang'], $quantity, $item['price_per_item'], $subtotal);
        if (!$stmt_order_item->execute()) {
            throw new Exception("Gagal menyimpan detail pesanan untuk barang '" . htmlspecialchars($item['nama_barang']) . "': " . $stmt_order_item->error);
        }
    }
    
    // Kirim email notifikasi ke supplier
    $supplier_info = $koneksi->query("SELECT nama_supplier, email FROM supplier WHERE id = " . (int)$data['supplier_id'])->fetch_assoc();
    if ($supplier_info) {
        $orderLink = BASE_URL . 'orders.php';
        send_new_order_email_to_supplier($supplier_info['email'], $supplier_info['nama_supplier'], $data['order_no'], $data['buyer_name'], $data['total_order_price'], $orderLink);
    }

    $koneksi->commit();

    // Bersihkan buffer sebelum mengirim JSON yang berhasil
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibuat dan notifikasi telah dikirim ke supplier!', 'order_id' => $order_id]);

} catch (Throwable $e) { // Menangkap semua jenis error
    // Jika ada transaksi yang berjalan, batalkan
    if ($koneksi->errno) {
        $koneksi->rollback();
    }
    
    // Catat error ke log server untuk debugging
    error_log("ERROR in create_order.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    // Bersihkan buffer dari semua output error HTML
    ob_end_clean();

    // Kirim respons JSON yang bersih dan valid
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}

// Tutup statement yang sudah disiapkan
if (isset($stmt_order) && $stmt_order) $stmt_order->close();
if (isset($stmt_order_item) && $stmt_order_item) $stmt_order_item->close();
if (isset($stmt_update_stock)) $stmt_update_stock->close();
$koneksi->close();

?>
