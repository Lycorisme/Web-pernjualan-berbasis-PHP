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
    // Ambil detail retur, termasuk barang_id dan jumlah, dan pastikan retur ini milik supplier yang sedang login
    $stmt_get = $koneksi->prepare(
        "SELECT r.jumlah, r.barang_id, b.kode_barang, b.nama_barang, r.no_retur, r.status, s.nama_supplier
         FROM retur r 
         JOIN barang b ON r.barang_id = b.id 
         JOIN supplier s ON r.supplier_id = s.id
         WHERE r.id = ? AND r.supplier_id = ?"
    );
    $stmt_get->bind_param("ii", $retur_id, $supplier_id_session);
    $stmt_get->execute();
    $retur = $stmt_get->get_result()->fetch_assoc();

    if (!$retur) throw new Exception("Retur tidak ditemukan atau bukan milik Anda.");
    if ($retur['status'] !== 'Menunggu Persetujuan') throw new Exception("Status retur ini sudah tidak bisa diubah.");

    if ($status_baru === 'Disetujui') {
        $kode_barang = $retur['kode_barang'];
        $barang_id_retur = $retur['barang_id']; // ID barang yang diretur (ini ID barang supplier)
        $jumlah_retur = $retur['jumlah'];

        // 1. Kurangi stok ADMIN
        // Cari ID barang admin berdasarkan kode_barang
        $stmt_find_admin_item = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ? AND supplier_id IS NULL");
        $stmt_find_admin_item->bind_param("s", $kode_barang);
        $stmt_find_admin_item->execute();
        $admin_item = $stmt_find_admin_item->get_result()->fetch_assoc();

        if (!$admin_item) {
            // This case should ideally not happen if admin is returning existing stock.
            // But if it does, it means the item code from supplier's perspective
            // does not match any item in admin's *main* stock.
            throw new Exception("Error: Tidak ditemukan barang yang cocok di stok utama (admin) untuk kode: " . htmlspecialchars($kode_barang));
        }

        $admin_barang_id = $admin_item['id'];
        $stmt_admin_stok = $koneksi->prepare("UPDATE barang SET stok = stok - ? WHERE id = ?");
        $stmt_admin_stok->bind_param("ii", $jumlah_retur, $admin_barang_id);
        if (!$stmt_admin_stok->execute()) {
            throw new Exception("Gagal mengurangi stok admin.");
        }

        // 2. Stok supplier TIDAK bertambah. (LOGIKA LAMA DIHAPUS)
        // $stmt_supplier_stok = $koneksi->prepare("UPDATE barang SET stok = stok + ? WHERE id = ?");
        // $stmt_supplier_stok->bind_param("ii", $jumlah_retur, $barang_id_retur);
        // if (!$stmt_supplier_stok->execute()) throw new Exception("Gagal menambah stok supplier.");

        // 3. Masukkan barang ke tabel barang_diretur (catatan bahwa supplier menerima retur)
        $tanggal_masuk_retur = date('Y-m-d');
        // Untuk catatan_supplier, bisa ditambahkan field input di modal konfirmasi di retur_supplier.php
        // Untuk sekarang, kita bisa isi dengan string kosong atau pesan default.
        $catatan_supplier = "Barang retur diterima oleh supplier."; 
        $stmt_insert_barang_diretur = $koneksi->prepare(
            "INSERT INTO barang_diretur (retur_id, barang_id, jumlah, tanggal_masuk, catatan_supplier) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt_insert_barang_diretur->bind_param(
            "iiiss", 
            $retur_id, 
            $barang_id_retur, // Ini adalah ID barang di tabel `barang` yang diretur (milik supplier)
            $jumlah_retur, 
            $tanggal_masuk_retur, 
            $catatan_supplier
        );
        if (!$stmt_insert_barang_diretur->execute()) {
            throw new Exception("Gagal memasukkan data ke tabel barang_diretur: " . $stmt_insert_barang_diretur->error);
        }

    } else if ($status_baru === 'Ditolak') {
        // Jika ditolak, tidak ada perubahan stok di admin maupun supplier (sesuai permintaan baru)
        // Aksi default sudah seperti ini, hanya perlu update status.
    }

    // Update status di tabel retur
    $stmt_update_status = $koneksi->prepare("UPDATE retur SET status = ? WHERE id = ?");
    $stmt_update_status->bind_param("si", $status_baru, $retur_id);
    if (!$stmt_update_status->execute()) throw new Exception("Gagal memperbarui status retur.");

    // Kirim email notifikasi ke admin
    if (defined('ADMIN_EMAIL')) {
        $email_data = [
            'no_retur' => $retur['no_retur'],
            'nama_barang' => $retur['nama_barang'],
            'nama_supplier' => $retur['nama_supplier'],
            'status_baru' => $status_baru
        ];
        send_status_update_email_to_admin(ADMIN_EMAIL, $email_data);
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => "Retur berhasil " . strtolower($status_baru) . ". Stok admin telah diperbarui."]);
} catch (Exception $e) {
    $koneksi->rollback();
    error_log("Error in update_retur_status.php: " . $e->getMessage()); // Tambahkan logging
    echo json_encode(['success' => false, 'message' => 'Gagal: ' . $e->getMessage()]);
}
?>