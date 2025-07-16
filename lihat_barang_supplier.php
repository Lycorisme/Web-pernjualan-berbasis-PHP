<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman
cekAdmin();

// Ambil dan validasi ID supplier dari URL
$supplier_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($supplier_id <= 0) {
    setAlert('error', 'ID Supplier tidak valid.');
    header("Location: supplier.php");
    exit();
}

// Ambil data supplier
$stmt_supplier = $koneksi->prepare("SELECT * FROM supplier WHERE id = ?");
$stmt_supplier->bind_param("i", $supplier_id);
$stmt_supplier->execute();
$result_supplier = $stmt_supplier->get_result();
if ($result_supplier->num_rows === 0) {
    setAlert('error', 'Supplier tidak ditemukan.');
    header("Location: supplier.php");
    exit();
}
$supplier = $result_supplier->fetch_assoc();
$nama_supplier = $supplier['nama_supplier'];
$user_id = $_SESSION['user_id'];

// Data untuk form pembelian baru
$no_pembelian = generateNoPembelian();
$tanggal = date('Y-m-d');

// Data untuk tabel riwayat (termasuk kolom status)
$query_pembelian = "SELECT p.id, p.no_pembelian, p.tanggal, p.total, p.status, u.nama_lengkap as admin FROM pembelian p JOIN users u ON p.user_id = u.id WHERE p.supplier_id = ? ORDER BY p.tanggal DESC, p.id DESC";
$stmt_pembelian = $koneksi->prepare($query_pembelian);
$stmt_pembelian->bind_param("i", $supplier_id);
$stmt_pembelian->execute();
$riwayat_pembelian = $stmt_pembelian->get_result();

require_once __DIR__ . '/template/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="card table-container mb-4">
    <div class="card-header bg-primary text-white"><h5 class="mb-0">Buat Pembelian Baru dari: <strong><?= htmlspecialchars($nama_supplier) ?></strong></h5></div>
    <div class="card-body"><div class="row"><div class="col-lg-8 mb-4 mb-lg-0"><div class="row mb-3"><div class="col-md-6"><label class="form-label">No. Pembelian</label><input type="text" class="form-control" id="no-pembelian" value="<?= htmlspecialchars($no_pembelian) ?>" readonly></div><div class="col-md-6"><label for="tanggal" class="form-label">Tanggal</label><input type="date" class="form-control" id="tanggal" value="<?= htmlspecialchars($tanggal) ?>"></div></div><div class="table-responsive"><table class="table table-bordered table-striped" id="keranjang-table"><thead class="table-dark"><tr><th width="25%">Nama Barang</th><th width="20%">Harga Beli</th><th width="20%">Jumlah</th><th width="25%">Subtotal</th><th width="10%">Aksi</th></tr></thead><tbody><tr id="empty-cart"><td colspan="5" class="text-center py-3">Keranjang masih kosong</td></tr></tbody><tfoot class="table-secondary"><tr><th colspan="3" class="text-end">TOTAL</th><th colspan="2" id="total-amount">Rp 0</th></tr></tfoot></table></div></div><div class="col-lg-4"><div class="card"><div class="card-header bg-success text-white"><h6 class="m-0 font-weight-bold">Ringkasan & Aksi</h6></div><div class="card-body"><div class="mb-3"><label for="total-pembelian" class="form-label">Total Pembelian</label><input type="text" class="form-control bg-light" id="total-pembelian" style="font-size: 1.2rem; font-weight: bold;" readonly></div><div class="d-grid gap-2"><button type="button" class="btn btn-primary btn-lg" id="btn-simpan" disabled><i class="fas fa-save"></i> Simpan Pembelian</button><button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#daftarBarangModal"><i class="fas fa-list"></i> Daftar Barang</button><a href="supplier.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a></div></div></div></div></div></div>
</div>

