<?php

/**
 * Menangani proses unggah file secara terpusat.
 *
 * @param array $fileData Data file dari superglobal $_FILES (misal: $_FILES['foto_produk'] atau $_FILES['contract_file']).
 * @param string $destinationDir Direktori tujuan untuk menyimpan file (misal: __DIR__ . '/../uploads/produk/').
 * @param string|null $oldFilename Nama file lama yang akan dihapus jika ada (untuk proses update).
 * @param array $allowedMimeTypes Array MIME types yang diizinkan (misal: ['image/jpeg', 'application/pdf']).
 * @param int $maxFileSize Ukuran file maksimal dalam byte (misal: 2 * 1024 * 1024 untuk 2MB).
 * @return array Mengembalikan array dengan `['success' => 'nama_file_baru.ext']` jika berhasil,
 * atau `['error' => 'Pesan error']` jika gagal.
 */
function handleProductPhotoUpload($fileData, $destinationDir, $oldFilename = null, $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'], $maxFileSize = 2 * 1024 * 1024) {
    // Cek apakah ada file yang diunggah atau ada error
    if (!isset($fileData) || $fileData['error'] !== UPLOAD_ERR_OK) {
        // Jika tidak ada file baru yang diunggah, ini bukan error, jadi kembalikan null.
        if (isset($fileData) && $fileData['error'] === UPLOAD_ERR_NO_FILE) {
            return ['success' => null]; // Tidak ada file yang diunggah
        }
        return ['error' => 'Gagal mengunggah file. Kode Error: ' . ($fileData['error'] ?? 'Tidak diketahui')];
    }

    // --- Validasi Keamanan ---

    // 1. Batasi ukuran file
    if ($fileData['size'] > $maxFileSize) {
        // Tambahkan logging untuk debugging
        error_log("Upload Error: File size too large. File: {$fileData['name']}, Size: {$fileData['size']} bytes.");
        return ['error' => 'Ukuran file terlalu besar. Maksimal ' . ($maxFileSize / 1024 / 1024) . 'MB.'];
    }

    // 2. Validasi tipe file
    $fileMimeType = mime_content_type($fileData['tmp_name']);
    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        // Tambahkan logging untuk debugging
        error_log("Upload Error: Invalid MIME type. File: {$fileData['name']}, MIME Type: {$fileMimeType}.");
        return ['error' => 'Format file tidak valid. Hanya izinkan ' . implode(', ', $allowedMimeTypes) . '.'];
    }

    // --- Proses File ---

    // Buat nama file yang unik untuk menghindari penimpaan
    $fileExtension = strtolower(pathinfo($fileData['name'], PATHINFO_BUTTON_EXTENSION));
    $newFilename = 'file_' . uniqid() . '_' . time() . '.' . $fileExtension; // Nama file lebih generik
    $destinationPath = rtrim($destinationDir, '/') . '/' . $newFilename;

    // Pastikan direktori tujuan ada
    if (!is_dir($destinationDir)) {
        if (!mkdir($destinationDir, 0755, true)) {
            error_log("Upload Error: Failed to create directory {$destinationDir}.");
            return ['error' => 'Gagal membuat direktori tujuan unggahan.'];
        }
    }

    // Pindahkan file ke direktori tujuan
    if (move_uploaded_file($fileData['tmp_name'], $destinationPath)) {
        // Jika berhasil, hapus file lama (jika ada)
        if ($oldFilename && is_file($destinationDir . $oldFilename)) {
            unlink($destinationDir . $oldFilename);
        }
        return ['success' => $newFilename];
    } else {
        error_log("Upload Error: Failed to move uploaded file from {$fileData['tmp_name']} to {$destinationPath}.");
        return ['error' => 'Gagal memindahkan file ke direktori tujuan.'];
    }
}
