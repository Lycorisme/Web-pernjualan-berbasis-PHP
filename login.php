<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/functions/helper.php';

// Alihkan jika sudah ada sesi login aktif
if (isset($_SESSION['user_id'])) { 
    header("Location: dashboard.php"); 
    exit(); 
}
if (isset($_SESSION['supplier_id'])) { 
    header("Location: dashboard_supplier.php"); 
    exit(); 
}

$prefilledPassword = '';
if (isset($_SESSION['prefilled_password'])) {
    $prefilledPassword = $_SESSION['prefilled_password'];
    unset($_SESSION['prefilled_password']);
}

$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['identifier'])) {
    $identifier = trim($_POST['identifier']);
    $password = trim($_POST['password']);

    if (empty($identifier) || empty($password)) {
        $loginError = "Username/Email dan Password wajib diisi.";
    } else {
        $loginAttempted = false;
        $stmt_user = $koneksi->prepare("SELECT * FROM users WHERE username = ?");
        $stmt_user->bind_param("s", $identifier);
        $stmt_user->execute();
        $result_user = $stmt_user->get_result();

        if ($result_user->num_rows === 1) {
            $loginAttempted = true;
            $user = $result_user->fetch_assoc();
            if ($password === $user['password']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role'] = $user['role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $loginError = "Password salah.";
            }
        }

        if (!$loginAttempted) {
            $stmt_supplier = $koneksi->prepare("SELECT * FROM supplier WHERE email = ?");
            $stmt_supplier->bind_param("s", $identifier);
            $stmt_supplier->execute();
            $result_supplier = $stmt_supplier->get_result();

            if ($result_supplier->num_rows === 1) {
                $supplier = $result_supplier->fetch_assoc();
                if ($password === $supplier['password']) {
                    $_SESSION['supplier_id'] = $supplier['id'];
                    $_SESSION['nama_lengkap'] = $supplier['nama_supplier'];
                    $_SESSION['role'] = 'supplier';
                    header("Location: dashboard_supplier.php");
                    exit();
                } else {
                    $loginError = "Password salah.";
                }
            }
        }
        
        if (empty($loginError)) {
            $loginError = "Username atau Email tidak terdaftar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Platinum Komputer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { max-width: 400px; margin: 80px auto; }
        .login-card { border-radius: 10px; box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1); background-color: #fff; padding: 20px; }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card login-card">
            <div class="text-center mb-4">
                <i class="fas fa-desktop fa-4x text-primary"></i>
                <h3 class="mt-2">PLATINUM KOMPUTER</h3>
            </div>
            
            <?php if (!empty($loginError)): ?>
            <div class="alert alert-danger"><?= $loginError ?></div>
            <?php endif; ?>
            
            <form method="POST" action="login.php">
                <div class="mb-3">
                    <label class="form-label">Username atau Email</label>
                    <input type="text" class="form-control" name="identifier" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" class="form-control" name="password" value="<?= htmlspecialchars($prefilledPassword) ?>" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">LOGIN</button>
                </div>
            </form>

            <div class="text-center mt-3">
                <a href="#" data-bs-toggle="modal" data-bs-target="#registerSupplierModal">Daftar sebagai Supplier</a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registerSupplierModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="registerModalLabel">Form Pendaftaran Supplier</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form id="form-registrasi-supplier">
              <div class="mb-3">
                <label class="form-label">Nama Anda (Penanggung Jawab) <span class="text-danger">*</span></label>
                <input type="text" class="form-control auto-generate-trigger" name="nama_supplier" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                <input type="text" class="form-control auto-generate-trigger" name="nama_perusahaan" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Alamat Lengkap <span class="text-danger">*</span></label>
                <textarea class="form-control auto-generate-trigger" name="alamat" rows="3" required></textarea>
              </div>
               <div class="mb-3">
                <label class="form-label">Deskripsi Singkat Perusahaan (Opsional)</label>
                <textarea class="form-control auto-generate-trigger" name="deskripsi" rows="3"></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Nomor Telepon</label>
                <input type="text" class="form-control auto-generate-trigger" name="telepon">
              </div>
              <div class="mb-3">
                <label class="form-label">Email (untuk login) <span class="text-danger">*</span></label>
                <input type="email" class="form-control auto-generate-trigger" name="email" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Password Login</label>
                <div class="input-group">
                    <input type="text" class="form-control bg-light" id="password-registrasi" name="password" placeholder="[Otomatis Terisi]" readonly>
                    <span class="input-group-text d-none" id="password-spinner"><i class="fas fa-spinner fa-spin"></i></span>
                </div>
              </div>
            </form>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="button" class="btn btn-primary" id="btn-submit-registrasi">Kirim Pendaftaran</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.getElementById('btn-submit-registrasi').addEventListener('click', function() {
        const form = document.getElementById('form-registrasi-supplier');
        if (form.checkValidity() === false) {
            Swal.fire('Gagal', 'Mohon isi semua kolom yang ditandai bintang (*).', 'error');
            return;
        }
        if (document.getElementById('password-registrasi').value === '') {
            Swal.fire('Gagal', 'Mohon tunggu hingga password selesai dibuat.', 'error');
            return;
        }
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim...';
        const formData = new FormData(form);
        fetch('ajax/register_supplier.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('registerSupplierModal')).hide();
                Swal.fire('Berhasil Terkirim!', data.message, 'success');
                form.reset();
            } else { Swal.fire('Gagal', data.message, 'error'); }
        })
        .catch(error => { Swal.fire('Error', 'Terjadi kesalahan. Silakan coba lagi.', 'error'); })
        .finally(() => {
            this.disabled = false;
            this.innerHTML = 'Kirim Pendaftaran';
        });
    });

    const triggerFields = document.querySelectorAll('.auto-generate-trigger');
    const passwordInput = document.getElementById('password-registrasi');
    const spinner = document.getElementById('password-spinner');
    let generationTimeout;
    const checkAndGeneratePassword = () => {
        clearTimeout(generationTimeout);
        passwordInput.value = '';
        const allFilled = Array.from(triggerFields).every(field => !field.hasAttribute('required') || field.value.trim() !== '');
        if (allFilled) {
            spinner.classList.remove('d-none');
            generationTimeout = setTimeout(() => {
                passwordInput.value = Math.floor(100000 + Math.random() * 900000);
                spinner.classList.add('d-none');
            }, 3000);
        } else {
            spinner.classList.add('d-none');
        }
    };
    triggerFields.forEach(field => field.addEventListener('input', checkAndGeneratePassword));
    </script>
</body>
</html>
<?php
ob_end_flush();
?>