<?php
// Start output buffering untuk mencegah output sebelum header
ob_start();

// Include required files
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Check if user is admin
cekAdmin();

// Check if export to PDF requested
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Clean any previous output
    ob_clean();
    
    // Set default dates if not provided
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    
    // Redirect to PDF export page
    header("Location: export_laporan_penjualan.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date));
    ob_end_flush();
    exit();
}

// Check if export to Excel requested
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Clean any previous output
    ob_clean();
    
    // Set default dates if not provided
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    
    // Redirect to Excel export page
    header("Location: export_laporan_penjualan_excel.php?start_date=" . urlencode($start_date) . "&end_date=" . urlencode($end_date));
    ob_end_flush();
    exit();
}

// Include header after handling exports
require_once __DIR__ . '/template/header.php';

// Set default dates (current month)
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
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Laporan Penjualan</h6>
                <div>
                    <a href="?start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&export=pdf" class="btn btn-sm btn-light me-2">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </a>
<!--                     <a href="?start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>&export=excel" class="btn btn-sm btn-light">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </a> -->
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Form -->
                <form method="GET" action="" class="mb-4">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= htmlspecialchars($start_date) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= htmlspecialchars($end_date) ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="alert alert-info mb-0 p-2">
                                <strong>Total Penjualan:</strong> <?= formatRupiah($total_penjualan) ?> (<?= $jumlah_transaksi ?> transaksi)
                            </div>
                        </div>
                    </div>
                </form>
                
                <!-- Transactions Table -->
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>No Transaksi</th>
                                <th>Tanggal</th>
                                <th>Kasir</th>
                                <th>Total</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($data_transaksi)):
                                $no = 1;
                                foreach ($data_transaksi as $row):
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                <td><?= formatTanggal($row['tanggal']) ?></td>
                                <td><?= htmlspecialchars($row['kasir']) ?></td>
                                <td><?= formatRupiah($row['total']) ?></td>
                                <td>
                                    <a href="detail_transaksi.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white">
                                        <i class="fas fa-eye"></i> Detail
                                    </a>
                                    <button class="btn btn-sm btn-primary" onclick="printReceipt(<?= (int)$row['id'] ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                                endforeach;
                            else:
                            ?>
                            <tr>
                                <td colspan="6" class="text-center py-3">Tidak ada data transaksi pada periode yang dipilih</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="4" class="text-end">Total</th>
                                <th colspan="2"><?= formatRupiah($total_penjualan) ?></th>
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
// Include footer
require_once __DIR__ . '/template/footer.php';

// End output buffering
ob_end_flush();
?>