<?php
// FILE: orders.php
// Halaman Pusat Manajemen Pesanan (Admin & Supplier)

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi halaman
cekLogin(); // Bisa diakses Admin atau Supplier
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'] ?? ($_SESSION['supplier_id'] ?? null); // Dapatkan ID user yang login

// Logika backend untuk mengambil data pesanan
$orders = [];
$query = "";
$stmt = null;

if ($role === 'admin') {
    $query = "
        SELECT 
            o.order_id, o.order_no, o.order_date, o.total_order_price, o.order_status,
            s.nama_perusahaan AS supplier_name, s.email AS supplier_email, s.nama_supplier AS supplier_pic_name, s.telepon AS supplier_contact,
            u.nama_lengkap AS admin_name, u.email AS admin_email, u.alamat AS admin_address, u.telepon AS admin_contact,
            o.payment_type, o.buyer_name, o.buyer_address, o.buyer_contact, o.receiving_warehouse,
            oc.payment_terms_description, oc.payment_due_date, oc.admin_contract_file_path
        FROM orders o
        JOIN supplier s ON o.supplier_id = s.id
        JOIN users u ON o.admin_user_id = u.id
        LEFT JOIN order_contracts oc ON o.order_id = oc.order_id
        ORDER BY o.order_date DESC, o.order_id DESC
    ";
    $result = $koneksi->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
} elseif ($role === 'supplier') {
    $query = "
        SELECT 
            o.order_id, o.order_no, o.order_date, o.total_order_price, o.order_status,
            s.nama_perusahaan AS supplier_name, s.email AS supplier_email, s.nama_supplier AS supplier_pic_name, s.telepon AS supplier_contact,
            u.nama_lengkap AS admin_name, u.email AS admin_email, u.alamat AS admin_address, u.telepon AS admin_contact,
            o.payment_type, o.buyer_name, o.buyer_address, o.buyer_contact, o.receiving_warehouse,
            oc.payment_terms_description, oc.payment_due_date, oc.admin_contract_file_path
        FROM orders o
        JOIN supplier s ON o.supplier_id = s.id
        JOIN users u ON o.admin_user_id = u.id
        LEFT JOIN order_contracts oc ON o.order_id = oc.order_id
        WHERE o.supplier_id = ?
        ORDER BY o.order_date DESC, o.order_id DESC
    ";
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

// Memuat header
require_once __DIR__ . '/template/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="card shadow mb-4">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold">Daftar Pesanan</h6>
        <?php if ($role === 'admin'): ?>
            <a href="supplier.php" class="btn btn-sm btn-light">
                <i class="fas fa-plus-circle"></i> Buat Pesanan Baru
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="ordersTable" width="100%" cellspacing="0">
                <thead class="table-dark">
                    <tr>
                        <th>No. Pesanan</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th>Admin Pembeli</th>
                        <th>Total Harga</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($orders)): ?>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['order_no']) ?></td>
                            <td><?= formatTanggal($order['order_date']) ?></td>
                            <td><?= htmlspecialchars($order['supplier_name']) ?></td>
                            <td><?= htmlspecialchars($order['admin_name']) ?></td>
                            <td><?= formatRupiah($order['total_order_price']) ?></td>
                            <td><?= buatBadgeStatus($order['order_status']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-info btn-detail-order" 
                                        data-order='<?= json_encode($order) ?>'>
                                    <i class="fas fa-eye"></i> Detail
                                </button>
                                <?php if ($role === 'supplier' && $order['order_status'] === 'Di Pesan'): ?>
                                    <button class="btn btn-sm btn-success btn-accept-order" 
                                            data-order-id="<?= $order['order_id'] ?>" 
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>" 
                                            data-admin-email="<?= htmlspecialchars($order['admin_email']) ?>" 
                                            data-supplier-company-name="<?= htmlspecialchars($order['supplier_name']) ?>"
                                            data-supplier-pic-name="<?= htmlspecialchars($order['supplier_pic_name']) ?>"
                                            data-supplier-contact="<?= htmlspecialchars($order['supplier_contact']) ?>"
                                            data-total-order-price="<?= htmlspecialchars($order['total_order_price']) ?>"
                                            data-payment-type="<?= htmlspecialchars($order['payment_type']) ?>">
                                        <i class="fas fa-check"></i> Terima
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-reject-order" 
                                            data-order-id="<?= $order['order_id'] ?>" 
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>" 
                                            data-admin-email="<?= htmlspecialchars($order['admin_email']) ?>" 
                                            data-supplier-company-name="<?= htmlspecialchars($order['supplier_name']) ?>">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $order['order_status'] === 'Diterima Supplier'): ?>
                                    <?php if ($order['payment_type'] === 'tunai'): ?>
                                        <button class="btn btn-sm btn-primary btn-upload-contract" data-order-id="<?= $order['order_id'] ?>" data-order-no="<?= htmlspecialchars($order['order_no']) ?>">
                                            <i class="fas fa-upload"></i> Upload Kontrak
                                        </button>
                                    <?php else: // Jika kredit, admin menunggu pembayaran, tidak ada upload kontrak di sini ?>
                                        <span class="badge bg-secondary">Menunggu Pembayaran</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $order['order_status'] === 'Kontrak Diunggah'): ?>
                                    <a href="generate_contract_pdf.php?order_id=<?= $order['order_id'] ?>" target="_blank" class="btn btn-sm btn-warning">
                                        <i class="fas fa-download"></i> Download Kontrak
                                    </a>
                                <?php endif; ?>
                                <?php if ($role === 'supplier' && ($order['order_status'] === 'Kontrak Diunggah' || $order['order_status'] === 'Menunggu Pembayaran' || $order['order_status'] === 'Lunas')): ?>
                                    <button class="btn btn-sm btn-success btn-confirm-shipment" 
                                            data-order-id="<?= $order['order_id'] ?>"
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>"
                                            data-admin-email="<?= htmlspecialchars($order['admin_email']) ?>"
                                            data-admin-address="<?= htmlspecialchars($order['buyer_address']) ?>"
                                            data-receiving-warehouse="<?= htmlspecialchars($order['receiving_warehouse']) ?>"
                                            data-payment-type="<?= htmlspecialchars($order['payment_type']) ?>"
                                            data-total-order-price="<?= htmlspecialchars($order['total_order_price']) ?>"
                                            data-payment-terms-description="<?= htmlspecialchars($order['payment_terms_description'] ?? '') ?>"
                                            data-payment-due-date="<?= htmlspecialchars($order['payment_due_date'] ?? '') ?>">
                                        <i class="fas fa-truck"></i> Konfirmasi Kirim
                                    </button>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $order['order_status'] === 'Di Antar'): ?>
                                    <button class="btn btn-sm btn-success btn-confirm-receipt" 
                                            data-order-id="<?= $order['order_id'] ?>"
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>"
                                            data-supplier-company-name="<?= htmlspecialchars($order['supplier_name']) ?>"
                                            data-supplier-email="<?= htmlspecialchars($order['supplier_email']) ?>">
                                        <i class="fas fa-box-open"></i> Konfirmasi Terima
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">Tidak ada pesanan ditemukan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="orderDetailModal" tabindex="-1" aria-labelledby="orderDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailModalLabel">Detail Pesanan #<span id="detail-order-no"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Informasi Pesanan</h6>
                        <dl class="row">
                            <dt class="col-sm-4">No. Pesanan</dt><dd class="col-sm-8" id="detail-order-no-val"></dd>
                            <dt class="col-sm-4">Tanggal</dt><dd class="col-sm-8" id="detail-order-date-val"></dd>
                            <dt class="col-sm-4">Supplier</dt><dd class="col-sm-8" id="detail-supplier-name-val"></dd>
                            <dt class="col-sm-4">Admin Pemesan</dt><dd class="col-sm-8" id="detail-admin-name-val"></dd>
                            <dt class="col-sm-4">Total Harga</dt><dd class="col-sm-8" id="detail-total-price-val"></dd>
                            <dt class="col-sm-4">Status</dt><dd class="col-sm-8" id="detail-order-status-val"></dd>
                            <dt class="col-sm-4">Jenis Pembayaran</dt><dd class="col-sm-8" id="detail-payment-type-val"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h6>Informasi Pembeli & Pengiriman</h6>
                        <dl class="row">
                            <dt class="col-sm-4">Nama Pembeli</dt><dd class="col-sm-8" id="detail-buyer-name-val"></dd>
                            <dt class="col-sm-4">Alamat Pembeli</dt><dd class="col-sm-8" id="detail-buyer-address-val"></dd>
                            <dt class="col-sm-4">Kontak Pembeli</dt><dd class="col-sm-8" id="detail-buyer-contact-val"></dd>
                            <dt class="col-sm-4">Gudang Penerima</dt><dd class="col-sm-8" id="detail-receiving-warehouse-val"></dd>
                        </dl>
                    </div>
                </div>
                <h6>Item Pesanan</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Jumlah</th>
                                <th>Harga Satuan</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detail-order-items">
                            </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deliveryDetailsModal" tabindex="-1" aria-labelledby="deliveryDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deliveryDetailsModalLabel">Lengkapi Data Serah Terima</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-delivery-details">
                <div class="modal-body">
                    <input type="hidden" id="delivery-order-id" name="order_id">
                    <input type="hidden" id="delivery-payment-type" name="payment_type">
                    <input type="hidden" id="delivery-admin-email" name="admin_email">
                    <input type="hidden" id="delivery-order-no" name="order_no">
                    <input type="hidden" id="delivery-supplier-company-name" name="supplier_company_name">

                    <div class="mb-3">
                        <label for="supplier-company-name-input" class="form-label">Nama Perusahaan Supplier</label>
                        <input type="text" class="form-control" id="supplier-company-name-input" name="supplier_company_name_contract" required>
                    </div>
                    <div class="mb-3">
                        <label for="supplier-pic-name-input" class="form-label">Nama Penanggung Jawab</label>
                        <input type="text" class="form-control" id="supplier-pic-name-input" name="supplier_pic_name_contract" required>
                    </div>
                    <div class="mb-3">
                        <label for="supplier-contact-input" class="form-label">Kontak Supplier</label>
                        <input type="text" class="form-control" id="supplier-contact-input" name="supplier_contact_contract" required>
                    </div>
                    <div class="mb-3" id="payment-due-date-group">
                        <label for="payment-due-date-input" class="form-label">Tanggal Jatuh Tempo Pembayaran</label>
                        <input type="date" class="form-control" id="payment-due-date-input" name="payment_due_date">
                    </div>
                    <div class="mb-3">
                        <label for="amount-to-pay-input" class="form-label">Jumlah yang Harus Dibayar</label>
                        <input type="text" class="form-control currency" id="amount-to-pay-input" name="amount_to_pay" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="payment-terms-input" class="form-label">Ketentuan Pembayaran</label>
                        <textarea class="form-control" id="payment-terms-input" name="payment_terms_description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-save-delivery-details">Simpan & Konfirmasi Pesanan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadContractModal" tabindex="-1" aria-labelledby="uploadContractModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="uploadContractModalLabel">Upload Kontrak Pesanan #<span id="upload-contract-order-no"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-upload-contract" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="upload-contract-order-id" name="order_id">
                    <div class="mb-3">
                        <label for="contract-file" class="form-label">Pilih File Kontrak (PDF/Gambar)</label>
                        <input class="form-control" type="file" id="contract-file" name="contract_file" accept=".pdf,image/*" required>
                        <small class="form-text text-muted">Hanya file PDF atau gambar (JPG, PNG, GIF). Maksimal 5MB.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btn-submit-contract">Upload Kontrak</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const orderDetailModal = new bootstrap.Modal(document.getElementById('orderDetailModal'));
    const deliveryDetailsModal = new bootstrap.Modal(document.getElementById('deliveryDetailsModal'));
    const uploadContractModal = new bootstrap.Modal(document.getElementById('uploadContractModal'));
    const role = "<?= $role ?>";
    // FIX: Menghapus baris const adminEmail yang tidak digunakan.
    
    // Fungsi untuk memformat angka sebagai Rupiah
    function formatRupiah(angka) {
        if (!angka) return "Rp 0";
        return "Rp " + number_format((parseFloat(angka) || 0), 0, ',', '.');
    }

    // Fungsi untuk memformat angka dengan pemisah ribuan
    function number_format(number, decimals, decPoint, thousandsSep) {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
        var n = !isFinite(+number) ? 0 : +number,
            prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
            sep = (typeof thousandsSep === 'undefined') ? '.' : thousandsSep,
            dec = (typeof decPoint === 'undefined') ? ',' : decPoint,
            s = '',
            toFixedFix = function(n, prec) {
                var k = Math.pow(10, prec);
                return '' + Math.round(n * k) / k;
            };

        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
        if (s[0].length > 3) {
            s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
        }
        if ((s[1] || '').length < prec) {
            s[1] = s[1] || '';
            s[1] += new Array(prec - s[1].length + 1).join('0');
        }
        return s.join(dec);
    }

    // Fungsi untuk mengubah format Rupiah kembali ke angka
    function parseRupiah(rupiah) {
        if (!rupiah) return 0;
        return parseInt(String(rupiah).replace(/[^0-9,-]/g, '').replace(/\./g, '').replace(',', '.')) || 0;
    }

    // Event listener untuk tombol Detail
    document.querySelectorAll('.btn-detail-order').forEach(button => {
        button.addEventListener('click', async function() {
            const orderData = JSON.parse(this.dataset.order);
            
            document.getElementById('detail-order-no').textContent = orderData.order_no;
            document.getElementById('detail-order-no-val').textContent = orderData.order_no;
            document.getElementById('detail-order-date-val').textContent = new Date(orderData.order_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
            document.getElementById('detail-supplier-name-val').textContent = orderData.supplier_name;
            document.getElementById('detail-admin-name-val').textContent = orderData.admin_name;
            document.getElementById('detail-total-price-val').textContent = formatRupiah(orderData.total_order_price);
            
            const statusBadge = createStatusBadge(orderData.order_status);
            document.getElementById('detail-order-status-val').innerHTML = statusBadge;
            
            document.getElementById('detail-payment-type-val').textContent = orderData.payment_type;
            document.getElementById('detail-buyer-name-val').textContent = orderData.buyer_name;
            document.getElementById('detail-buyer-address-val').textContent = orderData.buyer_address;
            document.getElementById('detail-buyer-contact-val').textContent = orderData.buyer_contact;
            document.getElementById('detail-receiving-warehouse-val').textContent = orderData.receiving_warehouse;

            const orderItemsBody = document.getElementById('detail-order-items');
            orderItemsBody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat item...</td></tr>';

            try {
                const response = await fetch(`ajax/get_order_items.php?order_id=${orderData.order_id}`);
                const result = await response.json();
                
                if (!result.success) throw new Error(result.message);
                const items = result.items;

                orderItemsBody.innerHTML = '';
                if (items.length > 0) {
                    items.forEach(item => {
                        const row = `
                            <tr>
                                <td>${item.kode_barang}</td>
                                <td>${item.nama_barang}</td>
                                <td>${item.quantity}</td>
                                <td>${formatRupiah(item.price_per_item)}</td>
                                <td>${formatRupiah(item.subtotal_item_price)}</td>
                            </tr>`;
                        orderItemsBody.insertAdjacentHTML('beforeend', row);
                    });
                } else {
                    orderItemsBody.innerHTML = '<tr><td colspan="5" class="text-center">Tidak ada item.</td></tr>';
                }
            } catch (error) {
                orderItemsBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Gagal memuat item: ${error.message}</td></tr>`;
            }

            orderDetailModal.show();
        });
    });

    function createStatusBadge(status) {
        const statusMap = {
            'Di Pesan': 'badge bg-warning text-dark',
            'Diterima Supplier': 'badge bg-info',
            'Ditolak Supplier': 'badge bg-danger',
            'Kontrak Diunggah': 'badge bg-primary',
            'Menunggu Pembayaran': 'badge bg-secondary',
            'Lunas': 'badge bg-success',
            'Di Antar': 'badge bg-primary',
            'Selesai': 'badge bg-success'
        };
        const badgeClass = statusMap[status] || 'badge bg-secondary';
        return `<span class="${badgeClass}">${status}</span>`;
    }

    document.querySelectorAll('.btn-accept-order').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const orderNo = this.dataset.orderNo;
            const supplierCompanyName = this.dataset.supplierCompanyName;
            const supplierPicName = this.dataset.supplierPicName;
            const supplierContact = this.dataset.supplierContact;
            const totalOrderPrice = this.dataset.totalOrderPrice;
            const paymentType = this.dataset.paymentType;
            const adminEmail = this.dataset.adminEmail;

            document.getElementById('delivery-order-id').value = orderId;
            document.getElementById('delivery-payment-type').value = paymentType;
            document.getElementById('delivery-admin-email').value = adminEmail;
            document.getElementById('delivery-order-no').value = orderNo;
            document.getElementById('delivery-supplier-company-name').value = supplierCompanyName;
            
            document.getElementById('supplier-company-name-input').value = supplierCompanyName;
            document.getElementById('supplier-pic-name-input').value = supplierPicName;
            document.getElementById('supplier-contact-input').value = supplierContact;
            document.getElementById('amount-to-pay-input').value = formatRupiah(totalOrderPrice);

            const paymentDueDateGroup = document.getElementById('payment-due-date-group');
            const paymentTermsInput = document.getElementById('payment-terms-input');
            const paymentDueDateInput = document.getElementById('payment-due-date-input');

            if (paymentType === 'kredit') {
                paymentDueDateGroup.style.display = 'block';
                paymentTermsInput.value = '';
                paymentDueDateInput.required = true;
            } else {
                paymentDueDateGroup.style.display = 'none';
                paymentTermsInput.value = 'Tunai';
                paymentDueDateInput.required = false;
            }
            deliveryDetailsModal.show();
        });
    });

    document.getElementById('form-delivery-details').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'accept_order');
        
        // Convert formatted currency back to number for amount_to_pay
        const amountToPayInput = document.getElementById('amount-to-pay-input');
        formData.set('amount_to_pay', parseRupiah(amountToPayInput.value));
        
        const btnSubmit = document.getElementById('btn-save-delivery-details');
        const originalText = btnSubmit.textContent;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        try {
            const response = await fetch('ajax/process_order_actions.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            
            if (result.success) {
                deliveryDetailsModal.hide();
                Swal.fire('Berhasil', result.message, 'success').then(() => location.reload());
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            Swal.fire('Error', `Gagal menerima pesanan: ${error.message}`, 'error');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = originalText;
        }
    });

   document.querySelectorAll('.btn-reject-order').forEach(button => {
       button.addEventListener('click', async function() {
           const orderId = this.dataset.orderId;
           const orderNo = this.dataset.orderNo;
           const adminEmail = this.dataset.adminEmail;
           const supplierCompanyName = this.dataset.supplierCompanyName;

           const result = await Swal.fire({
               title: 'Tolak Pesanan?',
               text: `Anda yakin ingin menolak pesanan ${orderNo}?`,
               icon: 'warning',
               showCancelButton: true,
               confirmButtonColor: '#d33',
               confirmButtonText: 'Ya, Tolak',
               cancelButtonText: 'Batal'
           });

           if (!result.isConfirmed) return;

           try {
               const formData = new FormData();
               formData.append('action', 'reject_order');
               formData.append('order_id', orderId);
               formData.append('admin_email', adminEmail);
               formData.append('order_no', orderNo);
               formData.append('supplier_company_name', supplierCompanyName);

               const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
               const result = await response.json();
               
               if (result.success) {
                   Swal.fire('Berhasil', result.message, 'success').then(() => location.reload());
               } else {
                   throw new Error(result.message);
               }
           } catch (error) {
               Swal.fire('Error', `Gagal menolak pesanan: ${error.message}`, 'error');
           }
       });
   });

   document.querySelectorAll('.btn-upload-contract').forEach(button => {
       button.addEventListener('click', function() {
           const orderId = this.dataset.orderId;
           const orderNo = this.dataset.orderNo;
           document.getElementById('upload-contract-order-id').value = orderId;
           document.getElementById('upload-contract-order-no').textContent = orderNo;
           uploadContractModal.show();
       });
   });

   document.getElementById('form-upload-contract').addEventListener('submit', async function(e) {
       e.preventDefault();
       
       const formData = new FormData(this);
       formData.append('action', 'upload_contract');

       const btnSubmit = document.getElementById('btn-submit-contract');
       btnSubmit.disabled = true;
       btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengupload...';

       try {
           const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
           const result = await response.json();
           
           if (result.success) {
               uploadContractModal.hide();
               Swal.fire('Berhasil', result.message, 'success').then(() => location.reload());
           } else {
               throw new Error(result.message);
           }
       } catch (error) {
           Swal.fire('Error', `Gagal mengupload kontrak: ${error.message}`, 'error');
       } finally {
           btnSubmit.disabled = false;
           btnSubmit.textContent = 'Upload Kontrak';
       }
   });

   document.querySelectorAll('.btn-confirm-shipment').forEach(button => {
       button.addEventListener('click', async function() {
           const { orderId, orderNo, adminEmail, adminAddress, receivingWarehouse, paymentType, totalOrderPrice, paymentTermsDescription, paymentDueDate } = this.dataset;

           const result = await Swal.fire({
               title: 'Konfirmasi Pengiriman',
               text: `Anda yakin pesanan ${orderNo} sudah dikirim?`,
               icon: 'question',
               showCancelButton: true,
               confirmButtonText: 'Ya, Kirim',
               cancelButtonText: 'Batal'
           });

           if (!result.isConfirmed) return;

           try {
               const formData = new FormData();
               formData.append('action', 'confirm_shipment');
               formData.append('order_id', orderId);
               formData.append('admin_email', adminEmail);
               formData.append('order_no', orderNo);
               formData.append('admin_address', adminAddress);
               formData.append('receiving_warehouse', receivingWarehouse);
               formData.append('payment_type', paymentType);
               formData.append('total_order_price', totalOrderPrice);
               formData.append('payment_terms_description', paymentTermsDescription);
               formData.append('payment_due_date', paymentDueDate);

               const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
               const result = await response.json();
               
               if (result.success) {
                   Swal.fire('Berhasil', result.message, 'success').then(() => location.reload());
               } else {
                   throw new Error(result.message);
               }
           } catch (error) {
               Swal.fire('Error', `Gagal konfirmasi pengiriman: ${error.message}`, 'error');
           }
       });
   });

   document.querySelectorAll('.btn-confirm-receipt').forEach(button => {
       button.addEventListener('click', async function() {
           const { orderId, orderNo, supplierCompanyName, supplierEmail } = this.dataset;
           const result = await Swal.fire({
               title: 'Konfirmasi Penerimaan',
               text: `Anda yakin pesanan ${orderNo} sudah diterima?`,
               icon: 'question',
               showCancelButton: true,
               confirmButtonText: 'Ya, Diterima',
               cancelButtonText: 'Batal'
           });

           if (!result.isConfirmed) return;

           try {
               const formData = new FormData();
               formData.append('action', 'confirm_receipt');
               formData.append('order_id', orderId);
               formData.append('supplier_email', supplierEmail);
               formData.append('order_no', orderNo);
               formData.append('supplier_company_name', supplierCompanyName);

               const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
               const result = await response.json();
               
               if (result.success) {
                   Swal.fire('Berhasil', result.message, 'success').then(() => location.reload());
               } else {
                   throw new Error(result.message);
               }
           } catch (error) {
               Swal.fire('Error', `Gagal konfirmasi penerimaan: ${error.message}`, 'error');
           }
       });
   });
});
</script>

<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>