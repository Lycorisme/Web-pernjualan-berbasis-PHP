<?php
// FILE: ajax/confirm_receipt.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekAdmin(); // Hanya admin yang bisa konfirmasi penerimaan

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pesanan tidak valid.']);
    exit;
}

$koneksi->begin_transaction();

try {
    // Ambil detail pesanan dan item-itemnya
    $stmt_order = $koneksi->prepare("SELECT o.order_no, o.order_status, o.supplier_id, s.nama_perusahaan AS supplier_company_name, s.email AS supplier_email FROM orders o JOIN supplier s ON o.supplier_id = s.id WHERE o.order_id = ? AND o.admin_user_id = ?");
    $stmt_order->bind_param("ii", $order_id, $_SESSION['user_id']);
    $stmt_order->execute();
    $order = $stmt_order->get_result()->fetch_assoc();

    if (!$order) {
        throw new Exception('Pesanan tidak ditemukan atau bukan milik Anda.');
    }
    if ($order['order_status'] !== 'Di Antar') {
        throw new Exception('Pesanan ini tidak dalam status "Di Antar" dan tidak dapat dikonfirmasi penerimaannya.');
    }

    // Ambil semua item dari pesanan
    $stmt_order_items = $koneksi->prepare("SELECT oi.quantity, oi.kode_barang, oi.nama_barang, oi.price_per_item, b.kategori_id, b.satuan_id, b.harga_jual, b.foto_produk FROM order_items oi JOIN barang b ON oi.barang_id_supplier_original = b.id WHERE oi.order_id = ?");
    $stmt_order_items->bind_param("i", $order_id);
    $stmt_order_items->execute();
    $order_items = $stmt_order_items->get_result();

    if ($order_items->num_rows === 0) {
        throw new Exception("Tidak ada item dalam pesanan ini untuk diperbarui stok.");
    }

    // Loop setiap item untuk memperbarui stok barang admin
    while ($item = $order_items->fetch_assoc()) {
        $quantity_received = (int)$item['quantity'];
        $kode_barang = $item['kode_barang'];
        $nama_barang = $item['nama_barang'];
        $harga_beli_item = (float)$item['price_per_item'];
        $harga_jual_item = (float)$item['harga_jual']; // Ambil harga jual dari item supplier
        $kategori_id_item = (int)$item['kategori_id'];
        $satuan_id_item = (int)$item['satuan_id'];
        $foto_produk_item = $item['foto_produk'];

        // Cari item di tabel `barang` yang dimiliki admin (supplier_id IS NULL)
        $stmt_check_admin_item = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ? AND supplier_id IS NULL");
        $stmt_check_admin_item->bind_param("s", $kode_barang);
        $stmt_check_admin_item->execute();
        $admin_item_exists = $stmt_check_admin_item->get_result()->fetch_assoc();

        if ($admin_item_exists) {
            // Barang sudah ada dan milik admin, update stoknya
            $admin_item_id = (int)$admin_item_exists['id'];
            $update_sql = "UPDATE barang SET stok = stok + ?, harga_beli = ?, harga_jual = ?, foto_produk = ? WHERE id = ?";
            $stmt_update_existing = $koneksi->prepare($update_sql);
            $stmt_update_existing->bind_param(
                "iddsi", 
                $quantity_received, 
                $harga_beli_item, 
                $harga_jual_item, 
                $foto_produk_item, 
                $admin_item_id
            );
            if (!$stmt_update_existing->execute()) {
                throw new Exception("Gagal mengupdate stok barang admin (ID: {$admin_item_id}): " . $stmt_update_existing->error);
            }
        } else {
            // Barang dengan kode_barang ini TIDAK ditemukan sebagai milik admin, maka INSERT sebagai barang baru milik admin
            $stmt_insert_new_item = $koneksi->prepare(
                "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id, foto_produk) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)"
            );
            $stmt_insert_new_item->bind_param(
                "ssiiddis", 
                $kode_barang, 
                $nama_barang, 
                $kategori_id_item, 
                $satuan_id_item, 
                $harga_beli_item, 
                $harga_jual_item, 
                $quantity_received, 
                $foto_produk_item
            );
            if (!$stmt_insert_new_item->execute()) {
                throw new Exception("Gagal menambahkan barang baru ke stok admin: " . $nama_barang . " - " . $stmt_insert_new_item->error);
            }
        }
    }

    // Update status pesanan menjadi 'Selesai'
    $stmt_update_order_status = $koneksi->prepare("UPDATE orders SET order_status = 'Selesai' WHERE order_id = ?");
    $stmt_update_order_status->bind_param("i", $order_id);
    if (!$stmt_update_order_status->execute()) {
        throw new Exception('Gagal memperbarui status pesanan menjadi Selesai.');
    }

    // Kirim email notifikasi ke supplier bahwa barang sudah diterima admin
    send_order_received_email_to_supplier(
        $order['supplier_email'],
        $order['supplier_company_name'],
        $order['order_no'],
        $_SESSION['nama_lengkap'] // Nama admin yang mengkonfirmasi
    );

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Penerimaan barang berhasil dikonfirmasi dan stok admin diperbarui! Notifikasi telah dikirim ke supplier.']);

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in ajax/confirm_receipt.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
} finally {
    if (isset($stmt_order)) $stmt_order->close();
    if (isset($stmt_order_items)) $stmt_order_items->close();
    if (isset($stmt_check_admin_item)) $stmt_check_admin_item->close();
    if (isset($stmt_update_existing)) $stmt_update_existing->close();
    if (isset($stmt_insert_new_item)) $stmt_insert_new_item->close();
    if (isset($stmt_update_order_status)) $stmt_update_order_status->close();
    $koneksi->close();
}
