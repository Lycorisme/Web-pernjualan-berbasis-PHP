<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman
cekAdmin();

// Inisialisasi variabel untuk form dan error
$edit_kategori = null;
$errors = [];

// Handle form submissions (Tambah & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kategori = sanitize($_POST['nama_kategori']);
    $deskripsi = sanitize($_POST['deskripsi']);
    $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : 0;

    if (empty($nama_kategori)) $errors[] = "Nama kategori tidak boleh kosong.";

    if (empty($errors)) {
        if ($id > 0) { // Proses Update
            $stmt = $koneksi->prepare("UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id = ? AND supplier_id IS NULL");
            $stmt->bind_param("ssi", $nama_kategori, $deskripsi, $id);
            $action_text = 'diperbarui';
        } else { // Proses Insert
            $stmt = $koneksi->prepare("INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)");
            $stmt->bind_param("ss", $nama_kategori, $deskripsi);
            $action_text = 'ditambahkan';
        }
        if ($stmt->execute()) setAlert('success', "Kategori berhasil {$action_text}.");
        else setAlert('error', 'Gagal menyimpan kategori: ' . $koneksi->error);
        
        header("Location: kategori.php");
        exit();
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt_check_usage = $koneksi->prepare("SELECT COUNT(*) as count FROM barang WHERE kategori_id = ?");
    $stmt_check_usage->bind_param("i", $id);
    $stmt_check_usage->execute();
    $count = $stmt_check_usage->get_result()->fetch_assoc()['count'];

    if ($count > 0) {
        setAlert('error', "Kategori tidak dapat dihapus karena digunakan oleh {$count} barang.");
    } else {
        $stmt_delete = $koneksi->prepare("DELETE FROM kategori WHERE id = ? AND supplier_id IS NULL");
        $stmt_delete->bind_param("i", $id);
        if ($stmt_delete->execute()) setAlert('success', 'Kategori berhasil dihapus.');
        else setAlert('error', 'Gagal menghapus kategori.');
    }
    header("Location: kategori.php");
    exit();
}

// Get data for edit form
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM kategori WHERE id = ? AND supplier_id IS NULL");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) $edit_kategori = $result->fetch_assoc();
}

$list_kategori = $koneksi->query("SELECT * FROM kategori WHERE supplier_id IS NULL ORDER BY nama_kategori");

require_once __DIR__ . '/template/header.php';
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold"><?= $edit_kategori ? 'Edit Kategori Umum' : 'Tambah Kategori Umum' ?></h6>
            </div>
            <div class="card-body">
                <form method="POST" action="kategori.php">
                    <?php if ($edit_kategori): ?><input type="hidden" name="id" value="<?= $edit_kategori['id'] ?>"><?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="nama_kategori" class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nama_kategori" name="nama_kategori" value="<?= htmlspecialchars($edit_kategori['nama_kategori'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?= htmlspecialchars($edit_kategori['deskripsi'] ?? '') ?></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                        <?php if ($edit_kategori): ?><a href="kategori.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Daftar Kategori Umum</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-dark">
                            <tr><th>No</th><th>Nama Kategori</th><th>Deskripsi</th><th>Aksi</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($list_kategori->num_rows > 0): $no = 1; ?>
                                <?php while ($row = $list_kategori->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nama_kategori']) ?></td>
                                    <td><?= htmlspecialchars($row['deskripsi'] ?? '') ?></td>
                                    <td>
                                        <a href="kategori.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                        <a href="kategori.php?delete=<?= $row['id'] ?>" class="btn btn-sm btn-danger btn-hapus" title="Hapus"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                            <tr><td colspan="4" class="text-center py-3">Belum ada data kategori umum.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Notifikasi untuk pesan sukses/gagal (dari session)
    <?php if (isset($_SESSION['alert'])): ?>
        const alertData = <?= json_encode($_SESSION['alert']) ?>;
        Swal.fire({
            title: alertData.type === 'success' ? 'Berhasil!' : 'Gagal!',
            text: alertData.message,
            icon: alertData.type,
            confirmButtonText: 'OK'
        });
        <?php unset($_SESSION['alert']); // Hapus session setelah ditampilkan ?>
    <?php endif; ?>

    // 2. Notifikasi untuk error validasi form
    <?php if (!empty($errors)): ?>
        Swal.fire({
            title: 'Terjadi Kesalahan',
            html: '<?= implode("<br>", array_map("htmlspecialchars", $errors)) ?>',
            icon: 'error',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

    // 3. Konfirmasi Hapus dengan SweetAlert
    const deleteButtons = document.querySelectorAll('.btn-hapus');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            // Mencegah link berjalan secara langsung
            event.preventDefault(); 
            const href = this.getAttribute('href');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Anda tidak akan dapat mengembalikan data ini!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                // Jika pengguna mengonfirmasi, lanjutkan ke link hapus
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
    });
});
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>