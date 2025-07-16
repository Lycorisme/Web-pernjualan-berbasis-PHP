<?php

/**
 * Menangani proses unggah file gambar produk secara terpusat.
 *
 * @param array $fileData Data file dari superglobal $_FILES (misal: $_FILES['foto_produk']).
 * @param string $destinationDir Direktori tujuan untuk menyimpan file (misal: __DIR__ . '/../uploads/produk/').
 * @param string|null $oldFilename Nama file lama yang akan dihapus jika ada (untuk proses update).
 * @return array Mengembalikan array dengan `['success' => 'nama_file_baru.jpg']` jika berhasil,
 * atau `['error' => 'Pesan error']` jika gagal.
 */
function handleProductPhotoUpload($fileData, $destinationDir, $oldFilename = null) {
    // Cek apakah ada file yang diunggah atau ada error
    if (!isset($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
        // Jika tidak ada file baru yang diunggah, ini bukan error, jadi kembalikan null.
        if (isset($fileData) && $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => null]; // Tidak ada file yang diunggah
        }
        return ['error' => 'Gagal mengunggah file. Kode Error: ' . ($fileData['error'] ?? 'Tidak diketahui')];
    }

    // --- Validasi Keamanan ---

    // 1. Batasi ukuran file (misal: 2MB)
    $maxFileSize = 2 * 1024 * 1024; // 2 MB
    if ($fileData['size'] > $maxFileSize) {
        return ['error' => 'Ukuran file terlalu besar. Maksimal 2MB.'];
    }

    // 2. Validasi tipe file (hanya izinkan gambar)
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileMimeType = mime_content_type($fileData['tmp_name']);
    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        return ['error' => 'Format file tidak valid. Hanya izinkan JPG, PNG, atau GIF.'];
    }

    // --- Proses File ---

    // Buat nama file yang unik untuk menghindari penimpaan
    $fileExtension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    $newFilename = 'produk_' . uniqid() . '_' . time() . '.' . $fileExtension;
    $destinationPath = rtrim($destinationDir, '/') . '/' . $newFilename;

    // Pastikan direktori tujuan ada
    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }

    // Pindahkan file ke direktori tujuan
    if (move_uploaded_file($fileData['tmp_name'], $destinationPath)) {
        // Jika berhasil, hapus file lama (jika ada)
        if ($oldFilename && is_file($destinationDir . $oldFilename)) {
            unlink($destinationDir . $oldFilename);
        }
        return ['success' => $newFilename];
    } else {
        return ['error' => 'Gagal memindahkan file ke direktori tujuan.'];
    }
}