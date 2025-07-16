<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');
cekAdmin(); // Hanya admin yang bisa mengunggah bukti

// Validasi input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Metode request tidak valid.']));
}

if (!isset($_POST['pembelian_id']) || !is_numeric($_POST['pembelian_id'])) {
    die(json_encode(['success' => false, 'message' => 'ID Pembelian tidak ditemukan.']));
}

if (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
    die(json_encode(['success' => false, 'message' => 'Gagal mengunggah file. Pastikan Anda sudah memilih file.']));
}

$pembelian_id = (int)$_POST['pembelian_id'];
$file = $_FILES['bukti_transfer'];

// Validasi file gambar
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$file_info = getimagesize($file['tmp_name']);
if ($file_info === false || !in_array($file_info['mime'], $allowed_types)) {
    die(json_encode(['success' => false, 'message' => 'File yang diunggah harus berupa gambar (jpg, png, gif).']));
}

// Batasi ukuran file (misal: 2MB)
if ($file['size'] > 2 * 1024 * 1024) {
    die(json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 2MB.']));
}

// Proses pemindahan file
$upload_dir = __DIR__ . '/../uploads/bukti/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Buat nama file yang unik untuk menghindari penimpaan
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$new_filename = 'bukti-' . $pembelian_id . '-' . uniqid() . '.' . $file_extension;
$destination = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    // Update database
    $stmt = $koneksi->prepare("UPDATE pembelian SET status = 'Proses', bukti_transfer = ? WHERE id = ?");
    $stmt->bind_param("si", $new_filename, $pembelian_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Bukti pembayaran berhasil diunggah. Menunggu konfirmasi dari supplier.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data ke database.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal memindahkan file yang diunggah.']);
}

exit();
?>