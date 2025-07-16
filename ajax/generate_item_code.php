<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Pastikan hanya request AJAX yang bisa mengakses
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die("Akses ditolak.");
}

// Validasi input kategori_id
if (!isset($_GET['kategori_id']) || !is_numeric($_GET['kategori_id']) || $_GET['kategori_id'] <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID Kategori tidak valid.']);
    exit();
}

$kategori_id = (int)$_GET['kategori_id'];

// 1. Ambil 3 huruf pertama dari nama kategori sebagai prefix
$stmt_kategori = $koneksi->prepare("SELECT nama_kategori FROM kategori WHERE id = ?");
$stmt_kategori->bind_param("i", $kategori_id);
$stmt_kategori->execute();
$result_kategori = $stmt_kategori->get_result();

if ($result_kategori->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Kategori tidak ditemukan.']);
    exit();
}
$nama_kategori = $result_kategori->fetch_assoc()['nama_kategori'];
$prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nama_kategori), 0, 3));

// 2. Cari nomor urut terakhir untuk prefix tersebut
$search_pattern = $prefix . '-%';
$stmt_barang = $koneksi->prepare("SELECT MAX(kode_barang) as max_kode FROM barang WHERE kode_barang LIKE ?");
$stmt_barang->bind_param("s", $search_pattern);
$stmt_barang->execute();
$max_kode = $stmt_barang->get_result()->fetch_assoc()['max_kode'];

$nomor_urut = 0;
if ($max_kode) {
    $parts = explode('-', $max_kode);
    $nomor_urut = (int)end($parts);
}
$nomor_urut++;

// ===================================================================================
// LOGIKA BARU: VALIDASI KEUNIKAN KODE
// Sistem akan terus mencari hingga menemukan kode yang 100% unik.
// ===================================================================================
do {
    // Format nomor urut menjadi 3 digit (e.g., 001, 012, 123)
    $nomor_urut_formatted = sprintf('%03d', $nomor_urut);
    $kode_barang_kandidat = $prefix . '-' . $nomor_urut_formatted;

    // Periksa apakah kode kandidat ini sudah ada di database
    $stmt_check = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ?");
    $stmt_check->bind_param("s", $kode_barang_kandidat);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        // Jika sudah ada, naikkan nomor urut dan coba lagi di iterasi berikutnya
        $nomor_urut++;
        $is_unique = false;
    } else {
        // Jika tidak ada, kode ini unik dan bisa digunakan
        $is_unique = true;
    }
} while (!$is_unique);


// Kirim response kode yang sudah dijamin unik dalam format JSON
header('Content-Type: application/json');
echo json_encode(['kode_barang' => $kode_barang_kandidat]);
?>