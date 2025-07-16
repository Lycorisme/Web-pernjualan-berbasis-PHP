<?php
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
    
    <link rel="icon" href="data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3e%3cdefs%3e%3clinearGradient id='grad1' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3e%3cstop offset='0%25' style='stop-color:%234a90e2;stop-opacity:1' /%3e%3cstop offset='100%25' style='stop-color:%23005cbf;stop-opacity:1' /%3e%3c/linearGradient%3e%3c/defs%3e%3cpath fill='url(%23grad1)' d='M17,18 C15.89,18 15,18.89 15,20 C15,21.11 15.89,22 17,22 C18.11,22 19,21.11 19,20 C19,18.89 18.11,18 17,18 M7,18 C5.89,18 5,18.89 5,20 C5,21.11 5.89,22 7,22 C8.11,22 9,21.11 9,20 C9,18.89 8.11,18 7,18 M7.17,14.75 L7.2,14.64 L8.1,13 H15.55 C16.3,13 16.96,12.59 17.3,11.97 L20.88,5.5 C20.95,5.37 21,5.24 21,5.1 C21,4.5 20.55,4 20,4 H4.21 L4.27,4.21 L5.27,6 H6.33 L6.7,6.73 L3,2 H1 V4 H3 L6.6,11.59 L5.25,14.04 C5.09,14.32 5,14.65 5,15 C5,16.1 5.9,17 7,17 H19 V15 H7.42 C7.29,15 7.17,14.89 7.17,14.75 Z'/%3e%3c/svg%3e">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .sidebar { 
            min-height: 100vh; 
            background-color: #212529; 
            color: #fff;
            position: sticky;
            top: 0;
            height: 100vh;
        }
        .sidebar .nav-link { 
            color: rgba(255, 255, 255, 0.8); 
            margin-bottom: 0.2rem; 
            padding: 0.75rem 1rem; 
            transition: all 0.2s ease-in-out; 
            white-space: nowrap;
        }
        .sidebar .nav-link:hover { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .nav-link.active { color: #fff; background-color: #0d6efd; border-left: 3px solid #fff; padding-left: calc(1rem - 3px); }
        .sidebar .nav-link i.fas { width: 20px; margin-right: 0.75rem; text-align: center; }
        .main-content { 
            padding: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .user-info { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 15px; }
        .user-info .user-role { font-size: 0.8rem; color: rgba(255, 255, 255, 0.6); }
        .page-header {
            position: sticky;
            top: 0;
            z-index: 1020;
            background-color: #f8f9fa;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }
        .content-wrapper {
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebarMenu">
                <div class="user-info text-center">
                    <i class="fa fa-user-circle fa-3x mb-2"></i>
                    <h6 class="mb-0"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'User') ?></h6>
                    <div class="user-role"><?= ucfirst(htmlspecialchars($role)) ?></div>
                </div>
                <div class="position-sticky">
                    <ul class="nav flex-column">

                        <?php if ($role === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'kategori.php' ? 'active' : '' ?>" href="kategori.php"><i class="fas fa-tags"></i> Kategori</a></li>
                            <li class="nav-item"><a class="nav-link <?= in_array($current_page, ['barang.php', 'form_barang.php']) ? 'active' : '' ?>" href="barang.php"><i class="fas fa-box"></i> Data Barang</a></li>
                            <li class="nav-item"><a class="nav-link <?= in_array($current_page, ['supplier.php', 'lihat_barang_supplier.php', 'pembelian.php']) ? 'active' : '' ?>" href="supplier.php"><i class="fas fa-truck"></i> Pembelian Barang</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'transaksi.php' ? 'active' : '' ?>" href="transaksi.php"><i class="fas fa-cash-register"></i> Transaksi</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'barang_retur.php' ? 'active' : '' ?>" href="barang_retur.php"><i class="fas fa-undo"></i> Barang Retur</a></li>
                            <li class="nav-item"><a class="nav-link <?= in_array($current_page, ['laporan_penjualan.php', 'laporan_pembelian.php']) ? 'active' : '' ?>" href="laporan_penjualan.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'users.php' ? 'active' : '' ?>" href="users.php"><i class="fas fa-users"></i> Manajemen User</a></li>

                        <?php elseif ($role === 'supplier'): ?>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'dashboard_supplier.php' ? 'active' : '' ?>" href="dashboard_supplier.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link <?= in_array($current_page, ['form_barang_supplier.php', 'form_tambah_barang_supplier.php']) ? 'active' : '' ?>" href="form_barang_supplier.php"><i class="fas fa-box"></i> Barang Saya</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'kelola_pembayaran.php' ? 'active' : '' ?>" href="kelola_pembayaran.php"><i class="fas fa-hand-holding-usd"></i> Kelola Pembayaran</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'barang_terjual.php' ? 'active' : '' ?>" href="barang_terjual.php"><i class="fas fa-history"></i> Barang Terjual</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'retur_supplier.php' ? 'active' : '' ?>" href="retur_supplier.php"><i class="fas fa-undo"></i> Barang Retur</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'laporan_supplier.php' ? 'active' : '' ?>" href="laporan_supplier.php"><i class="fas fa-chart-pie"></i> Laporan</a></li>

                        <?php elseif ($role === 'kasir'): ?>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link <?= $current_page == 'transaksi.php' ? 'active' : '' ?>" href="transaksi.php"><i class="fas fa-cash-register"></i> Transaksi</a></li>

                        <?php endif; ?>
                        
                        <hr class="text-secondary">
                        <li class="nav-item"><a class="nav-link" href="#" onclick="konfirmasiLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <div class="page-header d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div class="d-flex align-items-center">
                        <?php require_once 'logo_svg.php'; ?>
                        <h1 class="h2 ms-2">Platinum Komputer</h1>
                    </div>
                </div>
                
                <div class="content-wrapper">
                    <?php if (function_exists('showAlert')) { showAlert(); } ?>
                    
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>
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