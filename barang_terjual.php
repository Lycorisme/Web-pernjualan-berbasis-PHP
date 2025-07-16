<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman: Hanya supplier yang bisa mengakses
cekSupplier();

$supplier_id = $_SESSION['supplier_id'];
$nama_supplier = $_SESSION['nama_lengkap'];

// Query untuk mengambil semua detail barang dari semua pembelian yang terkait dengan supplier ini
$query = "
    SELECT 
        p.no_pembelian, 
        p.tanggal, 
        b.nama_barang, 
        pd.jumlah, 
        pd.harga, 
        pd.subtotal,
        u.nama_lengkap as admin_pembeli
    FROM pembelian_detail pd
    JOIN pembelian p ON pd.pembelian_id = p.id
    JOIN barang b ON pd.barang_id = b.id
    JOIN users u ON p.user_id = u.id
    WHERE p.supplier_id = ?
    ORDER BY p.tanggal DESC, p.id DESC
";

$stmt = $koneksi->prepare($query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

// Memuat header
require_once __DIR__ . '/template/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Riwayat Barang Terjual</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Tanggal Pembelian</th>
                        <th>No. Pembelian</th>
                        <th>Nama Barang</th>
                        <th>Jumlah</th>
                        <th>Harga Satuan</th>
                        <th>Subtotal</th>
                        <th>Dibeli oleh Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($result->num_rows > 0):
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars(formatTanggal($row['tanggal'])) ?></td>
                        <td><?= htmlspecialchars($row['no_pembelian']) ?></td>
                        <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                        <td><?= htmlspecialchars($row['jumlah']) ?></td>
                        <td><?= formatRupiah($row['harga']) ?></td>
                        <td><?= formatRupiah($row['subtotal']) ?></td>
                        <td><?= htmlspecialchars($row['admin_pembeli']) ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr><td colspan="8" class="text-center py-3">Belum ada barang Anda yang terjual.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>