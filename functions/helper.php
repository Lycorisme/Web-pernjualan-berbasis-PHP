<?php
// FILE: functions/helper.php (Final & Lengkap - Tanpa Duplikasi Fungsi)

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

// Fungsi ini digunakan untuk proses manual penerimaan barang oleh admin.
// Logikanya diperbarui agar sesuai dengan Pendekatan A (kode_barang unik global).
function prosesPenerimaanBarangDariPembelian($pembelian_id) {
    global $koneksi;
    $koneksi->begin_transaction();
    try {
        // Ambil semua detail barang yang diperlukan dari tabel barang supplier
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
            JOIN barang b ON pd.barang_id = b.id -- Join ke tabel barang supplier
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
            $kode_barang_dari_supplier = $item['kode_barang']; // Kode barang dari supplier (akan dijadikan kode global)
            $nama_barang_dari_supplier = $item['nama_barang'];
            $kategori_id_dari_supplier = $item['kategori_id'];
            $satuan_id_dari_supplier = $item['satuan_id'];
            $harga_jual_dari_supplier = $item['harga_jual'];
            $foto_produk_dari_supplier = $item['foto_produk'];

            // A. Cari item di tabel `barang` secara GLOBAL (tanpa filter supplier_id)
            $stmt_check_any_item = $koneksi->prepare("SELECT id, supplier_id FROM barang WHERE kode_barang = ?");
            $stmt_check_any_item->bind_param("s", $kode_barang_dari_supplier);
            $stmt_check_any_item->execute();
            $existing_item = $stmt_check_any_item->get_result()->fetch_assoc();

            if ($existing_item) {
                // Item ditemukan di tabel `barang` (bisa milik admin atau supplier lain)
                $existing_item_id = $existing_item['id'];
                // Perbarui item: stok bertambah, harga beli diperbarui, dan supplier_id menjadi NULL (milik admin)
                $update_query = "UPDATE barang SET stok = stok + ?, harga_beli = ?, harga_jual = ?, supplier_id = NULL, foto_produk = ? WHERE id = ?";
                $stmt_update_existing_item = $koneksi->prepare($update_query);
                // Parameter: jumlah(i), harga_beli_baru(d), harga_jual_dari_supplier(d), foto_produk_dari_supplier(s), existing_item_id(i)
                $stmt_update_existing_item->bind_param("iddsi", 
                    $jumlah_dibeli, 
                    $harga_beli_baru, 
                    $harga_jual_dari_supplier, 
                    $foto_produk_dari_supplier, // Update foto jika ada yang baru dari supplier
                    $existing_item_id
                );
                if (!$stmt_update_existing_item->execute()) {
                    throw new Exception("Gagal mengupdate stok barang yang sudah ada (ID: {$existing_item_id}): " . $stmt_update_existing_item->error);
                }
            } else {
                // Item TIDAK ditemukan di tabel `barang` secara global, maka INSERT sebagai barang baru admin
                $stmt_insert_new_item = $koneksi->prepare(
                    "INSERT INTO barang (kode_barang, nama_barang, kategori_id, satuan_id, harga_beli, harga_jual, stok, supplier_id, foto_produk) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)"
                );
                // Parameter: s, s, i, i, d, d, i, s (untuk foto_produk)
                $stmt_insert_new_item->bind_param("ssiiddis", 
                    $kode_barang_dari_supplier, // Gunakan kode_barang dari supplier
                    $nama_barang_dari_supplier,
                    $kategori_id_dari_supplier,
                    $satuan_id_dari_supplier,
                    $harga_beli_baru,
                    $harga_jual_dari_supplier,
                    $jumlah_dibeli, // Stok awal
                    $foto_produk_dari_supplier
                );
                if (!$stmt_insert_new_item->execute()) {
                    throw new Exception("Gagal memasukkan barang baru ke stok admin: " . $stmt_insert_new_item->error);
                }
            }
        }

        // Update status pembelian menjadi 'Lunas' setelah semua barang diproses
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


function send_retur_request_email_to_supplier($recipientEmail, $supplierName, $returData) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) {
        error_log("Email Error: Autoload atau SMTP_HOST tidak ditemukan.");
        return false;
    }
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
        error_log("PHPMailer Error (send_retur_request_email_to_supplier): " . $e->getMessage());
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
        error_log("PHPMailer Error (send_status_update_email_to_admin): " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk mengirim email notifikasi persetujuan supplier
function send_supplier_approval_email($recipientEmail, $supplierName, $loginLink) {
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
        $mail->Subject = 'Pendaftaran Supplier Anda Telah Disetujui!';
        $mail->Body    = "
            <h3>Yth. " . htmlspecialchars($supplierName) . ",</h3>
            <p>Pendaftaran Anda sebagai supplier di <strong>Platinum Komputer</strong> telah <b>DISETUJUI</b>.</p>
            <p>Anda sekarang dapat login ke sistem kami untuk mulai mengelola barang dan melihat riwayat pembelian Anda.</p>
            <p>Silakan klik tombol di bawah ini untuk menuju halaman login:</p>
            <p style='text-align: center;'>
                <a href='" . htmlspecialchars($loginLink) . "' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>LOGIN SEKARANG</a>
            </p>
            <p>Terima kasih atas kerja sama Anda.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_supplier_approval_email): " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk mengirim email notifikasi penolakan supplier
function send_supplier_rejection_email($recipientEmail, $companyName) {
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
        $mail->addAddress($recipientEmail, $companyName);
        $mail->isHTML(true);
        $mail->Subject = 'Pembaruan Status Pendaftaran Supplier Anda di Platinum Komputer';
        $mail->Body    = "
            <h3>Yth. Tim " . htmlspecialchars($companyName) . ",</h3>
            <p>Dengan hormat,</p>
            <p>Terima kasih atas minat Anda untuk bergabung sebagai supplier di <strong>Platinum Komputer</strong>.</p>
            <p>Setelah meninjau pengajuan Anda, dengan menyesal kami memberitahukan bahwa pendaftaran Anda saat ini <b>BELUM DAPAT KAMI SETUJUI</b>.</p>
            <p>Kami mungkin akan menghubungi Anda kembali jika ada informasi lebih lanjut yang dibutuhkan atau jika kriteria kami berubah di masa mendatang.</p>
            <p>Kami menghargai waktu dan upaya Anda.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_supplier_rejection_email): " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk mengirim email notifikasi komentar admin ke supplier
function send_admin_comment_email($recipientEmail, $supplierName, $adminName, $comment) {
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
        $mail->Subject = 'Admin Platinum Komputer Menambahkan Catatan pada Pendaftaran Anda';
        $mail->Body    = "
            <h3>Yth. " . htmlspecialchars($supplierName) . ",</h3>
            <p>Admin <strong>" . htmlspecialchars($adminName) . "</strong> dari Platinum Komputer telah menambahkan catatan terkait pendaftaran Anda:</p>
            <div style='background-color: #f0f0f0; padding: 15px; border-left: 5px solid #007bff; margin: 15px 0;'>
                <p style='font-style: italic;'>" . nl2br(htmlspecialchars($comment)) . "</p>
            </div>
            <p>Mohon periksa dasbor supplier Anda atau hubungi admin jika Anda memiliki pertanyaan.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_admin_comment_email): " . $e->getMessage());
        return false;
    }
}

