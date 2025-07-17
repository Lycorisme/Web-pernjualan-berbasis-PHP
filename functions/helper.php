<?php
// FILE: functions/helper.php (VERSI LENGKAP & DIPERBAIKI)

// --- PERBAIKAN: Memuat autoloader dengan lebih andal ---
$autoloader_path = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader_path)) {
    require_once $autoloader_path;
} else {
    // Hentikan eksekusi dan berikan pesan error yang jelas jika autoloader tidak ditemukan.
    // Ini akan mencegah error "Class not found" yang membingungkan.
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error Kritis: File autoloader Composer tidak ditemukan. Pastikan Anda sudah menjalankan "composer install". Path yang dicari: ' . $autoloader_path
    ]);
    exit;
}

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

// Fungsi sanitasi input
function sanitize($data) {
    global $koneksi; // Mengakses variabel koneksi global

    // Hapus spasi di awal dan akhir string
    $data = trim($data);
    // Hapus backslashes
    $data = stripslashes($data);
    // Konversi karakter khusus HTML ke entitas HTML
    $data = htmlspecialchars($data);
    // Hindari injeksi SQL (penting jika Anda menggunakan input ini dalam query SQL)
    if (isset($koneksi) && $koneksi instanceof mysqli) { // Pastikan $koneksi adalah objek mysqli yang valid
        $data = mysqli_real_escape_string($koneksi, $data);
    }
    return $data;
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

// Fungsi untuk menghasilkan kode barang (sudah memastikan global uniqueness)
function generateKodeBarang($kategori_id = null) {
    global $koneksi;
    $prefix = 'BRG'; // Default prefix jika kategori_id tidak diberikan

    if ($kategori_id) {
        $stmt_kategori = $koneksi->prepare("SELECT nama_kategori FROM kategori WHERE id = ?");
        $stmt_kategori->bind_param("i", $kategori_id);
        $stmt_kategori->execute();
        $result_kategori = $stmt_kategori->get_result();
        if ($result_kategori->num_rows > 0) {
            $nama_kategori = $result_kategori->fetch_assoc()['nama_kategori'];
            $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nama_kategori), 0, 3));
            while (strlen($prefix) < 3) {
                $prefix .= 'X'; // Tambahkan 'X' jika kurang dari 3 karakter
            }
        }
    }

    // Mencari kode barang terakhir dengan prefix yang sama di SELURUH tabel barang
    $search_pattern = $prefix . '-%';
    $stmt_barang = $koneksi->prepare("SELECT MAX(kode_barang) as max_kode FROM barang WHERE kode_barang LIKE ?");
    $stmt_barang->bind_param("s", $search_pattern);
    $stmt_barang->execute();
    $max_kode_result = $stmt_barang->get_result()->fetch_assoc();
    $max_kode = $max_kode_result['max_kode'];

    $nomor_urut = 0;
    if ($max_kode) {
        $parts = explode('-', $max_kode);
        $nomor_urut = (int)end($parts);
    }
    $nomor_urut++;

    // Pastikan kode yang dihasilkan benar-benar unik secara global
    do {
        $nomor_urut_formatted = sprintf('%03d', $nomor_urut);
        $kode_barang_kandidat = $prefix . '-' . $nomor_urut_formatted;
        $stmt_check = $koneksi->prepare("SELECT id FROM barang WHERE kode_barang = ?");
        $stmt_check->bind_param("s", $kode_barang_kandidat);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            // Jika kode sudah ada, naikkan nomor urut dan coba lagi
            $nomor_urut++;
            $is_unique = false;
        } else {
            // Jika kode tidak ada, berarti unik
            $is_unique = true;
        }
    } while (!$is_unique);

    return $kode_barang_kandidat;
}


// Fungsi untuk menghasilkan nomor transaksi
function generateNoTransaksi() {
    $today = date('Ymd');
    $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
    return "TRX{$today}{$random}";
}

