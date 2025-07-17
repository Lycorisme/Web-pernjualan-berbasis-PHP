<?php
// FILE: generate_contract_pdf.php
// Menggunakan library Dompdf untuk menghasilkan PDF

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Pastikan Dompdf terinstal via Composer: composer require dompdf/dompdf
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

cekLogin(); // Hanya user yang login bisa generate PDF

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    die("ID Pesanan tidak valid.");
}

// Ambil semua data yang diperlukan dari tabel orders, order_items, dan order_contracts
$query = "
    SELECT 
        o.order_no, o.order_date, o.total_order_price, o.payment_type,
        o.buyer_name, o.buyer_address, o.buyer_contact, o.receiving_warehouse,
        s.nama_perusahaan AS supplier_company, s.nama_supplier AS supplier_pic_original, s.telepon AS supplier_phone_original,
        u.nama_lengkap AS admin_name,
        oc.supplier_company_name_contract, oc.supplier_pic_name_contract, oc.supplier_contact_contract,
        oc.payment_due_date, oc.amount_to_pay, oc.payment_terms_description
    FROM orders o
    JOIN supplier s ON o.supplier_id = s.id
    JOIN users u ON o.admin_user_id = u.id
    LEFT JOIN order_contracts oc ON o.order_id = oc.order_id
    WHERE o.order_id = ?
";
$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_data = $stmt->get_result()->fetch_assoc();

if (!$order_data) {
    die("Data pesanan tidak ditemukan.");
}

// Ambil item pesanan
$query_items = "
    SELECT kode_barang, nama_barang, quantity, price_per_item, subtotal_item_price
    FROM order_items
    WHERE order_id = ?
