<?php
// FILE: barang_diretur_page.php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi halaman, HANYA UNTUK SUPPLIER
cekSupplier();
$supplier_id = $_SESSION['supplier_id']; // Dapatkan ID supplier dari sesi

// Query untuk mengambil data barang yang sudah diretur dan DITERIMA OLEH SUPPLIER INI
// Join dengan tabel retur untuk no_retur dan barang untuk detail barang
$query_barang_diretur = "
    SELECT 
        bd.id,
        bd.jumlah,
        bd.tanggal_masuk,
        bd.catatan_supplier,
        r.no_retur,
        b.kode_barang,
        b.nama_barang,
        b.harga_beli, -- Harga beli dari perspektif supplier (saat admin membeli dari supplier)
        s.nama_supplier,
        s.nama_perusahaan
    FROM barang_diretur bd
    JOIN retur r ON bd.retur_id = r.id
    JOIN barang b ON bd.barang_id = b.id -- b.id di sini adalah ID barang milik supplier
    JOIN supplier s ON r.supplier_id = s.id
    WHERE s.id = ? -- Filter berdasarkan ID supplier yang sedang login
    ORDER BY bd.tanggal_masuk DESC, bd.id DESC
";

$stmt_barang_diretur = $koneksi->prepare($query_barang_diretur);
$stmt_barang_diretur->bind_param("i", $supplier_id); // Bind ID supplier
$stmt_barang_diretur->execute();
$result_barang_diretur = $stmt_barang_diretur->get_result();

require_once __DIR__ . '/template/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Barang Retur yang Anda Terima</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Tanggal Masuk Retur</th>
                        <th>No. Retur Asal</th>
                        <th>Nama Barang</th>
                        <th>Kode Barang</th>
                        <th>Jumlah</th>
                        <th>Harga Beli Retur</th>
                        <th>Catatan Anda</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_barang_diretur->num_rows > 0): $no = 1; ?>
                        <?php while($row = $result_barang_diretur->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= formatTanggal($row['tanggal_masuk']) ?></td>
                            <td><?= htmlspecialchars($row['no_retur']) ?></td>
                            <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                            <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                            <td><?= htmlspecialchars($row['jumlah']) ?></td>
                            <td><?= formatRupiah($row['harga_beli']) ?></td>
                            <td><?= htmlspecialchars($row['catatan_supplier']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">Tidak ada barang retur yang tercatat Anda terima.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/template/footer.php'; ?>