// Fungsi untuk menghasilkan nomor pembelian
function generateNoPembelian() {
    $today = date('Ymd');
    $random = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);
    return "PMB{$today}{$random}";
}

// --- NEW FUNCTION for Orders (Phase 2) ---
function generateOrderNo($prefix = 'ORD') {
    global $koneksi;
    $tanggal = date('Ymd');
    // Cari nomor urut terakhir untuk hari ini dari tabel 'orders'
    $query = "SELECT MAX(order_no) as max_no FROM orders WHERE order_no LIKE ?";
    $search_pattern = $prefix . $tanggal . '%';
    $stmt = $koneksi->prepare($query);
    $stmt->bind_param("s", $search_pattern);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $max_no = $result['max_no'];
    
    $urutan = 0;
    if ($max_no) {
        // Ambil bagian nomor urut dari kode terakhir
        $urutan = (int) substr($max_no, -3); // Ambil 3 digit terakhir
    }
    $urutan++;
    return $prefix . $tanggal . sprintf('%03d', $urutan); // Format 3 digit angka
}
// --- END NEW FUNCTION ---


function buatBadgeStatus($status) {
    $badge_class = '';
    switch ($status) {
        case 'Lunas':
        case 'Selesai': // Final status for order
            $badge_class = 'bg-success';
            break;
        case 'Di Pesan':
        case 'Diterima Supplier':
        case 'Menunggu Kontrak':
        case 'Kontrak Diunggah':
        case 'Menunggu Pembayaran':
            $badge_class = 'bg-info text-dark';
            break;
        case 'Di Antar':
            $badge_class = 'bg-primary'; // Barang sedang dalam pengiriman
            break;
        case 'Ditolak Supplier':
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

function log_mailer_error($context, $error_message) {
    $timestamp = date("Y-m-d H:i:s");
    $log_file = __DIR__ . '/../mailer_errors.log';
    file_put_contents($log_file, "[$timestamp] PHPMailer Error ($context): " . $error_message . "\n", FILE_APPEND);
}

// Fungsi ini digunakan untuk proses manual penerimaan barang oleh admin.
function prosesPenerimaanBarangDariPembelian($pembelian_id) {
    global $koneksi;
    $koneksi->begin_transaction();
    try {
        $stmt_items = $koneksi->prepare("
            SELECT 
                pd.barang_id, 
                pd.jumlah, 
                pd.harga, 
                b.kode_barang, 
                b.nama_barang, 
                b.kategori_id, 
                b.satuan_id, 
                b.harga_jual,
                b.foto_produk
            FROM pembelian_detail pd
            JOIN barang b ON pd.barang_id = b.id
            WHERE pd.pembelian_id = ?
        ");
        $stmt_items->bind_param("i", $pembelian_id);
        $stmt_items->execute();
        $items = $stmt_items->get_result();

        if ($items->num_rows === 0) {
            throw new Exception("Tidak ada detail barang pada pembelian ini.");
        }

        while ($item = $items->fetch_assoc()) {
            $jumlah_dibeli = $item['jumlah'];
            $harga_beli_baru = $item['harga'];
            $kode_barang_dari_supplier = $item['kode_barang'];
            $nama_barang_dari_supplier = $item['nama_barang'];
            $kategori_id_dari_supplier = $item['kategori_id'];
            $satuan_id_dari_supplier = $item['satuan_id'];
            $harga_jual_dari_supplier = $item['harga_jual'];
            $foto_produk_dari_supplier = $item['foto_produk'];

            $stmt_check_any_item = $koneksi->prepare("SELECT id, supplier_id FROM barang WHERE kode_barang = ?");
            $stmt_check_any_item->bind_param("s", $kode_barang_dari_supplier);
            $stmt_check_any_item->execute();
            $existing_item = $stmt_check_any_item->get_result()->fetch_assoc();

            if ($existing_item) {
                $existing_item_id = $existing_item['id'];
                $update_query = "UPDATE barang SET stok = stok + ?, harga_beli = ?, harga_jual = ?, supplier_id = NULL, foto_produk = ? WHERE id = ?";
                $stmt_update_existing_item = $koneksi->prepare($update_query);
                $stmt_update_existing_item->bind_param("iddsi", 
                    $jumlah_dibeli, 
                    $harga_beli_baru, 
                    $harga_jual_dari_supplier, 
                    $foto_produk_dari_supplier,
                    $existing_item_id
                );
                if (!$stmt_update_existing_item->execute()) {
                    throw new Exception("Gagal mengupdate stok barang yang sudah ada (ID: {$existing_item_id}): " . $stmt_update_existing_item->error);
                }
            } else {
                $stmt_insert_new_item = $koneksi->prepare(
                    "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id, foto_produk) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)"
                );
                $stmt_insert_new_item->bind_param("ssiiddis", 
                    $kode_barang_dari_supplier,
                    $nama_barang_dari_supplier,
                    $kategori_id_dari_supplier,
                    $satuan_id_dari_supplier,
                    $harga_beli_baru,
                    $harga_jual_dari_supplier,
                    $jumlah_dibeli,
                    $foto_produk_dari_supplier
                );
                if (!$stmt_insert_new_item->execute()) {
                    throw new Exception("Gagal memasukkan barang baru ke stok admin: " . $stmt_insert_new_item->error);
                }
            }
        }

        $stmt_update_pembelian = $koneksi->prepare("UPDATE pembelian SET status = 'Lunas' WHERE id = ?");
        $stmt_update_pembelian->bind_param("i", $pembelian_id);
        if (!$stmt_update_pembelian->execute()) throw new Exception("Gagal update status pembelian.");
        
        $koneksi->commit();
        return ['success' => true, 'message' => 'Barang telah diterima dan stok utama berhasil diperbarui.'];
    } catch (Exception $e) {
        $koneksi->rollback();
        error_log("Error prosesPenerimaanBarangDariPembelian: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function send_new_order_email_to_supplier($supplierEmail, $supplierName, $orderNo, $buyerName, $totalPrice, $orderLink) {
    if (!defined('SMTP_HOST')) { log_mailer_error('send_new_order', 'Konfigurasi SMTP tidak ditemukan.'); return false; }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($supplierEmail, $supplierName);
        $mail->isHTML(true);
        $mail->Subject = 'Pesanan Baru dari Platinum Komputer - No: ' . $orderNo;
        $mail->Body = "<h3>Yth. " . htmlspecialchars($supplierName) . ",</h3><p>Anda menerima pesanan baru dari <strong>" . htmlspecialchars($buyerName) . "</strong>.</p><p><strong>No. Pesanan:</strong> " . htmlspecialchars($orderNo) . "</p><p><strong>Total:</strong> " . formatRupiah($totalPrice) . "</p><p>Silakan login untuk meninjau pesanan: <a href='" . htmlspecialchars($orderLink) . "'>Lihat Pesanan</a></p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_new_order_email_to_supplier", $e->getMessage());
        return false;
    }
}

function send_retur_request_email_to_supplier($recipientEmail, $supplierName, $returData) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipientEmail, $supplierName);
        $mail->isHTML(true);
        $mail->Subject = 'Permintaan Retur Baru - No: ' . $returData['no_retur'];
        $mail->Body    = "<h3>Yth. " . htmlspecialchars($supplierName) . ",</h3><p>Anda menerima permintaan retur baru dengan detail:</p><p><strong>No. Retur:</strong> " . htmlspecialchars($returData['no_retur']) . "<br><strong>Barang:</strong> " . htmlspecialchars($returData['nama_barang']) . "<br><strong>Jumlah:</strong> " . htmlspecialchars($returData['jumlah']) . " unit<br><strong>Alasan:</strong> " . nl2br(htmlspecialchars($returData['alasan'])) . "</p><p>Silakan login ke dasbor Anda untuk meninjau permintaan ini.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_retur_request_email", $e->getMessage());
        return false;
    }
}

