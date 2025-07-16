<?php
// FILE: ajax/reject_supplier.php (Diperbarui untuk menghapus data)

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekAdmin();
header('Content-Type: application/json');

$registration_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($registration_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pendaftaran tidak valid.']);
    exit;
}

try {
    // 1. Ambil data pendaftar untuk dikirim email SEBELUM datanya dihapus
    $stmt_get = $koneksi->prepare("SELECT nama_perusahaan, email FROM supplier_registrations WHERE id = ? AND status = 'pending'");
    $stmt_get->bind_param("i", $registration_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Pendaftar tidak ditemukan atau sudah diproses sebelumnya.');
    }
    $supplier = $result->fetch_assoc();

    // 2. HAPUS data pendaftar dari tabel registrasi
    // Perubahan dari UPDATE menjadi DELETE
    $stmt_delete = $koneksi->prepare("DELETE FROM supplier_registrations WHERE id = ?");
    $stmt_delete->bind_param("i", $registration_id);
    
    if ($stmt_delete->execute()) {
        // 3. Jika data berhasil dihapus, kirim email penolakan
        send_supplier_rejection_email($supplier['email'], $supplier['nama_perusahaan']);
        
        echo json_encode(['success' => true, 'message' => 'Pendaftaran supplier berhasil ditolak dan data telah dihapus.']);
    } else {
        throw new Exception('Gagal menghapus data pendaftaran.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>