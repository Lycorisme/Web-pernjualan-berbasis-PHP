<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';
require_once __DIR__ . '/system/upload_handler.php';

// Proteksi Halaman
cekAdmin();

// Variabel Dasar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($id > 0);
$barang = [];
$page_title = $is_edit ? 'Edit Barang' : 'Tambah Barang Baru';

$kategoris = $koneksi->query("SELECT * FROM kategori ORDER BY nama_kategori");
$satuans = $koneksi->query("SELECT * FROM satuan ORDER BY nama_satuan");

$kode_barang_otomatis = '';
if (!$is_edit) {
    $kode_barang_otomatis = generateKodeBarang();
}

// =================================================================================
// PENGAMBILAN DATA DARI DATABASE UNTUK MODE EDIT
// Data yang diambil di sini, termasuk harga_beli dan harga_jual, adalah data mentah
// dari tabel 'barang' di database.
// =================================================================================
if ($is_edit) {
    $stmt = $koneksi->prepare("SELECT * FROM barang WHERE id = ? AND supplier_id IS NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        setAlert('error', 'Barang tidak ditemukan atau bukan milik admin.');
        header("Location: barang.php");
        exit();
    }
    // Array $barang sekarang berisi data 100% dari database
    $barang = $result->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (logika untuk memproses form POST tidak diubah, karena sudah benar)
    $kode_barang = sanitize($_POST['kode_barang']);
    $nama_barang = sanitize($_POST['nama_barang']);
    $kategori_id = (int)$_POST['kategori_id'];
    $satuan_id = (int)$_POST['satuan_id'];
    
    // Konversi input yang diformat (misal: "Rp 1.000.000") kembali ke angka untuk disimpan
    $harga_beli = (float)str_replace('.', '', str_replace('Rp ', '', $_POST['harga_beli']));
    $harga_jual = (float)str_replace('.', '', str_replace('Rp ', '', $_POST['harga_jual']));

    $stok = (int)$_POST['stok'];
    
    $errors = [];
    if (empty($kode_barang)) $errors[] = "Kode barang tidak boleh kosong.";
    if (empty($nama_barang)) $errors[] = "Nama barang tidak boleh kosong.";
    if ($kategori_id <= 0) $errors[] = "Kategori harus dipilih.";
    if ($satuan_id <= 0) $errors[] = "Satuan harus dipilih.";
    if ($harga_beli <= 0) $errors[] = "Harga beli harus lebih besar dari 0.";
    if ($harga_jual <= 0) $errors[] = "Harga jual harus lebih besar dari 0.";
    if ($harga_jual <= $harga_beli) $errors[] = "Harga jual harus lebih besar dari harga beli.";
    if ($stok < 0) $errors[] = "Stok tidak boleh kurang dari 0.";
    
    $foto_produk = null;
    if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handleProductPhotoUpload(
            $_FILES['foto_produk'], 
            __DIR__ . '/uploads/produk/', 
            $is_edit ? $barang['foto_produk'] : null
        );
        
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $foto_produk = $uploadResult['success'];
        }
    }
    
    if (empty($errors)) {
        if ($is_edit) {
            if ($foto_produk) {
                $stmt = $koneksi->prepare("UPDATE barang SET nama_barang = ?, kategori_id = ?, satuan_id = ?, harga_beli = ?, harga_jual = ?, stok = ?, foto_produk = ? WHERE id = ? AND supplier_id IS NULL");
                $stmt->bind_param("siiddisi", $nama_barang, $kategori_id, $satuan_id, $harga_beli, $harga_jual, $stok, $foto_produk, $id);
            } else {
                $stmt = $koneksi->prepare("UPDATE barang SET nama_barang = ?, kategori_id = ?, satuan_id = ?, harga_beli = ?, harga_jual = ?, stok = ? WHERE id = ? AND supplier_id IS NULL");
                $stmt->bind_param("siiddii", $nama_barang, $kategori_id, $satuan_id, $harga_beli, $harga_jual, $stok, $id);
            }
            $action_text = 'diperbarui';
        } else {
            $stmt = $koneksi->prepare("INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, foto_produk, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)");
            $stmt->bind_param("ssiiddis", $kode_barang, $nama_barang, $kategori_id, $satuan_id, $harga_beli, $harga_jual, $stok, $foto_produk);
            $action_text = 'ditambahkan';
        }
        
        if ($stmt->execute()) {
            setAlert('success', "Barang berhasil {$action_text}.");
            header("Location: barang.php");
            exit();
        } else {
            $errors[] = "Gagal menyimpan ke database: " . $koneksi->error;
        }
    }
}

