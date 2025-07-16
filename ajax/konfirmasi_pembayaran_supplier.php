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

    // 2. Ambil semua item dari detail pembelian, TERMASUK SEMUA DETAIL DARI TABEL BARANG SUPPLIER
    $stmt_get_items = $koneksi->prepare(
        "SELECT pd.jumlah, pd.harga, b.kode_barang, b.nama_barang, b.kategori_id, b.satuan_id, b.harga_jual, b.foto_produk 
         FROM pembelian_detail pd
         JOIN barang b ON pd.barang_id = b.id
         WHERE pd.pembelian_id = ?"
    );
    $stmt_get_items->bind_param("i", $pembelian_id);
    $stmt_get_items->execute();
    $items_result = $stmt_get_items->get_result();

    // 3. Loop setiap item untuk ditambahkan ke inventaris utama (admin)
    while ($item = $items_result->fetch_assoc()) {
        $kode_barang_dari_supplier = $item['kode_barang'];
        $nama_barang_dari_supplier = $item['nama_barang'];
        $jumlah_dibeli = $item['jumlah'];
        $harga_beli_item = $item['harga']; // Harga beli saat pembelian
        $harga_jual_item = $item['harga_jual']; // Harga jual dari supplier
        $kategori_id_item = $item['kategori_id'];
        $satuan_id_item = $item['satuan_id'];
        $foto_produk_item = $item['foto_produk'];

        // A. Cek apakah barang dengan `kode_barang` ini sudah ada di tabel `barang` (GLOBAL CHECK)
        $stmt_check_existing_item = $koneksi->prepare("SELECT id, supplier_id FROM barang WHERE kode_barang = ?");
        $stmt_check_existing_item->bind_param("s", $kode_barang_dari_supplier);
        $stmt_check_existing_item->execute();
        $existing_item = $stmt_check_existing_item->get_result()->fetch_assoc();

        if ($existing_item) {
            // Barang sudah ada di tabel `barang` (bisa milik admin atau supplier lain)
            $existing_item_id = $existing_item['id'];
            
            // Perbarui item yang ada: tambahkan stok, update harga beli, harga jual, foto, dan set supplier_id ke NULL (milik admin)
            $update_query = "UPDATE barang SET stok = stok + ?, harga_beli = ?, harga_jual = ?, foto_produk = ?, supplier_id = NULL WHERE id = ?";
            $stmt_update_existing = $koneksi->prepare($update_query);
            $stmt_update_existing->bind_param(
                "iddsi", 
                $jumlah_dibeli, 
                $harga_beli_item, 
                $harga_jual_item, 
                $foto_produk_item, 
                $existing_item_id
            );
            if (!$stmt_update_existing->execute()) {
                throw new Exception("Gagal mengupdate barang yang sudah ada (ID: {$existing_item_id}): " . $stmt_update_existing->error);
            }
        } else {
            // Barang belum ada di tabel `barang` secara global, maka INSERT sebagai barang baru milik admin
            $stmt_insert_new_item = $koneksi->prepare(
                "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id, foto_produk) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)"
            );
            // Parameter: s, s, i, i, d, d, i, s
            $stmt_insert_new_item->bind_param(
                "ssiiddis", 
                $kode_barang_dari_supplier, 
                $nama_barang_dari_supplier, 
                $kategori_id_item, 
                $satuan_id_item, 
                $harga_beli_item, 
                $harga_jual_item, 
                $jumlah_dibeli, 
                $foto_produk_item
            );
            if (!$stmt_insert_new_item->execute()) {
                throw new Exception("Gagal menambahkan barang baru: " . $nama_barang_dari_supplier . " - " . $stmt_insert_new_item->error);
            }
        }
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran telah dikonfirmasi dan stok barang admin telah diperbarui!']);

} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in konfirmasi_pembayaran_supplier.php: " . $e->getMessage()); // Tambahkan logging
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>