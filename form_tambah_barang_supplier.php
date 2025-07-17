<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';
require_once __DIR__ . '/system/upload_handler.php';

// Proteksi Halaman
cekSupplier();

// Logika Backend
$page_title = 'Tambah Barang Baru dari Supplier';
$supplier_id = $_SESSION['supplier_id'];

// Mengambil kategori HANYA yang dibuat oleh supplier ini
$stmt_kategori = $koneksi->prepare("SELECT * FROM kategori WHERE supplier_id = ? ORDER BY nama_kategori");
$stmt_kategori->bind_param("i", $supplier_id);
$stmt_kategori->execute();
$kategoris = $stmt_kategori->get_result();

$satuans = $koneksi->query("SELECT * FROM satuan ORDER BY nama_satuan");
$kode_barang_otomatis = generateKodeBarang(); // Menggunakan fungsi yang memastikan kode unik global

// Menangani form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_barang = sanitize($_POST['kode_barang']);
    $nama_barang = sanitize($_POST['nama_barang']);
    $kategori_id = (int)$_POST['kategori_id'];
    $satuan_id = (int)$_POST['satuan_id'];
    
    $harga_dari_supplier = (float)preg_replace("/[^0-9]/", "", $_POST['harga_dari_supplier']);
    // Stok tidak lagi diambil dari input supplier, akan diset default 0 atau diabaikan
    // $stok = (int)$_POST['stok']; 
    
    $errors = [];
    if (empty($kode_barang)) $errors[] = "Kode barang tidak boleh kosong.";
    if (empty($nama_barang)) $errors[] = "Nama barang tidak boleh kosong.";
    if ($kategori_id <= 0) $errors[] = "Kategori harus dipilih.";
    if ($harga_dari_supplier <= 0) $errors[] = "Harga harus diisi.";

    // VALIDASI BARU: Pastikan kode barang unik secara GLOBAL (penting untuk Pendekatan A)
    $stmt_check_kode = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ?");
    $stmt_check_kode->bind_param("s", $kode_barang);
    $stmt_check_kode->execute();
    if ($stmt_check_kode->get_result()->num_rows > 0) {
        $errors[] = "Kode barang '<strong>" . htmlspecialchars($kode_barang) . "</strong>' sudah ada di sistem. Mohon gunakan kode lain.";
    }

    $foto_produk_nama = null; // Default value jika tidak ada foto diupload
    if (isset($_FILES['foto_produk']) && $_FILES['foto_produk']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = handleProductPhotoUpload(
            $_FILES['foto_produk'],
            __DIR__ . '/uploads/produk/'
        );
        
        if (isset($uploadResult['error'])) {
            $errors[] = $uploadResult['error'];
        } else {
            $foto_produk_nama = $uploadResult['success'];
        }
    }

    if (empty($errors)) {
        // Query INSERT sekarang tidak lagi menyertakan stok dari input supplier
        // Stok diset 0 secara default untuk barang supplier
        $stok_default = 0; 
        $stmt = $koneksi->prepare(
            "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id, foto_produk) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssiiddiis", $kode_barang, $nama_barang, $kategori_id, $satuan_id, $harga_dari_supplier, $harga_dari_supplier, $stok_default, $supplier_id, $foto_produk_nama);
        
        if ($stmt->execute()) {
            setAlert('success', "Barang baru berhasil ditambahkan.");
            header("Location: form_barang_supplier.php");
            exit();
        } else {
            // Jika gagal menyimpan ke database (misal: duplikasi kode barang yang tidak tertangkap validasi), hapus foto
            if ($foto_produk_nama && file_exists(__DIR__ . '/uploads/produk/' . $foto_produk_nama)) {
                unlink(__DIR__ . '/uploads/produk/' . $foto_produk_nama);
            }
            $db_error_message = $koneksi->error;
            if (strpos($db_error_message, 'Duplicate entry') !== false && strpos($db_error_message, 'kode_barang') !== false) {
                 $errors[] = "Gagal menyimpan: Kode barang '<strong>" . htmlspecialchars($kode_barang) . "</strong>' sudah ada (duplikasi terdeteksi di database).";
            } else {
                 $errors[] = "Gagal menyimpan ke database: " . $db_error_message;
            }
        }
    }
}

