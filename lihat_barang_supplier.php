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
$nama_supplier_perusahaan = htmlspecialchars($supplier['nama_perusahaan']);
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['nama_lengkap']; // Ambil nama admin dari sesi

// Data untuk form pesanan baru (akan diisi di JavaScript)
$order_no = generateOrderNo(); // Fungsi baru untuk generate no pesanan
$tanggal_pesan = date('Y-m-d');

// Memuat header
require_once __DIR__ . '/template/header.php';
?>

<!-- Perbaikan: Memuat SweetAlert2 CSS dan JS dengan benar -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<style>
    /* CSS tambahan untuk offcanvas dan tabel */
    .offcanvas-body {
        padding: 1rem;
    }
    .order-item-list {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        padding: 5px;
        background-color: #fcfcfc;
    }
    .order-item {
        display: flex;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px dashed #eee;
    }
    .order-item:last-child {
        border-bottom: none;
    }
    .order-item-info {
        flex-grow: 1;
        padding-left: 10px;
    }
    .order-item-controls {
        display: flex;
        align-items: center;
    }
    .order-item-qty {
        width: 60px;
        text-align: center;
        margin: 0 5px;
    }
    .img-checkbox {
        width: 50px; /* Lebar foto */
        height: 50px; /* Tinggi foto */
        object-fit: cover; /* Pastikan gambar proporsional */
        margin-right: 10px;
    }
    .form-check-input.item-checkbox {
        position: relative;
        top: -15px; /* Sesuaikan posisi checkbox */
        left: -5px;
    }