<div class="card table-container">
    <div class="card-header"><h5 class="mb-0">Riwayat Pembelian Terdahulu</h5></div>
    <div class="card-body"><div class="table-responsive"><table class="table table-hover table-striped"><thead class="table-dark"><tr><th>No</th><th>No. Pembelian</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Admin</th><th class="text-center">Aksi</th></tr></thead><tbody>
    <?php if ($riwayat_pembelian->num_rows > 0): $no = 1; while ($pembelian = $riwayat_pembelian->fetch_assoc()): ?>
    <tr><td><?= $no++ ?></td><td><?= htmlspecialchars($pembelian['no_pembelian']) ?></td><td><?= htmlspecialchars(formatTanggal($pembelian['tanggal'])) ?></td><td><?= htmlspecialchars(formatRupiah($pembelian['total'])) ?></td><td><?= buatBadgeStatus($pembelian['status']) ?></td><td><?= htmlspecialchars($pembelian['admin']) ?></td><td class="text-center"><div class="btn-group btn-group-sm">
    <?php if ($pembelian['status'] !== 'Belum Lunas'): ?><button onclick="printNota(<?= $pembelian['id'] ?>)" class="btn btn-secondary" title="Cetak Nota"><i class="fas fa-print"></i></button><?php endif; ?>
    <?php if ($pembelian['status'] == 'Belum Lunas'): ?><button onclick="bukaModalPembayaran(<?= $pembelian['id'] ?>, '<?= htmlspecialchars($pembelian['no_pembelian']) ?>', '<?= htmlspecialchars(formatRupiah($pembelian['total'])) ?>')" class="btn btn-primary" title="Bayar Sekarang"><i class="fas fa-money-bill-wave"></i></button><?php endif; ?>
    </div></td></tr>
    <?php endwhile; else: ?><tr><td colspan="7" class="text-center py-4">Belum ada riwayat pembelian.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>

<div class="modal fade" id="daftarBarangModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="daftarBarangModalLabel">Daftar Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="input-group mb-3"><input type="text" class="form-control" id="modal-search" placeholder="Cari barang..."><button class="btn btn-primary" type="button" id="btn-modal-search"><i class="fas fa-search"></i></button></div><div class="table-responsive" style="max-height: 400px; overflow-y: auto;"><table class="table table-hover" id="table-products"><thead class="table-dark"><tr><th>Foto</th><th>Kode</th><th>Nama</th><th>Harga Beli</th><th>Stok</th><th>Aksi</th></tr></thead><tbody></tbody></table></div></div></div></div></div>

