<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman
cekAdmin();

// Logika Backend untuk menangani semua proses di halaman ini
// ---------------------------------------------------------------------------------

// Logika untuk menghapus produk
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Ambil data barang terlebih dahulu untuk menghapus foto
    $stmt_get = $koneksi->prepare("SELECT foto_produk FROM barang WHERE id = ? AND supplier_id IS NULL");
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result_get = $stmt_get->get_result();
    $barang_data = $result_get->fetch_assoc();
    
    if ($barang_data) {
        // Hapus file foto jika ada
        if (!empty($barang_data['foto_produk'])) {
            $foto_path = __DIR__ . '/uploads/produk/' . $barang_data['foto_produk'];
            if (file_exists($foto_path)) {
                unlink($foto_path);
            }
        }
        
        // Hapus data dari database
        $stmt_delete = $koneksi->prepare("DELETE FROM barang WHERE id = ? AND supplier_id IS NULL");
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute()) {
            setAlert('success', 'Barang berhasil dihapus beserta fotonya.');
        } else {
            setAlert('error', 'Gagal menghapus barang: ' . $koneksi->error);
        }
    } else {
        setAlert('error', 'Barang tidak ditemukan atau bukan milik admin.');
    }
    
    header("Location: barang.php");
    exit();
}

// Mengambil data untuk dropdown filter
$kategori_result = $koneksi->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori");
$satuan_result = $koneksi->query("SELECT id, nama_satuan FROM satuan ORDER BY nama_satuan");

// ===================================================================================
// PERBAIKAN FATAL: Memastikan hanya barang milik admin (supplier_id IS NULL) yang diambil
// ===================================================================================
$params = [];
$types = "";
// Filter dasar untuk semua query di halaman ini
$base_filters = ["b.supplier_id IS NULL"]; 