</style>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Buat Pesanan Baru dari: <strong><?= $nama_supplier_perusahaan ?></strong></h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-8 mb-4 mb-lg-0">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">No. Pesanan</label>
                        <input type="text" class="form-control" id="order-no" value="<?= htmlspecialchars($order_no) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="order-date" class="form-label">Tanggal Pesan</label>
                        <input type="date" class="form-control" id="order-date" value="<?= htmlspecialchars($tanggal_pesan) ?>" readonly>
                    </div>
                </div>
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="m-0">Keranjang Pesanan</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="order-cart-table">
                                <thead class="table-dark">
                                    <tr>
                                        <th width="25%">Nama Barang</th>
                                        <th width="20%">Harga Supplier</th>
                                        <th width="20%">Jumlah Pesan</th>
                                        <th width="25%">Subtotal</th>
                                        <th width="10%">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="empty-cart-message">
                                        <td colspan="5" class="text-center py-3">Keranjang pesanan masih kosong</td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th colspan="3" class="text-end">TOTAL</th>
                                        <th colspan="2" id="total-order-amount">Rp 0</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Informasi Pembeli dan Gudang Penerima -->
                <div class="card shadow-sm">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="m-0">Informasi Pembeli & Pengiriman</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="buyer-name" class="form-label">Nama Admin (Pembeli)</label>
                            <input type="text" class="form-control" id="buyer-name" value="<?= htmlspecialchars($admin_name) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="buyer-address" class="form-label">Alamat Admin</label>
                            <textarea class="form-control" id="buyer-address" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="buyer-contact" class="form-label">Kontak Admin</label>
                            <input type="text" class="form-control" id="buyer-contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="receiving-warehouse" class="form-label">Gudang Penerima</label>
                            <input type="text" class="form-control" id="receiving-warehouse" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jenis Pembayaran</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_type" id="payment-cash" value="tunai" checked>
                                    <label class="form-check-label" for="payment-cash">Tunai</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="payment_type" id="payment-credit" value="kredit">
                                    <label class="form-check-label" for="payment-credit">Kredit</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            <div class="col-lg-4">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h6 class="m-0 font-weight-bold">Ringkasan Pesanan & Aksi</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="summary-total-order" class="form-label">Total Pesanan</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control bg-light" id="summary-total-order" style="font-size: 1.2rem; font-weight: bold;" readonly>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary btn-lg" id="btn-create-order" disabled>
                                <i class="fas fa-file-invoice"></i> Buat Pesanan
                            </button>
                            <button type="button" class="btn btn-info" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSupplierProducts" aria-controls="offcanvasSupplierProducts">
                                <i class="fas fa-list"></i> Pilih Barang Supplier
                            </button>
                            <a href="supplier.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali ke Daftar Supplier
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Offcanvas for Supplier Products -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasSupplierProducts" aria-labelledby="offcanvasSupplierProductsLabel">
    <div class="offcanvas-header">
        <h5 id="offcanvasSupplierProductsLabel">Barang dari <?= $nama_supplier_perusahaan ?></h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <div class="input-group mb-3">
            <input type="text" class="form-control" id="offcanvas-search-product" placeholder="Cari kode/nama barang...">
            <button class="btn btn-primary" type="button" id="btn-offcanvas-search"><i class="fas fa-search"></i></button>
        </div>
        <div class="order-item-list" id="offcanvas-product-list">
            <!-- Product items will be loaded here dynamically -->
            <p class="text-center text-muted">Memuat barang...</p>
        </div>
        <div class="d-grid gap-2 mt-3">
            <button class="btn btn-success" id="btn-add-selected-to-cart">Tambahkan ke Pesanan</button>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('lihat_barang_supplier.php script loaded.'); // Debugging: Pastikan skrip ini dimuat
    let orderCart = []; // The new cart for order items
    const supplierId = <?= $supplier_id ?>;
    const adminId = <?= $admin_id ?>;
    const adminName = '<?= htmlspecialchars($admin_name) ?>';
    const supplierCompanyName = '<?= $nama_supplier_perusahaan ?>';

    const orderNoInput = document.getElementById('order-no');
    const orderDateInput = document.getElementById('order-date');
    const orderCartTableBody = document.querySelector('#order-cart-table tbody');
    const totalOrderAmountElement = document.getElementById('total-order-amount');
    const summaryTotalOrderInput = document.getElementById('summary-total-order');
    const btnCreateOrder = document.getElementById('btn-create-order');
    const offcanvasProductList = document.getElementById('offcanvas-product-list');
    const btnAddSelectedToCart = document.getElementById('btn-add-selected-to-cart');
    const offcanvasSearchInput = document.getElementById('offcanvas-search-product');
    const btnOffcanvasSearch = document.getElementById('btn-offcanvas-search');

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

    // --- ORDER CART LOGIC ---
    const updateOrderCartDisplay = () => {
        orderCartTableBody.innerHTML = '';
        let total = 0;

        if (orderCart.length === 0) {
            orderCartTableBody.innerHTML = `
                <tr id="empty-cart-message">
                    <td colspan="5" class="text-center py-3">Keranjang pesanan masih kosong</td>
                </tr>`;
            btnCreateOrder.disabled = true;
        } else {
            btnCreateOrder.disabled = false;
            orderCart.forEach((item, index) => {
                const subtotal = item.quantity * item.price_per_item;
                total += subtotal;
                const row = `
                    <tr data-index="${index}">
                        <td>${item.nama_barang}<br><small class="text-muted">${item.kode_barang}</small></td>
                        <td>${formatRupiah(item.price_per_item)}</td>
                        <td>
                            <div class="input-group input-group-sm" style="width: 120px;">
                                <button class="btn btn-secondary btn-decrease-qty" type="button" data-index="${index}">-</button>
                                <input type="number" class="form-control text-center order-qty-input" value="${item.quantity}" min="1" data-index="${index}">
                                <button class="btn btn-secondary btn-increase-qty" type="button" data-index="${index}">+</button>
                            </div>
                        </td>
                        <td><strong>Rp ${formatRupiah(subtotal)}</strong></td>
                        <td>
                            <button class="btn btn-sm btn-danger btn-remove-item" type="button" data-index="${index}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                orderCartTableBody.insertAdjacentHTML('beforeend', row);
            });
        }
        totalOrderAmountElement.textContent = 'Rp ' + formatRupiah(total);
        summaryTotalOrderInput.value = formatRupiah(total);
    };

    // --- OFFCANVAS PRODUCT LIST LOGIC ---
    const loadSupplierProducts = async (search = '') => {
        offcanvasProductList.innerHTML = `<p class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Memuat barang...</p>`;
        try {
            // PERUBAHAN: Hapus p.stok dari query agar tidak ditampilkan
            const response = await fetch(`ajax/get_supplier_products.php?supplier_id=${supplierId}&search=${encodeURIComponent(search)}`);
            if (!response.ok) throw new Error('Network response was not ok.');
            const products = await response.json();
            
            offcanvasProductList.innerHTML = '';
            if (products.length === 0) {
                offcanvasProductList.innerHTML = `<p class="text-center text-muted">Tidak ada barang ditemukan dari supplier ini.</p>`;
                return;
            }

            products.forEach(p => {
                const isSelected = orderCart.some(cartItem => cartItem.barang_id_supplier_original === p.id);
                let fotoHtml;
                if (p.foto_produk) {
                    const fotoPath = `uploads/produk/${p.foto_produk}`;
                    fotoHtml = `<img src="${fotoPath}" alt="${p.nama_barang}" class="img-thumbnail img-checkbox" width="50" height="50">`;
                } else {
                    fotoHtml = `<img src="https://placehold.co/50x50/e2e8f0/adb5bd?text=N/A" alt="Tidak ada foto" class="img-thumbnail img-checkbox">`;
                }
                const productHtml = `
                    <div class="order-item">
                        <div class="form-check">
                            <input class="form-check-input item-checkbox" type="checkbox" value="${p.id}" data-product='${JSON.stringify(p)}' ${isSelected ? 'checked disabled' : ''}>
                        </div>
                        ${fotoHtml}
                        <div class="order-item-info">
                            <strong>${p.nama_barang}</strong><br>
                            <small class="text-muted">${p.kode_barang} | Harga: Rp ${formatRupiah(p.harga_beli)}</small>
                        </div>
                    </div>`;
                offcanvasProductList.insertAdjacentHTML('beforeend', productHtml);
            });
        } catch (error) {
            console.error('Error loading supplier products:', error);
            offcanvasProductList.innerHTML = `<p class="text-center text-danger">Gagal memuat barang supplier.</p>`;
        }
    };

    // --- EVENT LISTENERS ---
    const offcanvasElement = document.getElementById('offcanvasSupplierProducts');
    // Debugging: Pastikan event listener terpasang
    offcanvasElement.addEventListener('show.bs.offcanvas', () => {
        console.log('Offcanvas "Pilih Barang Supplier" is about to be shown.');
        loadSupplierProducts();
    });
    offcanvasElement.addEventListener('hidden.bs.offcanvas', () => {
        console.log('Offcanvas "Pilih Barang Supplier" is hidden.');
        offcanvasSearchInput.value = '';
        loadSupplierProducts(); 
    });

    // Debugging: Pastikan tombol terdeteksi dan event listener terpasang
    const btnPilihBarangSupplier = document.querySelector('button[data-bs-target="#offcanvasSupplierProducts"]');
    if (btnPilihBarangSupplier) {
        console.log('Tombol "Pilih Barang Supplier" ditemukan.');
        // Event listener sudah ada melalui data-bs-toggle="offcanvas"
    } else {
        console.error('Tombol "Pilih Barang Supplier" TIDAK ditemukan!');
    }


    btnOffcanvasSearch.addEventListener('click', () => loadSupplierProducts(offcanvasSearchInput.value));
    offcanvasSearchInput.addEventListener('keyup', (e) => {
        if (e.key === 'Enter') loadSupplierProducts(offcanvasSearchInput.value);
    });

    btnAddSelectedToCart.addEventListener('click', function() {
        const selectedCheckboxes = offcanvasProductList.querySelectorAll('.item-checkbox:checked:not(:disabled)');
        selectedCheckboxes.forEach(checkbox => {
            const product = JSON.parse(checkbox.dataset.product);
            const existingItem = orderCart.find(item => item.barang_id_supplier_original === product.id);

            if (!existingItem) {
                orderCart.push({
                    barang_id_supplier_original: product.id,
                    kode_barang: product.kode_barang,
                    nama_barang: product.nama_barang,
                    price_per_item: parseFloat(product.harga_beli), // Harga beli dari supplier
                    quantity: 1, // Default quantity
                });
                checkbox.disabled = true; // Disable once added
            }
        });
        updateOrderCartDisplay();
        // Pastikan offcanvas ditutup dengan benar
        const offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasElement);
        if (offcanvasInstance) {
            offcanvasInstance.hide();
        } else {
            console.error('Offcanvas instance not found for closing.');
        }
    });

    orderCartTableBody.addEventListener('click', function(e) {
        const target = e.target;
        const index = target.closest('tr')?.dataset.index;

        if (index === undefined) return;

        const item = orderCart[index];
        const qtyInput = this.querySelector(`input[data-index="${index}"]`);

        if (target.classList.contains('btn-increase-qty')) {
            item.quantity++; 
        } else if (target.classList.contains('btn-decrease-qty')) {
            if (item.quantity > 1) {
                item.quantity--;
            }
        } else if (target.classList.contains('btn-remove-item')) {
            Swal.fire({
                title: 'Hapus Barang?',
                text: `Hapus ${item.nama_barang} dari pesanan?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Ya, Hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    orderCart.splice(index, 1);
                    updateOrderCartDisplay();
                    loadSupplierProducts(); // Reload offcanvas to re-enable checkbox
                }
            });
            return; 
        }
        updateOrderCartDisplay();
    });

    orderCartTableBody.addEventListener('change', function(e) {
        const target = e.target;
        if (target.classList.contains('order-qty-input')) {
            const index = target.dataset.index;
            const newQty = parseInt(target.value);
            
            if (isNaN(newQty) || newQty < 1) {
                orderCart[index].quantity = 1;
            } else {
                orderCart[index].quantity = newQty;
            }
            updateOrderCartDisplay();
        }
    });

    // --- ORDER CREATION SUBMISSION ---
    btnCreateOrder.addEventListener('click', async function() {
        if (orderCart.length === 0) {
            showMessage('Keranjang Kosong', 'Tambahkan barang ke pesanan terlebih dahulu.', 'warning');
            return;
        }

        const buyerName = document.getElementById('buyer-name').value.trim();
        const buyerAddress = document.getElementById('buyer-address').value.trim();
        const buyerContact = document.getElementById('buyer-contact').value.trim();
        const receivingWarehouse = document.getElementById('receiving-warehouse').value.trim();
        const paymentType = document.querySelector('input[name="payment_type"]:checked').value;

        if (!buyerName || !buyerAddress || !buyerContact || !receivingWarehouse) {
            showMessage('Data Pembeli Belum Lengkap', 'Mohon lengkapi semua informasi pembeli dan gudang penerima.', 'error');
            return;
        }
        
        const orderData = {
            order_no: orderNoInput.value,
            order_date: orderDateInput.value,
            admin_user_id: adminId,
            supplier_id: supplierId,
            total_order_price: parseRupiah(summaryTotalOrderInput.value),
            buyer_name: buyerName,
            buyer_address: buyerAddress,
            buyer_contact: buyerContact,
            receiving_warehouse: receivingWarehouse,
            payment_type: paymentType,
            items: orderCart
        };

        Swal.fire({
            title: 'Buat Pesanan?',
            html: `Anda akan membuat pesanan senilai <strong>Rp ${summaryTotalOrderInput.value}</strong> kepada <strong>${supplierCompanyName}</strong>. Lanjutkan?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Buat Pesanan!',
            showLoaderOnConfirm: true,
            preConfirm: async () => {
                try {
                    const response = await fetch('ajax/create_order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(orderData)
                    });
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || response.statusText);
                    }
                    return await response.json();
                } catch (error) {
                    Swal.showValidationMessage(`Gagal: ${error.message || error}`);
                }
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                if (result.value.success) {
                    Swal.fire({
                        title: 'Pesanan Berhasil Dibuat!',
                        text: result.value.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.href = 'orders.php'; // Redirect to new orders page
                    });
                } else {
                    Swal.fire('Gagal', result.value.message || 'Terjadi kesalahan saat membuat pesanan.', 'error');
                }
            }
        });
    });

    updateOrderCartDisplay(); // Initial display
});
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>