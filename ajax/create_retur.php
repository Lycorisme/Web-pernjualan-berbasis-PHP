<?php
// FILE: ajax/create_retur.php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

cekAdmin(); // Proteksi halaman

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode tidak diizinkan.']);
    exit;
}

$pembelian_id = $_POST['pembelian_id'] ?? 0;
$barang_id = $_POST['barang_id'] ?? 0;
$jumlah = $_POST['jumlah'] ?? 0;
$alasan = trim($_POST['alasan'] ?? '');
$supplier_id = $_POST['supplier_id'] ?? 0;
$admin_id = $_SESSION['user_id'];

if (empty($pembelian_id) || empty($barang_id) || empty($jumlah) || empty($alasan) || empty($supplier_id)) {
    echo json_encode(['success' => false, 'message' => 'Semua kolom wajib diisi.']);
    exit;
}

$koneksi->begin_transaction();

try {
    // Validasi jumlah retur tidak melebihi jumlah pembelian
    $stmt_cek = $koneksi->prepare("SELECT jumlah FROM pembelian_detail WHERE pembelian_id = ? AND barang_id = ?");
    $stmt_cek->bind_param("ii", $pembelian_id, $barang_id);
    $stmt_cek->execute();
    $dibeli = $stmt_cek->get_result()->fetch_assoc()['jumlah'];
    if ($jumlah > $dibeli) {
        throw new Exception("Jumlah retur ($jumlah) tidak boleh melebihi jumlah pembelian ($dibeli).");
    }

    // Generate no_retur dan tanggal
    $no_retur = generateNoRetur();
    $tanggal_retur = date('Y-m-d');
    
    // Insert ke tabel retur
    $stmt_insert = $koneksi->prepare(
        "INSERT INTO retur (no_retur, tanggal_retur, pembelian_id, barang_id, supplier_id, admin_id, jumlah, alasan, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Persetujuan')"
    );
    $stmt_insert->bind_param("ssiiisis", $no_retur, $tanggal_retur, $pembelian_id, $barang_id, $supplier_id, $admin_id, $jumlah, $alasan);
    $stmt_insert->execute();
    $retur_id = $koneksi->insert_id;

    // Handle upload foto
    if (isset($_FILES['bukti_foto'])) {
        $upload_dir = __DIR__ . '/../uploads/retur/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        foreach ($_FILES['bukti_foto']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['bukti_foto']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = "retur_{$retur_id}_" . time() . "_" . basename($_FILES['bukti_foto']['name'][$key]);
                if (move_uploaded_file($tmp_name, $upload_dir . $file_name)) {
                    $stmt_foto = $koneksi->prepare("INSERT INTO retur_photos (retur_id, nama_file) VALUES (?, ?)");
                    $stmt_foto->bind_param("is", $retur_id, $file_name);
                    $stmt_foto->execute();
                }
            }
        }
    }
    
    // Kirim email notifikasi ke supplier
    $supplier_info = $koneksi->query("SELECT nama_supplier, email FROM supplier WHERE id = $supplier_id")->fetch_assoc();
    $barang_info = $koneksi->query("SELECT nama_barang FROM barang WHERE id = $barang_id")->fetch_assoc();

    if ($supplier_info && $barang_info) {
        $email_data = [
            'no_retur' => $no_retur,
            'nama_barang' => $barang_info['nama_barang'],
            'jumlah' => $jumlah,
            'alasan' => $alasan
        ];
        send_retur_request_email_to_supplier($supplier_info['email'], $supplier_info['nama_supplier'], $email_data);
    }

    $koneksi->commit();
    echo json_encode(['success' => true, 'message' => 'Permintaan retur berhasil dikirim dan email notifikasi telah dikirim ke supplier.']);

} catch (Exception $e) {
    $koneksi->rollback();
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>