// Filter tambahan dari input pengguna
$user_filters = [];
if (isset($_GET['filter']) && $_GET['filter'] == 1) {
    if (!empty($_GET['kategori'])) {
        $user_filters[] = "b.kategori_id = ?";
        $params[] = (int)$_GET['kategori'];
        $types .= "i";
    }
    if (!empty($_GET['satuan'])) {
        $user_filters[] = "b.satuan_id = ?";
        $params[] = (int)$_GET['satuan'];
        $types .= "i";
    }
    if (!empty($_GET['stok'])) {
        if ($_GET['stok'] == 'low') $user_filters[] = "b.stok < 5 AND b.stok > 0";
        if ($_GET['stok'] == 'empty') $user_filters[] = "b.stok = 0";
    }
    if (!empty($_GET['search'])) {
        $user_filters[] = "(b.nama_barang LIKE ? OR b.kode_barang LIKE ?)";
        $search_term = "%" . $_GET['search'] . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
}

// Gabungkan semua filter menjadi satu klausa WHERE
$all_filters = array_merge($base_filters, $user_filters);
$where_clause = " WHERE " . implode(" AND ", $all_filters);
// ===================================================================================
// AKHIR DARI PERBAIKAN LOGIKA QUERY
// ===================================================================================

// Logika Paginasi (penomoran halaman)
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$count_query = "SELECT COUNT(b.id) as total FROM barang b" . $where_clause;
if (!empty($params)) {
    $stmt_count = $koneksi->prepare($count_query);
    $stmt_count->bind_param($types, ...$params);
    $stmt_count->execute();
    $total_rows = $stmt_count->get_result()->fetch_assoc()['total'];
} else {
    // Jika tidak ada filter dari user, hanya ada filter dasar
    $total_rows = $koneksi->query($count_query)->fetch_assoc()['total'];
}
$total_pages = ceil($total_rows / $limit);

// Query utama untuk mengambil data barang
$query = "SELECT b.*, k.nama_kategori, s.nama_satuan FROM barang b 
          LEFT JOIN kategori k ON b.kategori_id = k.id 
          LEFT JOIN satuan s ON b.satuan_id = s.id" . $where_clause . " 
          ORDER BY b.id DESC LIMIT ?, ?";
$stmt = $koneksi->prepare($query);

$current_params = $params;
$current_params[] = $offset;
$current_params[] = $limit;
$current_types = $types . "ii";

$stmt->bind_param($current_types, ...$current_params);
$stmt->execute();
$result = $stmt->get_result();

// Memuat header
require_once __DIR__ . '/template/header.php';

// Ambil pesan alert dari session untuk ditampilkan dengan SweetAlert
$alert_message = null;
$alert_type = null;
if (isset($_SESSION['alert'])) {
    $alert_message = $_SESSION['alert']['message'];
    $alert_type = $_SESSION['alert']['type'];
    unset($_SESSION['alert']);
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold">Daftar Barang (Umum/Milik Admin)</h6>
                <a href="form_barang.php" class="btn btn-sm btn-light">
                    <i class="fas fa-plus-circle"></i> Tambah Barang
                </a>
            </div>
            <div class="card-body">
                <form method="GET" action="barang.php" class="mb-4">
                    <input type="hidden" name="filter" value="1">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Cari nama/kode barang..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="kategori">
                                <option value="">Semua Kategori</option>
                                <?php mysqli_data_seek($kategori_result, 0); while ($k = $kategori_result->fetch_assoc()): ?>
                                <option value="<?= $k['id'] ?>" <?= ($_GET['kategori'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($k['nama_kategori']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="satuan">
                                <option value="">Semua Satuan</option>
                                <?php mysqli_data_seek($satuan_result, 0); while ($s = $satuan_result->fetch_assoc()): ?>
                                <option value="<?= $s['id'] ?>" <?= ($_GET['satuan'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['nama_satuan']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="stok">
                                <option value="">Semua Stok</option>
                                <option value="low" <?= ($_GET['stok'] ?? '') == 'low' ? 'selected' : '' ?>>
                                    Stok Kritis (< 5)
                                </option>
                                <option value="empty" <?= ($_GET['stok'] ?? '') == 'empty' ? 'selected' : '' ?>>
                                    Stok Habis (0)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="barang.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
                
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Foto</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Kategori</th>
                                <th>Harga Beli</th>
                                <th>Harga Jual</th>
                                <th>Stok</th>
                                <th>Satuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): 
                                $no = ($page - 1) * $limit + 1; 
                                while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td>
                                    <?php if (!empty($row['foto_produk'])): ?>
                                        <img src="uploads/produk/<?= htmlspecialchars($row['foto_produk']) ?>" 
                                             alt="Foto Produk" 
                                             class="img-thumbnail" 
                                             style="width: 60px; height: 60px; object-fit: cover; cursor: pointer;"
                                             onclick="showImageModal('<?= htmlspecialchars($row['foto_produk']) ?>', '<?= htmlspecialchars($row['nama_barang']) ?>')">
                                    <?php else: ?>
                                        <div class="bg-light border rounded d-flex align-items-center justify-content-center" 
                                             style="width: 60px; height: 60px;">
                                            <i class="fas fa-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['kode_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_barang']) ?></td>
                                <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                <td><?= formatRupiah($row['harga_beli']) ?></td>
                                <td><?= formatRupiah($row['harga_jual']) ?></td>
                                <td>
                                    <?php if ($row['stok'] <= 0): ?>
                                        <span class="badge bg-danger">Habis</span>
                                    <?php elseif ($row['stok'] < 5): ?>
                                        <span class="badge bg-warning text-dark"><?= $row['stok'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?= $row['stok'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['nama_satuan']) ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="form_barang.php?id=<?= $row['id'] ?>" 
                                           class="btn btn-sm btn-warning" 
                                           title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" 
                                           onclick="confirmDelete(<?= $row['id'] ?>)" 
                                           class="btn btn-sm btn-danger" 
                                           title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-3">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2"></i>
                                        <br>Tidak ada data barang yang cocok dengan filter.
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mt-4">
                        <?php 
                        $query_string = http_build_query(array_diff_key($_GET, ['page' => ''])); 
                        $prev_page = max(1, $page - 1);
                        $next_page = min($total_pages, $page + 1);
                        ?>
                        
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $prev_page ?>&<?= $query_string ?>" 
                               aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php 
                        // Menentukan range halaman yang ditampilkan
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        // Jika di awal, tampilkan lebih banyak halaman ke depan
                        if ($page <= 3) {
                            $end_page = min($total_pages, 5);
                        }
                        
                        // Jika di akhir, tampilkan lebih banyak halaman ke belakang
                        if ($page >= $total_pages - 2) {
                            $start_page = max(1, $total_pages - 4);
                        }
                        
                        // Tampilkan halaman pertama jika tidak dalam range
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1&<?= $query_string ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= $query_string ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?>&<?= $query_string ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $next_page ?>&<?= $query_string ?>" 
                               aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <small class="text-muted">
                        Menampilkan <?= (($page - 1) * $limit) + 1 ?> - <?= min($page * $limit, $total_rows) ?> 
                        dari <?= $total_rows ?> data
                    </small>
                    <small class="text-muted">
                        Halaman <?= $page ?> dari <?= $total_pages ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Foto Produk</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="Foto Produk" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Tampilkan notifikasi SweetAlert jika ada
<?php if ($alert_message): ?>
document.addEventListener('DOMContentLoaded', function() {
    <?php
    $swal_icon = 'info';
    if ($alert_type === 'success') {
        $swal_icon = 'success';
    } elseif ($alert_type === 'error') {
        $swal_icon = 'error';
    } elseif ($alert_type === 'warning') {
        $swal_icon = 'warning';
    }
    ?>
    
    Swal.fire({
        icon: '<?= $swal_icon ?>',
        title: '<?= $alert_type === 'success' ? 'Berhasil!' : ($alert_type === 'error' ? 'Gagal!' : 'Informasi') ?>',
        text: '<?= addslashes($alert_message) ?>',
        showConfirmButton: true,
        confirmButtonText: 'OK',
        timer: <?= $alert_type === 'success' ? '3000' : '0' ?>,
        timerProgressBar: <?= $alert_type === 'success' ? 'true' : 'false' ?>
    });
});
<?php endif; ?>

function confirmDelete(id) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: 'Apakah Anda yakin ingin menghapus barang ini? Foto produk juga akan terhapus secara permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Tampilkan loading
            Swal.fire({
                title: 'Menghapus...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Redirect ke URL hapus
            window.location.href = 'barang.php?delete=' + id;
        }
    });
}

function showImageModal(filename, productName) {
    document.getElementById('modalImage').src = 'uploads/produk/' + filename;
    document.getElementById('imageModalLabel').textContent = 'Foto Produk: ' + productName;
    
    // Buka modal menggunakan Bootstrap 5
    var modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}

// Auto-submit form ketika dropdown berubah (opsional)
document.addEventListener('DOMContentLoaded', function() {
    // Tambahkan event listener untuk auto-submit jika diinginkan
    const filterInputs = document.querySelectorAll('select[name="kategori"], select[name="satuan"], select[name="stok"]');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            // Uncomment baris berikut jika ingin auto-submit
            // this.form.submit();
        });
    });
    
    // Enter key untuk search
    document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.form.submit();
        }
    });
});

// Fungsi untuk menampilkan notifikasi success dengan SweetAlert
function showSuccessAlert(message) {
    Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: message,
        showConfirmButton: true,
        confirmButtonText: 'OK',
        timer: 3000,
        timerProgressBar: true
    });
}

// Fungsi untuk menampilkan notifikasi error dengan SweetAlert
function showErrorAlert(message) {
    Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: message,
        showConfirmButton: true,
        confirmButtonText: 'OK'
    });
}

// Fungsi untuk menampilkan notifikasi warning dengan SweetAlert
function showWarningAlert(message) {
    Swal.fire({
        icon: 'warning',
        title: 'Peringatan!',
        text: message,
        showConfirmButton: true,
        confirmButtonText: 'OK'
    });
}

// Fungsi untuk menampilkan notifikasi info dengan SweetAlert
function showInfoAlert(message) {
    Swal.fire({
        icon: 'info',
        title: 'Informasi',
        text: message,
        showConfirmButton: true,
        confirmButtonText: 'OK'
    });
}
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>