// Memuat header (tampilan)
require_once __DIR__ . '/template/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold"><?= $page_title ?></h6>
            </div>
            <div class="card-body">
                <form method="POST" action="form_barang.php<?= $is_edit ? '?id=' . $id : '' ?>" enctype="multipart/form-data">
                    
                    <div class="row mb-3">
                        <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control bg-light" id="kode_barang" name="kode_barang" value="<?= htmlspecialchars($is_edit ? $barang['kode_barang'] : $kode_barang_otomatis) ?>" readonly required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="nama_barang" class="col-sm-3 col-form-label">Nama Barang</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="nama_barang" name="nama_barang" value="<?= htmlspecialchars($barang['nama_barang'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="kategori_id" class="col-sm-3 col-form-label">Kategori</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="kategori_id" name="kategori_id" required <?= $is_edit ? 'disabled' : '' ?>>
                                <option value="">-- Pilih Kategori --</option>
                                <?php mysqli_data_seek($kategoris, 0); while ($kategori = $kategoris->fetch_assoc()): ?>
                                <option value="<?= $kategori['id'] ?>" <?= ($barang['kategori_id'] ?? '') == $kategori['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <?php if ($is_edit): ?>
                                <input type="hidden" name="kategori_id" value="<?= $barang['kategori_id'] ?>">
                                <small class="form-text text-muted">Kategori tidak dapat diubah saat edit.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="satuan_id" class="col-sm-3 col-form-label">Satuan</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="satuan_id" name="satuan_id" required>
                                <option value="">-- Pilih Satuan --</option>
                                <?php mysqli_data_seek($satuans, 0); while ($satuan = $satuans->fetch_assoc()): ?>
                                <option value="<?= $satuan['id'] ?>" <?= ($barang['satuan_id'] ?? '') == $satuan['id'] ? 'selected' : '' ?>><?= htmlspecialchars($satuan['nama_satuan']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="harga_beli" class="col-sm-3 col-form-label">Harga Beli</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control currency" id="harga_beli" name="harga_beli" value="<?= isset($barang['harga_beli']) ? (float)$barang['harga_beli'] : '' ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="harga_jual" class="col-sm-3 col-form-label">Harga Jual</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control currency" id="harga_jual" name="harga_jual" value="<?= isset($barang['harga_jual']) ? (float)$barang['harga_jual'] : '' ?>" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="stok" class="col-sm-3 col-form-label">Stok</label>
                        <div class="col-sm-9">
                            <input type="number" class="form-control" id="stok" name="stok" value="<?= htmlspecialchars($barang['stok'] ?? '0') ?>" min="0" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <label for="foto_produk" class="col-sm-3 col-form-label">Foto Produk</label>
                        <div class="col-sm-9">
                            <input type="file" class="form-control" id="foto_produk" name="foto_produk" accept="image/*">
                            <small class="form-text text-muted">Format yang didukung: JPG, PNG, GIF. Maksimal 2MB.</small>
                            
                            <?php if ($is_edit && !empty($barang['foto_produk'])): ?>
                                <div class="mt-3" id="current-photo">
                                    <div class="d-flex align-items-center">
                                        <img src="uploads/produk/<?= htmlspecialchars($barang['foto_produk']) ?>" 
                                             alt="Foto Produk" 
                                             class="img-thumbnail me-3" 
                                             style="width: 100px; height: 100px; object-fit: cover;">
                                        <div>
                                            <p class="mb-1"><strong>Foto saat ini:</strong> <?= htmlspecialchars($barang['foto_produk']) ?></p>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="hapusFoto(<?= $id ?>)">
                                                <i class="fas fa-trash"></i> Hapus Foto
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-9 offset-sm-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Simpan
                            </button>
                            <a href="barang.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Kembali
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Seluruh JavaScript di bawah ini tidak diubah.
// Logikanya sudah benar: ia akan mengambil nilai APA PUN yang ada di dalam
// input 'currency' saat halaman dimuat, lalu memformatnya.
// Karena PHP sudah memasukkan angka mentah dari database, maka angka itulah yang akan diformat.
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['alert'])): ?>
        const alertData = <?= json_encode($_SESSION['alert']) ?>;
        Swal.fire({
            title: alertData.type === 'success' ? 'Berhasil!' : 'Error!',
            text: alertData.message,
            icon: alertData.type,
            confirmButtonText: 'OK'
        });
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>

    <?php if (isset($errors) && !empty($errors)): ?>
        Swal.fire({
            title: 'Error!',
            html: '<?= implode("<br>", array_map("htmlspecialchars", $errors)) ?>',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    const kategoriSelect = document.getElementById('kategori_id');
    const kodeBarangInput = document.getElementById('kode_barang');
    const isEditMode = <?= $is_edit ? 'true' : 'false' ?>;

    if (!isEditMode) {
        kategoriSelect.addEventListener('change', function() {
            const kategoriId = this.value;
            if (!kategoriId) {
                kodeBarangInput.value = '<?= $kode_barang_otomatis ?>';
                return;
            }
            const selectedOption = this.options[this.selectedIndex];
            const kategoriText = selectedOption.text;
            let kodeKategori = kategoriText.substring(0, 3).toUpperCase().replace(/[^A-Z]/g, '');
            while (kodeKategori.length < 3) kodeKategori += 'X';
            const randomNum = Math.floor(Math.random() * 999) + 1;
            const nomorUrut = String(randomNum).padStart(3, '0');
            kodeBarangInput.value = kodeKategori + nomorUrut;
        });
    }

    function formatToRupiah(angka) {
        let number_string = String(angka).replace(/[^0-9]/g, '').toString();
        if (number_string === '') return 'Rp 0';
        return 'Rp ' + number_string.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    document.querySelectorAll('.currency').forEach(function(input) {
        if (input.value) {
            const numericValue = input.value.replace(/[^0-9]/g, '');
            input.value = formatToRupiah(numericValue);
        }
        input.addEventListener('keyup', function(e) { this.value = formatToRupiah(this.value); });
        input.addEventListener('focus', function() { this.value = this.value.replace(/[^0-9]/g, ''); });
        input.addEventListener('blur', function() {
            this.value = this.value ? formatToRupiah(this.value) : 'Rp 0';
        });
    });

    document.getElementById('foto_produk').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                Swal.fire('Error!', 'Ukuran file terlalu besar. Maksimal 2MB.', 'error');
                this.value = ''; return;
            }
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire('Error!', 'Format file tidak valid. Hanya izinkan JPG, PNG, atau GIF.', 'error');
                this.value = ''; return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                const existingPreview = document.getElementById('foto-preview');
                if (existingPreview) existingPreview.remove();
                const previewDiv = document.createElement('div');
                previewDiv.id = 'foto-preview';
                previewDiv.className = 'mt-3';
                previewDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <img src="${e.target.result}" alt="Preview Foto" class="img-thumbnail me-3" style="width: 100px; height: 100px; object-fit: cover;">
                        <div>
                            <p class="mb-1"><strong>Preview foto baru:</strong> ${file.name}</p>
                            <small class="text-muted">Ukuran: ${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        </div>
                    </div>`;
                document.getElementById('foto_produk').parentNode.appendChild(previewDiv);
            };
            reader.readAsDataURL(file);
        }
    });
});

function hapusFoto(barangId) {
    Swal.fire({
        title: 'Apakah Anda yakin?',
        text: "Foto produk akan dihapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('ajax/hapus_foto_produk.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `barang_id=${barangId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil!', data.message, 'success').then(() => {
                        document.getElementById('current-photo')?.remove();
                    });
                } else {
                    Swal.fire('Error!', data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire('Error!', 'Terjadi kesalahan saat menghapus foto.', 'error');
            });
        }
    });
}
</script>

<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>