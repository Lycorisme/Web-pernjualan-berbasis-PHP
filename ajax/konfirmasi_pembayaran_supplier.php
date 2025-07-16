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

    // 2. Ambil semua item dari detail pembelian, TERMASUK FOTO PRODUK
    $stmt_get_items = $koneksi->prepare(
        "SELECT pd.jumlah, pd.harga, b.nama_barang, b.kategori_id, b.satuan_id, b.foto_produk 
         FROM pembelian_detail pd
         JOIN barang b ON pd.barang_id = b.id
         WHERE pd.pembelian_id = ?"
    );
    $stmt_get_items->bind_param("i", $pembelian_id);
    $stmt_get_items->execute();
    $items_result = $stmt_get_items->get_result();

    // Siapkan statement untuk mengecek, mengupdate, dan menginsert barang utama
    $stmt_check_main_item = $koneksi->prepare("SELECT id FROM barang WHERE nama_barang = ? AND supplier_id IS NULL");
    $stmt_update_stock = $koneksi->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
    
    // PERBAIKAN: Tambahkan 'foto_produk' ke dalam query INSERT
    $stmt_insert_new_item = $koneksi->prepare(
        "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, foto_produk) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    // 3. Loop setiap item untuk ditambahkan ke inventaris utama
    while ($item = $items_result->fetch_assoc()) {
        $nama_barang = $item['nama_barang'];
        $jumlah_dibeli = $item['jumlah'];
        
        $stmt_check_main_item->bind_param("s", $nama_barang);
        $stmt_check_main_item->execute();
        $main_item_result = $stmt_check_main_item->get_result();

        if ($main_item_result->num_rows > 0) {
            // Jika barang sudah ada, cukup update stoknya
            $main_item = $main_item_result->fetch_assoc();
            $stmt_update_stock->bind_param("ii", $jumlah_dibeli, $main_item['id']);
            if (!$stmt_update_stock->execute()) {
                throw new Exception("Gagal mengupdate stok untuk barang: " . $nama_barang);
            }
        } else {
            // Jika barang belum ada, tambahkan sebagai produk baru
            $kode_barang_baru = generateKodeBarang();
            $harga_beli = $item['harga'];
            $harga_jual = $item['harga']; // Harga jual sementara disamakan
            $kategori_id = $item['kategori_id'];
            $satuan_id = $item['satuan_id'];
            $foto_produk = $item['foto_produk']; // Ambil nama file foto

            // PERBAIKAN: Tambahkan 's' untuk foto_produk (string) dan variabelnya
            $stmt_insert_new_item->bind_param(
                "ssiiddis", 
                $kode_barang_baru, 
                $nama_barang, 
                $kategori_id, 
                $satuan_id, 
                $harga_beli, 
                $harga_jual, 
                $jumlah_dibeli,
                $foto_produk // Variabel foto disertakan
            );

            if (!$stmt_insert_new_item->execute()) {
                throw new Exception("Gagal menambahkan barang baru: " . $nama_barang . " - " . $stmt_insert_new_item->error);
            }
        }
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Pembayaran telah dikonfirmasi dan stok barang telah diperbarui!']);

} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>