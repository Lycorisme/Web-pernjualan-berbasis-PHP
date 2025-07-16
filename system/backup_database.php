<?php
// Menonaktifkan batas waktu eksekusi skrip
set_time_limit(0);

// --- KONFIGURASI DATABASE & BACKUP ---
$dbHost = 'localhost';
$dbUsername = 'root';
$dbPassword = '';
$dbName = 'database_platinum_komputer_sql';
$backupDir = __DIR__ . '/../backup_db_platinum/';

// --- LOGIKA SKRIP ---

// 1. Pastikan direktori backup ada, jika tidak, buat baru
if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true)) {
        die("Gagal membuat direktori backup: " . $backupDir);
    }
}

// 2. Buat nama file backup dengan format: nama_database_Tahun-Bulan-Tanggal.sql.gz
$backupFile = $dbName . '_' . date("Y-m-d") . '.sql.gz';
$backupPath = $backupDir . $backupFile;

// 3. Buat perintah mysqldump dan kompresi dengan gzip
// Perintah ini akan mengambil backup dan langsung mengompresnya untuk menghemat ruang
$command = "mysqldump --user={$dbUsername} --password={$dbPassword} --host={$dbHost} {$dbName} | gzip > {$backupPath}";

// 4. Jalankan perintah menggunakan shell_exec
$output = shell_exec($command);

// 5. Beri feedback (berguna untuk log cron job)
if (file_exists($backupPath) && filesize($backupPath) > 0) {
    echo "Backup database '{$dbName}' berhasil dibuat di: {$backupPath}\n";
} else {
    echo "Gagal membuat backup database '{$dbName}'.\n";
    // Anda bisa menambahkan notifikasi error ke email di sini jika diperlukan
}


// 6. (OPSIONAL TAPI SANGAT DIREKOMENDASIKAN) Hapus backup lama
// Hapus backup yang lebih tua dari 30 hari untuk menghemat ruang disk
$files = glob($backupDir . '*.sql.gz');
$retentionDays = 30;
$now = time();

foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= ($retentionDays * 24 * 60 * 60)) {
            unlink($file);
            echo "Menghapus backup lama: " . basename($file) . "\n";
        }
    }
}

?>