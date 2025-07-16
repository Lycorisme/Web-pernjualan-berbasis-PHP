<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman: Hanya supplier yang bisa mengakses
cekSupplier();

$supplier_id = $_SESSION['supplier_id'];

// Ambil semua data pembelian yang statusnya 'Proses' untuk supplier ini
$stmt = $koneksi->prepare("
    SELECT p.id, p.no_pembelian, p.tanggal, p.total, p.bukti_transfer, u.nama_lengkap as admin
    FROM pembelian p 
    JOIN users u ON p.user_id = u.id 
    WHERE p.supplier_id = ? AND p.status = 'Proses' 
    ORDER BY p.tanggal DESC
");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$pembayaran_tertunda = $stmt->get_result();

// Memuat header
require_once __DIR__ . '/template/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h6 class="m-0 font-weight-bold">Konfirmasi Pembayaran Tertunda</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            Halaman ini berisi daftar pembayaran dari Platinum Komputer yang menunggu konfirmasi dari Anda. Silakan periksa bukti transfer dan klik tombol "Konfirmasi" jika pembayaran sudah Anda terima.
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>No</th>
                        <th>Tanggal Pembelian</th>
                        <th>No. Pembelian</th>
                        <th>Total Dibayar</th>
                        <th>Admin Pembeli</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($pembayaran_tertunda->num_rows > 0): $no = 1; ?>
                        <?php while ($row = $pembayaran_tertunda->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= formatTanggal($row['tanggal']) ?></td>
                            <td><?= htmlspecialchars($row['no_pembelian']) ?></td>
                            <td><?= formatRupiah($row['total']) ?></td>
                            <td><?= htmlspecialchars($row['admin']) ?></td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL . 'uploads/bukti/' . htmlspecialchars($row['bukti_transfer']) ?>" target="_blank" class="btn btn-info text-white" title="Lihat Bukti Transfer">
                                        <i class="fas fa-receipt"></i> Lihat Bukti
                                    </a>
                                    <button onclick="konfirmasiPembayaran(<?= $row['id'] ?>, '<?= htmlspecialchars($row['no_pembelian']) ?>')" class="btn btn-success" title="Konfirmasi Pembayaran">
                                        <i class="fas fa-check-circle"></i> Konfirmasi
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">Tidak ada pembayaran yang menunggu konfirmasi saat ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function konfirmasiPembayaran(pembelianId, noPembelian) {
    Swal.fire({
        title: 'Konfirmasi Penerimaan Dana',
        html: `Apakah Anda yakin sudah menerima pembayaran untuk pembelian <strong>${noPembelian}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Sudah Diterima!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('pembelian_id', pembelianId);

            fetch('ajax/konfirmasi_pembayaran_supplier.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil!', data.message, 'success').then(() => {
                        location.reload(); // Muat ulang halaman untuk menghilangkan data dari tabel
                    });
                } else {
                    Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error', 'Gagal terhubung ke server.', 'error');
            });
        }
    });
}
</script>


<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>