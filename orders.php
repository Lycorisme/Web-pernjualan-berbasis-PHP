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

// Mengambil data untuk admin
if ($role === 'admin') {
    // ---- QUERY UNTUK ADMIN (DIPERBAIKI) ----
    $query = "
        SELECT 
            o.order_id, o.order_no, o.order_date, o.total_order_price, o.order_status,
            s.nama_perusahaan AS supplier_name, s.email AS supplier_email, s.nama_supplier AS supplier_pic_name, s.telepon AS supplier_contact,
            u.nama_lengkap AS admin_name, u.email AS admin_email, u.alamat AS admin_address, u.telepon AS admin_contact,
            o.payment_type, o.buyer_name, o.buyer_address, o.buyer_contact,
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
// Mengambil data untuk supplier
} elseif ($role === 'supplier') {
    // ---- QUERY UNTUK SUPPLIER (DIPERBAIKI) ----
    $query = "
        SELECT 
            o.order_id, o.order_no, o.order_date, o.total_order_price, o.order_status,
            s.nama_perusahaan AS supplier_name, s.email AS supplier_email, s.nama_supplier AS supplier_pic_name, s.telepon AS supplier_contact,
            u.nama_lengkap AS admin_name, u.email AS admin_email, u.alamat AS admin_address, u.telepon AS admin_contact,
            o.payment_type, o.buyer_name, o.buyer_address, o.buyer_contact,
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
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $order['order_status'] === 'Diterima Supplier'): ?>
                                    <button class="btn btn-sm btn-primary btn-upload-contract" data-order-id="<?= $order['order_id'] ?>" data-order-no="<?= htmlspecialchars($order['order_no']) ?>">
                                        <i class="fas fa-upload"></i> Upload Kontrak
                                    </button>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $order['order_status'] === 'Kontrak Diunggah'): ?>
                                    <a href="generate_contract_pdf.php?order_id=<?= $order['order_id'] ?>" target="_blank" class="btn btn-sm btn-warning">
                                        <i class="fas fa-download"></i> Download Kontrak
                                    </a>
                                <?php endif; ?>
                                <?php if ($role === 'supplier' && ($order['order_status'] === 'Kontrak Diunggah' || $order['order_status'] === 'Menunggu Pembayaran' || $order['order_status'] === 'Lunas')): ?>
                                    <button class="btn btn-sm btn-success btn-confirm-shipment" 
                                            data-order-id="<?= $order['order_id'] ?>"
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>">
                                        <i class="fas fa-truck"></i> Konfirmasi Kirim
                                    </button>
                                <?php endif; ?>
                                <?php if ($role === 'admin' && $order['order_status'] === 'Di Antar'): ?>
                                    <button class="btn btn-sm btn-success btn-confirm-receipt" 
                                            data-order-id="<?= $order['order_id'] ?>"
                                            data-order-no="<?= htmlspecialchars($order['order_no']) ?>">
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
                            <dt class="col-sm-5">No. Pesanan</dt><dd class="col-sm-7" id="detail-order-no-val"></dd>
                            <dt class="col-sm-5">Tanggal</dt><dd class="col-sm-7" id="detail-order-date-val"></dd>
                            <dt class="col-sm-5">Supplier</dt><dd class="col-sm-7" id="detail-supplier-name-val"></dd>
                            <dt class="col-sm-5">Admin Pemesan</dt><dd class="col-sm-7" id="detail-admin-name-val"></dd>
                            <dt class="col-sm-5">Total Harga</dt><dd class="col-sm-7" id="detail-total-price-val"></dd>
                            <dt class="col-sm-5">Status</dt><dd class="col-sm-7" id="detail-order-status-val"></dd>
                            <dt class="col-sm-5">Jenis Pembayaran</dt><dd class="col-sm-7" id="detail-payment-type-val"></dd>
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <h6>Informasi Pembeli & Pengiriman</h6>
                        <dl class="row">
                            <dt class="col-sm-5">Nama Pembeli</dt><dd class="col-sm-7" id="detail-buyer-name-val"></dd>
                            <dt class="col-sm-5">Alamat Pembeli</dt><dd class="col-sm-7" id="detail-buyer-address-val"></dd>
                            <dt class="col-sm-5">Kontak Pembeli</dt><dd class="col-sm-7" id="detail-buyer-contact-val"></dd>
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
                        <input type="text" class="form-control" id="amount-to-pay-input" name="amount_to_pay" readonly>
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
                        <small class="form-text text-muted">Hanya file PDF atau gambar (JPG, PNG). Maksimal 5MB.</small>
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
    
    function formatRupiah(angka) {
        return "Rp " + (new Intl.NumberFormat('id-ID').format(angka || 0));
    }

    function parseRupiah(rupiah) {
        return parseInt(String(rupiah).replace(/[^0-9]/g, '')) || 0;
    }

    document.querySelectorAll('.btn-detail-order').forEach(button => {
        button.addEventListener('click', async function() {
            const orderData = JSON.parse(this.dataset.order);
            
            // Mengisi data modal detail
            document.getElementById('detail-order-no').textContent = orderData.order_no;
            document.getElementById('detail-order-no-val').textContent = orderData.order_no;
            document.getElementById('detail-order-date-val').textContent = new Date(orderData.order_date).toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
            document.getElementById('detail-supplier-name-val').textContent = orderData.supplier_name;
            document.getElementById('detail-admin-name-val').textContent = orderData.admin_name;
            document.getElementById('detail-total-price-val').textContent = formatRupiah(orderData.total_order_price);
            document.getElementById('detail-order-status-val').innerHTML = `<?= buatBadgeStatus('STATUS_PLACEHOLDER') ?>`.replace('STATUS_PLACEHOLDER', orderData.order_status);
            document.getElementById('detail-payment-type-val').textContent = orderData.payment_type;
            document.getElementById('detail-buyer-name-val').textContent = orderData.buyer_name;
            document.getElementById('detail-buyer-address-val').textContent = orderData.buyer_address;
            document.getElementById('detail-buyer-contact-val').textContent = orderData.buyer_contact;
            // Baris untuk mengisi info gudang Dihapus dari sini

            const orderItemsBody = document.getElementById('detail-order-items');
            orderItemsBody.innerHTML = '<tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Memuat item...</td></tr>';

            try {
                const response = await fetch(`ajax/get_order_items.php?order_id=${orderData.order_id}`);
                const result = await response.json();
                
                if (!result.success) throw new Error(result.message);

                orderItemsBody.innerHTML = '';
                if (result.items.length > 0) {
                    result.items.forEach(item => {
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

    document.querySelectorAll('.btn-accept-order').forEach(button => {
        button.addEventListener('click', function() {
            const { orderId, orderNo, supplierCompanyName, supplierPicName, supplierContact, totalOrderPrice, paymentType } = this.dataset;

            document.getElementById('delivery-order-id').value = orderId;
            document.getElementById('delivery-payment-type').value = paymentType;
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
        formData.set('amount_to_pay', parseRupiah(formData.get('amount_to_pay')));
        
        const btnSubmit = document.getElementById('btn-save-delivery-details');
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';

        try {
            const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                deliveryDetailsModal.hide();
                Swal.fire('Berhasil', result.message, 'success').then(() => location.reload());
            } else { throw new Error(result.message); }
        } catch (error) {
            Swal.fire('Error', `Gagal menerima pesanan: ${error.message}`, 'error');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Simpan & Konfirmasi Pesanan';
        }
    });

    document.querySelectorAll('.btn-reject-order').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;

            Swal.fire({
                title: 'Tolak Pesanan Ini?',
                text: "Harap berikan alasan penolakan pesanan.",
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Ketik alasan penolakan di sini...',
                inputValidator: (value) => !value && 'Anda harus memberikan alasan penolakan!',
                showCancelButton: true,
                confirmButtonText: 'Ya, Tolak Pesanan',
                cancelButtonText: 'Batal',
            }).then((result) => {
                if (result.isConfirmed) {
                    const rejectionReason = result.value;
                    const formData = new URLSearchParams();
                    formData.append('action', 'reject_order');
                    formData.append('order_id', orderId);
                    formData.append('rejection_reason', rejectionReason);

                    fetch('ajax/process_order_actions.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Gagal', data.message, 'error');
                        }
                    })
                    .catch(() => Swal.fire('Error', 'Terjadi kesalahan saat menghubungi server.', 'error'));
                }
            });
        });
    });

   document.querySelectorAll('.btn-upload-contract').forEach(button => {
       button.addEventListener('click', function() {
           const { orderId, orderNo } = this.dataset;
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
           } else { throw new Error(result.message); }
       } catch (error) {
           Swal.fire('Error', `Gagal mengupload kontrak: ${error.message}`, 'error');
       } finally {
           btnSubmit.disabled = false;
           btnSubmit.textContent = 'Upload Kontrak';
       }
   });

   document.querySelectorAll('.btn-confirm-shipment').forEach(button => {
       button.addEventListener('click', async function() {
           const { orderId, orderNo } = this.dataset;
           const result = await Swal.fire({
               title: 'Konfirmasi Pengiriman',
               text: `Anda yakin pesanan ${orderNo} sudah dikirim?`,
               icon: 'question',
               showCancelButton: true,
               confirmButtonText: 'Ya, Kirim',
           });

           if (!result.isConfirmed) return;

           try {
               const formData = new FormData();
               formData.append('action', 'confirm_shipment');
               formData.append('order_id', orderId);
               const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
               const res = await response.json();
               if (res.success) {
                   Swal.fire('Berhasil', res.message, 'success').then(() => location.reload());
               } else { throw new Error(res.message); }
           } catch (error) {
               Swal.fire('Error', `Gagal konfirmasi pengiriman: ${error.message}`, 'error');
           }
       });
   });
    
   document.querySelectorAll('.btn-confirm-receipt').forEach(button => {
       button.addEventListener('click', async function() {
           const { orderId, orderNo } = this.dataset;
           const result = await Swal.fire({
               title: 'Konfirmasi Penerimaan',
               text: `Anda yakin pesanan ${orderNo} sudah diterima? Stok akan diperbarui.`,
               icon: 'question',
               showCancelButton: true,
               confirmButtonText: 'Ya, Diterima',
           });

           if (!result.isConfirmed) return;

           try {
               const formData = new FormData();
               formData.append('action', 'confirm_receipt');
               formData.append('order_id', orderId);
               const response = await fetch('ajax/process_order_actions.php', { method: 'POST', body: formData });
               const res = await response.json();
               if (res.success) {
                   Swal.fire('Berhasil', res.message, 'success').then(() => location.reload());
               } else { throw new Error(res.message); }
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