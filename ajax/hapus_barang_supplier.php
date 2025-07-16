<?php
// ajax/hapus_barang_supplier.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Pastikan request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Pastikan supplier sudah login
cekSupplier();
$supplier_id = $_SESSION['supplier_id'];

// Ambil data dari POST
$id = $_POST['id'] ?? '';

// Validasi input
if (empty($id) || !is_numeric($id)) {
    echo json_encode(['success' => false, 'message' => 'ID barang tidak valid']);
    exit;
}

try {
    // Mulai transaksi
    $koneksi->begin_transaction();

    // Cek apakah barang milik supplier ini
    $cek_query = "SELECT id, nama_barang, foto_produk FROM barang WHERE id = ? AND supplier_id = ?";
    $cek_stmt = $koneksi->prepare($cek_query);
    $cek_stmt->bind_param("ii", $id, $supplier_id);
    $cek_stmt->execute();
    $cek_result = $cek_stmt->get_result();
    
    if ($cek_result->num_rows === 0) {
        $koneksi->rollback();
        echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan atau bukan milik Anda']);
        exit;
    }

    $barang_data = $cek_result->fetch_assoc();
    $nama_barang = $barang_data['nama_barang'];
    $foto_produk = $barang_data['foto_produk'];

    // PERBAIKAN: Menggunakan nama tabel 'transaksi_detail' yang benar
    // Cek apakah barang sedang digunakan dalam transaksi penjualan
    $cek_transaksi_query = "SELECT COUNT(*) as total FROM transaksi_detail WHERE barang_id = ?";
    $cek_transaksi_stmt = $koneksi->prepare($cek_transaksi_query);
    $cek_transaksi_stmt->bind_param("i", $id);
    $cek_transaksi_stmt->execute();
    $transaksi_count = $cek_transaksi_stmt->get_result()->fetch_assoc()['total'];

    if ($transaksi_count > 0) {
        $koneksi->rollback();
        echo json_encode(['success' => false, 'message' => "Barang '{$nama_barang}' tidak dapat dihapus karena sudah pernah tercatat dalam transaksi penjualan."]);
        exit;
    }

    // Karena barang belum pernah terjual, kita bisa langsung hapus dari tabel barang
    // Tidak perlu lagi menghapus dari tabel detail karena sudah dicek di atas
    
    // Hapus barang
    $hapus_query = "DELETE FROM barang WHERE id = ? AND supplier_id = ?";
    $hapus_stmt = $koneksi->prepare($hapus_query);
    $hapus_stmt->bind_param("ii", $id, $supplier_id);
    
    if ($hapus_stmt->execute()) {
        if ($hapus_stmt->affected_rows > 0) {
            // Jika berhasil hapus dari DB, hapus juga file fotonya jika ada
            if (!empty($foto_produk) && file_exists(__DIR__ . '/../uploads/produk/' . $foto_produk)) {
                unlink(__DIR__ . '/../uploads/produk/' . $foto_produk);
            }
            // Commit transaksi
            $koneksi->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Barang '{$nama_barang}' berhasil dihapus"
            ]);
        } else {
            $koneksi->rollback();
            echo json_encode(['success' => false, 'message' => 'Barang tidak ditemukan atau sudah dihapus']);
        }
    } else {
        $koneksi->rollback();
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus barang: ' . $hapus_stmt->error]);
    }

} catch (Exception $e) {
    // Rollback jika terjadi error
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>