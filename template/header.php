<?php
// FILE INIT - WAJIB ADA DI ATAS
// Memastikan file koneksi database dan helper dimuat terlebih dahulu
// PERHATIAN: Pastikan file ini di-require_once di SETIAP file PHP utama yang membutuhkannya,
// sebelum ada pemanggilan fungsi yang menggunakan $koneksi atau $_SESSION['role'].
// Contoh: require_once __DIR__ . '/../config/koneksi.php'; di setiap file seperti barang.php, orders.php, dll.
// Error "prepare() on null" mengindikasikan $koneksi tidak terinisialisasi.
require_once __DIR__ . '/../config/koneksi.php'; 
require_once __DIR__ . '/../functions/helper.php'; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Mendapatkan nama file halaman saat ini untuk menyorot menu aktif
$current_page = basename($_SERVER['PHP_SELF']);
// Mendapatkan peran pengguna dari sesi, default ke 'guest' jika tidak ada
$role = $_SESSION['role'] ?? 'guest';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platinum Komputer</title>
    
    <link rel="icon" href="data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3e%3cdefs%3e%3clinearGradient id='grad1' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3e%3cstop offset='0%25' style='stop-color:%234a90e2;stop-opacity:1' /%3e%3cstop offset='100%25' style='stop-color:%23005cbf;stop-opacity:1' /%3e%3c/linearGradient%3e%3c/defs%3e%3cpath fill='url(%23grad1)' d='M17,18 C15.89,18 15,18.89 15,20 C15,21.11 15.89,22 17,22 C18.11,22 19,21.11 19,20 C19,18.89 18.11,18 17,18 M7,18 C5.89,18 5,18.89 5,20 C5,21.11 5.89,22 7,22 C8.11,22 9,21.11 9,20 C9,18.89 8.11,18 7,18 M7.17,14.75 L7.2,14.64 L8.1,13 H15.55 C16.3,13 16.96,12.59 17.3,11.97 L20.88,5.5 C20.95,5.37 21,5.24 21,5.1 C21,4.5 20.55,4 20,4 H4.21 L4.27,4.21 L5.27,6 H6.33 L6.7,6.73 L3,2 H1 V4 H3 L6.6,11.59 L5.25,14.04 C5.09,14.32 5,14.65 5,15 C5,16.1 5.9,17 7,17 H19 V15 H7.42 C7.29,15 7.17,14.89 7.17,14.75 Z'/%3e%3c/svg%3e" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background-color: #f8f9fa; 
            margin: 0; /* Hapus margin default body */
            padding: 0; /* Hapus padding default body */
        }
        .sidebar { 
            min-height: 100vh; 
            background-color: #212529; 
            color: #fff;
            position: fixed; /* Sidebar tetap pada posisinya */
            top: 0;
            left: 0;
            width: 250px; /* Lebar sidebar */
            height: 100vh;
            overflow-y: auto; /* Aktifkan scroll jika konten menu panjang */
            z-index: 1000; /* Pastikan sidebar di atas konten lain */
            transition: transform 0.3s ease; /* Transisi untuk efek slide-in/out di mobile */
            border-right: 1px solid rgba(255, 255, 255, 0.1); /* Garis pemisah visual */
        }
        .sidebar .nav-link { 
            color: rgba(255, 255, 255, 0.8); 
            margin-bottom: 0.2rem; 
            padding: 0.75rem 1rem; 
            transition: all 0.2s ease-in-out; 
            white-space: nowrap;
            border-radius: 0; /* Hapus border-radius default */
        }
        .sidebar .nav-link:hover { 
            color: #fff; 
            background-color: rgba(255, 255, 255, 0.1); 
        }
        .sidebar .nav-link.active { 
            color: #fff; 
            background-color: #0d6efd; 
            border-left: 3px solid #fff; /* Garis aktif */
            padding-left: calc(1rem - 3px); /* Sesuaikan padding agar tidak terlalu menjorok */
        }
        .sidebar .nav-link i { 
            width: 20px; 
            margin-right: 0.75rem; 
            text-align: center; 
        }
        .main-content { 
            margin-left: 250px; /* Konten utama digeser sejauh lebar sidebar */
            padding: 0; /* Hapus padding default */
            min-height: 100vh; /* Pastikan area konten mengambil tinggi penuh */
            background-color: #f8f9fa; /* Samakan dengan background body */
            position: relative; /* Diperlukan untuk toggle menu mobile */
        }
        .user-info { 
            padding: 20px 15px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); 
            margin-bottom: 15px; 
            text-align: center;
        }
        .user-info .user-role { 
            font-size: 0.8rem; 
            color: rgba(255, 255, 255, 0.6); 
            margin-top: 5px;
        }
        .page-header {
            position: sticky;
            top: 0;
            z-index: 999;
            background-color: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        .content-wrapper {
            padding: 1.5rem;
        }
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            margin-top: 1rem;
            letter-spacing: 0.5px;
            cursor: default;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%); /* Sembunyikan sidebar di mobile */
                width: 220px; /* Sedikit lebih kecil di mobile */
            }
            .sidebar.show { /* Kelas ini ditambahkan JS saat tombol toggle diklik */
                transform: translateX(0); /* Tampilkan sidebar */
            }
            .main-content {
                margin-left: 0; /* Tidak ada margin di mobile */
            }
            .mobile-menu-toggle {
                display: block; /* Tampilkan tombol toggle di mobile */
                position: fixed; /* Agar tombol tetap terlihat */
                top: 15px;
                left: 15px;
                z-index: 1001; /* Di atas sidebar saat tersembunyi */
                background-color: #fff;
                border-radius: 5px;
            }
            .page-header {
                padding-left: 50px; /* Sisakan ruang untuk tombol toggle */
            }
        }
        
        @media (min-width: 769px) {
            .mobile-menu-toggle {
                display: none; /* Sembunyikan tombol toggle di desktop */
            }
        }
        
        /* Scrollbar Styling for Sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #2c3034;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #495057;
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #6c757d;
        }
    </style>
</head>
<body>
    <button class="btn btn-outline-secondary mobile-menu-toggle d-md-none" type="button" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <nav class="sidebar" id="sidebarMenu">
        <div class="user-info">
            <i class="fa fa-user-circle fa-3x mb-2 text-primary"></i>
            <h6 class="mb-0"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></h6>
            <div class="user-role"><?= ucfirst(htmlspecialchars($role)) ?></div>
        </div>
        
        <div class="nav-menu">
            <ul class="nav flex-column">
                <?php if ($role === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Manajemen Produk</div>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'kategori.php' ? 'active' : '' ?>" href="kategori.php">
                            <i class="fas fa-tags"></i> Kategori
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['barang.php', 'form_barang.php']) ? 'active' : '' ?>" href="barang.php">
                            <i class="fas fa-box"></i> Data Barang
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['supplier.php', 'lihat_barang_supplier.php', 'pembelian.php']) ? 'active' : '' ?>" href="supplier.php">
                            <i class="fas fa-truck"></i> Pembelian Barang
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Transaksi</div>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>" href="orders.php">
                            <i class="fas fa-clipboard-list"></i> Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'transaksi.php' ? 'active' : '' ?>" href="transaksi.php">
                            <i class="fas fa-cash-register"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'barang_retur.php' ? 'active' : '' ?>" href="barang_retur.php">
                            <i class="fas fa-undo"></i> Barang Retur
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Laporan & Manajemen</div>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['laporan_penjualan.php', 'laporan_pembelian.php']) ? 'active' : '' ?>" href="laporan_penjualan.php">
                            <i class="fas fa-chart-bar"></i> Laporan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>" href="users.php">
                            <i class="fas fa-users"></i> Manajemen User
                        </a>
                    </li>

                <?php elseif ($role === 'supplier'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'dashboard_supplier.php' ? 'active' : '' ?>" href="dashboard_supplier.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Manajemen Produk</div>
                    <li class="nav-item">
                        <a class="nav-link <?= in_array($current_page, ['form_barang_supplier.php', 'form_tambah_barang_supplier.php']) ? 'active' : '' ?>" href="form_barang_supplier.php">
                            <i class="fas fa-box"></i> Barang Saya
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Pesanan & Pembayaran</div>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'orders.php' ? 'active' : '' ?>" href="orders.php">
                            <i class="fas fa-clipboard-list"></i> Pesanan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'kelola_pembayaran.php' ? 'active' : '' ?>" href="kelola_pembayaran.php">
                            <i class="fas fa-credit-card"></i> Kelola Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'barang_terjual.php' ? 'active' : '' ?>" href="barang_terjual.php">
                            <i class="fas fa-history"></i> Barang Terjual
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Retur & Laporan</div>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'retur_supplier.php' ? 'active' : '' ?>" href="retur_supplier.php">
                            <i class="fas fa-inbox"></i> Permintaan Retur
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'barang_diretur_page.php' ? 'active' : '' ?>" href="barang_diretur_page.php">
                            <i class="fas fa-dolly-flatbed"></i> Barang Retur
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'laporan_supplier.php' ? 'active' : '' ?>" href="laporan_supplier.php">
                            <i class="fas fa-chart-pie"></i> Laporan
                        </a>
                    </li>

                <?php elseif ($role === 'kasir'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    
                    <div class="nav-section-title">Transaksi</div>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'transaksi.php' ? 'active' : '' ?>" href="transaksi.php">
                            <i class="fas fa-cash-register"></i> Transaksi
                        </a>
                    </li>

                <?php endif; ?>
                
                <li class="nav-item mt-4">
                    <hr class="text-secondary">
                    <a class="nav-link text-danger" href="#" onclick="konfirmasiLogout(event)">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="main-content">
        <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
            <div class="d-flex align-items-center">
                <?php require_once __DIR__ . '/logo_svg.php'; // Pastikan path benar ?>
                <h1 class="h2 ms-2 mb-0">Platinum Komputer</h1>
            </div>
            <div class="d-flex align-items-center">
                <small class="text-muted">
                    <?= date('d F Y, H:i') ?> WIB
                </small>
            </div>
        </div>
        
        <div class="content-wrapper">
            <?php 
            // Fungsi showAlert() biasanya di functions/helper.php dan menampilkan alert dari $_SESSION
            // Pastikan fungsi ini ada dan bisa diakses.
            if (function_exists('showAlert')) { 
                showAlert(); 
            } 
            ?>
            
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            <script>
            // Fungsi untuk toggle sidebar di mobile
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebarMenu');
                sidebar.classList.toggle('show');
            }

            function konfirmasiLogout(event) {
                event.preventDefault(); 
                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Anda akan keluar dari sesi ini.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, Keluar!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'logout.php';
                    }
                });
            }
            </script>