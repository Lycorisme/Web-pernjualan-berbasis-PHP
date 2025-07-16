<?php
// FILE: ajax/get_comments.php

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekAdmin();
header('Content-Type: application/json');

$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($registration_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Pendaftaran tidak valid.']);
    exit;
}

// Query untuk mengambil komentar dan nama admin yang membuatnya
$query = "
    SELECT ac.comment, ac.created_at, u.nama_lengkap as admin_name
    FROM admin_comments ac
    JOIN users u ON ac.admin_id = u.id
    WHERE ac.registration_id = ?
    ORDER BY ac.created_at ASC
";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $registration_id);
$stmt->execute();
$result = $stmt->get_result();

$comments = [];
while ($row = $result->fetch_assoc()) {
    // Format tanggal agar lebih mudah dibaca
    $row['created_at'] = date('d M Y, H:i', strtotime($row['created_at']));
    $comments[] = $row;
}

echo json_encode(['success' => true, 'comments' => $comments]);
?>