<?php
// FILE: ajax/get_pending_suppliers.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Pastikan hanya admin yang bisa mengakses
cekAdmin();

header('Content-Type: application/json');

// Mengambil semua kolom data dari pendaftar yang statusnya 'pending'
$query = "SELECT * FROM supplier_registrations WHERE status = 'pending' ORDER BY created_at DESC";
$result = $koneksi->query($query);

$pending_suppliers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_suppliers[] = $row;
    }
}

// Mengembalikan data lengkap dalam format JSON
echo json_encode($pending_suppliers);
?>