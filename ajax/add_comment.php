<?php
// FILE: ajax/add_comment.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak valid.']);
    exit;
}

$registration_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['nama_lengkap'];

if ($registration_id <= 0 || empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'ID Pendaftaran dan komentar tidak boleh kosong.']);
    exit;
}

// 1. Simpan komentar ke database
$stmt = $koneksi->prepare("INSERT INTO admin_comments (registration_id, admin_id, comment) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $registration_id, $admin_id, $comment);

if ($stmt->execute()) {
    // 2. Jika komentar berhasil disimpan, ambil data supplier untuk kirim email
    $stmt_get = $koneksi->prepare("SELECT nama_supplier, email FROM supplier_registrations WHERE id = ?");
    $stmt_get->bind_param("i", $registration_id);
    $stmt_get->execute();
    $supplier = $stmt_get->get_result()->fetch_assoc();

    if ($supplier) {
        // 3. Kirim email notifikasi komentar
        send_admin_comment_email($supplier['email'], $supplier['nama_supplier'], $admin_name, $comment);
    }
    
    echo json_encode(['success' => true, 'message' => 'Komentar berhasil ditambahkan dan notifikasi telah dikirim.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan komentar.']);
}
?>