<?php
// FILE: retur_supplier.php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi halaman, hanya untuk supplier
cekSupplier();
$supplier_id = $_SESSION['supplier_id'];

// Query lengkap untuk mengambil semua data yang dibutuhkan untuk riwayat dan modal detail
$retur_history_query = "
    SELECT
        r.*,
        b.nama_barang,
        b.kode_barang,
        p.no_pembelian,
        u.nama_lengkap as admin_pembuat,
        (SELECT GROUP_CONCAT(rp.nama_file) FROM retur_photos rp WHERE rp.retur_id = r.id) as foto_retur
    FROM retur r
    JOIN barang b ON r.barang_id = b.id
    JOIN users u ON r.admin_id = u.id
    JOIN pembelian p ON r.pembelian_id = p.id
    WHERE r.supplier_id = ?
    ORDER BY r.id DESC
";
$retur_history = $koneksi->prepare($retur_history_query);
$retur_history->bind_param("i", $supplier_id);
$retur_history->execute();
$result = $retur_history->get_result();

require_once __DIR__ . '/template/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Permintaan Retur Barang</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="retur-table-supplier">
                <thead class="table-dark">
                    <tr><th>No. Retur</th><th>Tanggal</th><th>Barang</th><th>Jumlah</th><th>Status</th><th>Admin</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['no_retur']) ?></td>
                        <td><?= formatTanggal($row['tanggal_retur']) ?></td>
                        <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                        <td><?= $row['jumlah'] ?></td>
                        <td><?= buatBadgeStatus($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['admin_pembuat']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info btn-detail-retur" data-retur='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-eye"></i> Detail
                            </button>
                            <?php if($row['status'] == 'Menunggu Persetujuan'): ?>
                                <button class="btn btn-sm btn-success" onclick="approveRetur(<?= $row['id'] ?>)"><i class="fas fa-check"></i> Setujui</button>
                                <button class="btn btn-sm btn-danger" onclick="rejectRetur(<?= $row['id'] ?>)"><i class="fas fa-times"></i> Tolak</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center">Belum ada permintaan retur untuk Anda.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="detailReturModal" tabindex="-1" aria-labelledby="detailReturModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailReturModalLabel">Detail Permintaan Retur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <dl id="detail-list" class="row"></dl>
        <hr>
        <h6><i class="fas fa-images"></i> Foto Bukti</h6>
        <div id="photo-gallery" class="row g-2"></div>
      </div>
       <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
// JavaScript untuk menangani klik tombol detail
document.getElementById('retur-table-supplier').addEventListener('click', function(e) {
    const button = e.target.closest('.btn-detail-retur');
    if (button) {
        const returData = JSON.parse(button.dataset.retur);

        document.getElementById('detailReturModalLabel').innerText = `Detail Retur: ${returData.no_retur}`;

        const detailList = document.getElementById('detail-list');
        detailList.innerHTML = `
            <dt class="col-sm-4">No. Pembelian Asal</dt><dd class="col-sm-8">: ${returData.no_pembelian}</dd>
            <dt class="col-sm-4">Admin Pembuat</dt><dd class="col-sm-8">: ${returData.admin_pembuat}</dd>
            <hr class="my-2">
            <dt class="col-sm-4">Kode Barang</dt><dd class="col-sm-8">: ${returData.kode_barang}</dd>
            <dt class="col-sm-4">Nama Barang</dt><dd class="col-sm-8">: ${returData.nama_barang}</dd>
            <dt class="col-sm-4">Jumlah Retur</dt><dd class="col-sm-8">: ${returData.jumlah} unit</dd>
            <dt class="col-sm-4">Alasan</dt><dd class="col-sm-8">: ${returData.alasan}</dd>
        `;

        const photoGallery = document.getElementById('photo-gallery');
        if (returData.foto_retur) {
            photoGallery.innerHTML = '';
            const photos = returData.foto_retur.split(',');
            photos.forEach(photo => {
                photoGallery.innerHTML += `<div class="col-md-4 mb-2"><a href="uploads/retur/${photo}" target="_blank"><img src="uploads/retur/${photo}" class="img-fluid rounded" alt="Bukti Retur"></a></div>`;
            });
        } else {
            photoGallery.innerHTML = '<p class="text-muted">Tidak ada foto bukti yang dilampirkan.</p>';
        }

        const detailModal = new bootstrap.Modal(document.getElementById('detailReturModal'));
        detailModal.show();
    }
});

// JavaScript untuk approve/reject retur
function updateStatus(id, status) {
    const formData = new FormData();
    formData.append('retur_id', id);
    formData.append('status', status);

    fetch('ajax/update_retur_status.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Gagal!', data.message, 'error');
        }
    }).catch(err => Swal.fire('Error!', 'Terjadi kesalahan koneksi.', 'error'));
}

function approveRetur(id) {
    Swal.fire({
        title: 'Anda yakin?', text: "Anda akan menyetujui permintaan retur ini.", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Setujui!', cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) { updateStatus(id, 'Disetujui'); }
    });
}

function rejectRetur(id) {
    Swal.fire({
        title: 'Anda yakin?', text: "Anda akan menolak permintaan retur ini. Stok akan dikembalikan ke pengirim.", icon: 'warning',
        showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Tolak!', cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) { updateStatus(id, 'Ditolak'); }
    });
}
</script>

<?php require_once __DIR__ . '/template/footer.php'; ?>