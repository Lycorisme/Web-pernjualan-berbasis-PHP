<?php
// ===================================================================================
// FILE INTI - WAJIB ADA DI ATAS UNTUK MENGHINDARI SEMUA ERROR
// Memuat file koneksi database dan semua fungsi bantuan terlebih dahulu.
// ===================================================================================
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// ===================================================================================
// LOGIKA BACKEND HALAMAN
// Menjalankan fungsi dan mengambil data setelah semua dependensi siap.
// ===================================================================================

// Proteksi Halaman: Memastikan hanya supplier yang bisa mengakses.
// Fungsi ini sekarang dijamin ada karena helper.php sudah dimuat.
cekSupplier();

// Mengambil ID supplier dari sesi yang aktif
$supplier_id = $_SESSION['supplier_id'];

// Mengambil data statistik khusus untuk supplier ini
$stmt_total = $koneksi->prepare("SELECT SUM(total) as total_pembelian FROM pembelian WHERE supplier_id = ?");
$stmt_total->bind_param("i", $supplier_id);
$stmt_total->execute();
$total_pembelian = $stmt_total->get_result()->fetch_assoc()['total_pembelian'];

$stmt_count = $koneksi->prepare("SELECT COUNT(id) as jumlah_transaksi FROM pembelian WHERE supplier_id = ?");
$stmt_count->bind_param("i", $supplier_id);
$stmt_count->execute();
$jumlah_transaksi = $stmt_count->get_result()->fetch_assoc()['jumlah_transaksi'];


// ===================================================================================
// MEMUAT TAMPILAN (VIEW)
// Header dimuat setelah semua logika backend selesai.
// ===================================================================================
require_once __DIR__ . '/template/header.php';
?>

<div class="mb-4">
    <h3>Selamat Datang di Dashboard Supplier, <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>!</h3>
    <p>Halaman ini berisi ringkasan aktivitas bisnis Anda dengan Platinum Komputer.</p>
</div>

<div class="row">
    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Nilai Pembelian (dari Anda)</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= formatRupiah($total_pembelian ?? 0) ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Jumlah Transaksi Pembelian</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $jumlah_transaksi ?? 0 ?> Transaksi</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Informasi</h6>
    </div>
    <div class="card-body">
        <p>Selamat datang di portal supplier Platinum Komputer. Anda dapat menggunakan halaman ini untuk melihat ringkasan performa Anda. Fitur-fitur lainnya akan segera ditambahkan.</p>
        <a href="logout.php" class="btn btn-primary">Logout</a>
    </div>
</div>
<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>