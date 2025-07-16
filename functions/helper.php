<?php
// FILE: functions/helper.php (Final & Lengkap)

// Menggunakan Class dari PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Memastikan file koneksi dimuat dan BASE_URL didefinisikan
if (!isset($koneksi)) {
    if (file_exists(__DIR__ . '/../config/koneksi.php')) {
        require_once __DIR__ . '/../config/koneksi.php';
    } else {
        die("<h1>Error Kritis</h1><p>File 'config/koneksi.php' tidak ditemukan.</p>");
    }
}
if (!defined('BASE_URL')) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\') . '/';
    define('BASE_URL', $base_url);
}

function cekLogin() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['supplier_id'])) {
        if (!headers_sent()) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
    }
}

function cekAdmin() {
    cekLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        if (!headers_sent()) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
    }
}

function cekSupplier() {
    cekLogin();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supplier') {
        if (!headers_sent()) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
    }
}

function formatRupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function formatTanggal($tanggal) {
    if (empty($tanggal) || $tanggal === '0000-00-00') { return '-'; }
    try {
        return (new DateTime($tanggal))->format('d M Y');
    } catch (Exception $e) {
        return $tanggal;
    }
}

function generateNoRetur($prefix = 'RTN') {
    global $koneksi;
    $tanggal = date('Ymd');
    $query = "SELECT MAX(no_retur) as max_no FROM retur WHERE no_retur LIKE ?";
    $search_pattern = $prefix . $tanggal . '%';
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $max_no = $result['max_no'];
    $urutan = $max_no ? (int) substr($max_no, -4) : 0;
    $urutan++;
    return $prefix . $tanggal . sprintf('%04d', $urutan);
}

function buatBadgeStatus($status) {
    $badge_class = '';
    switch ($status) {
        case 'Lunas':
        case 'Disetujui':
        case 'Selesai':
            $badge_class = 'bg-success';
            break;
        case 'Proses':
        case 'Menunggu Persetujuan':
        case 'Barang Dikirim':
            $badge_class = 'bg-info text-dark';
            break;
        case 'Belum Lunas':
            $badge_class = 'bg-warning text-dark';
            break;
        case 'Ditolak':
        case 'Dibatalkan':
            $badge_class = 'bg-danger';
            break;
        default:
            $badge_class = 'bg-secondary';
    }
    return '<span class="badge ' . $badge_class . '">' . htmlspecialchars($status) . '</span>';
}

function setAlert($type, $message) {
    $_SESSION['alert'] = ['type' => $type, 'message' => $message];
}

