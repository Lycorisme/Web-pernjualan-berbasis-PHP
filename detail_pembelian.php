<?php
// Include header
require_once __DIR__ . '/template/header.php';

// Check if user is admin
cekAdmin();

// Get purchase ID
$pembelian_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($pembelian_id <= 0) {
    setAlert('error', 'ID Pembelian tidak valid');
    header("Location: laporan_pembelian.php");
    exit();
}

// Get purchase data
$stmt = $koneksi->prepare("
    SELECT p.*, s.nama_supplier, s.alamat as alamat_supplier, s.telepon as telepon_supplier, 
           u.nama_lengkap as admin_name 
    FROM pembelian p 
    LEFT JOIN supplier s ON p.supplier_id = s.id
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.id = ?
");
$stmt->bind_param("i", $pembelian_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setAlert('error', 'Pembelian tidak ditemukan');
    header("Location: laporan_pembelian.php");
    exit();
}

$pembelian = $result->fetch_assoc();

// Get purchase details
$stmt = $koneksi->prepare("
    SELECT pd.*, b.kode_barang, b.nama_barang, s.nama_satuan 
    FROM pembelian_detail pd 
    LEFT JOIN barang b ON pd.barang_id = b.id
    LEFT JOIN satuan s ON b.satuan_id = s.id
    WHERE pd.pembelian_id = ?
");
$stmt->bind_param("i", $pembelian_id);
$stmt->execute();
$result_detail = $stmt->get_result();
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Detail Pembelian</h6>
                <div>
                    <button class="btn btn-sm btn-light me-2" onclick="printPurchase(<?= $pembelian_id ?>)">
                        <i class="fas fa-print"></i> Cetak Nota
                    </button>
                    <a href="laporan_pembelian.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="40%">No Pembelian</th>
                                <td width="60%">: <?= $pembelian['no_pembelian'] ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal</th>
                                <td>: <?= formatTanggal($pembelian['tanggal']) ?></td>
                            </tr>
                            <tr>
                                <th>Supplier</th>
                                <td>: <?= $pembelian['nama_supplier'] ?></td>
                            </tr>
                            <tr>
                                <th>Alamat Supplier</th>
                                <td>: <?= $pembelian['alamat_supplier'] ?></td>
                            </tr>
                            <tr>
                                <th>Telepon Supplier</th>
                                <td>: <?= $pembelian['telepon_supplier'] ?></td>
                            </tr>
                            <tr>
                                <th>Admin</th>
                                <td>: <?= $pembelian['admin_name'] ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h4>Total: <?= formatRupiah($pembelian['total']) ?></h4>
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
                                <td><?= $item['kode_barang'] ?></td>
                                <td><?= $item['nama_barang'] ?></td>
                                <td><?= $item['nama_satuan'] ?></td>
                                <td><?= formatRupiah($item['harga']) ?></td>
                                <td><?= $item['jumlah'] ?></td>
                                <td><?= formatRupiah($item['subtotal']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="6" class="text-end">Total</th>
                                <th><?= formatRupiah($pembelian['total']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function printPurchase(id) {
    window.open('cetak_nota_pembelian.php?id=' + id, '_blank', 'width=400,height=600');
}
</script>

<?php
// Include footer
require_once __DIR__ . '/template/footer.php';
?>