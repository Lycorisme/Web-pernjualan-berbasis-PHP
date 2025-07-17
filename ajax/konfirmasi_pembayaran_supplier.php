<?php
// FILE: ajax/konfirmasi_pembayaran_supplier.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekSupplier(); // Hanya supplier yang bisa konfirmasi

$pembelian_id = isset($_POST['pembelian_id']) ? (int)$_POST['pembelian_id'] : 0;
$supplier_id = $_SESSION['supplier_id'];

if ($pembelian_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'ID Pembelian tidak valid.']));
}

$koneksi->begin_transaction();

try {
    // 1. Update status pembelian menjadi 'Lunas'
    $stmt_update_status = $koneksi->prepare("UPDATE pembelian SET status = 'Lunas' WHERE id = ? AND supplier_id = ?");
    $stmt_update_status->bind_param("ii", $pembelian_id, $supplier_id);
    
    if (!$stmt_update_status->execute() || $stmt_update_status->affected_rows === 0) {
        throw new Exception('Gagal mengonfirmasi atau pembelian tidak ditemukan.');
    }

    // 2. Ambil semua item dari detail pembelian, TERMASUK ORIGINAL BARANG ID DARI SUPPLIER
    //    Kita perlu ID barang yang dibeli (purchased_barang_id) untuk mengupdate baris yang tepat di tabel 'barang'
    $stmt_get_items = $koneksi->prepare(
        "SELECT pd.jumlah, pd.harga, pd.barang_id AS purchased_barang_id, b.kode_barang, b.nama_barang, b.kategori_id, b.satuan_id, b.harga_jual, b.foto_produk 
         FROM pembelian_detail pd
         JOIN barang b ON pd.barang_id = b.id
         WHERE pd.pembelian_id = ?"
    );
    $stmt_get_items->bind_param("i", $pembelian_id);
    $stmt_get_items->execute();
    $items_result = $stmt_get_items->get_result();

    // 3. Loop setiap item untuk memperbarui atau mentransfer kepemilikannya ke admin
    while ($item = $items_result->fetch_assoc()) {
        $purchased_barang_id = (int)$item['purchased_barang_id']; // Ini adalah ID dari baris item di tabel 'barang' yang dibeli dari supplier.
        $kode_barang = $item['kode_barang'];
        $nama_barang = $item['nama_barang'];
        $jumlah_dibeli = (int)$item['jumlah'];
        $harga_beli_item = (float)$item['harga'];
        $harga_jual_item = (float)$item['harga_jual'];
        $kategori_id_item = (int)$item['kategori_id'];
        $satuan_id_item = (int)$item['satuan_id'];
        $foto_produk_item = $item['foto_produk'];

        // Langkah A: Temukan entri master barang berdasarkan kode_barang (UNIQUE KEY menjamin hanya ada satu)
        $stmt_find_master_item = $koneksi->prepare("SELECT id, supplier_id, stok FROM barang WHERE kode_barang = ?");
        $stmt_find_master_item->bind_param("s", $kode_barang);
        $stmt_find_master_item->execute();
        $master_item_result = $stmt_find_master_item->get_result();
        $master_item_data = $master_item_result->fetch_assoc();

        if (!$master_item_data) {
            // Ini seharusnya tidak terjadi jika kode_barang adalah UNIQUE KEY dan item ini sudah ada karena dibeli.
            throw new Exception("Error fatal: Barang dengan kode '{$kode_barang}' tidak ditemukan di katalog master (meskipun seharusnya ada).");
        }

        $current_master_item_id = (int)$master_item_data['id'];
        $current_master_item_supplier_id = $master_item_data['supplier_id']; // NULL jika admin, ID jika supplier
        $current_master_item_stok = (int)$master_item_data['stok'];

        // Langkah B: Perbarui kepemilikan dan stok item master.
        // Stok item ini (`$current_master_item_id`) sudah dikurangi oleh `save_purchase.php` (dari sisi supplier).
        // Sekarang, kuantitas yang dibeli ($jumlah_dibeli) perlu ditambahkan ke stok utama admin.

        if ($current_master_item_supplier_id === NULL) {
            // Kasus 1: Barang sudah menjadi milik Admin (supplier_id IS NULL).
            // Cukup tambahkan kuantitas yang baru dibeli ke stoknya, dan perbarui harga/foto.
            $update_sql = "UPDATE barang SET stok = stok + ?, harga_beli = ?, harga_jual = ?, foto_produk = ? WHERE id = ?";
            $stmt_update = $koneksi->prepare($update_sql);
            $stmt_update->bind_param(
                "iddsi", // int stok_add, double harga_beli, double harga_jual, string foto_produk, int id
                $jumlah_dibeli,
                $harga_beli_item,
                $harga_jual_item,
                $foto_produk_item,
                $current_master_item_id
            );
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal mengupdate stok barang admin yang sudah ada (ID: {$current_master_item_id}): " . $stmt_update->error);
            }
        } else {
            // Kasus 2: Barang saat ini dimiliki oleh SUPPLIER (supplier_id IS NOT NULL).
            // Ini berarti kita perlu MENTRANSFER kepemilikan ke Admin (set supplier_id = NULL).
            // Jumlah dibeli ($jumlah_dibeli) adalah jumlah yang diterima admin.
            // Stok total admin untuk barang ini akan menjadi stok yang tersisa dari supplier + jumlah yang baru dibeli.
            
            $update_sql = "UPDATE barang SET supplier_id = NULL, stok = stok + ?, harga_beli = ?, harga_jual = ?, foto_produk = ? WHERE id = ?";
            $stmt_update = $koneksi->prepare($update_sql);
            $stmt_update->bind_param(
                "iddsi", // int stok_add, double harga_beli, double harga_jual, string foto_produk, int id
                $jumlah_dibeli,
                $harga_beli_item,
                $harga_jual_item,
                $foto_produk_item,
                $current_master_item_id
            );
            if (!$stmt_update->execute()) {
                throw new Exception("Gagal mentransfer kepemilikan dan mengupdate stok barang (ID: {$current_master_item_id}): " . $stmt_update->error);
            }
        }
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran telah dikonfirmasi dan stok barang admin telah diperbarui!']);

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in konfirmasi_pembayaran_supplier.php: " . $e->getMessage()); 
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>