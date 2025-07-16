<?php
// FILE: ajax/approve_supplier.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Pastikan hanya admin yang bisa mengakses
cekAdmin();

header('Content-Type: application/json');

// Ambil ID dari request POST
$registration_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($registration_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pendaftaran tidak valid.']);
    exit;
}

// Mulai transaksi database untuk memastikan semua proses berjalan atau tidak sama sekali
$koneksi->begin_transaction();

try {
    // 1. Ambil data pendaftar dari tabel registrasi
    $stmt_get = $koneksi->prepare("SELECT * FROM supplier_registrations WHERE id = ? AND status = 'pending'");
    $stmt_get->bind_param("i", $registration_id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();

    if ($result_get->num_rows === 0) {
        throw new Exception('Pendaftar tidak ditemukan atau sudah diproses.');
    }
    $pendaftar = $result_get->fetch_assoc();

    // 2. Masukkan data pendaftar ke tabel supplier utama
    $stmt_insert = $koneksi->prepare(
        "INSERT INTO supplier (nama_supplier, nama_perusahaan, alamat, telepon, email, password) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt_insert->bind_param(
        "ssssss",
        $pendaftar['nama_supplier'],
        $pendaftar['nama_perusahaan'],
        $pendaftar['alamat'],
        $pendaftar['telepon'],
        $pendaftar['email'],
        $pendaftar['password']
    );
    if (!$stmt_insert->execute()) {
        throw new Exception('Gagal memindahkan data ke tabel supplier utama.');
    }
    
    // Ambil ID supplier yang baru saja dibuat
    $new_supplier_id = $koneksi->insert_id;

    // 3. Ubah status di tabel registrasi menjadi 'approved'
    $stmt_update = $koneksi->prepare("UPDATE supplier_registrations SET status = 'approved' WHERE id = ?");
    $stmt_update->bind_param("i", $registration_id);
    if (!$stmt_update->execute()) {
        throw new Exception('Gagal memperbarui status pendaftaran.');
    }

    // 4. Buat link login khusus
    $loginLink = BASE_URL . 'prepare_login.php?supplier_id=' . $new_supplier_id;

    // 5. Kirim email notifikasi persetujuan
    $emailSent = send_supplier_approval_email($pendaftar['email'], $pendaftar['nama_supplier'], $loginLink);
    if (!$emailSent) {
        // Jika email gagal terkirim, batalkan semua proses (opsional, bisa juga tetap disetujui)
        throw new Exception('Data supplier berhasil dibuat, tetapi email notifikasi gagal dikirim.');
    }

    // Jika semua langkah berhasil, konfirmasi transaksi
    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Supplier berhasil disetujui dan email notifikasi telah dikirim.']);

} catch (Exception $e) {
    // Jika terjadi error di salah satu langkah, batalkan semua perubahan
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>