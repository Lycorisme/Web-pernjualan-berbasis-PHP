<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Atur header sebagai JSON
header('Content-Type: application/json');

// Cek login, bisa diakses oleh admin maupun supplier
cekLogin();

// Ambil semua data satuan dari database
$result = $koneksi->query("SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan ASC");

$satuan = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $satuan[] = $row;
    }
}

// Kembalikan data dalam format JSON
echo json_encode($satuan);
?>