function send_status_update_email_to_admin($adminEmail, $returData) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    $status_text = htmlspecialchars($returData['status_baru']);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($adminEmail, 'Admin Platinum Komputer');
        $mail->isHTML(true);
        $mail->Subject = 'Update Status Retur No. ' . $returData['no_retur'];
        $mail->Body    = "<h3>Halo Admin,</h3><p>Supplier <strong>" . htmlspecialchars($returData['nama_supplier']) . "</strong> telah memperbarui status retur No. " . htmlspecialchars($returData['no_retur']) . " menjadi <strong>" . $status_text . "</strong>.</p><p>Silakan periksa dasbor retur Anda untuk detailnya.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_status_update_email_to_admin", $e->getMessage());
        return false;
    }
}

function send_supplier_approval_email($recipientEmail, $supplierName, $loginLink) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipientEmail, $supplierName);
        $mail->isHTML(true);
        $mail->Subject = 'Pendaftaran Supplier Anda Telah Disetujui!';
        $mail->Body    = "<h3>Yth. " . htmlspecialchars($supplierName) . ",</h3><p>Pendaftaran Anda sebagai supplier di <strong>Platinum Komputer</strong> telah <b>DISETUJUI</b>.</p><p>Anda sekarang dapat login ke sistem kami melalui link berikut: <a href='" . htmlspecialchars($loginLink) . "'>Login Sekarang</a></p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_supplier_approval_email", $e->getMessage());
        return false;
    }
}