// --- NEW EMAIL FUNCTIONS FOR ORDER WORKFLOW (Phase 3 & 4) ---

// Email ke Admin: Pesanan Diterima oleh Supplier (dengan/tanpa kontrak)
function send_order_accepted_with_contract_email_to_admin($adminEmail, $orderNo, $supplierCompanyName, $amountToPay, $paymentTerms, $paymentDueDate, $orderPageLink) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) {
        error_log("Email Error: Autoload atau SMTP_HOST tidak ditemukan.");
        return false;
    }
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
        $mail->addAddress($adminEmail, 'Admin Platinum Komputer');
        $mail->isHTML(true);
        $mail->Subject = 'Pesanan Anda DITERIMA & Siap Diproses! - No: ' . $orderNo;
        
        $payment_details_html = '';
        if (strtolower($paymentTerms) === 'tunai') {
            $payment_details_html = "<p>Pembayaran akan dilakukan secara <strong>Tunai</strong>.</p>";
        } else {
            $payment_details_html = "
                <p>Jumlah yang harus dibayarkan: <strong>" . formatRupiah($amountToPay) . "</strong>.</p>
                <p>Ketentuan Pembayaran: " . nl2br(htmlspecialchars($paymentTerms)) . "</p>";
            if ($paymentDueDate) {
                $payment_details_html .= "<p>Tanggal Jatuh Tempo: <strong>" . formatTanggal($paymentDueDate) . "</strong></p>";
            }
        }

        $mail->Body    = "
            <h3>Halo Admin,</h3>
            <p>Pesanan Anda dengan nomor <strong>" . htmlspecialchars($orderNo) . "</strong> telah <b>DITERIMA</b> oleh supplier <strong>" . htmlspecialchars($supplierCompanyName) . "</strong>.</p>
            <p>Berikut adalah detail pembayaran dan serah terima:</p>
            " . $payment_details_html . "
            <p>Mohon segera unggah kontrak pembelian yang sudah ditandatangani (jika diperlukan) atau lakukan pembayaran sesuai ketentuan.</p>
            <p style='text-align: center;'>
                <a href='" . htmlspecialchars($orderPageLink) . "' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>LIHAT PESANAN DI SISTEM</a>
            </p>
            <p>Terima kasih.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_order_accepted_with_contract_email_to_admin): " . $e->getMessage());
        return false;
    }
}

