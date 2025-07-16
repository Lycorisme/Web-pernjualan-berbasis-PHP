<?php
// FILE: ajax/register_supplier.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

// Ambil dan bersihkan data dari form
$nama_supplier = sanitize($_POST['nama_supplier'] ?? '');
$nama_perusahaan = sanitize($_POST['nama_perusahaan'] ?? '');
$alamat = sanitize($_POST['alamat'] ?? '');
$deskripsi = sanitize($_POST['deskripsi'] ?? '');
$telepon = sanitize($_POST['telepon'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$password = sanitize($_POST['password'] ?? '');

// Validasi input
if (empty($nama_supplier) || empty($nama_perusahaan) || empty($alamat) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Semua kolom yang ditandai bintang (*) wajib diisi.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
    exit;
}

try {
    // Cek duplikasi email di tabel supplier utama dan pendaftaran
    $stmt_check_supplier = $koneksi->prepare("SELECT id FROM supplier WHERE email = ?");
    $stmt_check_supplier->bind_param("s", $email);
    $stmt_check_supplier->execute();
    if ($stmt_check_supplier->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email ini sudah terdaftar sebagai supplier.']);
        exit;
    }

    $stmt_check_pending = $koneksi->prepare("SELECT id FROM supplier_registrations WHERE email = ? AND status = 'pending'");
    $stmt_check_pending->bind_param("s", $email);
    $stmt_check_pending->execute();
    if ($stmt_check_pending->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Anda sudah mengirim pendaftaran dengan email ini. Mohon tunggu.']);
        exit;
    }

    // Simpan data pendaftaran ke tabel supplier_registrations
    $stmt_insert = $koneksi->prepare(
        "INSERT INTO supplier_registrations (nama_supplier, nama_perusahaan, alamat, deskripsi, telepon, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt_insert->bind_param("sssssss", $nama_supplier, $nama_perusahaan, $alamat, $deskripsi, $telepon, $email, $password);

    if ($stmt_insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pendaftaran Anda telah berhasil dikirim. Mohon tunggu persetujuan dari Admin.']);
    } else {
        throw new Exception('Gagal menyimpan data pendaftaran.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()]);
}
?>