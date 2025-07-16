<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Mulai session jika belum ada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login dan adalah admin
cekAdmin();

header('Content-Type: application/json');

// Ambil ID supplier dan kata kunci pencarian
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validasi supplier_id
if ($supplier_id <= 0) {
    echo json_encode(['error' => 'ID supplier tidak valid']);
    exit();
}

try {
    // PERUBAHAN: Menambahkan kolom 'foto_produk' ke dalam query SELECT
    $query = "SELECT id, kode_barang, nama_barang, harga_beli, stok, foto_produk FROM barang WHERE supplier_id = ?";
    $params = [$supplier_id];
    $types = "i";

    // Tambahkan kondisi pencarian jika ada
    if (!empty($search)) {
        $query .= " AND (nama_barang LIKE ? OR kode_barang LIKE ?)";
        $search_term = '%' . $search . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    $query .= " ORDER BY nama_barang ASC";

    $stmt = $koneksi->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $koneksi->error);
    }

    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $products = [];

    while ($row = $result->fetch_assoc()) {
        // PERUBAHAN: Menambahkan 'foto_produk' ke dalam array JSON yang dikirim
        $products[] = [
            'id' => (int)$row['id'],
            'kode_barang' => $row['kode_barang'],
            'nama_barang' => $row['nama_barang'],
            'harga_beli' => (float)$row['harga_beli'],
            'stok' => (int)$row['stok'],
            'foto_produk' => $row['foto_produk'] // <-- Data foto ditambahkan di sini
        ];
    }

    $stmt->close();
    echo json_encode($products);

} catch (Exception $e) {
    error_log("Error in get_supplier_products.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Terjadi kesalahan saat mengambil data barang']);
}

$koneksi->close();
?>