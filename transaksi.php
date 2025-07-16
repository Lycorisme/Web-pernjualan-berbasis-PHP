<?php
// ===================================================================================
// PERBAIKAN: Menggunakan jalur (path) yang benar dari direktori root.
// __DIR__ memastikan path dimulai dari folder di mana file ini berada.
// ===================================================================================
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman: Memastikan hanya user yang sudah login bisa mengakses.
cekLogin();

// Inisialisasi variabel yang dibutuhkan untuk halaman ini.
$no_transaksi = generateNoTransaksi();
$tanggal = date('Y-m-d');
$user_id = $_SESSION['user_id'];

// Memuat Tampilan Header
require_once __DIR__ . '/template/header.php';
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js"></script>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Transaksi Penjualan</h6>
                <span>No: <strong id="no-transaksi"><?= htmlspecialchars($no_transaksi) ?></strong></span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" id="search-barang" placeholder="Cari barang (kode/nama)">
                            <button class="btn btn-primary" type="button" id="btn-cari">
                                <i class="fas fa-search"></i> Cari
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#daftarBarangModal">
                            <i class="fas fa-list"></i> Daftar Barang
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="keranjang-table">
                        <thead class="table-dark">
                            <tr>
                                <th width="5%">No</th>
                                <th width="15%">Kode</th>
                                <th width="30%">Nama Barang</th>
                                <th width="15%">Harga</th>
                                <th width="15%">Jumlah</th>
                                <th width="15%">Subtotal</th>
                                <th width="5%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="empty-cart">
                                <td colspan="7" class="text-center py-3">Belum ada barang dipilih</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <th colspan="5" class="text-end">Total</th>
                                <th colspan="2" id="total-amount">Rp 0</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow mb-4">
            <div class="card-header bg-success text-white">
                <h6 class="m-0 font-weight-bold">Detail Pembayaran</h6>
            </div>
            <div class="card-body">
                <form id="payment-form">
                    <div class="mb-3">
                        <label for="total-belanja" class="form-label">Total Belanja</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control bg-light" id="total-belanja" readonly>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="bayar" class="form-label">Jumlah Bayar</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control" id="bayar" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="kembalian" class="form-label">Kembalian</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" class="form-control bg-light" id="kembalian" readonly>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btn-bayar" disabled>
                            <i class="fas fa-cash-register"></i> Proses Pembayaran
                        </button>
                        <button type="button" class="btn btn-danger" id="btn-reset">
                            <i class="fas fa-sync-alt"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h6 class="m-0 font-weight-bold">Tombol Cepat</h6>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 quick-cash" data-amount="50000">50,000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 quick-cash" data-amount="100000">100,000</button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 quick-cash" data-amount="200000">200,000</button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-primary w-100 quick-cash" data-amount="500000">500,000</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="daftarBarangModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Daftar Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="modal-search" placeholder="Cari nama/kode barang">
                    <button class="btn btn-primary" type="button" id="btn-modal-search"><i class="fas fa-search"></i></button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped" id="table-products">
                        <thead class="table-dark">
                            <tr><th>Kode</th><th>Nama Barang</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- AJAX Script to handle cart -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    let total = 0;
    
    // Load products in modal when opened
    const daftarBarangModal = document.getElementById('daftarBarangModal');
    daftarBarangModal.addEventListener('show.bs.modal', function() {
        loadProducts();
    });
    
    // Search products in modal
    document.getElementById('btn-modal-search').addEventListener('click', function() {
        loadProducts(document.getElementById('modal-search').value);
    });
    
    document.getElementById('modal-search').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            loadProducts(this.value);
        }
    });
    
    // Search products in main form
    document.getElementById('btn-cari').addEventListener('click', function() {
        const searchTerm = document.getElementById('search-barang').value;
        searchProduct(searchTerm);
    });
    
    document.getElementById('search-barang').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            searchProduct(this.value);
        }
    });
    
    // Process payment
    document.getElementById('btn-bayar').addEventListener('click', function() {
        processTransaction();
    });
    
    // Reset form
    document.getElementById('btn-reset').addEventListener('click', function() {
        resetCart();
    });
    
    // Quick cash buttons
    document.querySelectorAll('.quick-cash').forEach(function(button) {
        button.addEventListener('click', function() {
            const amount = parseInt(this.dataset.amount);
            document.getElementById('bayar').value = formatRupiah(amount);
            calculateChange();
        });
    });
    
    // Calculate change when payment amount changes
    document.getElementById('bayar').addEventListener('keyup', function() {
        calculateChange();
    });
    
    document.getElementById('bayar').addEventListener('input', function(e) {
        // Format currency
        let value = this.value.replace(/\D/g, '');
        if (value === '') {
            this.value = '';
            calculateChange();
            return;
        }
        this.value = formatRupiah(parseInt(value));
        calculateChange();
    });
    
    // Function to load products via AJAX
    function loadProducts(search = '') {
        const tbody = document.querySelector('#table-products tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
        
        fetch('ajax/get_products.php?search=' + encodeURIComponent(search))
            .then(response => response.json())
            .then(data => {
                tbody.innerHTML = '';
                
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada produk ditemukan</td></tr>';
                    return;
                }
                
                data.forEach(product => {
                    let stockBadge = '';
                    if (product.stok <= 0) {
                        stockBadge = '<span class="badge bg-danger">Habis</span>';
                    } else if (product.stok < 5) {
                        stockBadge = `<span class="badge bg-warning text-dark">${product.stok}</span>`;
                    } else {
                        stockBadge = `<span class="badge bg-success">${product.stok}</span>`;
                    }
                    
                    const row = `
                        <tr>
                            <td>${product.kode_barang}</td>
                            <td>${product.nama_barang}</td>
                            <td>${formatRupiah(product.harga_jual)}</td>
                            <td>${stockBadge}</td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-add-to-cart" 
                                    data-id="${product.id}" 
                                    data-kode="${product.kode_barang}"
                                    data-nama="${product.nama_barang}" 
                                    data-harga="${product.harga_jual}"
                                    data-stok="${product.stok}"
                                    ${product.stok <= 0 ? 'disabled' : ''}>
                                    <i class="fas fa-plus"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
                
                // Add event listeners to add-to-cart buttons
                document.querySelectorAll('.btn-add-to-cart').forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = this.dataset.id;
                        const productCode = this.dataset.kode;
                        const productName = this.dataset.nama;
                        const productPrice = parseInt(this.dataset.harga);
                        const productStock = parseInt(this.dataset.stok);
                        
                        addToCart(productId, productCode, productName, productPrice, productStock);
                        
                        // Close the modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('daftarBarangModal'));
                        modal.hide();
                    });
                });
            })
            .catch(error => {
                console.error('Error loading products:', error);
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading products</td></tr>';
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Terjadi kesalahan saat memuat data produk'
                });
            });
    }
    
    // Function to search product by code or name
    function searchProduct(search) {
        if (!search) return;
        
        fetch('ajax/get_product.php?search=' + encodeURIComponent(search))
            .then(response => response.json())
            .then(data => {
                if (data.id) {
                    addToCart(data.id, data.kode_barang, data.nama_barang, data.harga_jual, data.stok);
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Barang tidak ditemukan',
                        text: 'Silakan coba pencarian lain'
                    });
                }
                document.getElementById('search-barang').value = '';
            })
            .catch(error => {
                console.error('Error searching product:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Terjadi kesalahan saat mencari barang'
                });
            });
    }
    
    // Function to add product to cart
    function addToCart(id, code, name, price, stock) {
        // Check if product is already in cart
        const existingItem = cart.find(item => item.id === id);
        
        if (existingItem) {
            // Check if we have enough stock
            if (existingItem.qty >= stock) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Stok Tidak Mencukupi',
                    text: `Stok tersedia: ${stock} unit`
                });
                return;
            }
            
            // Update quantity
            existingItem.qty += 1;
            existingItem.subtotal = existingItem.qty * existingItem.price;
        } else {
            // Add new item to cart
            cart.push({
                id: id,
                code: code,
                name: name,
                price: price,
                qty: 1,
                subtotal: price,
                stock: stock
            });
        }
        
        updateCartDisplay();
    }
    
    // Function to update cart display
    function updateCartDisplay() {
        const tbody = document.querySelector('#keranjang-table tbody');
        const emptyCart = document.getElementById('empty-cart');
        
        if (cart.length === 0) {
            tbody.innerHTML = '<tr id="empty-cart"><td colspan="7" class="text-center py-3">Belum ada barang dipilih</td></tr>';
            document.getElementById('total-amount').textContent = 'Rp 0';
            document.getElementById('total-belanja').value = '0';
            document.getElementById('btn-bayar').disabled = true;
            return;
        }
        
        // Remove empty cart message if exists
        if (emptyCart) {
            emptyCart.remove();
        }
        
        tbody.innerHTML = '';
        total = 0;
        
        cart.forEach((item, index) => {
            const row = `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.code}</td>
                    <td>${item.name}</td>
                    <td>${formatRupiah(item.price)}</td>
                    <td>
                        <div class="input-group input-group-sm">
                            <button class="btn btn-sm btn-secondary btn-decrease" data-index="${index}">-</button>
                            <input type="number" class="form-control text-center qty-input" value="${item.qty}" min="1" max="${item.stock}" data-index="${index}">
                            <button class="btn btn-sm btn-secondary btn-increase" data-index="${index}">+</button>
                        </div>
                    </td>
                    <td>${formatRupiah(item.subtotal)}</td>
                    <td>
                        <button class="btn btn-sm btn-danger btn-remove" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            tbody.innerHTML += row;
            total += item.subtotal;
        });
        
        // Update total
        document.getElementById('total-amount').textContent = formatRupiah(total);
        document.getElementById('total-belanja').value = formatRupiah(total);
        
        // Enable/disable payment button based on cart
        if (total > 0) {
            document.getElementById('btn-bayar').disabled = false;
            calculateChange(); // Recalculate change
        } else {
            document.getElementById('btn-bayar').disabled = true;
        }
        
        // Add event listeners to buttons
        document.querySelectorAll('.btn-decrease').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                decreaseQuantity(index);
            });
        });
        
        document.querySelectorAll('.btn-increase').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                increaseQuantity(index);
            });
        });
        
        document.querySelectorAll('.btn-remove').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                removeItem(index);
            });
        });
        
        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', function() {
                const index = parseInt(this.dataset.index);
                updateQuantity(index, parseInt(this.value));
            });
        });
    }
    
    // Function to decrease quantity
    function decreaseQuantity(index) {
        if (cart[index].qty > 1) {
            cart[index].qty -= 1;
            cart[index].subtotal = cart[index].qty * cart[index].price;
            updateCartDisplay();
        }
    }
    
    // Function to increase quantity
    function increaseQuantity(index) {
        if (cart[index].qty < cart[index].stock) {
            cart[index].qty += 1;
            cart[index].subtotal = cart[index].qty * cart[index].price;
            updateCartDisplay();
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Stok Tidak Mencukupi',
                text: `Stok tersedia: ${cart[index].stock} unit`
            });
        }
    }
    
    // Function to update quantity
    function updateQuantity(index, qty) {
        if (qty < 1) {
            qty = 1;
        } else if (qty > cart[index].stock) {
            qty = cart[index].stock;
            Swal.fire({
                icon: 'warning',
                title: 'Stok Tidak Mencukupi',
                text: `Stok tersedia: ${cart[index].stock} unit`
            });
        }
        
        cart[index].qty = qty;
        cart[index].subtotal = cart[index].qty * cart[index].price;
        updateCartDisplay();
    }
    
    // Function to remove item from cart
    function removeItem(index) {
        Swal.fire({
            title: 'Konfirmasi',
            text: "Apakah Anda yakin ingin menghapus barang ini dari keranjang?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                cart.splice(index, 1);
                updateCartDisplay();
            }
        });
    }
    
    // Function to calculate change
    function calculateChange() {
        const totalAmount = parseInt(document.getElementById('total-belanja').value.replace(/\D/g, '')) || 0;
        const paidAmount = parseInt(document.getElementById('bayar').value.replace(/\D/g, '')) || 0;
        const change = paidAmount - totalAmount;
        
        if (paidAmount === 0 || totalAmount === 0) {
            document.getElementById('kembalian').value = '';
            document.getElementById('btn-bayar').disabled = true;
            return;
        }
        
        if (change >= 0) {
            document.getElementById('kembalian').value = formatRupiah(change);
            document.getElementById('btn-bayar').disabled = false;
        } else {
            document.getElementById('kembalian').value = 'Kurang ' + formatRupiah(Math.abs(change));
            document.getElementById('btn-bayar').disabled = true;
        }
    }
    
    // Function to process transaction
    function processTransaction() {
        // Validation
        if (cart.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Keranjang Kosong',
                text: 'Silakan tambahkan barang ke keranjang terlebih dahulu'
            });
            return;
        }
        
        const totalAmount = parseInt(document.getElementById('total-belanja').value.replace(/\D/g, '')) || 0;
        const paidAmount = parseInt(document.getElementById('bayar').value.replace(/\D/g, '')) || 0;
        
        if (totalAmount <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Total Belanja Tidak Valid',
                text: 'Total belanja harus lebih dari 0'
            });
            return;
        }
        
        if (paidAmount <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Jumlah Bayar Tidak Valid',
                text: 'Silakan masukkan jumlah pembayaran yang valid'
            });
            return;
        }
        
        if (paidAmount < totalAmount) {
            Swal.fire({
                icon: 'warning',
                title: 'Pembayaran Kurang',
                text: 'Jumlah pembayaran tidak mencukupi'
            });
            return;
        }
        
        // Show loading
        Swal.fire({
            title: 'Memproses Transaksi',
            html: 'Mohon tunggu...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Prepare transaction data
        const transactionData = {
            no_transaksi: document.getElementById('no-transaksi').textContent,
            tanggal: '<?= $tanggal ?>',
            user_id: <?= $user_id ?>,
            total: totalAmount,
            items: cart
        };
        
        console.log('Transaction Data:', transactionData); // Debug log
        
        // Send transaction data to server
        fetch('ajax/save_transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(transactionData),
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            Swal.close();
            console.log('Response data:', data);
            
            if (data.success) {
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Transaksi Berhasil',
                    html: `
                        <div class="text-center mb-4">
                            <h4>Transaksi Telah Selesai</h4>
                            <p class="mb-1">No Transaksi: <strong>${transactionData.no_transaksi}</strong></p>
                            <p class="mb-1">Total: <strong>${formatRupiah(totalAmount)}</strong></p>
                            <p class="mb-1">Bayar: <strong>${formatRupiah(paidAmount)}</strong></p>
                            <p>Kembalian: <strong>${formatRupiah(paidAmount - totalAmount)}</strong></p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-print"></i> Cetak Struk',
                    cancelButtonText: 'Transaksi Baru'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Print receipt
                        window.open('cetak_struk.php?id=' + data.transaction_id, '_blank', 'width=400,height=600');
                    }
                    
                    // Reset cart for new transaction
                    doResetCart();
                });
            } else {
                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Transaksi Gagal',
                    text: data.message || 'Terjadi kesalahan saat menyimpan transaksi',
                    confirmButtonText: 'Coba Lagi'
                });
            }
        })
        .catch(error => {
            Swal.close();
            console.error('Error saving transaction:', error);
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Terjadi kesalahan saat menyimpan transaksi',
                confirmButtonText: 'Coba Lagi'
            });
        });
    }
    
    // Function to reset cart
    function resetCart() {
        if (cart.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Keranjang Sudah Kosong',
                text: 'Tidak ada yang perlu direset'
            });
            return;
        }
        
        Swal.fire({
            title: 'Reset Transaksi',
            text: "Apakah Anda yakin ingin mengosongkan keranjang?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Reset!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                doResetCart();
                
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: 'Transaksi telah direset',
                    showConfirmButton: false,
                    timer: 1500
                });
            }
        });
    }
    
    // Function to actually reset the cart
    function doResetCart() {
        cart = [];
        total = 0;
        document.getElementById('no-transaksi').textContent = generateTransactionNumber();
        document.getElementById('search-barang').value = '';
        document.getElementById('bayar').value = '';
        document.getElementById('kembalian').value = '';
        updateCartDisplay();
    }
    
    // Function to generate transaction number
    function generateTransactionNumber() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
        
        return `TRX${year}${month}${day}${random}`;
    }
    
    // Format currency
    function formatRupiah(angka) {
        if (angka === 0 || angka === '0') return '0';
        return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
});
</script>

<?php
// Include footer
require_once __DIR__ . '/template/footer.php';
?>