";
$stmt_items = $koneksi->prepare($query_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$order_items = $stmt_items->get_result();

// Siapkan HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Kontrak Pembelian - ' . htmlspecialchars($order_data['order_no']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.5; }
        .container { width: 100%; margin: 0 auto; padding: 10mm; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18pt; color: #333; }
        .header p { margin: 0; font-size: 10pt; color: #666; }
        .section-title { font-size: 12pt; font-weight: bold; margin-top: 15px; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding-bottom: 3px; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background-color: #f2f2f2; font-weight: bold; }
        .info-table td { padding: 2px 0; border: none; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #f9f9f9; }
        .signature-section { margin-top: 40px; page-break-inside: avoid; }
        .signature-columns { width: 100%; display: table; table-layout: fixed; }
        .signature-column { width: 50%; display: table-cell; text-align: center; vertical-align: top; }
        .signature-line { border-bottom: 1px solid #000; width: 80%; margin: 60px auto 5px auto; }
        .footer { margin-top: 30px; font-size: 8pt; text-align: center; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KONTRAK PEMBELIAN BARANG</h1>
            <p><strong>PLATINUM KOMPUTER</strong></p>
            <p>Jl. Ahmad Yani No. 123, Banjarmasin, Kalimantan Selatan</p>
            <p>Telp: 0812-3456-7890</p>
        </div>

        <div class="section-title">Detail Pesanan</div>
        <table class="info-table">
            <tr>
                <td width="25%">No. Pesanan</td>
                <td width="25%">: ' . htmlspecialchars($order_data['order_no']) . '</td>
                <td width="25%">Tanggal Pesan</td>
                <td width="25%">: ' . formatTanggal($order_data['order_date']) . '</td>
            </tr>
            <tr>
                <td>Admin Pemesan</td>
                <td>: ' . htmlspecialchars($order_data['admin_name']) . '</td>
                <td>Total Harga Pesanan</td>
                <td>: ' . formatRupiah($order_data['total_order_price']) . '</td>
            </tr>
            <tr>
                <td>Jenis Pembayaran</td>
                <td>: ' . htmlspecialchars(ucfirst($order_data['payment_type'])) . '</td>
                <td>Gudang Penerima</td>
                <td>: ' . htmlspecialchars($order_data['receiving_warehouse']) . '</td>
            </tr>
        </table>

        <div class="section-title">Informasi Pembeli (Admin)</div>
        <table class="info-table">
            <tr>
                <td width="25%">Nama Pembeli</td>
                <td width="75%">: ' . htmlspecialchars($order_data['buyer_name']) . '</td>
            </tr>
            <tr>
                <td>Alamat</td>
                <td>: ' . htmlspecialchars($order_data['buyer_address']) . '</td>
            </tr>
            <tr>
                <td>Kontak</td>
                <td>: ' . htmlspecialchars($order_data['buyer_contact']) . '</td>
            </tr>
        </table>

        <div class="section-title">Informasi Supplier & Ketentuan Pembayaran</div>
        <table class="info-table">
            <tr>
                <td width="25%">Nama Perusahaan Supplier</td>
                <td width="75%">: ' . htmlspecialchars($order_data['supplier_company_name_contract'] ?? $order_data['supplier_company']) . '</td>
            </tr>
            <tr>
                <td>Nama Penanggung Jawab</td>
                <td>: ' . htmlspecialchars($order_data['supplier_pic_name_contract'] ?? $order_data['supplier_pic_original']) . '</td>
            </tr>
            <tr>
                <td>Kontak Supplier</td>
                <td>: ' . htmlspecialchars($order_data['supplier_contact_contract'] ?? $order_data['supplier_phone_original']) . '</td>
            </tr>
            <tr>
                <td>Jumlah yang Harus Dibayar</td>
                <td>: ' . formatRupiah($order_data['amount_to_pay'] ?? $order_data['total_order_price']) . '</td>
            </tr>';

            if ($order_data['payment_type'] === 'kredit' && !empty($order_data['payment_due_date'])) {
                $html .= '
                <tr>
                    <td>Tanggal Jatuh Tempo</td>
                    <td>: ' . formatTanggal($order_data['payment_due_date']) . '</td>
                </tr>';
            }
            
            $html .= '
            <tr>
                <td>Ketentuan Pembayaran</td>
                <td>: ' . nl2br(htmlspecialchars($order_data['payment_terms_description'])) . '</td>
            </tr>
        </table>

        <div class="section-title">Daftar Barang Dipesan</div>
        <table>
            <thead>
                <tr>
                    <th width="20%">Kode Barang</th>
                    <th width="40%">Nama Barang</th>
                    <th width="10%">Jumlah</th>
                    <th width="15%">Harga Satuan</th>
                    <th width="15%">Subtotal</th>
                </tr>
            </thead>
            <tbody>';
            $subtotal_items = 0;
            if ($order_items->num_rows > 0) {
                while ($item = $order_items->fetch_assoc()) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['kode_barang']) . '</td>
                        <td>' . htmlspecialchars($item['nama_barang']) . '</td>
                        <td>' . htmlspecialchars($item['quantity']) . '</td>
                        <td>' . formatRupiah($item['price_per_item']) . '</td>
                        <td>' . formatRupiah($item['subtotal_item_price']) . '</td>
                    </tr>';
                    $subtotal_items += $item['subtotal_item_price'];
                }
            } else {
                $html .= '<tr><td colspan="5" style="text-align: center;">Tidak ada item dalam pesanan ini.</td></tr>';
            }
            $html .= '
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total Keseluruhan</td>
                    <td class="text-right">' . formatRupiah($subtotal_items) . '</td>
                </tr>
            </tbody>
        </table>

        <div class="signature-section">
            <table class="signature-columns">
                <tr>
                    <td class="signature-column">
                        <p>Admin Pembeli,</p>
                        <div class="signature-line"></div>
                        <p>(' . htmlspecialchars($order_data['admin_name']) . ')</p>
                    </td>
                    <td class="signature-column">
                        <p>Supplier,</p>
                        <div class="signature-line"></div>
                        <p>(' . htmlspecialchars($order_data['supplier_company_name_contract'] ?? $order_data['supplier_company']) . ')</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>Dokumen ini dibuat secara otomatis oleh Sistem Platinum Komputer pada ' . date('d F Y H:i:s') . '</p>
        </div>
    </div>
</body>
</html>';

// Inisialisasi Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Penting jika ada gambar eksternal atau CSS dari CDN
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);

// (Opsional) Atur ukuran dan orientasi kertas
$dompdf->setPaper('A4', 'portrait');

// Render HTML menjadi PDF
$dompdf->render();

// Output PDF ke browser
$dompdf->stream("Kontrak_Pembelian_" . $order_data['order_no'] . ".pdf", ["Attachment" => false]);

exit();
?>
