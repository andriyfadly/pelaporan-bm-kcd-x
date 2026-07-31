
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `data_barang_acuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `data_barang_acuan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sekolah` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `satuan_pendidikan` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `npsn` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `kodering` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bku` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uraian` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `nominal` decimal(15,2) DEFAULT '0.00',
  `bulan` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sekolah` (`id_sekolah`),
  KEY `idx_npsn` (`npsn`)
) ENGINE=InnoDB AUTO_INCREMENT=17536 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kode_sekolah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kode_sekolah` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_urut` int NOT NULL,
  `nama_sekolah` varchar(150) NOT NULL,
  `kota_kab` varchar(100) NOT NULL,
  `kode_sub_pengguna` varchar(20) NOT NULL,
  `kode_wilayah` varchar(10) NOT NULL,
  `id_sekolah` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `laporan_realisasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_realisasi` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sekolah` varchar(50) NOT NULL,
  `bulan` int NOT NULL,
  `status` enum('Menunggu Approval','Disetujui','Ditolak') DEFAULT 'Menunggu Approval',
  `tanggal_kirim` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sekolah_bulan` (`id_sekolah`,`bulan`)
) ENGINE=InnoDB AUTO_INCREMENT=129 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `master_barang_sekolah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `master_barang_sekolah` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sekolah` varchar(50) NOT NULL COMMENT 'ID atau NPSN Sekolah',
  `kategori` varchar(50) DEFAULT NULL,
  `id_uraian` int DEFAULT NULL COMMENT 'Relasi ke acuan pagu utama (jika ada)',
  `no_sp2d` varchar(100) NOT NULL COMMENT 'Nomor SP2D',
  `sumber_perolehan` varchar(100) NOT NULL COMMENT 'Contoh: BOS Reguler, BOSda, BOP',
  `bulan_realisasi` int NOT NULL COMMENT 'Bulan realisasi SPJ (1-12)',
  `no_spk` varchar(150) NOT NULL COMMENT 'Nomor SPK / Nota / Kwitansi',
  `ba_no` varchar(150) DEFAULT NULL COMMENT 'Nomor Berita Acara Penerimaan',
  `ba_tgl` date DEFAULT NULL COMMENT 'Tanggal Berita Acara',
  `kode_barang` varchar(50) NOT NULL COMMENT 'Kode Aset / Kode Barang Sipelah',
  `nama_barang` varchar(255) NOT NULL COMMENT 'Nama atau Uraian Barang',
  `jenis_aset` varchar(100) NOT NULL COMMENT 'Contoh: PERSONAL KOMPUTER, Persediaan',
  `merk_tipe` varchar(255) DEFAULT NULL COMMENT 'Spesifikasi teknis / Merk',
  `no_sertifikat` varchar(150) DEFAULT NULL COMMENT 'No. Sertifikat / No. Pabrik',
  `ukuran_bangunan` varchar(150) DEFAULT NULL COMMENT 'Ukuran / Dimensi Barang',
  `satuan` varchar(50) NOT NULL COMMENT 'Contoh: Unit, Pcs, Rim',
  `volume` decimal(10,2) NOT NULL COMMENT 'QTY / Jumlah Barang',
  `harga_satuan` decimal(15,2) NOT NULL COMMENT 'Harga per satuan barang',
  `is_realisasi` int NOT NULL DEFAULT '0',
  `nilai_perolehan` decimal(15,2) NOT NULL COMMENT 'Total Perolehan (Volume x Harga Satuan)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sekolah_bulan` (`id_sekolah`,`bulan_realisasi`),
  KEY `idx_no_sp2d` (`no_sp2d`)
) ENGINE=InnoDB AUTO_INCREMENT=1264 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `realisasi_barang_sekolah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `realisasi_barang_sekolah` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `id_sekolah` varchar(50) NOT NULL,
  `id_master_barang` int DEFAULT NULL,
  `id_uraian` int DEFAULT NULL,
  `no_sp2d` varchar(100) DEFAULT NULL,
  `sumber_perolehan` varchar(100) DEFAULT NULL,
  `kodering_belanja` varchar(255) DEFAULT NULL,
  `bulan_realisasi` varchar(50) DEFAULT NULL,
  `no_spk` varchar(100) DEFAULT NULL,
  `ba_no` varchar(100) DEFAULT NULL,
  `ba_tgl` date DEFAULT NULL,
  `kode_barang` varchar(100) NOT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `jenis_aset` varchar(100) DEFAULT NULL,
  `merk_tipe` varchar(255) DEFAULT NULL,
  `no_sertifikat` varchar(255) DEFAULT '-',
  `ukuran_bangunan` varchar(100) DEFAULT '-',
  `satuan` varchar(50) DEFAULT NULL,
  `volume` int NOT NULL DEFAULT '0',
  `harga_satuan` double NOT NULL DEFAULT '0',
  `nilai_perolehan` double NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_realisasi` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_realisasi_master` (`id_master_barang`),
  CONSTRAINT `fk_realisasi_master` FOREIGN KEY (`id_master_barang`) REFERENCES `master_barang_sekolah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1327 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `realisasi_lock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `realisasi_lock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sekolah` varchar(50) DEFAULT NULL,
  `bulan` varchar(10) DEFAULT NULL,
  `status` enum('0','1') DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `status_kirim_berkas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `status_kirim_berkas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_sekolah` int NOT NULL,
  `bulan` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'Menunggu Approval',
  `tanggal_kirim` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_sekolah` (`id_sekolah`),
  KEY `bulan` (`bulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `id_sekolah` int DEFAULT NULL,
  `nama_sekolah` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
