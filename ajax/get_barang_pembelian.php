<?php
require_once 'koneksi.php';

// Pastikan parameter pembelian_id ada
if (isset($_GET['pembelian_id']) && !empty($_GET['pembelian_id'])) {
    $pembelian_id = intval($_GET['pembelian_id']);

    // Query untuk mengambil barang dari detail pembelian
    $query = "
        SELECT 
            pd.barang_id, 
            b.nama_barang, 
            pd.jumlah AS jumlah_dibeli
        FROM pembelian_detail pd
        JOIN barang b ON pd.barang_id = b.id
        WHERE pd.pembelian_id = ?
    ";

    $stmt = $koneksi->prepare($query);
    $stmt->bind_param('i', $pembelian_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $barang_list = [];
    while ($row = $result->fetch_assoc()) {
        $barang_list[] = $row;
    }

    // Mengembalikan data sebagai JSON
    header('Content-Type: application/json');
    echo json_encode($barang_list);

    $stmt->close();
} else {
    // Jika tidak ada ID, kembalikan array kosong
    header('Content-Type: application/json');
    echo json_encode([]);
}

$koneksi->close();
?>