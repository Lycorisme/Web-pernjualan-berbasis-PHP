<?php
// FILE INTI - WAJIB ADA DI ATAS UNTUK MENGHINDARI ERROR
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman: Memastikan hanya user yang sudah login bisa mengakses
cekLogin();

// Ambil dan validasi ID transaksi dari URL
$transaksi_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($transaksi_id <= 0) {
    setAlert('error', 'ID Transaksi tidak valid');
    header("Location: dashboard.php");
    exit();
}

// Ambil data transaksi utama
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
    setAlert('error', 'Transaksi tidak ditemukan');
    header("Location: dashboard.php");
    exit();
}
$transaksi = $result->fetch_assoc();

// Ambil detail item dari transaksi tersebut
$stmt_detail = $koneksi->prepare("
    SELECT td.*, b.kode_barang, b.nama_barang, s.nama_satuan 
    FROM transaksi_detail td 
    LEFT JOIN barang b ON td.barang_id = b.id
    LEFT JOIN satuan s ON b.satuan_id = s.id
    WHERE td.transaksi_id = ?
");
$stmt_detail->bind_param("i", $transaksi_id);
$stmt_detail->execute();
$result_detail = $stmt_detail->get_result();

// Memuat header setelah semua logika backend selesai
require_once __DIR__ . '/template/header.php';
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Detail Transaksi</h6>
                <div>
                    <button class="btn btn-sm btn-light me-2" onclick="printReceipt(<?= $transaksi_id ?>)">
                        <i class="fas fa-print"></i> Cetak Struk
                    </button>
                    <a href="<?= $_SESSION['role'] === 'admin' ? 'laporan_penjualan.php' : 'dashboard.php' ?>" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">No Transaksi</th>
                                <td width="60%">: <?= htmlspecialchars($transaksi['no_transaksi']) ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal</th>
                                <td>: <?= formatTanggal($transaksi['tanggal']) ?></td>
                            </tr>
                            <tr>
                                <th>Kasir</th>
                                <td>: <?= htmlspecialchars($transaksi['kasir']) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h4>Total: <?= formatRupiah($transaksi['total']) ?></h4>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Harga</th>
                                <th>Jumlah</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            while ($item = $result_detail->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($item['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($item['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($item['nama_satuan']) ?></td>
                                <td><?= formatRupiah($item['harga']) ?></td>
                                <td><?= $item['jumlah'] ?></td>
                                <td><?= formatRupiah($item['subtotal']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="6" class="text-end">Total</th>
                                <th><?= formatRupiah($transaksi['total']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printReceipt(id) {
    window.open('cetak_struk.php?id=' + id, '_blank', 'width=400,height=600');
}
</script>

<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>