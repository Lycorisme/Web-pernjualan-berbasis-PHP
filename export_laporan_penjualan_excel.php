<?php
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

// Set headers for Excel download
$filename = 'Laporan_Penjualan_' . $start_date . '_' . $end_date . '.xls';
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

// Start output buffering to prevent any unwanted output
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 18px; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #e6e6e6; }
    </style>
</head>
<body>
    <div class="header">
        <h1>PLATINUM KOMPUTER</h1>
        <p>Jl. Ahmad Yani No. 123, Banjarmasin</p>
        <p>Telepon: 0812-3456-7890</p>
        <h2>LAPORAN PENJUALAN</h2>
        <p>Periode: <?= formatTanggal($start_date) ?> s/d <?= formatTanggal($end_date) ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50">No</th>
                <th width="150">No Transaksi</th>
                <th width="100">Tanggal</th>
                <th width="150">Kasir</th>
                <th width="120">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($data_transaksi)): ?>
                <?php $no = 1; foreach ($data_transaksi as $row): ?>
                <tr>
                    <td class="text-center"><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                    <td class="text-center"><?= formatTanggal($row['tanggal']) ?></td>
                    <td><?= htmlspecialchars($row['kasir']) ?></td>
                    <td class="text-right"><?= $row['total'] ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">Tidak ada data transaksi pada periode yang dipilih</td>
                </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td colspan="4" class="text-right"><strong>TOTAL PENJUALAN</strong></td>
                <td class="text-right"><strong><?= $total_penjualan ?></strong></td>
            </tr>
        </tbody>
    </table>

    <br><br>
    <h3>RINGKASAN</h3>
    <table style="width: 50%;">
        <tr>
            <td><strong>Jumlah Transaksi</strong></td>
            <td><?= $jumlah_transaksi ?> transaksi</td>
        </tr>
        <tr>
            <td><strong>Total Penjualan</strong></td>
            <td><?= $total_penjualan ?></td>
        </tr>
        <?php if ($jumlah_transaksi > 0): ?>
        <tr>
            <td><strong>Rata-rata Per Transaksi</strong></td>
            <td><?= round($total_penjualan / $jumlah_transaksi) ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><strong>Periode Laporan</strong></td>
            <td><?= formatTanggal($start_date) ?> s/d <?= formatTanggal($end_date) ?></td>
        </tr>
        <tr>
            <td><strong>Dicetak Tanggal</strong></td>
            <td><?= date('d F Y H:i:s') ?></td>
        </tr>
    </table>
</body>
</html>

<?php
// Get content and clean buffer
$content = ob_get_clean();

// Output the content
echo $content;
exit;
?>