function send_supplier_rejection_email($recipientEmail, $companyName) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipientEmail, $companyName);
        $mail->isHTML(true);
        $mail->Subject = 'Pembaruan Status Pendaftaran Supplier';
        $mail->Body    = "<h3>Yth. Tim " . htmlspecialchars($companyName) . ",</h3><p>Dengan menyesal kami memberitahukan bahwa pendaftaran Anda sebagai supplier saat ini <b>BELUM DAPAT KAMI SETUJUI</b>.</p><p>Terima kasih atas minat Anda.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_supplier_rejection_email", $e->getMessage());
        return false;
    }
}

function send_admin_comment_email($recipientEmail, $supplierName, $adminName, $comment) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($recipientEmail, $supplierName);
        $mail->isHTML(true);
        $mail->Subject = 'Admin Menambahkan Catatan pada Pendaftaran Anda';
        $mail->Body    = "<h3>Yth. " . htmlspecialchars($supplierName) . ",</h3><p>Admin <strong>" . htmlspecialchars($adminName) . "</strong> telah menambahkan catatan:<blockquote>" . nl2br(htmlspecialchars($comment)) . "</blockquote></p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_admin_comment_email", $e->getMessage());
        return false;
    }
}

/**
 * Mengirim email ke Admin bahwa pesanan telah diterima oleh Supplier.
 * @param mysqli $koneksi Koneksi database.
 * @param int $order_id ID pesanan yang statusnya diubah.
 * @param string $supplierName Nama supplier yang menerima.
 * @param float $amountToPay Jumlah yang harus dibayar.
 * @param string $paymentTerms Deskripsi termin pembayaran.
 * @param string $paymentDueDate Tanggal jatuh tempo.
 * @param string $orderPageLink Link ke halaman detail pesanan.
 * @return bool
 */
