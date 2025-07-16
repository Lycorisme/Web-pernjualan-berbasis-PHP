<?php
// FILE: ajax/update_retur_status.php (Logika Stok Final)

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekSupplier();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$retur_id = $_POST['retur_id'] ?? 0;
$status_baru = $_POST['status'] ?? '';
$supplier_id_session = $_SESSION['supplier_id'];

$allowed_status = ['Disetujui', 'Ditolak'];
if (empty($retur_id) || !in_array($status_baru, $allowed_status)) {
    echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
    exit;
}

$koneksi->begin_transaction();
try {
    $stmt_get = $koneksi->prepare(
        "SELECT r.jumlah, r.barang_id as supplier_barang_id, b.kode_barang, s.nama_supplier, b.nama_barang, r.no_retur, r.status
         FROM retur r JOIN barang b ON r.barang_id = b.id JOIN supplier s ON r.supplier_id = s.id
         WHERE r.id = ? AND r.supplier_id = ?"
    );
    $stmt_get->bind_param("ii", $retur_id, $supplier_id_session);
    $stmt_get->execute();
    $retur = $stmt_get->get_result()->fetch_assoc();

    if (!$retur) throw new Exception("Retur tidak ditemukan.");
    if ($retur['status'] !== 'Menunggu Persetujuan') throw new Exception("Status retur ini sudah tidak bisa diubah.");

    if ($status_baru === 'Disetujui') {
        $kode_barang = $retur['kode_barang'];
        $stmt_find_admin_item = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ? AND supplier_id IS NULL");
        $stmt_find_admin_item->bind_param("s", $kode_barang);
        $stmt_find_admin_item->execute();
        $admin_item = $stmt_find_admin_item->get_result()->fetch_assoc();

        if (!$admin_item) throw new Exception("Error: Tidak ditemukan barang yang cocok di stok utama (admin) untuk kode: " . htmlspecialchars($kode_barang));
        
        $admin_barang_id = $admin_item['id'];
        $supplier_barang_id = $retur['supplier_barang_id'];
        $jumlah_retur = $retur['jumlah'];

        // Kurangi stok ADMIN
        $stmt_admin_stok = $koneksi->prepare("UPDATE barang SET stok = stok - ? WHERE id = ?");
        $stmt_admin_stok->bind_param("ii", $jumlah_retur, $admin_barang_id);
        if (!$stmt_admin_stok->execute()) throw new Exception("Gagal mengurangi stok admin.");

        // Tambah stok SUPPLIER
        $stmt_supplier_stok = $koneksi->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
        $stmt_supplier_stok->bind_param("ii", $jumlah_retur, $supplier_barang_id);
        if (!$stmt_supplier_stok->execute()) throw new Exception("Gagal menambah stok supplier.");
    }

    $stmt_update_status = $koneksi->prepare("UPDATE retur SET status = ? WHERE id = ?");
    $stmt_update_status->bind_param("si", $status_baru, $retur_id);
    if (!$stmt_update_status->execute()) throw new Exception("Gagal memperbarui status retur.");

    if (defined('ADMIN_EMAIL')) {
        $email_data = [
            'no_retur' => $retur['no_retur'], 'nama_barang' => $retur['nama_barang'],
            'nama_supplier' => $retur['nama_supplier'], 'status_baru' => $status_baru
        ];
        send_status_update_email_to_admin(ADMIN_EMAIL, $email_data);
    }
    
    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => "Retur berhasil " . strtolower($status_baru) . ". Stok telah diperbarui."]);
} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}
?>