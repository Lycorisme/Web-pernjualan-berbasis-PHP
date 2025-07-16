<?php
// FILE: proses_pembelian.php

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

cekAdmin(); // Hanya admin yang bisa melakukan aksi ini

$pembelian_id = $_GET['id'] ?? 0;

if (empty($pembelian_id)) {
    header('Location: supplier.php'); // Arahkan ke halaman daftar supplier jika ID tidak ada
    exit;
}

// Panggil fungsi pintar yang baru kita buat
$hasil = prosesPenerimaanBarangDariPembelian($pembelian_id);

// Atur pesan notifikasi berdasarkan hasil
setAlert($hasil['success'] ? 'success' : 'danger', $hasil['message']);

// Arahkan kembali ke halaman detail pembelian atau halaman daftar pembelian
header('Location: pembelian.php?id=' . $pembelian_id); // Asumsi Anda punya halaman detail pembelian
exit;
?>