function send_order_accepted_with_contract_email_to_admin($koneksi, $adminEmail, $orderNo, $supplierCompanyName, $amountToPay, $paymentTerms, $paymentDueDate, $orderPageLink) {
    if (!defined('SMTP_HOST')) {
        log_mailer_error('send_order_accepted_email', 'Konfigurasi SMTP tidak ditemukan.');
        return false;
    }

    // --- PERBAIKAN: Mengambil data pesanan dan email admin dengan benar ---
    $stmt = $koneksi->prepare("
        SELECT 
            o.order_no, 
            u.nama AS admin_name, 
            u.email AS admin_email
        FROM orders o
        JOIN users u ON o.admin_user_id = u.id
        WHERE o.order_id = ?
    ");
    if ($stmt === false) {
        log_mailer_error('send_order_accepted_email', 'Gagal mempersiapkan query: ' . $koneksi->error);
        return false;
    }
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order_data) {
        log_mailer_error('send_order_accepted_email', 'Data pesanan atau admin tidak ditemukan untuk order_id: ' . $order_id);
        return false;
    }
    // --- AKHIR PERBAIKAN ---

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
        $mail->addAddress($order_data['admin_email'], $order_data['admin_name']); // Menggunakan email admin dari database
        $mail->isHTML(true);
        $mail->Subject = 'Pesanan Diterima oleh Supplier - No: ' . htmlspecialchars($order_data['order_no']);
        
        $payment_details_html = "<p>Jumlah yang harus dibayar: <strong>" . formatRupiah($amountToPay) . "</strong>.</p>";
        if($paymentDueDate) {
             $payment_details_html .= "<p>Jatuh Tempo: <strong>" . formatTanggal($paymentDueDate) . "</strong></p>";
        }
        $payment_details_html .= "<p>Ketentuan: " . nl2br(htmlspecialchars($paymentTerms)) . "</p>";

        $mail->Body = "
            <h3>Halo " . htmlspecialchars($order_data['admin_name']) . ",</h3>
            <p>Kabar baik! Pesanan Anda dengan nomor <strong>" . htmlspecialchars($order_data['order_no']) . "</strong> telah <b>DITERIMA</b> oleh supplier <strong>" . htmlspecialchars($supplierName) . "</strong>.</p>
            <hr>
            <h4>Detail Pembayaran & Serah Terima:</h4>
            " . $payment_details_html . "
            <hr>
            <p>Langkah selanjutnya adalah mengunggah kontrak atau melakukan pembayaran sesuai ketentuan.</p>
            <p style='text-align: center; margin-top: 20px;'>
                <a href='" . htmlspecialchars($orderPageLink) . "' style='background-color: #0d6efd; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px;'>Lihat Detail Pesanan</a>
            </p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_order_accepted_email", $e->getMessage());
        return false;
    }
}

function send_contract_uploaded_email_to_supplier($supplierEmail, $supplierCompanyName, $orderNo, $orderPageLink) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($supplierEmail, $supplierCompanyName);
        $mail->isHTML(true);
        $mail->Subject = 'Kontrak Pesanan Telah Diunggah - No: ' . $orderNo;
        $mail->Body = "<h3>Yth. Tim " . htmlspecialchars($supplierCompanyName) . ",</h3><p>Admin telah mengunggah kontrak untuk pesanan No. <strong>" . htmlspecialchars($orderNo) . "</strong>. Mohon segera proses pengiriman barang. <a href='" . htmlspecialchars($orderPageLink) . "'>Lihat Pesanan</a></p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_contract_uploaded_email", $e->getMessage());
        return false;
    }
}

function send_order_shipment_email_to_admin($adminEmail, $orderNo, $adminAddress, $receivingWarehouse, $paymentType, $paymentDueDate, $totalOrderPrice, $paymentTermsDescription) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($adminEmail, 'Admin Platinum Komputer');
        $mail->isHTML(true);
        $mail->Subject = 'Barang Pesanan Telah Dikirim! - No: ' . $orderNo;
        $payment_method_info = (strtolower($paymentType) === 'tunai') ? "Pembayaran akan dilakukan secara <strong>Tunai</strong> saat barang diterima." : "Pembayaran via transfer sebesar <strong>" . formatRupiah($totalOrderPrice) . "</strong>. Jatuh tempo: <strong>" . formatTanggal($paymentDueDate) . "</strong>.";
        $mail->Body = "<h3>Halo Admin,</h3><p>Barang untuk pesanan No. <strong>" . htmlspecialchars($orderNo) . "</strong> telah dikirim ke: <strong>" . htmlspecialchars($adminAddress) . " (" . htmlspecialchars($receivingWarehouse) . ")</strong>.</p><p>" . $payment_method_info . "</p><p>Mohon konfirmasi penerimaan setelah barang sampai.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_order_shipment_email", $e->getMessage());
        return false;
    }
}

