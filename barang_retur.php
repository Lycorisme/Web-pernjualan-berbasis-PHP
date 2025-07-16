<?php
// FILE: barang_retur.php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
cekAdmin();

// Ambil data untuk dropdown supplier
$suppliers = $koneksi->query("SELECT id, nama_perusahaan FROM supplier ORDER BY nama_perusahaan ASC");

// Query lengkap untuk mengambil semua data yang dibutuhkan untuk riwayat dan modal detail
$retur_history_query = "
    SELECT
        r.*,
        s.nama_perusahaan,
        b.nama_barang,
        b.kode_barang,
        p.no_pembelian,
        u.nama_lengkap as admin_pembuat,
        (SELECT GROUP_CONCAT(rp.nama_file) FROM retur_photos rp WHERE rp.retur_id = r.id) as foto_retur
    FROM retur r
    JOIN supplier s ON r.supplier_id = s.id
    JOIN barang b ON r.barang_id = b.id
    JOIN pembelian p ON r.pembelian_id = p.id
    JOIN users u ON r.admin_id = u.id
    ORDER BY r.id DESC
";
$retur_history = $koneksi->query($retur_history_query);

require_once __DIR__ . '/template/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Riwayat Retur Barang ke Supplier</h6>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#returModal">
            <i class="fas fa-plus-circle"></i> Buat Retur Baru
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="retur-table">
                <thead class="table-dark">
                    <tr><th>No. Retur</th><th>Tanggal</th><th>Supplier</th><th>Barang</th><th>Jumlah</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if ($retur_history->num_rows > 0): while($row = $retur_history->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['no_retur']) ?></td>
                        <td><?= formatTanggal($row['tanggal_retur']) ?></td>
                        <td><?= htmlspecialchars($row['nama_perusahaan']) ?></td>
                        <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                        <td><?= $row['jumlah'] ?></td>
                        <td><?= buatBadgeStatus($row['status']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info btn-detail-retur" data-retur='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'>
                                <i class="fas fa-eye"></i> Detail
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="7" class="text-center">Belum ada riwayat retur.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="returModal" tabindex="-1" aria-labelledby="returModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="returModalLabel">Form Permintaan Retur</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="form-retur" enctype="multipart/form-data">
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label">1. Pilih Supplier</label>
                <select class="form-select" id="supplier_id" name="supplier_id" required>
                    <option value="">-- Pilih --</option>
                    <?php mysqli_data_seek($suppliers, 0); // Reset pointer hasil query ?>
                    <?php while($s = $suppliers->fetch_assoc()) echo "<option value='{$s['id']}'>{$s['nama_perusahaan']}</option>"; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">2. Pilih Transaksi Pembelian</label>
                <select class="form-select" id="pembelian_id" name="pembelian_id" required disabled><option>Pilih supplier dulu</option></select>
            </div>
            <div class="mb-3">
                <label class="form-label">3. Pilih Barang yang Akan Diretur</label>
                <select class="form-select" id="barang_id" name="barang_id" required disabled><option>Pilih transaksi dulu</option></select>
            </div>
            <div class="mb-3">
                <label class="form-label">4. Jumlah Retur</label>
                <input type="number" class="form-control" name="jumlah" id="jumlah" min="1" required disabled>
                <div id="jumlah_info" class="form-text"></div>
            </div>
            <div class="mb-3">
                <label class="form-label">5. Alasan Retur</label>
                <textarea class="form-control" name="alasan" rows="3" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">6. Unggah Foto Bukti (Bisa lebih dari satu)</label>
                <input class="form-control" type="file" name="bukti_foto[]" multiple accept="image/*">
            </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Kirim Permintaan</button>
        </div>
      </form>
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
document.addEventListener('DOMContentLoaded', function() {
    // JavaScript untuk Form Retur Baru
    const supplierSelect = document.getElementById('supplier_id');
    const pembelianSelect = document.getElementById('pembelian_id');
    const barangSelect = document.getElementById('barang_id');
    const jumlahInput = document.getElementById('jumlah');
    const jumlahInfo = document.getElementById('jumlah_info');

    supplierSelect.addEventListener('change', function() {
        const supplierId = this.value;
        pembelianSelect.innerHTML = '<option value="">Memuat...</option>';
        pembelianSelect.disabled = true;
        barangSelect.innerHTML = '<option value="">Pilih transaksi dulu</option>';
        barangSelect.disabled = true;
        jumlahInput.disabled = true;
        jumlahInput.value = '';
        jumlahInfo.textContent = '';

        if (!supplierId) {
            pembelianSelect.innerHTML = '<option value="">Pilih supplier dulu</option>';
            return;
        }

        fetch(`ajax/get_purchases_for_retur.php?supplier_id=${supplierId}`)
        .then(res => res.json()).then(data => {
            pembelianSelect.innerHTML = '<option value="">-- Pilih Transaksi --</option>';
            data.forEach(p => {
                pembelianSelect.innerHTML += `<option value="${p.id}">${p.no_pembelian} (${p.tanggal})</option>`;
            });
            pembelianSelect.disabled = false;
        }).catch(err => {
            pembelianSelect.innerHTML = '<option value="">Gagal memuat data</option>';
        });
    });

    pembelianSelect.addEventListener('change', function() {
        const pembelianId = this.value;
        barangSelect.innerHTML = '<option value="">Memuat...</option>';
        barangSelect.disabled = true;
        jumlahInput.disabled = true;
        jumlahInput.value = '';
        jumlahInfo.textContent = '';

        if (!pembelianId) {
            barangSelect.innerHTML = '<option value="">Pilih transaksi dulu</option>';
            return;
        }

        fetch(`ajax/get_items_from_purchase.php?pembelian_id=${pembelianId}`)
        .then(res => res.json()).then(data => {
            barangSelect.innerHTML = '<option value="">-- Pilih Barang --</option>';
            data.forEach(item => {
                barangSelect.innerHTML += `<option value="${item.barang_id}" data-max="${item.jumlah}">${item.nama_barang}</option>`;
            });
            barangSelect.disabled = false;
        }).catch(err => {
            barangSelect.innerHTML = '<option value="">Gagal memuat data</option>';
        });
    });
    
    barangSelect.addEventListener('change', function() {
        if(this.value) {
            const maxJumlah = this.options[this.selectedIndex].dataset.max;
            jumlahInput.disabled = false;
            jumlahInput.max = maxJumlah;
            jumlahInput.placeholder = `Maksimal ${maxJumlah} unit`;
            jumlahInfo.textContent = `Jumlah yang dibeli pada transaksi ini: ${maxJumlah} unit.`;
        } else {
            jumlahInput.disabled = true;
            jumlahInput.placeholder = '';
            jumlahInfo.textContent = '';
        }
    });

    document.getElementById('form-retur').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim...';

        fetch('ajax/create_retur.php', { method: 'POST', body: formData })
        .then(res => res.json()).then(data => {
            if (data.success) {
                Swal.fire('Berhasil', data.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        }).catch(err => {
            Swal.fire('Error', 'Terjadi kesalahan koneksi.', 'error');
        }).finally(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = 'Kirim Permintaan';
        });
    });
    
    // JavaScript untuk menangani klik tombol detail
    document.getElementById('retur-table').addEventListener('click', function(e) {
        const button = e.target.closest('.btn-detail-retur');
        if (button) {
            const returData = JSON.parse(button.dataset.retur);

            document.getElementById('detailReturModalLabel').innerText = `Detail Retur: ${returData.no_retur}`;
            
            const detailList = document.getElementById('detail-list');
            detailList.innerHTML = `
                <dt class="col-sm-4">No. Pembelian Asal</dt><dd class="col-sm-8">: ${returData.no_pembelian}</dd>
                <dt class="col-sm-4">Supplier</dt><dd class="col-sm-8">: ${returData.nama_perusahaan}</dd>
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
});
</script>

<?php require_once __DIR__ . '/template/footer.php'; ?>