<?php
// Include header
require_once __DIR__ . '/template/header.php';

// Check if user is admin
cekAdmin();

// Initialize variables
$no_pembelian = generateNoPembelian();
$tanggal = date('Y-m-d');
$user_id = $_SESSION['user_id'];

// Get suppliers, categories, and units for dropdowns
$suppliers_result = $koneksi->query("SELECT * FROM supplier ORDER BY nama_supplier");
$kategori_result = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori");
$satuan_result = $koneksi->query("SELECT * FROM satuan ORDER BY nama_satuan");
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Pembelian Barang</h6>
                <span>No: <strong id="no-pembelian"><?= htmlspecialchars($no_pembelian) ?></strong></span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="supplier" class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" id="supplier" required>
                            <option value="">-- Pilih Supplier --</option>
                            <?php while ($supplier = $suppliers_result->fetch_assoc()): ?>
                            <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['nama_supplier']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="tanggal" class="form-label">Tanggal</label>
                        <input type="date" class="form-control" id="tanggal" value="<?= htmlspecialchars($tanggal) ?>">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12">
                        <label for="search-barang" class="form-label">Cari Barang (Kode/Nama)</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search-barang" placeholder="Ketik untuk mencari barang yang sudah ada...">
                            <button class="btn btn-primary" type="button" id="btn-cari"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="keranjang-table">
                        <thead class="table-dark">
                            <tr>
                                <th width="25%">Nama Barang</th>
                                <th width="20%">Harga Beli</th>
                                <th width="20%">Jumlah</th>
                                <th width="25%">Subtotal</th>
                                <th width="10%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="empty-cart">
                                <td colspan="5" class="text-center py-3">Belum ada barang dipilih</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="3" class="text-end">TOTAL</th>
                                <th colspan="2" id="total-amount">Rp 0</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="m-0 font-weight-bold">Detail Pembelian</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="total-pembelian" class="form-label">Total Pembelian</label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" class="form-control bg-light" id="total-pembelian" style="font-size: 1.2rem; font-weight: bold;" readonly>
                    </div>
                </div>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary btn-lg" id="btn-simpan" disabled>
                        <i class="fas fa-save"></i> Simpan Pembelian
                    </button>
                    <button type="button" class="btn btn-danger" id="btn-reset">
                        <i class="fas fa-sync-alt"></i> Reset Form
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="m-0 font-weight-bold">Aksi Cepat</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#daftarBarangModal">
                        <i class="fas fa-list"></i> Daftar Barang
                    </button>
                    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#newProductModal">
                        <i class="fas fa-plus"></i> Tambah Barang Baru
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="daftarBarangModal" tabindex="-1" aria-labelledby="daftarBarangModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="daftarBarangModalLabel">Daftar Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                 <div class="input-group mb-3">
                    <input type="text" class="form-control" id="modal-search" placeholder="Cari nama/kode barang">
                    <button class="btn btn-primary" type="button" id="btn-modal-search"><i class="fas fa-search"></i></button>
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover table-striped" id="table-products">
                        <thead class="table-dark">
                            <tr><th>Kode</th><th>Nama Barang</th><th>Harga Beli Terakhir</th><th>Stok</th><th>Aksi</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newProductModal" tabindex="-1" aria-labelledby="newProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newProductModalLabel">Tambah Barang Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="form-new-product">
                    <div class="mb-3">
                        <label for="new-kode" class="form-label">Kode Barang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new-kode" required>
                    </div>
                    <div class="mb-3">
                        <label for="new-nama" class="form-label">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="new-nama" required>
                    </div>
                     <div class="mb-3">
                        <label for="new-kategori" class="form-label">Kategori</label>
                        <select class="form-select" id="new-kategori" required>
                            <?php $kategori_result->data_seek(0); while ($k = $kategori_result->fetch_assoc()): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new-satuan" class="form-label">Satuan</label>
                        <select class="form-select" id="new-satuan" required>
                            <?php $satuan_result->data_seek(0); while ($s = $satuan_result->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nama_satuan']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="new-harga-beli" class="form-label">Harga Beli Awal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control currency" id="new-harga-beli" required>
                    </div>
                    <div class="mb-3">
                        <label for="new-harga-jual" class="form-label">Harga Jual Awal <span class="text-danger">*</span></label>
                        <input type="text" class="form-control currency" id="new-harga-jual" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn-save-new-product">Simpan & Tambah</button>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    let total = 0;
    const userId = <?= $user_id ?>;
    const daftarBarangModalEl = document.getElementById('daftarBarangModal');
    const newProductModalEl = document.getElementById('newProductModal');

    // --- UTILITY FUNCTIONS ---
    const formatRupiah = (angka) => {
        if (angka === null || isNaN(angka)) return '0';
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    };

    const parseRupiah = (rupiah) => {
        return parseInt(String(rupiah).replace(/[^0-9]/g, ''), 10) || 0;
    };
    
    const showMessage = (title, text, icon) => {
        Swal.fire({ title, text, icon });
    };

    // --- CART LOGIC ---
    const addToCart = (product) => {
        const existingItem = cart.find(item => item.id === product.id);
        if (existingItem) {
            existingItem.qty++;
        } else {
            cart.push({
                id: product.id,
                code: product.kode_barang,
                name: product.nama_barang,
                price: parseInt(product.harga_beli) || 0,
                qty: 1,
            });
        }
        updateCartDisplay();
    };

    const updateCartDisplay = () => {
        const tbody = document.querySelector('#keranjang-table tbody');
        tbody.innerHTML = '';
        total = 0;

        if (cart.length === 0) {
            tbody.innerHTML = '<tr id="empty-cart"><td colspan="5" class="text-center py-3">Belum ada barang dipilih</td></tr>';
            document.getElementById('btn-simpan').disabled = true;
        } else {
            document.getElementById('btn-simpan').disabled = false;
            cart.forEach((item, index) => {
                const subtotal = item.qty * item.price;
                total += subtotal;
                const row = `
                    <tr>
                        <td>${item.name}<br><small class="text-muted">${item.code}</small></td>
                        <td><input type="text" class="form-control form-control-sm price-input" value="${formatRupiah(item.price)}" data-index="${index}"></td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <button class="btn btn-secondary btn-decrease" data-index="${index}">-</button>
                                <input type="number" class="form-control text-center qty-input" value="${item.qty}" min="1" data-index="${index}">
                                <button class="btn btn-secondary btn-increase" data-index="${index}">+</button>
                            </div>
                        </td>
                        <td><strong>${formatRupiah(subtotal)}</strong></td>
                        <td><button class="btn btn-sm btn-danger btn-remove" data-index="${index}"><i class="fas fa-trash"></i></button></td>
                    </tr>`;
                tbody.insertAdjacentHTML('beforeend', row);
            });
        }
        document.getElementById('total-amount').textContent = 'Rp ' + formatRupiah(total);
        document.getElementById('total-pembelian').value = formatRupiah(total);
    };

    const updateCartItem = (index, key, value) => {
        if (cart[index]) {
            cart[index][key] = value;
            updateCartDisplay();
        }
    };

    // --- EVENT LISTENERS ---
    daftarBarangModalEl.addEventListener('show.bs.modal', () => loadProducts());
    document.getElementById('btn-modal-search').addEventListener('click', () => loadProducts(document.getElementById('modal-search').value));
    document.getElementById('modal-search').addEventListener('keyup', (e) => e.key === 'Enter' && loadProducts(e.target.value));

    document.getElementById('btn-cari').addEventListener('click', () => searchProduct(document.getElementById('search-barang').value));
    document.getElementById('search-barang').addEventListener('keyup', (e) => e.key === 'Enter' && searchProduct(e.target.value));

    document.querySelector('#keranjang-table tbody').addEventListener('click', (e) => {
        const index = e.target.closest('button')?.dataset.index;
        if (index) {
            if (e.target.closest('.btn-increase')) cart[index].qty++;
            if (e.target.closest('.btn-decrease')) cart[index].qty = Math.max(1, cart[index].qty - 1);
            if (e.target.closest('.btn-remove')) {
                Swal.fire({
                    title: "Hapus Barang?",
                    text: `Hapus ${cart[index].name} dari daftar?`,
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonColor: "#d33",
                    confirmButtonText: "Ya, hapus!"
                }).then((result) => {
                    if (result.isConfirmed) cart.splice(index, 1);
                    updateCartDisplay();
                });
            }
            updateCartDisplay();
        }
    });

    document.querySelector('#keranjang-table tbody').addEventListener('change', (e) => {
        const index = e.target.dataset.index;
        if (index) {
            if (e.target.classList.contains('qty-input')) updateCartItem(index, 'qty', Math.max(1, parseInt(e.target.value) || 1));
            if (e.target.classList.contains('price-input')) updateCartItem(index, 'price', parseRupiah(e.target.value));
        }
    });
    
    document.querySelector('#keranjang-table tbody').addEventListener('input', (e) => {
        if (e.target.classList.contains('price-input')) {
            let cursorPosition = e.target.selectionStart;
            let originalLength = e.target.value.length;
            e.target.value = formatRupiah(parseRupiah(e.target.value));
            let newLength = e.target.value.length;
            e.target.setSelectionRange(cursorPosition + (newLength - originalLength), cursorPosition + (newLength - originalLength));
        }
    });
    
    document.querySelectorAll('.currency').forEach(input => {
        input.addEventListener('input', (e) => {
            e.target.value = formatRupiah(parseRupiah(e.target.value));
        });
    });

    document.getElementById('btn-save-new-product').addEventListener('click', saveNewProduct);
    document.getElementById('btn-simpan').addEventListener('click', processPurchase);
    document.getElementById('btn-reset').addEventListener('click', () => {
         Swal.fire({
            title: "Reset Form?",
            text: `Semua data pembelian yang belum disimpan akan hilang.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Ya, Reset!"
        }).then((result) => {
            if (result.isConfirmed) location.reload();
        });
    });

    // --- AJAX FUNCTIONS ---
    async function loadProducts(search = '') {
        const tbody = document.querySelector('#table-products tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Memuat...</td></tr>';
        try {
            const response = await fetch(`ajax/get_products.php?search=${encodeURIComponent(search)}`);
            const products = await response.json();
            tbody.innerHTML = '';
            if (products.length === 0) {
                 tbody.innerHTML = '<tr><td colspan="5" class="text-center">Barang tidak ditemukan.</td></tr>';
                 return;
            }
            products.forEach(p => {
                tbody.innerHTML += `
                    <tr>
                        <td>${p.kode_barang}</td>
                        <td>${p.nama_barang}</td>
                        <td>${formatRupiah(p.harga_beli)}</td>
                        <td>${p.stok}</td>
                        <td><button class="btn btn-sm btn-primary btn-add-cart" data-product='${JSON.stringify(p)}'><i class="fas fa-plus"></i></button></td>
                    </tr>`;
            });
            document.querySelectorAll('.btn-add-cart').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    addToCart(JSON.parse(e.currentTarget.dataset.product));
                    bootstrap.Modal.getInstance(daftarBarangModalEl).hide();
                });
            });
        } catch (error) {
            console.error('Error loading products:', error);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Gagal memuat data.</td></tr>';
        }
    }

    async function searchProduct(search) {
        if (!search) return;
        try {
            const response = await fetch(`ajax/get_product.php?search=${encodeURIComponent(search)}`);
            const product = await response.json();
            if (product.id) {
                addToCart(product);
                document.getElementById('search-barang').value = '';
            } else {
                showMessage('Tidak Ditemukan', `Barang dengan kode/nama "${search}" tidak ada di database.`, 'warning');
            }
        } catch (error) {
             showMessage('Error', 'Terjadi kesalahan saat mencari produk.', 'error');
        }
    }

    async function saveNewProduct() {
        const formData = {
            kode_barang: document.getElementById('new-kode').value,
            nama_barang: document.getElementById('new-nama').value,
            kategori_id: document.getElementById('new-kategori').value,
            satuan_id: document.getElementById('new-satuan').value,
            harga_beli: parseRupiah(document.getElementById('new-harga-beli').value),
            harga_jual: parseRupiah(document.getElementById('new-harga-jual').value),
        };
        // Simple Validation
        if (!formData.kode_barang || !formData.nama_barang || !formData.harga_beli || !formData.harga_jual) {
            return showMessage('Data Tidak Lengkap', 'Semua kolom yang ditandai * wajib diisi.', 'error');
        }

        try {
            const response = await fetch('ajax/save_new_product.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(formData)
            });
            const result = await response.json();

            if (result.success) {
                addToCart(result.product);
                bootstrap.Modal.getInstance(newProductModalEl).hide();
                document.getElementById('form-new-product').reset();
                showMessage('Berhasil', 'Barang baru berhasil disimpan dan ditambahkan.', 'success');
            } else {
                showMessage('Gagal', result.message, 'error');
            }
        } catch (error) {
            showMessage('Error', 'Terjadi kesalahan.', 'error');
        }
    }

    async function processPurchase() {
        const supplier_id = document.getElementById('supplier').value;
        if (!supplier_id) return showMessage('Supplier Belum Dipilih', 'Silakan pilih supplier terlebih dahulu.', 'warning');
        if (cart.length === 0) return showMessage('Keranjang Kosong', 'Tambahkan barang yang akan dibeli.', 'warning');
        
        const purchaseData = {
            no_pembelian: document.getElementById('no-pembelian').textContent,
            tanggal: document.getElementById('tanggal').value,
            supplier_id,
            user_id: userId,
            total: total,
            items: cart
        };

        Swal.fire({
            title: 'Konfirmasi Pembelian',
            html: `Anda akan menyimpan pembelian dari <strong>${document.getElementById('supplier').options[document.getElementById('supplier').selectedIndex].text}</strong> sebesar <strong>Rp ${formatRupiah(total)}</strong>. Lanjutkan?`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Ya, Simpan!',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const response = await fetch('ajax/save_purchase.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(purchaseData)
                    });
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || response.statusText);
                    }
                    return await response.json();
                } catch (error) {
                    Swal.showValidationMessage(`Gagal: ${error}`);
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                if(result.value.success) {
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Data pembelian telah berhasil disimpan.',
                        icon: 'success'
                    }).then(() => location.reload()); // Reload for new transaction
                } else {
                    // This part might not be reached if preConfirm throws an error
                    Swal.fire('Gagal', result.value.message || 'Terjadi kesalahan di server.', 'error');
                }
            }
        });
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/template/footer.php';
?>