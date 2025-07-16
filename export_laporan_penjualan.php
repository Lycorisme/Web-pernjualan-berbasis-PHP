<?php
// Clean any previous output and start fresh
if (ob_get_level()) {
    ob_end_clean();
}

// Start output buffering
ob_start();

// Include required files
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Check if user is admin
cekAdmin();

// Set default dates if not provided
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get transactions for the period
$query = "
    SELECT t.*, u.nama_lengkap as kasir 
    FROM transaksi t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE t.tanggal BETWEEN ? AND ?
    ORDER BY t.tanggal DESC, t.id DESC
";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Calculate summary
$total_penjualan = 0;
$jumlah_transaksi = 0;
$data_transaksi = [];

while ($row = $result->fetch_assoc()) {
    $data_transaksi[] = $row;
    $total_penjualan += $row['total'];
    $jumlah_transaksi++;
}

// Generate HTML content
$html_content = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12px; 
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .company-name { 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 5px;
            color: #333;
        }
        .company-info { 
            font-size: 14px; 
            margin-bottom: 20px;
            color: #666;
        }
        .report-title { 
            font-size: 18px; 
            font-weight: bold;
            margin-top: 15px;
            color: #333;
        }
        .period { 
            font-size: 14px; 
            margin-top: 10px;
            color: #555;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 10px; 
            text-align: left; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: bold;
            color: #333;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row { 
            font-weight: bold; 
            background-color: #e9ecef;
            color: #333;
        }
        
        .summary { 
            margin-top: 30px; 
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #007bff;
        }
        .summary h4 {
            margin-top: 0;
            color: #333;
        }
        .summary p {
            margin: 8px 0;
            font-size: 14px;
        }
        
        .signature { 
            margin-top: 50px; 
            text-align: right;
            page-break-inside: avoid;
        }
        .signature-box {
            display: inline-block;
            text-align: center;
            min-width: 200px;
        }
        .signature-line {
            border-bottom: 1px solid #333;
            margin-bottom: 5px;
            height: 50px;
        }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        .print-info {
            font-size: 10px;
            color: #999;
            text-align: center;
            margin-top: 20px;
            border-top: 1px solid #eee;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">PLATINUM KOMPUTER</div>
        <div class="company-info">
            Jl. Ahmad Yani No. 123, Banjarmasin<br>
            Kalimantan Selatan - Telepon: 0812-3456-7890
        </div>
        <div class="report-title">LAPORAN PENJUALAN</div>
        <div class="period">Periode: ' . formatTanggal($start_date) . ' s/d ' . formatTanggal($end_date) . '</div>
    </div>

    <table>
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="20%">No Transaksi</th>
                <th width="15%" class="text-center">Tanggal</th>
                <th width="25%">Kasir</th>
                <th width="20%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>';

if (!empty($data_transaksi)) {
    $no = 1;
    foreach ($data_transaksi as $row) {
        $html_content .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td>' . htmlspecialchars($row['no_transaksi']) . '</td>
                <td class="text-center">' . formatTanggal($row['tanggal']) . '</td>
                <td>' . htmlspecialchars($row['kasir']) . '</td>
                <td class="text-right">' . formatRupiah($row['total']) . '</td>
            </tr>';
    }
} else {
    $html_content .= '
        <tr>
            <td colspan="5" class="text-center" style="padding: 30px; color: #666;">
                Tidak ada data transaksi pada periode yang dipilih
            </td>
        </tr>';
}

$html_content .= '
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL PENJUALAN</td>
                <td class="text-right">' . formatRupiah($total_penjualan) . '</td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <h4>Ringkasan Laporan</h4>
        <p><strong>Jumlah Transaksi:</strong> ' . number_format($jumlah_transaksi) . ' transaksi</p>
        <p><strong>Total Penjualan:</strong> ' . formatRupiah($total_penjualan) . '</p>';

if ($jumlah_transaksi > 0) {
    $html_content .= '<p><strong>Rata-rata Per Transaksi:</strong> ' . formatRupiah($total_penjualan / $jumlah_transaksi) . '</p>';
}

$html_content .= '
        <p><strong>Periode Laporan:</strong> ' . formatTanggal($start_date) . ' s/d ' . formatTanggal($end_date) . '</p>
    </div>

    <div class="signature">
        <p>Banjarmasin, ' . date('d F Y') . '</p>
        <br>
        <div class="signature-box">
            <div class="signature-line"></div>
            <p><strong>Manager</strong></p>
        </div>
    </div>

    <div class="print-info">
        <p>Dicetak pada: ' . date('d F Y H:i:s') . ' | Sistem Informasi Penjualan - Platinum Komputer</p>
    </div>
</body>
</html>';

// Clear any previous output
ob_end_clean();

// Set headers for PDF
$filename = 'Laporan_Penjualan_' . $start_date . '_' . $end_date . '.html';
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output the content
echo $html_content;
exit;
?>