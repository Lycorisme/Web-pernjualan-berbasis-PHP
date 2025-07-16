<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

cekSupplier();
$supplier_id = $_SESSION['supplier_id']; // ID Supplier dideteksi dari sesi login

// Query mengambil data barang milik supplier
$query_barang = "SELECT b.id, b.kode_barang, b.nama_barang, b.kategori_id, b.satuan_id, b.foto_produk, b.harga_beli, b.harga_jual, k.nama_kategori, s.nama_satuan, b.stok 
                 FROM barang b 
                 LEFT JOIN kategori k ON b.kategori_id = k.id 
                 LEFT JOIN satuan s ON b.satuan_id = s.id 
                 WHERE b.supplier_id = ?
                 ORDER BY b.nama_barang ASC";
$stmt = $koneksi->prepare($query_barang);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

require_once __DIR__ . '/template/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Daftar Barang yang Anda Tambahkan</h6>
        <div>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#kategoriModal"><i class="fas fa-tags"></i> Kelola Kategori</button>
            <a href="form_tambah_barang_supplier.php" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Tambah Barang Baru</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                <thead class="table-dark">
                    <tr><th>No</th><th>Foto</th><th>Kode Barang</th><th>Nama Barang</th><th>Kategori</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): $no = 1; while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $foto_produk = htmlspecialchars($row['foto_produk'] ?? '');
                        $nama_barang = htmlspecialchars($row['nama_barang'] ?? '');
                        $kode_barang = htmlspecialchars($row['kode_barang'] ?? '');
                        $nama_kategori = htmlspecialchars($row['nama_kategori'] ?? '');
                        // PERBAIKAN: Mengubah harga menjadi integer untuk menghilangkan ,00
                        $harga_beli = (int)($row['harga_beli'] ?? 0);
                    ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <?php
                                $foto_path = 'uploads/produk/' . $foto_produk;
                                if (!empty($foto_produk) && file_exists($foto_path)) {
                                    echo '<img src="' . $foto_path . '" alt="' . $nama_barang . '" class="img-thumbnail" width="60" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#imagePreviewModal">';
                                } else {
                                    echo '<img src="https://placehold.co/60x60/e2e8f0/adb5bd?text=N/A" alt="Tidak ada foto" class="img-thumbnail">';
                                }
                            ?>
                        </td>
                        <td><?= $kode_barang ?></td>
                        <td><?= $nama_barang ?></td>
                        <td><?= $nama_kategori ?></td>
                        <td><?= htmlspecialchars(formatRupiah($harga_beli)) ?></td>
                        <td><span class="badge bg-<?= $row['stok'] <= 0 ? 'danger' : 'success' ?>"><?= $row['stok'] ?></span></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-warning btn-edit-barang" data-id="<?= $row['id'] ?>" data-kode="<?= $kode_barang ?>" data-nama="<?= $nama_barang ?>" data-kategori-id="<?= $row['kategori_id'] ?>" data-satuan-id="<?= $row['satuan_id'] ?>" data-harga="<?= $harga_beli ?>" data-stok="<?= $row['stok'] ?>" data-foto="<?= $foto_produk ?>" title="Edit Barang"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-outline-danger btn-hapus-barang" data-id="<?= $row['id'] ?>" data-nama="<?= $nama_barang ?>" title="Hapus Barang"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center py-3">Anda belum menambahkan barang.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Preview Gambar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body text-center"><img src="" id="imagePreviewSrc" class="img-fluid"></div></div></div></div>
