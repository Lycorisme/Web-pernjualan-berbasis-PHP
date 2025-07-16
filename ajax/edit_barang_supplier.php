<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';
require_once __DIR__ . '/../system/upload_handler.php';

// Atur header untuk output JSON
header('Content-Type: application/json');

// Proteksi: Pastikan hanya supplier yang login yang bisa mengakses
try {
    cekSupplier();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak: ' . $e->getMessage()]);
    exit;
}

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// Ambil data dari POST
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$kode_barang = sanitize($_POST['kode_barang'] ?? '');
$nama_barang = sanitize($_POST['nama_barang'] ?? '');
$kategori_id = (int)($_POST['kategori_id'] ?? 0);
$satuan_id = (int)($_POST['satuan_id'] ?? 0);
$stok = (int)($_POST['stok'] ?? 0);
$supplier_id = $_SESSION['supplier_id'];

// Validasi dasar
if ($id <= 0 || empty($nama_barang) || $kategori_id <= 0 || $satuan_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap. Semua field wajib diisi.']);
    exit;
}

$new_photo_filename = null;
$old_photo_filename = null;

// LOGIKA UTAMA: Handle upload foto baru jika ada
if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] === UPLOAD_ERR_OK) {
    
    // 1. Ambil nama foto lama dari DB untuk dihapus nanti
    $stmt_old_photo = $koneksi->prepare("SELECT foto_produk FROM barang WHERE id = ? AND supplier_id = ?");
    $stmt_old_photo->bind_param("ii", $id, $supplier_id);
    $stmt_old_photo->execute();
    $result_old_photo = $stmt_old_photo->get_result();
    if($result_old_photo->num_rows > 0){
        $old_photo_filename = $result_old_photo->fetch_assoc()['foto_produk'];
    }
    
    // 2. Proses upload foto baru menggunakan handler
    $uploadResult = handleProductPhotoUpload(
        $_FILES['foto_produk'], 
        __DIR__ . '/../uploads/produk/'
    );

    if (isset($uploadResult['error'])) {
        echo json_encode(['success' => false, 'message' => $uploadResult['error']]);
        exit;
    }
    $new_photo_filename = $uploadResult['success'];
}

// Persiapan query UPDATE ke database
$params = [];
$types = "";

$sql = "UPDATE barang SET nama_barang = ?, kode_barang = ?, kategori_id = ?, satuan_id = ?, stok = ?";
array_push($params, $nama_barang, $kode_barang, $kategori_id, $satuan_id, $stok);
$types .= "ssiii";

// Jika ada foto baru, tambahkan ke query UPDATE
if ($new_photo_filename !== null) {
    $sql .= ", foto_produk = ?";
    array_push($params, $new_photo_filename);
    $types .= "s";
}

$sql .= " WHERE id = ? AND supplier_id = ?";
array_push($params, $id, $supplier_id);
$types .= "ii";

$stmt = $koneksi->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    // Jika update DB berhasil dan ada foto lama, hapus file foto lama
    if ($old_photo_filename && file_exists(__DIR__ . '/../uploads/produk/' . $old_photo_filename)) {
        unlink(__DIR__ . '/../uploads/produk/' . $old_photo_filename);
    }
    echo json_encode(['success' => true, 'message' => 'Barang berhasil diperbarui.']);
} else {
    // Jika update DB gagal, hapus foto baru yang terlanjur diupload
    if ($new_photo_filename && file_exists(__DIR__ . '/../uploads/produk/' . $new_photo_filename)) {
        unlink(__DIR__ . '/../uploads/produk/' . $new_photo_filename);
    }
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui barang: ' . $stmt->error]);
}

$stmt->close();
$koneksi->close();