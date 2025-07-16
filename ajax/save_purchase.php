<?php
// Mengatur header sebagai JSON di baris paling atas
header('Content-Type: application/json');

// Memulai output buffering untuk menangkap semua output tak terduga (termasuk error)
ob_start();

// Memuat file-file inti
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Memulai sesi jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Memastikan hanya admin yang bisa melakukan aksi ini
cekAdmin();

// Mengambil data JSON yang dikirim dari JavaScript
$data = json_decode(file_get_contents('php://input'), true);

// Validasi data dasar yang diterima
if (!$data || !isset($data['items']) || empty($data['items']) || !isset($data['total'])) {
    ob_end_clean(); // Bersihkan buffer sebelum mengirim JSON error
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data pembelian tidak lengkap atau format JSON salah.']);
    exit();
}

// Memulai transaksi database. Ini sangat penting untuk integritas data.
$koneksi->begin_transaction();

try {
    // 1. Simpan data utama ke tabel 'pembelian'
    $stmt_pembelian = $koneksi->prepare(
        "INSERT INTO pembelian (no_pembelian, tanggal, supplier_id, user_id, total, status) VALUES (?, ?, ?, ?, ?, 'Belum Lunas')"
    );
    if ($stmt_pembelian === false) {
        throw new Exception("Gagal mempersiapkan query pembelian: " . $koneksi->error);
    }

    $stmt_pembelian->bind_param(
        "ssiid",
        $data['no_pembelian'],
        $data['tanggal'],
        $data['supplier_id'],
        $data['user_id'],
        $data['total']
    );

    if (!$stmt_pembelian->execute()) {
        throw new Exception("Gagal menyimpan data pembelian utama: " . $stmt_pembelian->error);
    }
    
    $pembelian_id = $koneksi->insert_id;
    if ($pembelian_id === 0) {
        throw new Exception("Gagal mendapatkan ID pembelian baru.");
    }

    // Siapkan statement untuk menyimpan detail dan update stok
    $stmt_detail = $koneksi->prepare(
        "INSERT INTO pembelian_detail (pembelian_id, barang_id, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)"
    );
    if ($stmt_detail === false) {
        throw new Exception("Gagal mempersiapkan query detail pembelian: " . $koneksi->error);
    }
    
    // PERBAIKAN: Ganti tanda + dengan - untuk mengurangi stok supplier
    $stmt_update_stok = $koneksi->prepare(
        "UPDATE barang SET stok = stok - ? WHERE id = ?"
    );
    if ($stmt_update_stok === false) {
        throw new Exception("Gagal mempersiapkan query update stok: " . $koneksi->error);
    }

    // Statement untuk mengecek stok sebelum update
    $stmt_check_stok = $koneksi->prepare(
        "SELECT stok, nama_barang FROM barang WHERE id = ?"
    );
    if ($stmt_check_stok === false) {
        throw new Exception("Gagal mempersiapkan query check stok: " . $koneksi->error);
    }

    // 2. Loop setiap barang di keranjang untuk disimpan dan diupdate
    foreach ($data['items'] as $item) {
        if (!isset($item['id'], $item['qty'], $item['price'])) {
            throw new Exception('Data item di dalam keranjang tidak lengkap.');
        }

        // Validasi stok sebelum melakukan pembelian
        $stmt_check_stok->bind_param("i", $item['id']);
        $stmt_check_stok->execute();
        $result = $stmt_check_stok->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Barang dengan ID {$item['id']} tidak ditemukan.");
        }
        
        $barang_data = $result->fetch_assoc();
        
        // Cek apakah stok mencukupi
        if ($barang_data['stok'] < $item['qty']) {
            throw new Exception("Stok barang '{$barang_data['nama_barang']}' tidak mencukupi. Stok tersedia: {$barang_data['stok']}, diminta: {$item['qty']}");
        }

        // Hitung subtotal di sisi server untuk keamanan
        $subtotal = $item['qty'] * $item['price'];

        // Simpan ke 'pembelian_detail'
        $stmt_detail->bind_param(
            "iiidd",
            $pembelian_id,
            $item['id'],
            $item['qty'],
            $item['price'],
            $subtotal // Gunakan subtotal yang dihitung di server
        );
        if (!$stmt_detail->execute()) {
            throw new Exception("Gagal menyimpan detail barang (ID: {$item['id']}): " . $stmt_detail->error);
        }

        // PERBAIKAN: Update stok dengan mengurangi (bukan menambah)
        $stmt_update_stok->bind_param(
            "ii",
            $item['qty'],
            $item['id']
        );
        if (!$stmt_update_stok->execute()) {
            throw new Exception("Gagal mengupdate stok barang (ID: {$item['id']}): " . $stmt_update_stok->error);
        }
    }

    // Jika semua query berhasil, konfirmasi semua perubahan ke database
    $koneksi->commit();

    // Bersihkan buffer sebelum mengirim JSON sukses
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Data pembelian berhasil disimpan!']);

} catch (Exception $e) {
    // Jika terjadi error di salah satu langkah, batalkan semua perubahan
    $koneksi->rollback();

    // Bersihkan buffer sebelum mengirim JSON error
    ob_end_clean();
    http_response_code(500); // Set status code error server
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Selalu tutup statement jika sudah dibuat
if (isset($stmt_pembelian) && $stmt_pembelian) $stmt_pembelian->close();
if (isset($stmt_detail) && $stmt_detail) $stmt_detail->close();
if (isset($stmt_update_stok) && $stmt_update_stok) $stmt_update_stok->close();
if (isset($stmt_check_stok) && $stmt_check_stok) $stmt_check_stok->close();
$koneksi->close();

exit();
?>