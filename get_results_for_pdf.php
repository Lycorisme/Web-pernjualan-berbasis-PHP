<?php
require 'config.php';

header('Content-Type: application/json');

try {
    // Query untuk mengambil hasil pemungutan suara
    // Menghitung jumlah suara untuk setiap kandidat
    $query = "
        SELECT 
            c.id_calon,
            c.nama_calon,
            COUNT(v.id_vote) AS jumlah_suara
        FROM 
            candidates c
        LEFT JOIN 
            votes v ON c.id_calon = v.id_calon
        GROUP BY 
            c.id_calon, c.nama_calon
        ORDER BY 
            jumlah_suara DESC
    ";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception('Gagal mengambil data hasil pemilu dari database.');
    }

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'results' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>