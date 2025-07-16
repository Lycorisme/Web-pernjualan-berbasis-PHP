<?php
// Include required files
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get transaction ID
$transaksi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($transaksi_id <= 0) {
    die("Invalid transaction ID");
}

// Get transaction data
$stmt = $koneksi->prepare("
    SELECT t.*, u.nama_lengkap as kasir 
    FROM transaksi t 
    LEFT JOIN users u ON t.user_id = u.id 
    WHERE t.id = ?
");
$stmt->bind_param("i", $transaksi_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Transaction not found");
}

$transaksi = $result->fetch_assoc();

// Get transaction details
$stmt = $koneksi->prepare("
    SELECT td.*, b.kode_barang, b.nama_barang, s.nama_satuan 
    FROM transaksi_detail td 
    LEFT JOIN barang b ON td.barang_id = b.id
    LEFT JOIN satuan s ON b.satuan_id = s.id
    WHERE td.transaksi_id = ?
");
$stmt->bind_param("i", $transaksi_id);
$stmt->execute();
$result_detail = $stmt->get_result();

$items = [];
while ($row = $result_detail->fetch_assoc()) {
    $items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk - <?= $transaksi['no_transaksi'] ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
        }
        .receipt-header, .receipt-footer {
            text-align: center;
            margin-bottom: 10px;
        }
        .receipt-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .transaction-info {
            margin-bottom: 10px;
        }
        .transaction-info div {
            display: flex;
            justify-content: space-between;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table, th, td {
            border-bottom: 1px dashed #ddd;
        }
        th, td {
            padding: 5px;
            text-align: left;
        }
        th:last-child, td:last-child {
            text-align: right;
        }
        .total-section {
            margin-top: 10px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        .total-section div {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .bold {
            font-weight: bold;
        }
        .thank-you {
            margin-top: 20px;
            text-align: center;
            font-style: italic;
        }
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                width: 72mm;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-header">
        <div class="receipt-title">PLATINUM KOMPUTER</div>
        <div>Jl. Ahmad Yani No. 123</div>
        <div>Banjarmasin, Kalimantan Selatan</div>
        <div>Telp: 0812-3456-7890</div>
    </div>
    
    <div class="transaction-info">
        <div>
            <span>No:</span>
            <span><?= $transaksi['no_transaksi'] ?></span>
        </div>
        <div>
            <span>Tanggal:</span>
            <span><?= formatTanggal($transaksi['tanggal']) ?></span>
        </div>
        <div>
            <span>Kasir:</span>
            <span><?= $transaksi['kasir'] ?></span>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="50%">Item</th>
                <th width="10%">Qty</th>
                <th width="20%">Harga</th>
                <th width="20%">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?= $item['nama_barang'] ?></td>
                <td><?= $item['jumlah'] ?></td>
                <td><?= number_format($item['harga'], 0, ',', '.') ?></td>
                <td><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="total-section">
        <div class="bold">
            <span>TOTAL</span>
            <span><?= formatRupiah($transaksi['total']) ?></span>
        </div>
    </div>
    
    <div class="thank-you">
        Terima Kasih Atas Kunjungan Anda
    </div>
    
    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>
    
    <script>
        // Auto print when page loads
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