<div class="modal fade" id="kategoriModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Kelola Kategori Anda</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><h6>Tambah Kategori Baru</h6><form id="form-tambah-kategori" class="mb-3"><div class="input-group"><input type="text" class="form-control" id="nama-kategori-input" placeholder="Ketik nama kategori baru..." required><button type="submit" class="btn btn-primary">Simpan</button></div></form><hr><h6>Kategori yang Sudah Anda Buat</h6><div class="list-group" id="daftar-kategori-supplier" style="max-height: 300px; overflow-y: auto;"></div></div></div></div></div>
<div class="modal fade" id="editKategoriModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Nama Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="form-edit-kategori"><input type="hidden" id="edit-kategori-id"><div class="mb-3"><label for="edit-kategori-nama" class="form-label">Nama Kategori</label><input type="text" class="form-control" id="edit-kategori-nama" required></div></form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="btn-update-kategori">Simpan Perubahan</button></div></div></div></div>
<div class="modal fade" id="editBarangModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="form-edit-barang" enctype="multipart/form-data"><input type="hidden" id="edit-barang-id"><div class="row"><div class="col-md-6"><div class="mb-3"><label for="edit-barang-kode" class="form-label">Kode Barang</label><input type="text" class="form-control" id="edit-barang-kode" readonly></div><div class="mb-3"><label for="edit-barang-nama" class="form-label">Nama Barang</label><input type="text" class="form-control" id="edit-barang-nama" required></div><div class="mb-3"><label for="edit-barang-kategori" class="form-label">Kategori</label><select class="form-select" id="edit-barang-kategori" required></select></div><div class="mb-3"><label for="edit-barang-satuan" class="form-label">Satuan</label><select class="form-select" id="edit-barang-satuan" required></select></div><div class="mb-3"><label for="edit-barang-harga" class="form-label">Harga</label><input type="text" class="form-control currency" id="edit-barang-harga" required></div><div class="mb-3"><label for="edit-barang-stok" class="form-label">Stok</label><input type="number" class="form-control" id="edit-barang-stok" min="0" required></div></div><div class="col-md-6"><div class="mb-3"><label class="form-label">Foto Produk Saat Ini</label><div id="edit-barang-foto-preview" class="text-center border rounded p-2" style="min-height: 150px;"></div></div><div class="mb-3"><label for="edit-barang-foto-input" class="form-label">Ganti Foto (Opsional)</label><input type="file" class="form-control" id="edit-barang-foto-input" name="foto_produk" accept="image/*"><small class="form-text text-muted">Pilih file baru jika ingin mengganti foto.</small></div></div></div></form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="btn-update-barang">Simpan Perubahan</button></div></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ... (Inisialisasi Modal dan Logika Kategori tidak diubah)
    const imagePreviewModalEl = document.getElementById('imagePreviewModal');
    const kategoriModalEl = document.getElementById('kategoriModal');
    const editKategoriModalEl = document.getElementById('editKategoriModal');
    const editBarangModalEl = document.getElementById('editBarangModal');
    const editKategoriModal = new bootstrap.Modal(editKategoriModalEl);
    const editBarangModal = new bootstrap.Modal(editBarangModalEl);

    if (imagePreviewModalEl) {
        imagePreviewModalEl.addEventListener('show.bs.modal', function (event) {
            const triggerElement = event.relatedTarget;
            const imageSrc = triggerElement.getAttribute('src');
            const imageAlt = triggerElement.getAttribute('alt');
            imagePreviewModalEl.querySelector('.modal-title').textContent = 'Preview: ' + imageAlt;
            imagePreviewModalEl.querySelector('#imagePreviewSrc').src = imageSrc;
        });
    }
    
    const formTambahKategori = document.getElementById('form-tambah-kategori');
    const kategoriInput = document.getElementById('nama-kategori-input');
    const daftarKategoriContainer = document.getElementById('daftar-kategori-supplier');
    const btnUpdateKategori = document.getElementById('btn-update-kategori');
    const muatDaftarKategori = async () => {
        daftarKategoriContainer.innerHTML = '<div class="list-group-item text-center"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';
        try {
            const response = await fetch('ajax/get_supplier_categories.php');
            const categories = await response.json();
            daftarKategoriContainer.innerHTML = '';
            if (categories.length > 0) {
                categories.forEach(cat => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    item.innerHTML = `<span>${cat.nama_kategori}</span><div class="btn-group btn-group-sm"><button class="btn btn-outline-warning btn-edit-kategori" data-id="${cat.id}" data-nama="${cat.nama_kategori}"><i class="fas fa-edit"></i></button><button class="btn btn-outline-danger btn-hapus-kategori" data-id="${cat.id}" data-nama="${cat.nama_kategori}"><i class="fas fa-trash"></i></button></div>`;
                    daftarKategoriContainer.appendChild(item);
                });
            } else {
                daftarKategoriContainer.innerHTML = '<div class="list-group-item text-center text-muted">Anda belum membuat kategori.</div>';
            }
        } catch (error) {
            daftarKategoriContainer.innerHTML = '<div class="list-group-item text-center text-danger">Gagal memuat daftar kategori.</div>';
        }
    };
    if (kategoriModalEl) { kategoriModalEl.addEventListener('show.bs.modal', muatDaftarKategori); }
    if(formTambahKategori) {
        formTambahKategori.addEventListener('submit', async function(e) {
            e.preventDefault();
            const namaKategori = kategoriInput.value.trim();
            if (namaKategori === '') { Swal.fire('Error', 'Nama kategori tidak boleh kosong.', 'error'); return; }
            const formData = new FormData();
            formData.append('nama_kategori', namaKategori);
            const response = await fetch('ajax/tambah_kategori_supplier.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) { Swal.fire('Berhasil!', result.message, 'success'); kategoriInput.value = ''; muatDaftarKategori(); }
            else { Swal.fire('Gagal', result.message, 'error'); }
        });
    }
    if (daftarKategoriContainer) {
        daftarKategoriContainer.addEventListener('click', function(e) {
            const btnEdit = e.target.closest('.btn-edit-kategori');
            const btnHapus = e.target.closest('.btn-hapus-kategori');
            if (btnEdit) { document.getElementById('edit-kategori-id').value = btnEdit.dataset.id; document.getElementById('edit-kategori-nama').value = btnEdit.dataset.nama; editKategoriModal.show(); }
            if (btnHapus) {
                const kategoriId = btnHapus.dataset.id; const namaKategori = btnHapus.dataset.nama;
                Swal.fire({ title: 'Anda yakin?', text: `Kategori "${namaKategori}" akan dihapus?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData(); formData.append('id', kategoriId);
                        const response = await fetch('ajax/hapus_kategori_supplier.php', { method: 'POST', body: formData });
                        const resData = await response.json();
                        Swal.fire(resData.success ? 'Dihapus!' : 'Gagal!', resData.message, resData.success ? 'success' : 'error');
                        if (resData.success) muatDaftarKategori();
                    }
                });
            }
        });
    }
    if (btnUpdateKategori) {
        btnUpdateKategori.addEventListener('click', async function() {
            const id = document.getElementById('edit-kategori-id').value; const namaBaru = document.getElementById('edit-kategori-nama').value.trim();
            if(namaBaru === '') { Swal.fire('Error', 'Nama kategori tidak boleh kosong.', 'error'); return; }
            const formData = new FormData(); formData.append('id', id); formData.append('nama', namaBaru);
            const response = await fetch('ajax/edit_kategori_supplier.php', { method: 'POST', body: formData });
            const resData = await response.json();
            editKategoriModal.hide();
            Swal.fire(resData.success ? 'Berhasil!' : 'Gagal!', resData.message, resData.success ? 'success' : 'error');
            if (resData.success) { muatDaftarKategori(); location.reload(); }
        });
    }
    
    const formatInputCurrency = (input) => {
        let value = input.value.replace(/[^0-9]/g, '');
        // Format tanpa desimal
        input.value = value ? new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value) : '';
    };

    document.querySelectorAll('.currency').forEach(input => {
        input.addEventListener('keyup', () => formatInputCurrency(input));
    });
    
    const muatOpsiEditBarang = async (kategoriIdTerpilih = null, satuanIdTerpilih = null) => {
        const kategoriSelect = document.getElementById('edit-barang-kategori');
        const satuanSelect = document.getElementById('edit-barang-satuan');
        kategoriSelect.innerHTML = '<option value="">Memuat...</option>'; satuanSelect.innerHTML = '<option value="">Memuat...</option>';
        try {
            const [kategoriResponse, satuanResponse] = await Promise.all([ fetch('ajax/get_supplier_categories.php'), fetch('ajax/get_satuan.php') ]);
            const kategoris = await kategoriResponse.json(); const satuans = await satuanResponse.json();
            kategoriSelect.innerHTML = '<option value="">-- Pilih Kategori --</option>';
            kategoris.forEach(cat => { const option = document.createElement('option'); option.value = cat.id; option.textContent = cat.nama_kategori; if (cat.id == kategoriIdTerpilih) option.selected = true; kategoriSelect.appendChild(option); });
            satuanSelect.innerHTML = '<option value="">-- Pilih Satuan --</option>';
            satuans.forEach(sat => { const option = document.createElement('option'); option.value = sat.id; option.textContent = sat.nama_satuan; if (sat.id == satuanIdTerpilih) option.selected = true; satuanSelect.appendChild(option); });
        } catch (error) { console.error('Gagal memuat opsi edit:', error); kategoriSelect.innerHTML = '<option value="">Gagal</option>'; satuanSelect.innerHTML = '<option value="">Gagal</option>'; }
    };
    
    document.querySelector('#dataTable tbody').addEventListener('click', function(e) {
        const btnEditBarang = e.target.closest('.btn-edit-barang'); 
        const btnHapusBarang = e.target.closest('.btn-hapus-barang');
        
        if (btnEditBarang) {
            const data = btnEditBarang.dataset;
            document.getElementById('edit-barang-id').value = data.id; 
            document.getElementById('edit-barang-kode').value = data.kode; 
            document.getElementById('edit-barang-nama').value = data.nama; 
            document.getElementById('edit-barang-stok').value = data.stok;
            const hargaInput = document.getElementById('edit-barang-harga');
            hargaInput.value = data.harga;
            formatInputCurrency(hargaInput);
            
            const fotoPreviewContainer = document.getElementById('edit-barang-foto-preview');
            const fotoInput = document.getElementById('edit-barang-foto-input');
            fotoInput.value = '';
            if (data.foto) { fotoPreviewContainer.innerHTML = `<img src="uploads/produk/${data.foto}" class="img-fluid rounded" alt="Foto Produk">`; }
            else { fotoPreviewContainer.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100 text-muted">Tidak ada foto</div>'; }
            
            muatOpsiEditBarang(data.kategoriId, data.satuanId).then(() => {
                editBarangModal.show();
            });
        }
        
        if (btnHapusBarang) {
            const barangId = btnHapusBarang.dataset.id; 
            const namaBarang = btnHapusBarang.dataset.nama;
            Swal.fire({ title: 'Anda yakin?', text: `Barang "${namaBarang}" akan dihapus?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    const formData = new FormData(); 
                    formData.append('id', barangId);
                    try {
                        const response = await fetch('ajax/hapus_barang_supplier.php', { method: 'POST', body: formData });
                        const resData = await response.json();
                        if (resData.success) { Swal.fire('Dihapus!', resData.message, 'success').then(() => location.reload()); }
                        else { Swal.fire('Gagal!', resData.message, 'error'); }
                    } catch (error) { Swal.fire('Error!', 'Terjadi kesalahan.', 'error'); }
                }
            });
        }
    });
    
    document.getElementById('btn-update-barang').addEventListener('click', async function() {
        const formData = new FormData();
        const hargaValue = document.getElementById('edit-barang-harga').value.replace(/[^0-9]/g, '');

        formData.append('id', document.getElementById('edit-barang-id').value);
        formData.append('nama_barang', document.getElementById('edit-barang-nama').value);
        formData.append('kategori_id', document.getElementById('edit-barang-kategori').value);
        formData.append('satuan_id', document.getElementById('edit-barang-satuan').value);
        formData.append('stok', document.getElementById('edit-barang-stok').value);
        formData.append('harga', hargaValue);

        const fotoInput = document.getElementById('edit-barang-foto-input');
        if (fotoInput.files.length > 0) formData.append('foto_produk', fotoInput.files[0]);
        if (!formData.get('nama_barang') || !formData.get('kategori_id') || !formData.get('satuan_id')) { Swal.fire('Error', 'Nama, kategori, dan satuan harus diisi.', 'error'); return; }
        
        try {
            const response = await fetch('ajax/edit_barang_supplier.php', { method: 'POST', body: formData });
            const resData = await response.json();
            editBarangModal.hide();
            if (resData.success) { Swal.fire('Berhasil!', resData.message, 'success').then(() => location.reload()); }
            else { Swal.fire('Gagal!', resData.message, 'error'); }
        } catch (error) { console.error('Update error:', error); Swal.fire('Error!', 'Terjadi kesalahan.', 'error'); }
    });
});
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>