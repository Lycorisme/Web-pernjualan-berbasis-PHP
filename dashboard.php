<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi halaman
cekLogin();

// Logika Backend untuk mengambil data
$stmt_sales = $koneksi->prepare("SELECT COUNT(*) as total_transactions, COALESCE(SUM(total), 0) as total_sales FROM transaksi WHERE tanggal = CURDATE()");
$stmt_sales->execute();
$sales_today = $stmt_sales->get_result()->fetch_assoc();

$stmt_low_stock = $koneksi->prepare("SELECT COUNT(*) as count FROM barang WHERE stok < 5");
$stmt_low_stock->execute();
$low_stock = $stmt_low_stock->get_result()->fetch_assoc();

$stmt_total_products = $koneksi->prepare("SELECT COUNT(*) as count FROM barang");
$stmt_total_products->execute();
$total_products = $stmt_total_products->get_result()->fetch_assoc();

$stmt_total_suppliers = $koneksi->prepare("SELECT COUNT(*) as count FROM supplier");
$stmt_total_suppliers->execute();
$total_suppliers = $stmt_total_suppliers->get_result()->fetch_assoc();

$stmt_recent = $koneksi->prepare("SELECT t.id, t.no_transaksi, t.tanggal, t.total, u.nama_lengkap FROM transaksi t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.tanggal DESC, t.id DESC LIMIT 5");
$stmt_recent->execute();
$recent_transactions = $stmt_recent->get_result();

$stmt_low_items = $koneksi->prepare("SELECT b.id, b.kode_barang, b.nama_barang, b.stok, k.nama_kategori, s.nama_satuan FROM barang b LEFT JOIN kategori k ON b.kategori_id = k.id LEFT JOIN satuan s ON b.satuan_id = s.id WHERE b.stok < 5 ORDER BY b.stok ASC LIMIT 5");
$stmt_low_items->execute();
$low_stock_items = $stmt_low_items->get_result();

// Memuat header (tampilan) setelah semua data siap
require_once __DIR__ . '/template/header.php';
?>

<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Penjualan Hari Ini</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= formatRupiah($sales_today['total_sales']) ?></div>
                        <div class="text-xs text-muted"><?= $sales_today['total_transactions'] ?> transaksi</div>
                    </div>
                    <div class="col-auto"><i class="fas fa-calendar fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Barang</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($total_products['count']) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-box fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Stok Menipis</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($low_stock['count']) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Supplier</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= htmlspecialchars($total_suppliers['count']) ?></div>
                    </div>
                    <div class="col-auto"><i class="fas fa-truck fa-2x text-gray-300"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold">Aksi Cepat</h6></div>
            <div class="card-body">
                <div class="row">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="col-md-3 mb-2"><a href="users.php" class="btn btn-primary w-100"><i class="fas fa-users me-2"></i> Kelola User</a></div>
                        <div class="col-md-3 mb-2"><a href="form_barang.php" class="btn btn-success w-100"><i class="fas fa-plus me-2"></i> Tambah Barang</a></div>
                        <div class="col-md-3 mb-2"><a href="supplier.php" class="btn btn-info w-100 text-white"><i class="fas fa-dolly me-2"></i> Pembelian Barang</a></div>
                        <div class="col-md-3 mb-2"><a href="laporan_penjualan.php" class="btn btn-secondary w-100"><i class="fas fa-chart-line me-2"></i> Lihat Laporan</a></div>
                    <?php else: ?>
                        <div class="col-md-12"><a href="transaksi.php" class="btn btn-primary w-100"><i class="fas fa-cash-register me-2"></i> Buka Kasir / Transaksi Baru</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold">5 Transaksi Terbaru</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>No Transaksi</th><th>Tanggal</th><th>Kasir</th><th>Total</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php if ($recent_transactions->num_rows > 0): while($row = $recent_transactions->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['no_transaksi']) ?></td>
                                <td><?= formatTanggal($row['tanggal']) ?></td>
                                <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                <td><?= formatRupiah($row['total']) ?></td>
                                <td><a href="detail_transaksi.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info text-white" title="Lihat Detail"><i class="fas fa-eye"></i></a></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="5" class="text-center">Belum ada transaksi.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card shadow">
            <div class="card-header bg-danger text-white"><h6 class="m-0 font-weight-bold">Barang Stok Kritis</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Nama Barang</th><th>Sisa Stok</th></tr></thead>
                        <tbody>
                             <?php if ($low_stock_items->num_rows > 0): while($row = $low_stock_items->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><span class="badge bg-danger"><?= htmlspecialchars($row['stok'] . ' ' . $row['nama_satuan']) ?></span></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="2" class="text-center">Stok semua barang dalam kondisi aman.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>