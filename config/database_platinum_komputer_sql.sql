-- Database: platinum_komputer
-- Jalankan script ini di phpMyAdmin atau MySQL CLI

-- Buat database
CREATE DATABASE IF NOT EXISTS `platinum_komputer` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Gunakan database
USE `platinum_komputer`;

-- Struktur tabel untuk kategori
CREATE TABLE IF NOT EXISTS `kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(50) NOT NULL,
  `deskripsi` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data awal untuk kategori
INSERT INTO `kategori` (`id`, `nama_kategori`, `deskripsi`) VALUES
(1, 'Laptop', 'Kategori untuk laptop dan notebook'),
(2, 'Komputer Desktop', 'Kategori untuk PC desktop'),
(3, 'Aksesoris', 'Kategori untuk aksesoris komputer'),
(4, 'Software', 'Kategori untuk software'),
(5, 'Printer', 'Kategori untuk printer dan scanner');

-- Struktur tabel untuk satuan
CREATE TABLE IF NOT EXISTS `satuan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_satuan` varchar(20) NOT NULL,
  `deskripsi` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data awal untuk satuan
INSERT INTO `satuan` (`id`, `nama_satuan`, `deskripsi`) VALUES
(1, 'Unit', 'Satuan unit/buah'),
(2, 'Set', 'Satuan set'),
(3, 'Paket', 'Satuan paket'),
(4, 'License', 'Satuan license software'),
(5, 'Meter', 'Satuan meter untuk kabel');

-- Struktur tabel untuk supplier
CREATE TABLE IF NOT EXISTS `supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_supplier` varchar(100) NOT NULL,
  `alamat` text NOT NULL,
  `telepon` varchar(20),
  `email` varchar(100),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data awal untuk supplier
INSERT INTO `supplier` (`id`, `nama_supplier`, `alamat`, `telepon`, `email`) VALUES
(1, 'PT. Komputer Teknologi', 'Jl. Teknologi No. 123, Jakarta', '021-1234567', 'info@komptek.com'),
(2, 'CV. Digital Solution', 'Jl. Digital Raya No. 456, Surabaya', '031-7654321', 'sales@digsol.com'),
(3, 'Toko Komputer ABC', 'Jl. ABC No. 789, Bandung', '022-9876543', 'abc@komputer.com');

-- Struktur tabel untuk barang
CREATE TABLE IF NOT EXISTS `barang` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_barang` varchar(50) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `kategori_id` int(11) NOT NULL,
  `satuan_id` int(11) NOT NULL,
  `harga_beli` decimal(12,2) NOT NULL DEFAULT 0.00,
  `harga_jual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stok` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_barang` (`kode_barang`),
  KEY `fk_barang_kategori` (`kategori_id`),
  KEY `fk_barang_satuan` (`satuan_id`),
  CONSTRAINT `fk_barang_kategori` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`),
  CONSTRAINT `fk_barang_satuan` FOREIGN KEY (`satuan_id`) REFERENCES `satuan` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data awal untuk barang
INSERT INTO `barang` (`id`, `kode_barang`, `nama_barang`, `kategori_id`, `satuan_id`, `harga_beli`, `harga_jual`, `stok`) VALUES
(1, 'LPT001', 'Laptop ASUS X441BA', 1, 1, 3500000.00, 4200000.00, 10),
(2, 'LPT002', 'Laptop Acer Aspire 3', 1, 1, 4000000.00, 4800000.00, 8),
(3, 'PC001', 'PC Built Up Core i5', 2, 1, 4500000.00, 5400000.00, 5),
(4, 'ACC001', 'Mouse Wireless Logitech', 3, 1, 75000.00, 120000.00, 25),
(5, 'ACC002', 'Keyboard Gaming RGB', 3, 1, 250000.00, 350000.00, 15),
(6, 'PRT001', 'Printer Canon Pixma', 5, 1, 800000.00, 1200000.00, 12);

-- Struktur tabel untuk users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','kasir') NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Data awal untuk users (password: password123)
INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `active`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 1),
(2, 'kasir', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kasir', 'kasir', 1);

-- Struktur tabel untuk transaksi
CREATE TABLE IF NOT EXISTS `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_transaksi` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_transaksi` (`no_transaksi`),
  KEY `fk_transaksi_user` (`user_id`),
  CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur tabel untuk transaksi_detail
CREATE TABLE IF NOT EXISTS `transaksi_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaksi_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_detail_transaksi` (`transaksi_id`),
  KEY `fk_detail_barang` (`barang_id`),
  CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_detail_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur tabel untuk pembelian
CREATE TABLE IF NOT EXISTS `pembelian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_pembelian` varchar(50) NOT NULL,
  `tanggal` date NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_pembelian` (`no_pembelian`),
  KEY `fk_pembelian_supplier` (`supplier_id`),
  KEY `fk_pembelian_user` (`user_id`),
  CONSTRAINT `fk_pembelian_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `supplier` (`id`),
  CONSTRAINT `fk_pembelian_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur tabel untuk pembelian_detail
CREATE TABLE IF NOT EXISTS `pembelian_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pembelian_id` int(11) NOT NULL,
  `barang_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga` decimal(12,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pembelian_detail_pembelian` (`pembelian_id`),
  KEY `fk_pembelian_detail_barang` (`barang_id`),
  CONSTRAINT `fk_pembelian_detail_pembelian` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pembelian_detail_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Set AUTO_INCREMENT values
ALTER TABLE `kategori` AUTO_INCREMENT = 6;
ALTER TABLE `satuan` AUTO_INCREMENT = 6;
ALTER TABLE `supplier` AUTO_INCREMENT = 4;
ALTER TABLE `barang` AUTO_INCREMENT = 7;
ALTER TABLE `users` AUTO_INCREMENT = 3;
ALTER TABLE `transaksi` AUTO_INCREMENT = 1;
ALTER TABLE `transaksi_detail` AUTO_INCREMENT = 1;
ALTER TABLE `pembelian` AUTO_INCREMENT = 1;
ALTER TABLE `pembelian_detail` AUTO_INCREMENT = 1;