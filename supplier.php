<?php
// FILE: supplier.php

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';
cekAdmin();

// Logika untuk handle edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $nama_supplier = sanitize($_POST['nama_supplier']);
    $nama_perusahaan = sanitize($_POST['nama_perusahaan']);
    $alamat = sanitize($_POST['alamat']);
    $deskripsi = sanitize($_POST['deskripsi'] ?? '');
    $telepon = sanitize($_POST['telepon']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (!empty($password)) {
        $stmt = $koneksi->prepare("UPDATE supplier SET nama_supplier = ?, nama_perusahaan = ?, alamat = ?, deskripsi = ?, telepon = ?, email = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssssssi", $nama_supplier, $nama_perusahaan, $alamat, $deskripsi, $telepon, $email, $password, $id);
    } else {
        $stmt = $koneksi->prepare("UPDATE supplier SET nama_supplier = ?, nama_perusahaan = ?, alamat = ?, deskripsi = ?, telepon = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $nama_supplier, $nama_perusahaan, $alamat, $deskripsi, $telepon, $email, $id);
    }
    
    if ($stmt->execute()) setAlert('success', 'Supplier berhasil diperbarui.');
    else setAlert('error', 'Gagal memperbarui data.');
    
    header("Location: supplier.php");
    exit();
}

// Logika untuk handle delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt_check = $koneksi->prepare("SELECT COUNT(*) as count FROM pembelian WHERE supplier_id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $count = $stmt_check->get_result()->fetch_assoc()['count'];
    
    if ($count > 0) {
        setAlert('error', "Supplier tidak dapat dihapus karena sudah memiliki data pembelian.");
    } else {
        $stmt_delete = $koneksi->prepare("DELETE FROM supplier WHERE id = ?");
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute()) setAlert('success', 'Supplier berhasil dihapus.');
        else setAlert('error', 'Gagal menghapus supplier.');
    }
    header("Location: supplier.php");
    exit();
}