function send_order_received_email_to_supplier($supplierEmail, $supplierCompanyName, $orderNo, $adminName) {
    if (!defined('SMTP_HOST')) return false;
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP(); $mail->Host = SMTP_HOST; $mail->SMTPAuth = true; $mail->Username = SMTP_USERNAME; $mail->Password = SMTP_PASSWORD; $mail->SMTPSecure = SMTP_SECURE; $mail->Port = SMTP_PORT;
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($supplierEmail, $supplierCompanyName);
        $mail->isHTML(true);
        $mail->Subject = 'Barang Pesanan Telah Diterima Admin - No: ' . $orderNo;
        $mail->Body = "<h3>Yth. Tim " . htmlspecialchars($supplierCompanyName) . ",</h3><p>Barang untuk pesanan No. <strong>" . htmlspecialchars($orderNo) . "</strong> telah diterima oleh Admin <strong>" . htmlspecialchars($adminName) . "</strong>. Status pesanan kini <b>Selesai</b>.</p><p>Terima kasih.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_order_received_email_to_supplier", $e->getMessage());
        return false;
    }
}

/**
 * Mengirim email notifikasi update status pesanan ke Admin.
 * @param mysqli $koneksi Koneksi database.
 * @param int $order_id ID pesanan yang statusnya diubah.
 * @param string $new_status Status baru (misal: 'Ditolak Supplier').
 * @param string $rejection_reason Alasan penolakan (opsional).
 * @return bool True jika email berhasil dikirim, false jika gagal.
 */
function send_order_status_update_email_to_admin($koneksi, $order_id, $new_status, $rejection_reason = '') {
    if (!defined('SMTP_HOST')) {
        log_mailer_error('send_order_status_update_to_admin', 'Konfigurasi SMTP tidak ditemukan.');
        return false;
    }

    // Ambil detail pesanan dan email admin
    $stmt = $koneksi->prepare("
        SELECT 
            o.order_no, 
            u.nama_lengkap AS admin_name, 
            u.email AS admin_email,
            s.nama_supplier
        FROM orders
        JOIN users ON orders.admin_user_id = users.id
        JOIN supplier ON orders.supplier_id = supplier.id
        WHERE orders.order_id = ?
    ");
    
    if ($stmt === false) {
        log_mailer_error('send_order_status_update_to_admin', 'Gagal mempersiapkan query: ' . $koneksi->error);
        return false;
    }

    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order_data) {
        log_mailer_error('send_order_status_update_to_admin', 'Data pesanan atau admin tidak ditemukan untuk order_id: ' . $order_id);
        return false;
    }

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
        $mail->addAddress($order_data['admin_email'], $order_data['admin_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Update Status Pesanan No: ' . htmlspecialchars($order_data['order_no']);

        $reason_html = '';
        if (!empty($rejection_reason)) {
            $reason_html = "<p><strong>Alasan Penolakan:</strong> " . nl2br(htmlspecialchars($rejection_reason)) . "</p>";
        }

        $mail->Body = "
            <h3>Halo " . htmlspecialchars($order_data['admin_name']) . ",</h3>
            <p>Supplier <strong>" . htmlspecialchars($order_data['nama_supplier']) . "</strong> telah memperbarui status pesanan Anda.</p>
            <p><strong>Nomor Pesanan:</strong> " . htmlspecialchars($order_data['order_no']) . "</p>
            <p><strong>Status Baru:</strong> <strong style='color: #dc3545;'>" . htmlspecialchars($new_status) . "</strong></p>
            " . $reason_html . "
            <p>Silakan periksa dasbor pesanan Anda untuk detail lebih lanjut.</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_mailer_error("send_order_status_update_to_admin", $e->getMessage());
        return false;
    }
}

?>