<?php
// Include header
require_once __DIR__ . '/template/header.php';

// Check if user is admin
cekAdmin();

// Set default dates (current month)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get purchases for the period
$query = "
    SELECT p.*, s.nama_supplier, u.nama_lengkap as admin 
    FROM pembelian p 
    LEFT JOIN supplier s ON p.supplier_id = s.id
    LEFT JOIN users u ON p.user_id = u.id 
    WHERE p.tanggal BETWEEN ? AND ?
    ORDER BY p.tanggal DESC, p.id DESC
";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Calculate summary
$total_pembelian = 0;
$jumlah_pembelian = 0;
$data_pembelian = [];

while ($row = $result->fetch_assoc()) {
    $data_pembelian[] = $row;
    $total_pembelian += $row['total'];
    $jumlah_pembelian++;
}

// Check if export to PDF requested
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Redirect to PDF export page
    header("Location: export_laporan_pembelian.php?start_date=$start_date&end_date=$end_date");
    exit();
}

// Check if export to Excel requested
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Redirect to Excel export page
    header("Location: export_laporan_pembelian_excel.php?start_date=$start_date&end_date=$end_date");
    exit();
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Laporan Pembelian</h6>
                <div>
                    <a href="laporan_pembelian.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=pdf" class="btn btn-sm btn-light me-2">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
                    <a href="laporan_pembelian.php?start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=excel" class="btn btn-sm btn-light">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="alert alert-info mb-0 p-2">
                                <strong>Total Pembelian:</strong> <?= formatRupiah($total_pembelian) ?> (<?= $jumlah_pembelian ?> pembelian)
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Purchases Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>No Pembelian</th>
                                <th>Tanggal</th>
                                <th>Supplier</th>
                                <th>Admin</th>
                                <th>Total</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($data_pembelian)):
                                $no = 1;
                                foreach ($data_pembelian as $row):
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $row['no_pembelian'] ?></td>
                                <td><?= formatTanggal($row['tanggal']) ?></td>
                                <td><?= $row['nama_supplier'] ?></td>
                                <td><?= $row['admin'] ?></td>
                                <td><?= formatRupiah($row['total']) ?></td>
                                <td>
                                    <a href="detail_pembelian.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                    <button class="btn btn-sm btn-primary" onclick="printPurchase(<?= $row['id'] ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="7" class="text-center py-3">Tidak ada data pembelian pada periode yang dipilih</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="5" class="text-end">Total</th>
                                <th colspan="2"><?= formatRupiah($total_pembelian) ?></th>
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