function prosesPenerimaanBarangDariPembelian($pembelian_id) {
    global $koneksi;
    $koneksi->begin_transaction();
    try {
        $stmt_items = $koneksi->prepare("SELECT barang_id, jumlah, harga FROM pembelian_detail WHERE pembelian_id = ?");
        $stmt_items->bind_param("i", $pembelian_id);
        $stmt_items->execute();
        $items = $stmt_items->get_result();

        if ($items->num_rows === 0) {
            throw new Exception("Tidak ada detail barang pada pembelian ini.");
        }

        while ($item = $items->fetch_assoc()) {
            $supplier_barang_id = $item['barang_id'];
            $jumlah_dibeli = $item['jumlah'];
            $harga_beli_baru = $item['harga'];

            $stmt_supplier_item = $koneksi->prepare("SELECT * FROM barang WHERE id = ?");
            $stmt_supplier_item->bind_param("i", $supplier_barang_id);
            $stmt_supplier_item->execute();
            $supplier_item_data = $stmt_supplier_item->get_result()->fetch_assoc();
            
            if (!$supplier_item_data) continue;

            $kode_barang = $supplier_item_data['kode_barang'];

            $stmt_admin_item = $koneksi->prepare("SELECT id, stok FROM barang WHERE kode_barang = ? AND supplier_id IS NULL");
            $stmt_admin_item->bind_param("s", $kode_barang);
            $stmt_admin_item->execute();
            $admin_item = $stmt_admin_item->get_result()->fetch_assoc();

            if ($admin_item) {
                // UPDATE: Barang sudah ada di stok admin, tambah stoknya
                $stmt_update = $koneksi->prepare("UPDATE barang SET stok = stok + ?, harga_beli = ? WHERE id = ?");
                $stmt_update->bind_param("idi", $jumlah_dibeli, $harga_beli_baru, $admin_item['id']);
                if (!$stmt_update->execute()) throw new Exception("Gagal update stok admin.");
            } else {
                // INSERT: Barang belum ada, buat entri baru untuk admin
                $stmt_insert = $koneksi->prepare(
                    "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)"
                );
                $stmt_insert->bind_param("ssiidis",
                    $kode_barang, $supplier_item_data['nama_barang'],
                    $supplier_item_data['kategori_id'], $supplier_item_data['satuan_id'],
                    $harga_beli_baru, $supplier_item_data['harga_jual'], $jumlah_dibeli
                );
                if (!$stmt_insert->execute()) throw new Exception("Gagal insert barang baru ke stok admin.");
            }
        }

        $stmt_update_pembelian = $koneksi->prepare("UPDATE pembelian SET status = 'Lunas' WHERE id = ?");
        $stmt_update_pembelian->bind_param("i", $pembelian_id);
        if (!$stmt_update_pembelian->execute()) throw new Exception("Gagal update status pembelian.");
        
        $koneksi->commit();
        return ['success' => true, 'message' => 'Barang telah diterima dan stok utama berhasil diperbarui.'];
    } catch (Exception $e) {
        $koneksi->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function send_retur_request_email_to_supplier($recipientEmail, $supplierName, $returData) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) return false;
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipientEmail, $supplierName);
        $mail->isHTML(true);
        $mail->Subject = 'Permintaan Retur Baru dari Platinum Komputer - No: ' . $returData['no_retur'];
        $mail->Body    = "
            <h3>Yth. " . htmlspecialchars($supplierName) . ",</h3>
            <p>Anda menerima permintaan retur baru dari <strong>Platinum Komputer</strong> dengan detail sebagai berikut:</p>
            <table style='border-collapse: collapse; width: 100%; border: 1px solid #ddd;'>
                <tr style='background-color: #f2f2f2;'><td style='padding: 8px; border: 1px solid #ddd; width: 30%;'><strong>No. Retur</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($returData['no_retur']) . "</td></tr>
                <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Barang</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($returData['nama_barang']) . "</td></tr>
                <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Jumlah</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($returData['jumlah']) . " unit</td></tr>
                <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Alasan</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . nl2br(htmlspecialchars($returData['alasan'])) . "</td></tr>
            </table>
            <br>
            <p>Silakan login ke dasbor supplier Anda untuk meninjau permintaan ini lebih lanjut.</p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function send_status_update_email_to_admin($adminEmail, $returData) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) return false;
    require_once __DIR__ . '/../vendor/autoload.php';

    $mail = new PHPMailer(true);
    $status_text = htmlspecialchars($returData['status_baru']);
    $status_color = ($returData['status_baru'] == 'Disetujui') ? '#28a745' : '#dc3545';

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($adminEmail, 'Admin Platinum Komputer');
        $mail->isHTML(true);
        $mail->Subject = 'Update Status Retur No. ' . $returData['no_retur'] . ' oleh Supplier';
        $mail->Body    = "
            <h3>Halo Admin,</h3>
            <p>Supplier <strong>" . htmlspecialchars($returData['nama_supplier']) . "</strong> telah memperbarui status untuk permintaan retur barang.</p>
            <table style='border-collapse: collapse; width: 100%; border: 1px solid #ddd;'>
                <tr style='background-color: #f2f2f2;'><td style='padding: 8px; border: 1px solid #ddd; width: 30%;'><strong>No. Retur</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($returData['no_retur']) . "</td></tr>
                <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Barang</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($returData['nama_barang']) . "</td></tr>
                <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Status Baru</strong></td><td style='padding: 8px; border: 1px solid #ddd;'><strong style='color: " . $status_color . ";'>" . $status_text . "</strong></td></tr>
            </table>
            <br>
            <p>Anda dapat melihat detailnya di halaman 'Barang Retur' pada dasbor admin Anda.</p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>