// Memuat header
require_once __DIR__ . '/template/header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white"><h6 class="m-0 font-weight-bold"><?= $page_title ?></h6></div>
            <div class="card-body">
                <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $error) echo "<li>".$error."</li>"; ?></ul></div>
                <?php endif; ?>
                
                <form method="POST" action="form_tambah_barang_supplier.php" enctype="multipart/form-data">
                    <div class="row mb-3">
                        <label for="kode_barang" class="col-sm-3 col-form-label">Kode Barang</label>
                        <div class="col-sm-9"><input type="text" class="form-control bg-light" id="kode_barang" name="kode_barang" value="<?= htmlspecialchars($kode_barang_otomatis) ?>" readonly required></div>
                    </div>
                    <div class="row mb-3">
                        <label for="nama_barang" class="col-sm-3 col-form-label">Nama Barang</label>
                        <div class="col-sm-9"><input type="text" class="form-control" id="nama_barang" name="nama_barang" required></div>
                    </div>
                    <div class="row mb-3">
                        <label for="kategori_id" class="col-sm-3 col-form-label">Kategori</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="kategori_id" name="kategori_id" required>
                                <option value="">-- Pilih Kategori Anda --</option>
                                <?php if ($kategoris->num_rows > 0): mysqli_data_seek($kategoris, 0); while ($kategori = $kategoris->fetch_assoc()): ?>
                                <option value="<?= $kategori['id'] ?>"><?= htmlspecialchars($kategori['nama_kategori']) ?></option>
                                <?php endwhile; else: ?>
                                <option value="" disabled>Anda belum membuat kategori. Silakan buat di halaman utama.</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label for="satuan_id" class="col-sm-3 col-form-label">Satuan</label>
                        <div class="col-sm-9">
                            <select class="form-select" id="satuan_id" name="satuan_id" required>
                                <option value="">-- Pilih Satuan --</option>
                                <?php mysqli_data_seek($satuans, 0); while ($satuan = $satuans->fetch_assoc()): ?>
                                <option value="<?= $satuan['id'] ?>"><?= htmlspecialchars($satuan['nama_satuan']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <label for="harga_dari_supplier" class="col-sm-3 col-form-label">Harga Jual Anda (ke Toko)</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control currency" id="harga_dari_supplier" name="harga_dari_supplier" required>
                        </div>
                    </div>

                    <?php /* Baris untuk input stok telah dihapus */ ?>
                    <div class="row mb-3">
                        <label for="foto_produk" class="col-sm-3 col-form-label">Foto Produk</label>
                        <div class="col-sm-9">
                            <input type="file" class="form-control" id="foto_produk" name="foto_produk" accept="image/jpeg, image/png, image/gif">
                            <small class="form-text text-muted">Opsional. Format: JPG, PNG, GIF. Ukuran maks: 2MB.</small>
                            <div id="foto-preview-container" class="mt-2"></div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-9 offset-sm-3">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Barang</button>
                            <a href="form_barang_supplier.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fungsi untuk format input mata uang
    function formatToRupiah(angka) {
        let number_string = String(angka).replace(/[^\d]/g, '').toString();
        return 'Rp ' + (number_string ? new Intl.NumberFormat('id-ID').format(number_string) : '0');
    }
    
    document.querySelectorAll('.currency').forEach(function(input) {
        input.addEventListener('keyup', function(e) { this.value = formatToRupiah(this.value); });
        input.addEventListener('focus', function() { this.value = this.value.replace(/[^\d]/g, ''); });
        input.addEventListener('blur', function() { if(this.value) { this.value = formatToRupiah(this.value); }});
    });

    // Script untuk menampilkan preview gambar saat dipilih
    const fotoInput = document.getElementById('foto_produk');
    const fotoPreviewContainer = document.getElementById('foto-preview-container');

    fotoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        fotoPreviewContainer.innerHTML = ''; // Kosongkan preview sebelumnya

        if (file) {
            // Validasi sederhana di sisi client
            if (file.size > 2 * 1024 * 1024) { // 2MB
                alert('Ukuran file terlalu besar. Maksimal 2MB.');
                this.value = ''; // Reset input file
                return;
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                const img = document.createElement('img');
                img.src = event.target.result;
                img.alt = 'Preview Foto Produk';
                img.className = 'img-thumbnail';
                img.style.maxWidth = '150px';
                img.style.marginTop = '10px';
                fotoPreviewContainer.appendChild(img);
            }
            reader.readAsDataURL(file);
        }
    });
});
</script>

<?php
// Memuat footer
require_once __DIR__ . '/template/footer.php';
?>