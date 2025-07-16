<?php
// Include required files
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../functions/helper.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki akses']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

// Check if AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Hanya AJAX request yang diizinkan']);
    exit;
}

// Get JSON data from request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Data JSON tidak valid']);
    exit;
}

// Validate transaction data
if (!isset($data['no_transaksi']) || empty($data['no_transaksi'])) {
    echo json_encode(['success' => false, 'message' => 'Nomor transaksi tidak boleh kosong']);
    exit;
}

if (!isset($data['tanggal']) || empty($data['tanggal'])) {
    echo json_encode(['success' => false, 'message' => 'Tanggal transaksi tidak boleh kosong']);
    exit;
}

if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID tidak valid']);
    exit;
}

if (!isset($data['total']) || !is_numeric($data['total']) || $data['total'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'Total transaksi tidak valid']);
    exit;
}

if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
    echo json_encode(['success' => false, 'message' => 'Item transaksi tidak boleh kosong']);
    exit;
}

// Validate each item
foreach ($data['items'] as $index => $item) {
    if (!isset($item['id']) || !is_numeric($item['id'])) {
        echo json_encode(['success' => false, 'message' => "ID barang pada item $index tidak valid"]);
        exit;
    }
    
    if (!isset($item['qty']) || !is_numeric($item['qty']) || $item['qty'] <= 0) {
        echo json_encode(['success' => false, 'message' => "Jumlah barang pada item $index tidak valid"]);
        exit;
    }
    
    if (!isset($item['price']) || !is_numeric($item['price']) || $item['price'] <= 0) {
        echo json_encode(['success' => false, 'message' => "Harga barang pada item $index tidak valid"]);
        exit;
    }
    
    if (!isset($item['subtotal']) || !is_numeric($item['subtotal']) || $item['subtotal'] <= 0) {
        echo json_encode(['success' => false, 'message' => "Subtotal pada item $index tidak valid"]);
        exit;
    }
}

// Log the transaction data for debugging
error_log('Transaction Data: ' . json_encode($data));

// Start transaction
$koneksi->begin_transaction();

try {
    // Check if transaction number already exists
    $check_stmt = $koneksi->prepare("SELECT id FROM transaksi WHERE no_transaksi = ?");
    $check_stmt->bind_param("s", $data['no_transaksi']);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        throw new Exception("Nomor transaksi sudah ada: " . $data['no_transaksi']);
    }
    
    // Insert transaction header
    $stmt = $koneksi->prepare("INSERT INTO transaksi (no_transaksi, tanggal, user_id, total) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Error preparing transaction insert: " . $koneksi->error);
    }
    
    $stmt->bind_param("ssid", $data['no_transaksi'], $data['tanggal'], $data['user_id'], $data['total']);
    
    if (!$stmt->execute()) {
        throw new Exception("Error inserting transaction header: " . $stmt->error);
    }
    
    // Get transaction ID
    $transaksi_id = $koneksi->insert_id;
    
    if (!$transaksi_id) {
        throw new Exception("Failed to get transaction ID");
    }
    
    // Prepare statement for transaction details
    $detail_stmt = $koneksi->prepare("INSERT INTO transaksi_detail (transaksi_id, barang_id, jumlah, harga, subtotal) VALUES (?, ?, ?, ?, ?)");
    if (!$detail_stmt) {
        throw new Exception("Error preparing transaction detail insert: " . $koneksi->error);
    }
    
    // Prepare statement for stock update
    $stock_stmt = $koneksi->prepare("UPDATE barang SET stok = stok - ? WHERE id = ?");
    if (!$stock_stmt) {
        throw new Exception("Error preparing stock update: " . $koneksi->error);
    }
    
    // Insert transaction details and update stock
    foreach ($data['items'] as $item) {
        $barang_id = (int)$item['id'];
        $jumlah = (int)$item['qty'];
        $harga = (float)$item['price'];
        $subtotal = (float)$item['subtotal'];
        
        // Check if product exists and has enough stock
        $check_stock = $koneksi->prepare("SELECT nama_barang, stok FROM barang WHERE id = ?");
        $check_stock->bind_param("i", $barang_id);
        $check_stock->execute();
        $stock_result = $check_stock->get_result();
        
        if ($stock_result->num_rows === 0) {
            throw new Exception("Barang dengan ID $barang_id tidak ditemukan");
        }
        
        $product = $stock_result->fetch_assoc();
        $current_stock = (int)$product['stok'];
        $product_name = $product['nama_barang'];
        
        if ($current_stock < $jumlah) {
            throw new Exception("Stok tidak mencukupi untuk $product_name. Tersedia: $current_stock, Diminta: $jumlah");
        }
        
        // Insert transaction detail
        $detail_stmt->bind_param("iiidi", $transaksi_id, $barang_id, $jumlah, $harga, $subtotal);
        if (!$detail_stmt->execute()) {
            throw new Exception("Error inserting transaction detail for product ID $barang_id: " . $detail_stmt->error);
        }
        
        // Update stock
        $stock_stmt->bind_param("ii", $jumlah, $barang_id);
        if (!$stock_stmt->execute()) {
            throw new Exception("Error updating stock for product ID $barang_id: " . $stock_stmt->error);
        }
        
        // Verify stock was updated
        if ($stock_stmt->affected_rows === 0) {
            throw new Exception("Failed to update stock for product ID $barang_id");
        }
    }
    
    // Commit transaction
    $koneksi->commit();
    
    // Log successful transaction
    error_log("Transaction successful: ID $transaksi_id, No: " . $data['no_transaksi']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Transaksi berhasil disimpan', 
        'transaction_id' => $transaksi_id,
        'no_transaksi' => $data['no_transaksi']
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $koneksi->rollback();
    
    // Log the error
    error_log('Transaction Error: ' . $e->getMessage());
    error_log('Transaction Data: ' . json_encode($data));
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>