// Mengambil data supplier untuk ditampilkan
$result = $koneksi->query("SELECT * FROM supplier ORDER BY nama_perusahaan");
require_once __DIR__ . '/template/header.php';
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold">Daftar Supplier</h6>
            <button class="btn btn-info position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasPending">
                Permintaan Pendaftar
                <span id="pending-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none">0</span>
            </button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr><th>No</th><th>Perusahaan</th><th>Nama Supplier</th><th>Email</th><th>Password</th><th>Aksi</th></tr>
                </thead>
                <tbody id="supplier-table-body">
                    <?php if ($result && $result->num_rows > 0): $no = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['nama_perusahaan']) ?></td>
                        <td><?= htmlspecialchars($row['nama_supplier']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['password']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="lihat_barang_supplier.php?id=<?= $row['id'] ?>" class="btn btn-info text-white" title="Lihat Barang"><i class="fas fa-box-open"></i></a>
                                <button type="button" class="btn btn-warning btn-edit" data-supplier='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>' title="Edit"><i class="fas fa-edit"></i></button>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-danger" title="Hapus"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center">Belum ada data supplier terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasPending">
  <div class="offcanvas-header"><h5 class="offcanvas-title">Pendaftar Menunggu Persetujuan</h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body"><div id="pending-list" class="list-group"></div></div>
</div>

<div class="modal fade" id="detailSupplierModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="detailModalLabel">Detail Pendaftar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body"><dl id="detail-list" class="row"></dl></div>
  </div></div>
</div>

<div class="modal fade" id="commentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="commentModalLabel">Catatan untuk Pendaftar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="comment-list" class="mb-3"></div><hr>
        <form id="form-add-comment"><input type="hidden" id="comment-registration-id" name="id">
          <div class="mb-3"><label for="comment-text" class="form-label">Tambah Komentar Baru</label><textarea class="form-control" id="comment-text" name="comment" rows="3" required></textarea></div>
          <button type="button" id="btn-submit-comment" class="btn btn-primary w-100">Kirim Komentar</button>
        </form>
      </div>
  </div></div>
</div>

<div class="modal fade" id="editSupplierModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Data Supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form id="edit-supplier-form" method="POST" action="supplier.php">
        <div class="modal-body">
            <input type="hidden" name="id" id="edit-id">
            <div class="mb-3"><label class="form-label">Nama Supplier</label><input type="text" class="form-control" name="nama_supplier" id="edit-nama-supplier" required></div>
            <div class="mb-3"><label class="form-label">Nama Perusahaan</label><input type="text" class="form-control" name="nama_perusahaan" id="edit-nama-perusahaan" required></div>
            <div class="mb-3"><label class="form-label">Alamat</label><textarea class="form-control" name="alamat" id="edit-alamat" rows="3" required></textarea></div>
            <div class="mb-3"><label class="form-label">Deskripsi</label><textarea class="form-control" name="deskripsi" id="edit-deskripsi" rows="3"></textarea></div>
            <div class="mb-3"><label class="form-label">Telepon</label><input type="text" class="form-control" name="telepon" id="edit-telepon"></div>
            <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="edit-email" required></div>
            <div class="mb-3"><label class="form-label">Password Baru (Opsional)</label><input type="text" class="form-control" name="password" placeholder="Kosongkan jika tidak diubah"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
      </form>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // ** PERBAIKAN: Semua fungsi didefinisikan di scope global agar bisa diakses oleh `onclick` **

    function loadPendingSuppliers() {
        fetch('ajax/get_pending_suppliers.php').then(response => response.json()).then(data => {
            const listContainer = document.getElementById('pending-list');
            const countBadge = document.getElementById('pending-count');
            countBadge.innerText = data.length > 0 ? data.length : '';
            if (data.length > 0) countBadge.classList.remove('d-none'); else countBadge.classList.add('d-none');
            listContainer.innerHTML = '';
            if (data.length > 0) {
                data.forEach(supplier => {
                    const supplierJson = JSON.stringify(supplier);
                    listContainer.innerHTML += `<div class="list-group-item list-group-item-action"><div class="d-flex w-100 justify-content-between"><h6 class="mb-1">${supplier.nama_perusahaan}</h6><small>${new Date(supplier.created_at).toLocaleDateString('id-ID')}</small></div><p class="mb-1">${supplier.nama_supplier} - ${supplier.email}</p><div class="btn-group mt-2 btn-group-sm"><button class="btn btn-secondary" onclick='showDetailModal(${supplierJson})'><i class="fas fa-eye"></i></button><button class="btn btn-primary" onclick="openCommentModal(${supplier.id}, '${supplier.nama_perusahaan}')"><i class="fas fa-comments"></i></button><button class="btn btn-success" onclick="approveSupplier(${supplier.id}, '${supplier.nama_perusahaan}')"><i class="fas fa-check"></i></button><button class="btn btn-danger" onclick="rejectSupplier(${supplier.id}, '${supplier.nama_perusahaan}')"><i class="fas fa-times"></i></button></div></div>`;
                });
            } else {
                listContainer.innerHTML = '<p class="text-center text-muted">Tidak ada pendaftar baru.</p>';
            }
        });
    }

    function showDetailModal(supplierData) {
        const detailList = document.getElementById('detail-list');
        document.getElementById('detailModalLabel').innerText = `Detail: ${supplierData.nama_perusahaan}`;
        detailList.innerHTML = `<dt class="col-sm-4">Nama PIC</dt><dd class="col-sm-8">: ${supplierData.nama_supplier}</dd><dt class="col-sm-4">Perusahaan</dt><dd class="col-sm-8">: ${supplierData.nama_perusahaan}</dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8">: ${supplierData.email}</dd><dt class="col-sm-4">Telepon</dt><dd class="col-sm-8">: ${supplierData.telepon || '-'}</dd><dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">: ${supplierData.alamat}</dd><dt class="col-sm-4">Deskripsi</dt><dd class="col-sm-8">: ${supplierData.deskripsi || '-'}</dd><dt class="col-sm-4">Password</dt><dd class="col-sm-8">: ${supplierData.password}</dd>`;
        new bootstrap.Modal(document.getElementById('detailSupplierModal')).show();
    }

    function approveSupplier(id, name) {
        Swal.fire({title: 'Anda yakin?', text: `Anda akan menyetujui "${name}" sebagai supplier.`, icon: 'question', showCancelButton: true, confirmButtonColor: '#28a745', confirmButtonText: 'Ya, Setujui!'}).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData(); formData.append('id', id);
                fetch('ajax/approve_supplier.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                    if (data.success) { Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload()); } 
                    else { Swal.fire('Gagal!', data.message, 'error'); }
                });
            }
        });
    }

    function rejectSupplier(id, name) {
        Swal.fire({title: 'Anda yakin?', text: `Anda akan menolak pendaftaran dari "${name}".`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Tolak!'}).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData(); formData.append('id', id);
                fetch('ajax/reject_supplier.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => {
                    if (data.success) { Swal.fire('Ditolak!', data.message, 'info'); loadPendingSuppliers(); }
                    else { Swal.fire('Gagal!', data.message, 'error'); }
                });
            }
        });
    }

    function confirmDelete(id) {
        Swal.fire({title: 'Anda yakin?', text: "Data supplier akan dihapus permanen!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!'}).then((result) => {
            if (result.isConfirmed) window.location.href = 'supplier.php?delete=' + id;
        });
    }

    function openCommentModal(id, name) {
        const modal = new bootstrap.Modal(document.getElementById('commentModal'));
        document.getElementById('commentModalLabel').innerText = `Catatan untuk: ${name}`;
        document.getElementById('comment-registration-id').value = id;
        document.getElementById('form-add-comment').reset();
        const commentList = document.getElementById('comment-list');
        commentList.innerHTML = '<p class="text-center">Memuat komentar...</p>';
        fetch(`ajax/get_comments.php?id=${id}`).then(response => response.json()).then(data => {
            commentList.innerHTML = '';
            if (data.success && data.comments.length > 0) {
                data.comments.forEach(c => {
                    commentList.innerHTML += `<div class="card bg-light mb-2"><div class="card-body p-2"><p class="card-text mb-1">${c.comment.replace(/\n/g, '<br>')}</p><small class="text-muted d-block text-end">Oleh: <strong>${c.admin_name}</strong> - ${c.created_at}</small></div></div>`;
                });
            } else {
                commentList.innerHTML = '<p class="text-center text-muted">Belum ada komentar.</p>';
            }
        });
        modal.show();
    }
    
    // ** PERBAIKAN: Event listener dipindahkan ke dalam DOMContentLoaded **
    document.addEventListener('DOMContentLoaded', function() {
        // Panggil fungsi untuk memuat data saat halaman siap
        loadPendingSuppliers();

        // Event listener untuk tombol edit di tabel utama
        document.getElementById('supplier-table-body').addEventListener('click', function(e) {
            const editButton = e.target.closest('.btn-edit');
            if (editButton) {
                const data = JSON.parse(editButton.dataset.supplier);
                document.getElementById('edit-id').value = data.id;
                document.getElementById('edit-nama-supplier').value = data.nama_supplier;
                document.getElementById('edit-nama-perusahaan').value = data.nama_perusahaan;
                document.getElementById('edit-alamat').value = data.alamat;
                document.getElementById('edit-deskripsi').value = data.deskripsi || '';
                document.getElementById('edit-telepon').value = data.telepon;
                document.getElementById('edit-email').value = data.email;
                new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
            }
        });
        
        // Event listener untuk tombol kirim komentar
        document.getElementById('btn-submit-comment').addEventListener('click', function() {
            const form = document.getElementById('form-add-comment');
            const formData = new FormData(form);
            const regId = formData.get('id');
            const pendaftarName = document.getElementById('commentModalLabel').innerText.replace('Catatan untuk: ', '');
            
            if (!formData.get('comment').trim()) {
                Swal.fire('Gagal!', 'Komentar tidak boleh kosong.', 'error');
                return;
            }

            fetch('ajax/add_comment.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    openCommentModal(regId, pendaftarName);
                } else {
                    Swal.fire('Gagal!', data.message, 'error');
                }
            });
        });
    });
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>