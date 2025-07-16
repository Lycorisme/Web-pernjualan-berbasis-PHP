<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Pastikan hanya request AJAX dan dari supplier yang login
header('Content-Type: application/json');
cekSupplier();

// Validasi input
if (!isset($_POST['nama_kategori']) || empty(trim($_POST['nama_kategori']))) {
    echo json_encode(['success' => false, 'message' => 'Nama kategori tidak boleh kosong.']);
    exit();
}

$nama_kategori = sanitize($_POST['nama_kategori']);
$supplier_id = $_SESSION['supplier_id'];

// Simpan ke database dengan menautkan ID supplier
$stmt = $koneksi->prepare("INSERT INTO kategori (nama_kategori, supplier_id) VALUES (?, ?)");
$stmt->bind_param("si", $nama_kategori, $supplier_id);

if ($stmt->execute()) {
    // Ambil data kategori yang baru saja ditambahkan untuk dikirim kembali
    $new_id = $koneksi->insert_id;
    $new_category = [
        'id' => $new_id,
        'nama_kategori' => $nama_kategori
    ];
    echo json_encode(['success' => true, 'message' => 'Kategori baru berhasil ditambahkan!', 'data' => $new_category]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan kategori ke database.']);
}

exit();