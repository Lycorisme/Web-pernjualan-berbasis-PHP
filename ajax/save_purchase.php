<?php
header('Content-Type: application/json');
ob_start();
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
cekAdmin();

$data = json_decode(file_get_contents('php://input'), true);

// --- TAMBAHKAN BARIS INI UNTUK LOG DATA YANG DITERIMA ---
error_log("DEBUG save_purchase.php - Data diterima: " . json_encode($data));

if (!$data || !isset($data['items']) || empty($data['items']) || !isset($data['total'])) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data pembelian tidak lengkap atau format JSON salah.']);
    exit();
}

$koneksi->begin_transaction();

try {
    $stmt_pembelian = $koneksi->prepare(
        "INSERT INTO pembelian (no_pembelian, tanggal, supplier_id, user_id, total, status) VALUES (?, ?, ?, ?, ?, 'Belum Lunas')"
    );
    if ($stmt_pembelian === false) {
        throw new Exception("Gagal mempersiapkan query pembelian: " . $koneksi->error);
    }

    $stmt_pembelian->bind_param(
        "ssiid",
        $data['no_pembelian'],
        $data['tanggal'],
        $data['supplier_id'],
        $data['user_id'],
        $data['total']
    );

    if (!$stmt_pembelian->execute()) {
        throw new Exception("Gagal menyimpan data pembelian utama: " . $stmt_pembelian->error);
    }

    $pembelian_id = $koneksi->insert_id;
    if ($pembelian_id === 0) {
        throw new Exception("Gagal mendapatkan ID pembelian baru.");
    }

    $stmt_detail = $koneksi->prepare(
        "INSERT INTO pembelian_detail (pembelian_id, barang_id, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt_detail === false) {
        throw new Exception("Gagal mempersiapkan query detail pembelian: " . $koneksi->error);
    }

    $stmt_update_stok = $koneksi->prepare(
        "UPDATE barang SET stok = stok - ? WHERE id = ?"
    );
    if ($stmt_update_stok === false) {
        throw new Exception("Gagal mempersiapkan query update stok: " . $koneksi->error);
    }

    $stmt_check_stok = $koneksi->prepare(
        "SELECT stok, nama_barang FROM barang WHERE id = ?"
    );
    if ($stmt_check_stok === false) {
        throw new Exception("Gagal mempersiapkan query check stok: " . $koneksi->error);
    }

    foreach ($data['items'] as $item) {
        // --- TAMBAHKAN BARIS INI UNTUK LOG SETIAP ITEM ---
        error_log("DEBUG save_purchase.php - Memproses item ID: " . $item['id'] . ", Kuantitas dari keranjang: " . $item['qty'] . ", Harga: " . $item['price']);

        // Validasi stok sebelum melakukan pembelian
        // --- Lakukan casting eksplisit untuk memastikan tipe data ---
        $barang_id_from_cart = (int)$item['id'];
        $jumlah_from_cart = (int)$item['qty'];
        $harga_from_cart = (float)$item['price'];

        $stmt_check_stok->bind_param("i", $barang_id_from_cart);
        $stmt_check_stok->execute();
        $result = $stmt_check_stok->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Barang dengan ID {$barang_id_from_cart} tidak ditemukan di database supplier.");
        }

        $barang_data = $result->fetch_assoc();

        // --- TAMBAHKAN BARIS INI UNTUK LOG STOK SEBELUM UPDATE ---
        error_log("DEBUG save_purchase.php - Stok awal supplier untuk barang {$barang_data['nama_barang']} (ID: {$barang_id_from_cart}): {$barang_data['stok']}");

        if ($barang_data['stok'] < $jumlah_from_cart) {
            throw new Exception("Stok barang '{$barang_data['nama_barang']}' tidak mencukupi untuk pembelian. Stok tersedia: {$barang_data['stok']}, diminta: {$jumlah_from_cart}.");
        }

        $subtotal = $jumlah_from_cart * $harga_from_cart;

        $stmt_detail->bind_param(
            "iiidd",
            $pembelian_id,
            $barang_id_from_cart,
            $jumlah_from_cart,
            $harga_from_cart,
            $subtotal
        );
        if (!$stmt_detail->execute()) {
            throw new Exception("Gagal menyimpan detail barang (ID: {$barang_id_from_cart}): " . $stmt_detail->error);
        }

        $stmt_update_stok->bind_param(
            "ii",
            $jumlah_from_cart, // Gunakan kuantitas yang sudah di-cast
            $barang_id_from_cart // Gunakan ID yang sudah di-cast
        );
        if (!$stmt_update_stok->execute()) {
            throw new Exception("Gagal mengupdate stok barang (ID: {$barang_id_from_cart}): " . $stmt_update_stok->error);
        }
         // --- TAMBAHKAN BARIS INI UNTUK LOG STOK SETELAH UPDATE ---
        // Re-fetch stok setelah update untuk konfirmasi
        $stmt_check_stok_after_update = $koneksi->prepare("SELECT stok FROM barang WHERE id = ?");
        $stmt_check_stok_after_update->bind_param("i", $barang_id_from_cart);
        $stmt_check_stok_after_update->execute();
        $stok_after_update = $stmt_check_stok_after_update->get_result()->fetch_assoc()['stok'];
        error_log("DEBUG save_purchase.php - Stok akhir supplier untuk barang {$barang_data['nama_barang']} (ID: {$barang_id_from_cart}): {$stok_after_update}");
    }

    $koneksi->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Data pembelian berhasil disimpan!']);

} catch (Exception $e) {
    $koneksi->rollback();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($stmt_pembelian) && $stmt_pembelian) $stmt_pembelian->close();
if (isset($stmt_detail) && $stmt_detail) $stmt_detail->close();
if (isset($stmt_update_stok) && $stmt_update_stok) $stmt_update_stok->close();
if (isset($stmt_check_stok) && $stmt_check_stok) $stmt_check_stok->close();
$koneksi->close();
exit();
?>