<div class="modal fade" id="pembayaranModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="pembayaranModalLabel">Form Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="form-pembayaran" enctype="multipart/form-data"><input type="hidden" id="pembelian-id-input" name="pembelian_id"><div class="mb-3"><label class="form-label">No. Pembelian</label><input type="text" class="form-control bg-light" id="no-pembelian-display" readonly></div><div class="mb-3"><label class="form-label">Total</label><input type="text" class="form-control bg-light" id="total-pembelian-display" readonly></div><div class="mb-3"><label for="bukti-transfer-input" class="form-label">Unggah Bukti Transfer</label><input class="form-control" type="file" id="bukti-transfer-input" name="bukti_transfer" accept="image/*" required></div></form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary" id="btn-kirim-bukti">Kirim Bukti</button></div></div></div></div>

<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Preview Gambar</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center"><img src="" id="imagePreviewSrc" class="img-fluid" alt="Preview"></div>
        </div>
    </div>
</div>

<script>
function printNota(pembelianId) { window.open(`cetak_nota_pembelian.php?id=${pembelianId}`, '_blank'); }
function bukaModalPembayaran(id, no, total) {
    document.getElementById('pembelian-id-input').value = id;
    document.getElementById('no-pembelian-display').value = no;
    document.getElementById('total-pembelian-display').value = total;
    document.getElementById('bukti-transfer-input').value = '';
    new bootstrap.Modal(document.getElementById('pembayaranModal')).show();
}

document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    const supplierId = <?= $supplier_id ?>;
    const userId = <?= $user_id ?>;
    
    const daftarBarangModal = new bootstrap.Modal(document.getElementById('daftarBarangModal'));
    const formatRupiah = (angka) => `Rp ${new Intl.NumberFormat('id-ID').format(angka)}`;

    const imagePreviewModalEl = document.getElementById('imagePreviewModal');
    if (imagePreviewModalEl) {
        imagePreviewModalEl.addEventListener('show.bs.modal', function (event) {
            const triggerElement = event.relatedTarget;
            const imageSrc = triggerElement.getAttribute('src');
            const imageAlt = triggerElement.getAttribute('alt');
            imagePreviewModalEl.querySelector('.modal-title').textContent = 'Preview: ' + imageAlt;
            imagePreviewModalEl.querySelector('#imagePreviewSrc').src = imageSrc;
        });
    }

    const renderCart = () => {
        const tbody = document.getElementById('keranjang-table').querySelector('tbody');
        let total = 0;
        if (cart.length === 0) {
            tbody.innerHTML = `<tr id="empty-cart"><td colspan="5" class="text-center py-3">Keranjang masih kosong</td></tr>`;
        } else {
            tbody.innerHTML = '';
            cart.forEach((item, index) => {
                const subtotal = item.qty * item.price;
                total += subtotal;
                const row = document.createElement('tr');
                row.dataset.index = index;
                row.innerHTML = `<td>${item.name}</td><td>${formatRupiah(item.price)}</td><td><div class="input-group input-group-sm"><button class="btn btn-secondary btn-decrease" type="button">-</button><input type="number" class="form-control text-center qty-input" value="${item.qty}" min="1"><button class="btn btn-secondary btn-increase" type="button">+</button></div></td><td>${formatRupiah(subtotal)}</td><td><button class="btn btn-danger btn-sm btn-remove" type="button"><i class="fas fa-trash"></i></button></td>`;
                tbody.appendChild(row);
            });
        }
        document.getElementById('total-amount').textContent = formatRupiah(total);
        document.getElementById('total-pembelian').value = formatRupiah(total);
        document.getElementById('btn-simpan').disabled = cart.length === 0;
    };

    const addToCart = (product) => {
        const existingItem = cart.find(item => item.id == product.id);
        if (existingItem) { existingItem.qty++; } 
        else { cart.push({ id: product.id, name: product.nama_barang, price: parseFloat(product.harga_beli), qty: 1 }); }
        renderCart();
    };
    
    const loadProductsInModal = async (searchTerm = '') => {
        const tbody = document.querySelector('#table-products tbody');
        tbody.innerHTML = `<tr><td colspan="6" class="text-center">Memuat...</td></tr>`;
        try {
            const response = await fetch(`ajax/get_supplier_products.php?supplier_id=${supplierId}&search=${encodeURIComponent(searchTerm)}`);
            if (!response.ok) throw new Error('Network error');
            const products = await response.json();
            tbody.innerHTML = '';
            if (products.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" class="text-center">Supplier ini belum memiliki barang atau barang tidak ditemukan.</td></tr>`;
                return;
            }
            products.forEach(product => {
                const row = document.createElement('tr');
                let fotoHtml;
                if (product.foto_produk) {
                    const fotoPath = `uploads/produk/${product.foto_produk}`;
                    fotoHtml = `<img src="${fotoPath}" alt="${product.nama_barang}" class="img-thumbnail" width="50" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#imagePreviewModal">`;
                } else {
                    fotoHtml = `<img src="https://placehold.co/50x50/e2e8f0/adb5bd?text=N/A" alt="Tidak ada foto" class="img-thumbnail">`;
                }
                row.innerHTML = `<td>${fotoHtml}</td><td>${product.kode_barang}</td><td>${product.nama_barang}</td><td>${formatRupiah(product.harga_beli)}</td><td>${product.stok}</td><td><button class="btn btn-sm btn-primary btn-add-from-modal" data-product='${JSON.stringify(product)}' type="button"><i class="fas fa-plus"></i> Tambah</button></td>`;
                tbody.appendChild(row);
            });
        } catch (error) { 
            console.error('Error loading products:', error);
            tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Gagal memuat data.</td></tr>`; 
        }
    };

    document.getElementById('daftarBarangModal').addEventListener('show.bs.modal', () => loadProductsInModal());
    document.getElementById('btn-modal-search').addEventListener('click', () => loadProductsInModal(document.getElementById('modal-search').value));
    document.getElementById('modal-search').addEventListener('keypress', (e) => { if (e.key === 'Enter') loadProductsInModal(document.getElementById('modal-search').value); });
    document.getElementById('table-products').addEventListener('click', e => {
        const button = e.target.closest('.btn-add-from-modal');
        if (button) {
            try { const product = JSON.parse(button.dataset.product); addToCart(product); daftarBarangModal.hide(); Swal.fire({title: 'Berhasil!', text: 'Barang ditambahkan ke keranjang.', icon: 'success', timer: 1500, showConfirmButton: false}); }
            catch (error) { Swal.fire('Error', 'Gagal menambahkan barang.', 'error'); }
        }
    });
    document.getElementById('keranjang-table').addEventListener('click', e => {
        const row = e.target.closest('tr'); const index = row?.dataset.index;
        if (index === undefined) return;
        const itemIndex = parseInt(index);
        if (e.target.closest('.btn-remove')) { cart.splice(itemIndex, 1); } 
        else if (e.target.closest('.btn-increase')) { cart[itemIndex].qty++; } 
        else if (e.target.closest('.btn-decrease')) { if (cart[itemIndex].qty > 1) cart[itemIndex].qty--; }
        renderCart();
    });
    document.getElementById('keranjang-table').addEventListener('change', e => {
        if (e.target.classList.contains('qty-input')) {
            const row = e.target.closest('tr'); const index = row?.dataset.index;
            if (index !== undefined) {
                const itemIndex = parseInt(index); const newQty = parseInt(e.target.value);
                if (newQty > 0) { cart[itemIndex].qty = newQty; renderCart(); }
            }
        }
    });
    document.getElementById('btn-simpan').addEventListener('click', async () => {
        if(cart.length === 0) { Swal.fire('Peringatan', 'Keranjang masih kosong!', 'warning'); return; }
        const purchaseData = { no_pembelian: document.getElementById('no-pembelian').value, tanggal: document.getElementById('tanggal').value, supplier_id: supplierId, user_id: userId, total: cart.reduce((sum, item) => sum + (item.qty * item.price), 0), items: cart };
        const result = await Swal.fire({ title: 'Simpan Pembelian?', text: 'Apakah Anda yakin?', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Simpan!', cancelButtonText: 'Batal' });
        if (result.isConfirmed) {
            try {
                const response = await fetch('ajax/save_purchase.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(purchaseData) });
                if (!response.ok) throw new Error('Network error');
                const data = await response.json();
                if (data.success) { Swal.fire('Berhasil!', data.message, 'success').then(() => { location.reload(); }); }
                else { Swal.fire('Gagal!', data.message || 'Terjadi kesalahan.', 'error'); }
            } catch (error) { Swal.fire('Error', 'Gagal terhubung ke server', 'error'); }
        }
    });
    document.getElementById('btn-kirim-bukti').addEventListener('click', async function() {
        const form = document.getElementById('form-pembayaran'); const formData = new FormData(form);
        const fileInput = document.getElementById('bukti-transfer-input'); const pembelianId = document.getElementById('pembelian-id-input').value;
        if (!pembelianId) { Swal.fire('Error', 'ID Pembelian tidak valid.', 'error'); return; }
        if (fileInput.files.length === 0) { Swal.fire('Error', 'Silakan pilih file bukti transfer.', 'error'); return; }
        formData.set('pembelian_id', pembelianId);
        this.disabled = true; this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengunggah...';
        try {
            const response = await fetch('ajax/upload_bukti_pembayaran.php', { method: 'POST', body: formData });
            if (!response.ok) throw new Error('Network error');
            const result = await response.json();
            if (result.success) {
                new bootstrap.Modal(document.getElementById('pembayaranModal')).hide();
                Swal.fire('Berhasil!', result.message, 'success').then(() => { location.reload(); });
            } else { Swal.fire('Gagal!', result.message || 'Terjadi kesalahan.', 'error'); }
        } catch (error) { Swal.fire('Error', 'Gagal terhubung ke server.', 'error'); }
        finally { this.disabled = false; this.innerHTML = 'Kirim Bukti'; }
    });

    renderCart();
});
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>