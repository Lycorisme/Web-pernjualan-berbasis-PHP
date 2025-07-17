<?php
// FILE: orders.php
// Halaman Pusat Manajemen Pesanan (Admin & Supplier)

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi halaman
cekLogin(); // Bisa diakses Admin atau Supplier
$role = $_SESSION['role'];

// Logika backend untuk mengambil data pesanan akan ditambahkan di fase selanjutnya
// Tergantung peran, query akan berbeda.

// Memuat header
require_once __DIR__ . '/template/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">Daftar Pesanan</h6>
        <?php if ($role === 'admin'): ?>
            <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($role === 'admin'): ?>
            <p>Ini adalah halaman Pesanan untuk Admin. Daftar pesanan akan ditampilkan di sini.</p>
            <?php elseif ($role === 'supplier'): ?>
            <p>Ini adalah halaman Pesanan untuk Supplier. Daftar pesanan yang masuk kepada Anda akan ditampilkan di sini.</p>
            <?php else: ?>
            <p>Anda tidak memiliki akses ke halaman ini.</p>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-bordered" id="ordersTable" width="100%" cellspacing="0">
                <thead class="table-dark">
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Admin Pembeli</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-3">Memuat data pesanan...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>