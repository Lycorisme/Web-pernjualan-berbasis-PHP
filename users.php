<?php
// FILE INTI - WAJIB ADA DI ATAS
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Proteksi Halaman
cekAdmin();

// Inisialisasi variabel
$edit_user = null;
$errors = [];

// Handle form submissions (Tambah & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = sanitize($_POST['username']);
    $nama_lengkap = sanitize($_POST['nama_lengkap']);
    $role = sanitize($_POST['role']);
    $password = $_POST['password'];

    // Validasi dasar
    if (empty($username)) $errors[] = "Username harus diisi";
    if (empty($nama_lengkap)) $errors[] = "Nama lengkap harus diisi";

    if (empty($errors)) {
        if ($id > 0) { // Proses Update
            if (!empty($password)) {
                $stmt = $koneksi->prepare("UPDATE users SET username=?, nama_lengkap=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $username, $nama_lengkap, $role, $password, $id);
            } else {
                $stmt = $koneksi->prepare("UPDATE users SET username=?, nama_lengkap=?, role=? WHERE id=?");
                $stmt->bind_param("sssi", $username, $nama_lengkap, $role, $id);
            }
            if ($stmt->execute()) {
                setAlert('success', 'User berhasil diperbarui.');
            } else {
                setAlert('error', 'Gagal memperbarui user.');
            }
        } else { // Proses Insert
            $stmt = $koneksi->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $password, $nama_lengkap, $role);
            if ($stmt->execute()) {
                setAlert('success', 'User berhasil ditambahkan.');
            } else {
                setAlert('error', 'Gagal menambahkan user.');
            }
        }
        header("Location: users.php");
        exit();
    }
}

// Handle delete request
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id !== $_SESSION['user_id']) {
        $stmt = $koneksi->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            setAlert('success', 'User berhasil dihapus.');
        } else {
            setAlert('error', 'Gagal menghapus user.');
        }
    } else {
        setAlert('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
    }
    header("Location: users.php");
    exit();
}

// Get data for edit form
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $koneksi->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

// Get all users for display
$result = $koneksi->query("SELECT * FROM users ORDER BY username");

require_once __DIR__ . '/template/header.php';
?>

<!-- Load SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold"><?= isset($edit_user) ? 'Edit User' : 'Tambah User' ?></h6>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <form id="user-form" method="POST" action="users.php">
                    <?php if (isset($edit_user)): ?>
                    <input type="hidden" name="id" value="<?= $edit_user['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($edit_user['username'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($edit_user['nama_lengkap'] ?? '') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="<?= isset($edit_user) ? 'Kosongkan jika tidak diubah' : '' ?>" <?= isset($edit_user) ? '' : 'required' ?>>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="admin" <?= (isset($edit_user) && $edit_user['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="kasir" <?= (isset($edit_user) && $edit_user['role'] === 'kasir') ? 'selected' : '' ?>>Kasir</option>
                        </select>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h6 class="m-0 font-weight-bold">Daftar User</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>No</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php $no = 1; while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $row['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                            <?= ucfirst($row['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="users.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                        <a href="javascript:void(0);" onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-3">Tidak ada data user.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fungsi untuk konfirmasi hapus menggunakan SweetAlert
function confirmDelete(id) {
    Swal.fire({
        title: 'Anda yakin?',
        text: "User ini akan dihapus secara permanen!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Jika dikonfirmasi, alihkan ke URL hapus
            window.location.href = 'users.php?delete=' + id;
        }
    });
}

// Fungsi untuk menampilkan notifikasi dari PHP Session menggunakan SweetAlert
document.addEventListener('DOMContentLoaded', function() {
    <?php
    if (isset($_SESSION['alert'])):
        $alert = $_SESSION['alert'];
        // Escape string untuk JavaScript
        $message = addslashes($alert['message']);
        $type = $alert['type'];
        
        // Konversi type sesuai dengan SweetAlert icons
        $icon = '';
        switch($type) {
            case 'success':
                $icon = 'success';
                break;
            case 'error':
                $icon = 'error';
                break;
            case 'warning':
                $icon = 'warning';
                break;
            case 'info':
                $icon = 'info';
                break;
            default:
                $icon = 'info';
        }
        
        $title = ucfirst($type);
        if ($type === 'error') {
            $title = 'Gagal';
        } elseif ($type === 'success') {
            $title = 'Berhasil';
        }
    ?>
    
    Swal.fire({
        icon: '<?= $icon ?>',
        title: '<?= $title ?>',
        text: '<?= $message ?>',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        toast: true,
        position: 'top-end'
    });
    
    <?php
        unset($_SESSION['alert']); // Hapus session setelah ditampilkan
    endif;
    ?>
});

// Konfirmasi sebelum submit form
document.getElementById('user-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const isEdit = document.querySelector('input[name="id"]') !== null;
    const title = isEdit ? 'Update User' : 'Tambah User';
    const text = isEdit ? 'Apakah Anda yakin ingin mengupdate user ini?' : 'Apakah Anda yakin ingin menambahkan user ini?';
    
    Swal.fire({
        title: title,
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Ya, Simpan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Submit form jika dikonfirmasi
            this.submit();
        }
    });
});
</script>

<?php
require_once __DIR__ . '/template/footer.php';
?>