// Email ke Supplier: Admin sudah upload kontrak
function send_contract_uploaded_email_to_supplier($supplierEmail, $supplierCompanyName, $orderNo, $orderPageLink) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) {
        error_log("Email Error: Autoload atau SMTP_HOST tidak ditemukan.");
        return false;
    }
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
        $mail->addAddress($supplierEmail, $supplierCompanyName);
        $mail->isHTML(true);
        $mail->Subject = 'Kontrak Pesanan Telah Diunggah oleh Admin - No: ' . $orderNo;
        $mail->Body    = "
            <h3>Yth. Tim " . htmlspecialchars($supplierCompanyName) . ",</h3>
            <p>Admin Platinum Komputer telah berhasil mengunggah kontrak untuk pesanan nomor <strong>" . htmlspecialchars($orderNo) . "</strong>.</p>
            <p>Anda dapat melihat dan mengunduh kontrak tersebut melalui halaman pesanan di dasbor Anda.</p>
            <p>Mohon segera lakukan proses pengiriman barang sesuai kesepakatan.</p>
            <p style='text-align: center;'>
                <a href='" . htmlspecialchars($orderPageLink) . "' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>LIHAT PESANAN DI SISTEM</a>
            </p>
            <p>Terima kasih atas kerja sama Anda.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_contract_uploaded_email_to_supplier): " . $e->getMessage());
        return false;
    }
}

// Email ke Admin: Barang Dikirim oleh Supplier
function send_order_shipment_email_to_admin($adminEmail, $orderNo, $adminAddress, $receivingWarehouse, $paymentType, $paymentDueDate, $totalOrderPrice, $paymentTermsDescription) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) {
        error_log("Email Error: Autoload atau SMTP_HOST tidak ditemukan.");
        return false;
    }
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
        $mail->addAddress($adminEmail, 'Admin Platinum Komputer');
        $mail->isHTML(true);
        $mail->Subject = 'Barang Pesanan Anda Telah Dikirim! - No: ' . $orderNo;

        $payment_method_info = '';
        if (strtolower($paymentType) === 'tunai') {
            $payment_method_info = "Pembayaran akan dilakukan secara <strong>Tunai</strong> kepada kurir saat barang diterima.";
        } else { // Kredit/Transfer
            $payment_method_info = "Pembayaran via transfer dengan nominal <strong>" . formatRupiah($totalOrderPrice) . "</strong>. <br>Ketentuan: " . nl2br(htmlspecialchars($paymentTermsDescription));
            if ($paymentDueDate) {
                $payment_method_info .= "<br>Wajib dibayarkan paling lambat tanggal: <strong>" . formatTanggal($paymentDueDate) . "</strong>.";
            }
        }

        $mail->Body    = "
            <h3>Halo Admin,</h3>
            <p>Barang untuk pesanan nomor <strong>" . htmlspecialchars($orderNo) . "</strong> telah dikirimkan!</p>
            <p>Barang akan dikirimkan ke alamat: <strong>" . htmlspecialchars($adminAddress) . "</strong>, ke gudang: <strong>" . htmlspecialchars($receivingWarehouse) . "</strong>.</p>
            <p>Metode pembayaran: " . $payment_method_info . "</p>
            <p>Mohon segera konfirmasi penerimaan barang setelah barang sampai di tujuan.</p>
            <p>Terima kasih.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_order_shipment_email_to_admin): " . $e->getMessage());
        return false;
    }
}

// Email ke Supplier: Barang Sudah Diterima Admin
function send_order_received_email_to_supplier($supplierEmail, $supplierCompanyName, $orderNo, $adminName) {
    if (!file_exists(__DIR__ . '/../vendor/autoload.php') || !defined('SMTP_HOST')) {
        error_log("Email Error: Autoload atau SMTP_HOST tidak ditemukan.");
        return false;
    }
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
        $mail->addAddress($supplierEmail, $supplierCompanyName);
        $mail->isHTML(true);
        $mail->Subject = 'Barang Pesanan Telah Diterima Admin! - No: ' . $orderNo;
        $mail->Body    = "
            <h3>Yth. Tim " . htmlspecialchars($supplierCompanyName) . ",</h3>
            <p>Kami ingin memberitahukan bahwa barang untuk pesanan nomor <strong>" . htmlspecialchars($orderNo) . "</strong> telah berhasil diterima oleh Admin <strong>" . htmlspecialchars($adminName) . "</strong>.</p>
            <p>Status pesanan Anda kini telah berubah menjadi <b>Selesai</b>.</p>
            <p>Terima kasih atas pengiriman yang cepat dan kerja sama Anda.</p>
            <p>Hormat kami,<br><strong>Platinum Komputer</strong></p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (send_order_received_email_to_supplier): " . $e->getMessage());
        return false;
    }
}
