-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: hris_db
-- ------------------------------------------------------
-- Server version	8.4.3

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

--
-- Table structure for table `manpower_requests`
--

DROP TABLE IF EXISTS `manpower_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `manpower_requests` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `requested_by` char(26) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_position_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `manpower_requests_company_id_foreign` (`company_id`),
  KEY `manpower_requests_job_position_id_foreign` (`job_position_id`),
  KEY `manpower_requests_requested_by_foreign` (`requested_by`),
  CONSTRAINT `manpower_requests_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `m_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manpower_requests_job_position_id_foreign` FOREIGN KEY (`job_position_id`) REFERENCES `m_job_positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `manpower_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `manpower_requests`
--

LOCK TABLES `manpower_requests` WRITE;
/*!40000 ALTER TABLE `manpower_requests` DISABLE KEYS */;
INSERT INTO `manpower_requests` VALUES ('01kajacbsb8cvxq8g3cwe715rz','01k8271xa4nq8cezg3dq9vfr14',NULL,'01kad06hjgsdvtaw4f5sspfy7y','additional_employee',5,NULL,'approved',NULL,NULL,'2025-11-21 11:22:25','2025-11-21 11:23:06'),('01kbm2eqfjxjzgktdk30e88xg5','01k8271xa4nq8cezg3dq9vfr14',NULL,'01kad06hjgsdvtaw4f5sspfy7y','additional_quota',1,NULL,'approved',NULL,NULL,'2025-12-04 13:58:05','2025-12-04 13:58:31'),('01kbm2hyvss6ef87ygf3a7x9t0','01k8271xa4nq8cezg3dq9vfr14',NULL,'01kbm28v88g7fk2aazc5mn9nkb','additional_employee',1,NULL,'approved',NULL,NULL,'2025-12-04 13:59:51','2025-12-04 14:00:29'),('01kngvh3rcn4z3dkvd9tnq7f9d','01J2F0C8KQ9M7D4S8RBX6E1ZVP',NULL,'01kem0ksc3pvm3e2zqhrd1vy9b','employee_replacement',1,NULL,'approved',NULL,NULL,'2026-04-06 14:36:18','2026-04-06 14:38:09'),('01kpw7qcjxgs6yx8stbz4qpejh','01J2F0C8KQ9M7D4S8RBX6E1ZVP',NULL,'01kem0rf928g3a5v8nvy5262yy','additional_employee',1,NULL,'requested',NULL,'2026-04-23 11:18:22','2026-04-23 10:56:47','2026-04-23 11:18:22'),('01kpw8zp845avba1qrfktyp4xj','01J2F0C8KQ9M7D4S8RBX6E1ZVP',NULL,'01kem0rf928g3a5v8nvy5262yy','additional_employee',1,NULL,'requested',NULL,'2026-04-23 11:21:13','2026-04-23 11:18:48','2026-04-23 11:21:13'),('01kpw94t6sc97gj4ej8pb0nm7n','01J2F0C8KQ9M7D4S8RBX6E1ZVP',NULL,'01kem0rf928g3a5v8nvy5262yy','additional_employee',1,NULL,'requested',NULL,'2026-04-23 14:33:35','2026-04-23 11:21:36','2026-04-23 14:33:35'),('01kpwm4txz3s9sjb2myqgw1sz7','01J2F0C8KQ9M7D4S8RBX6E1ZVP',NULL,'01kem0rf928g3a5v8nvy5262yy','additional_employee',1,NULL,'requested',NULL,'2026-04-23 14:52:54','2026-04-23 14:33:51','2026-04-23 14:52:54'),('01kqbxqxadndxh3dwyz760c5m5','01J2F0C8KQ9M7D4S8RBX6E1ZVP',NULL,'01kem0rf928g3a5v8nvy5262yy','additional_employee',1,NULL,'approved',NULL,NULL,'2026-04-29 13:10:09','2026-04-29 13:11:48');
/*!40000 ALTER TABLE `manpower_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_openings`
--

DROP TABLE IF EXISTS `job_openings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_openings` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_position_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `quota` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `opened_at` timestamp NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_openings_company_id_foreign` (`company_id`),
  KEY `job_openings_job_position_id_foreign` (`job_position_id`),
  CONSTRAINT `job_openings_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `m_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_openings_job_position_id_foreign` FOREIGN KEY (`job_position_id`) REFERENCES `m_job_positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_openings`
--

LOCK TABLES `job_openings` WRITE;
/*!40000 ALTER TABLE `job_openings` DISABLE KEYS */;
INSERT INTO `job_openings` VALUES ('01kajadkpzc1mpb9mrx3hr8a1f','01k8271xa4nq8cezg3dq9vfr14','01kad06hjgsdvtaw4f5sspfy7y',5,1,NULL,'<p><strong>Deskripsi Pekerjaan</strong></p><p>Kami sedang mencari individu yang berdedikasi, bertanggung jawab, dan memiliki motivasi tinggi untuk bergabung dengan tim kami. Kandidat yang terpilih akan berperan dalam menjalankan aktivitas operasional harian, mendukung pencapaian target tim, serta berkontribusi dalam mencapai tujuan perusahaan.</p><ul><li><p>Tanggung Jawab:</p></li><li><p>Melaksanakan tugas dan pekerjaan sesuai dengan tanggung jawab yang diberikan</p></li><li><p>Bekerja sama dengan tim dan departemen terkait untuk memastikan kelancaran operasional</p></li><li><p>Mematuhi kebijakan, prosedur, dan peraturan perusahaan</p></li><li><p>Melaporkan hasil pekerjaan, kendala, dan progres kepada atasan</p></li><li><p>Menjaga kualitas dan efektivitas kerja</p></li><li><p>Melaksanakan tugas lain yang diberikan oleh atasan sesuai kebutuhan perusahaan</p></li></ul>','<ul><li><p>Pendidikan minimal SMA/SMK/D3/S1 (disesuaikan dengan kebutuhan posisi)</p></li><li><p>Terbuka untuk fresh graduate, pengalaman kerja menjadi nilai tambah</p></li><li><p>Memiliki kemampuan komunikasi dan interpersonal yang baik</p></li><li><p>Mampu bekerja secara mandiri maupun dalam tim</p></li><li><p>Bersedia belajar dan beradaptasi dengan lingkungan kerja yang dinamis</p></li><li><p>Bertanggung jawab, disiplin, dan teliti</p></li><li><p>Memiliki kemampuan dasar komputer dan aplikasi kerja</p></li><li><p>Mampu bekerja di bawah tekanan dan memenuhi target waktu</p></li></ul>','2026-04-04 11:13:40','2025-11-21 11:23:06','2026-04-04 11:13:40'),('01kbm2k40dbae57vjjtzk6v96h','01k8271xa4nq8cezg3dq9vfr14','01kbm28v88g7fk2aazc5mn9nkb',1,1,NULL,'<p><strong>Deskripsi Pekerjaan</strong></p><p>Kami sedang mencari individu yang berdedikasi, bertanggung jawab, dan memiliki motivasi tinggi untuk bergabung dengan tim kami. Kandidat yang terpilih akan berperan dalam menjalankan aktivitas operasional harian, mendukung pencapaian target tim, serta berkontribusi dalam mencapai tujuan perusahaan.</p><p>Tanggung Jawab:</p><ol start=\"1\"><li><p>Melaksanakan tugas dan pekerjaan sesuai dengan tanggung jawab yang diberikan</p></li><li><p>Bekerja sama dengan tim dan departemen terkait untuk memastikan kelancaran operasional</p></li><li><p>Mematuhi kebijakan, prosedur, dan peraturan perusahaan</p></li><li><p>Melaporkan hasil pekerjaan, kendala, dan progres kepada atasan</p></li><li><p>Menjaga kualitas dan efektivitas kerja</p></li><li><p>Melaksanakan tugas lain yang diberikan oleh atasan sesuai kebutuhan perusahaan</p></li></ol>','<ol start=\"1\"><li><p>Pendidikan minimal SMA/SMK/D3/S1 (disesuaikan dengan kebutuhan posisi)</p></li><li><p>Terbuka untuk fresh graduate, pengalaman kerja menjadi nilai tambah</p></li><li><p>Memiliki kemampuan komunikasi dan interpersonal yang baik</p></li><li><p>Mampu bekerja secara mandiri maupun dalam tim</p></li><li><p>Bersedia belajar dan beradaptasi dengan lingkungan kerja yang dinamis</p></li><li><p>Bertanggung jawab, disiplin, dan teliti</p></li><li><p>Memiliki kemampuan dasar komputer dan aplikasi kerja</p></li><li><p>Mampu bekerja di bawah tekanan dan memenuhi target waktu</p></li></ol>','2026-04-04 11:13:57','2025-12-04 14:00:29','2026-04-04 11:13:57'),('01kngvmfy8jhxe2v81cnqehv76','01J2F0C8KQ9M7D4S8RBX6E1ZVP','01kem0ksc3pvm3e2zqhrd1vy9b',1,1,NULL,'<ul><li><p>Mengkoordinasikan dan mengarahkan pekerjaan tukang, mandor, dan vendor di lapangan.</p></li><li><p>Menentukan prioritas pekerjaan gedung berdasarkan urgensi operasional.</p></li><li><p>Mengajukan kebutuhan material, biaya, dan jadwal pekerjaan kepada atasan.</p></li><li><p>Menghentikan sementara pekerjaan maintenance apabila tidak memenuhi standar keselamatan, kualitas, atau prosedur kerja.</p></li><li><p>Memberikan rekomendasi perbaikan, penggantian, atau peningkatan sistem fasilitas gedung kepada atasan.</p></li><li><p>Menilai hasil pekerjaan maintenance internal maupun vendor berdasarkan standar yang ditetapkan.</p></li></ul>','<ul><li><p>Pendidikan minimal D3 Teknik Sipil</p></li><li><p>Pengalaman minimal 1-2 tahun dalam proyek kontruksi</p></li><li><p>Menguasai gambar teknik, RAB dan manajemen proyek</p></li><li><p>Mampu menggunakan software teknik</p></li></ul>',NULL,'2026-04-06 14:38:09','2026-04-06 15:04:48'),('01kqbxtx4e3m5ra95cnyxsye1s','01J2F0C8KQ9M7D4S8RBX6E1ZVP','01kem0rf928g3a5v8nvy5262yy',1,0,NULL,'<p>Mengembangkan dan meningkatkan sistem IT agar sesuai dengan kebutuhan operasional dan keamanan data.</p><p>Memberikan sosialisasi kepada karyawan tentang penggunaan sistem IT dan perangkat lunak yang baru diimplementasikan.</p><p>Mengawasi keamanan data perusahaan untuk mencegah akses yang tidak sah dan kerusakan data.</p><p>Berkoordinasi dengan semua departemen untuk memastikan bahwa sistem IT mendukung proses bisnis mereka.</p>','<p>D3 /  S1 Teknik Informatika, Ilmu Komputer, Sistem Informasi</p><p>Berpengalaman dalam membuat program aplikasi / pengembangan web</p><p>Memiliki kemampuan analisa, pemecahan masalah, detail dan manajemen waktu</p><p>Inisiatif dan mampu bekerja sama dengan tim</p><p>Menguasai framework PHP dan JavaScript</p><p>Menguasai MySQL Expert (Procedure, Trigger, Function)</p>',NULL,'2026-04-29 13:11:47','2026-07-15 04:47:27'),('01kqbyhg8gmr81m4p51p7s6j0t','01J2F0C8KQ9M7D4S8RBX6E1ZVP','01kem0rf928g3a5v8nvy5262yy',1,0,NULL,'{\"type\":\"doc\",\"content\":[{\"type\":\"paragraph\",\"content\":[{\"type\":\"text\",\"text\":\"Mengembangkan dan meningkatkan sistem IT agar sesuai dengan kebutuhan operasional dan keamanan data.\"}],\"attrs\":{\"textAlign\":\"start\"}},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Memberikan sosialisasi kepada karyawan tentang penggunaan sistem IT dan perangkat lunak yang baru diimplementasikan.\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Mengawasi keamanan data perusahaan untuk mencegah akses yang tidak sah dan kerusakan data.\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Berkoordinasi dengan semua departemen untuk memastikan bahwa sistem IT mendukung proses bisnis mereka.\"}]}]}','{\"type\":\"doc\",\"content\":[{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"D3 \\/  S1 Teknik Informatika, Ilmu Komputer, Sistem Informasi\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Berpengalaman dalam membuat program aplikasi \\/ pengembangan web\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Memiliki kemampuan analisa, pemecahan masalah, detail dan manajemen waktu\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Inisiatif dan mampu bekerja sama dengan tim\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Menguasai framework PHP dan JavaScript\"}]},{\"type\":\"paragraph\",\"attrs\":{\"textAlign\":\"start\"},\"content\":[{\"type\":\"text\",\"text\":\"Menguasai MySQL Expert (Procedure, Trigger, Function)\"}]}]}',NULL,'2026-04-29 13:24:08','2026-04-29 13:24:08');
/*!40000 ALTER TABLE `job_openings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidates`
--

DROP TABLE IF EXISTS `candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candidates` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blood_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body_height_weight` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marriage_date` date DEFAULT NULL,
  `social_media_accounts` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_education` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nationality` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `religion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_kk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nik` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_place` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `status_marital` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ktp_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `domicile_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `village` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mother_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `education` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `major` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `university` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `graduation_year` year DEFAULT NULL,
  `no_bpjskes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faskes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_bpjstk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_bpjstk` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bsi_bank_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_onboarded` tinyint(1) NOT NULL DEFAULT '0',
  `overall_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidates_email_unique` (`email`),
  KEY `candidates_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidates`
--

LOCK TABLES `candidates` WRITE;
/*!40000 ALTER TABLE `candidates` DISABLE KEYS */;
INSERT INTO `candidates` VALUES ('01kcfpsmhas2ksj4w7ny6g967a','Sella Gesit Cahyaningtyas','sellaaalita@gmail.com','08993974965','$2y$12$73RWmFU6UaFp11OkJXWszuWwLToI/CG0RneKP2HMupZ.JJ8.fOm/2',NULL,'4yEyb6sGUIGtVryUiNDIv4YweePp4XMGjQSfUlOQfPQiYTSm8DmbWZxqMPKf',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2025-12-15 07:33:04','2025-12-15 07:33:04'),('01kcjtkc12q4cabb9z5r423eaq','Zulfa','ulz0707@gmail.com','081234567890','$2y$12$3yOgSOFnejX9407oFLVpXOssihZn.1nBLRvFT8mPs3/cvnYkhJvFG','2025-12-16 12:52:55','7REAZmyEXGLgO8gfWy1YeDEMx0HMDvaU1n7n46VIaMcJXrQaTOQpbI1DybhY',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'1234561234567878','1234561234567878','Jakarta','2025-12-08','Belum Menikah','Jalan ABCD','Jalan ABCD','Depok','Condongcatur','Sleman','DI Yogyakarta','Jane','S1','IT','UGM',2025,'123','Puskesmas 1','123','PT ABC','3321','candidate-profiles/01kcjtkc12q4cabb9z5r423eaq//01KCJX9DKZXMQK7F36FEMSMQD7.jpg',1,'new',NULL,'2025-12-16 12:37:16','2026-04-09 09:00:10'),('01kckjktzsvyq3b1qs4de6hwa8','Nurhanafiah Nabila Putri','naablptri@gmail.con','+6283143646547','$2y$12$MhsnfjsgjjmGuShuCrh8bO0kYiZpWNAjOreCG28i0z5KFnRl0rIDW',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2025-12-16 19:36:57','2025-12-16 19:36:57'),('01kckjsh8dz488btf9r6xw8xrr','Nurhanafiah Nabila Putri','naablptri@gmail.com','+6283143646547','$2y$12$AfPCrxgkCS8fexLe8XYAh.jmgao722lSnjBkJfGP3bp4jvPBWvIwy','2025-12-16 19:40:27',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2025-12-16 19:40:04','2025-12-16 19:40:27'),('01kczjabg4p5kknxc0rthsa2z1','Dian Novrizal','diannovrizal9@gmail.com','085182391956','$2y$12$Y.27XotBUfPmssEkGS2J/OjZK9XBnRg4XifnCqh2dnLMNxYaKH0zG','2025-12-21 11:23:16','LLb4GGgtJVrz7y4JhZlnvnvPBkSp19f3ZX62bGE9hcXkUbyUVAdI3ytCJ4qD',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2025-12-21 11:22:39','2025-12-21 11:23:16'),('01kecynm59a9cxth8yb7h94n7q','Selly febriyanti ','sellyfebriyanti265@gmail.com','082254941466','$2y$12$2rPReB5sTYqeXXIqdXizIeTAP4itWeTIpcebeGKd82VagRMSzWJzy','2026-01-08 02:25:18','XbL95W1qvQm6g8QKbl9k9zfetevMBzIsKiakh6uq6aLEylTulx0UCRtSljUP',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'perempuan',NULL,'6311041803250001','6311045109990001','Banjarmasin ','1999-09-11','Cerai','Bungur ','Bungur batumandi rt 03','Bungur','Batumandi','Balangan','Kalimantan selatan','Rusmili','SMK','TKJ','-',2017,'-','-','-','-','-','candidate-profiles/01kecynm59a9cxth8yb7h94n7q//01KECZHGSB5XDQJEHQ5D8ZH0H3.jpeg',1,'new',NULL,'2026-01-08 02:24:21','2026-01-08 02:39:36'),('01kevdbqnk34wdw3knnpaq2aby','Putri Olga Adeliya ','putriolgaadeliya@gmail.com','081253481904','$2y$12$yLiE7488uWFcXswWY/oy3ObqwRg2krH54bqQmHSuhquMUrN8CszBu','2026-01-13 17:12:57',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-01-13 17:10:28','2026-01-13 17:12:57'),('01kexqgq1qvgp2rq2p693qyfhc','Dhimas Agung Priyanda Putra','dhimaspriyanda@gmail.com','089519096267','$2y$12$xN4PRUIn7SQQnSTt05NXielALGUk/HWZnj1TFwkQRP3SkVCoBouRi','2026-01-14 14:46:46','czTZrOC7XOevIJvYVMwTYHQRVcvLFljry6evgsHEt8GHifO73dDDhBSVwY4B',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'6372062812150002','6372060710910001','Banjarbaru','1991-10-07','Menikah','JL PERSADA XI NO 42 RT 011 RW 002 KOMP GALUH MARINDU','JL PERSADA XI NO 42 RT 011 RW 002 KOMP GALUH MARINDU','SUNGAI BESAR','BANJARBARU SELATAN','BANJARBARU','KALIMANTAN SELATAN','Dayang Mai Jamilah','D3','ELEKTROMEDIK','SEKOLAH TINGGI ILMU KESEHATAN SEMARANG',2025,'0001312564059','dr. Aulia Rachman','-','-','-','candidate-profiles/01kexqgq1qvgp2rq2p693qyfhc//01KEXRD14H6G5CFJ6KCY81V54B.jpeg',1,'new',NULL,'2026-01-14 14:46:25','2026-01-17 13:31:49'),('01kf42cj773px69fn1n6y8g7gd','Muhammad Ikhwani Muslim','ikhwanimuslim2004@gmail.com','085348041616','$2y$12$yswWRRzgaw24AAGaZfYAOOQbVqKpfzd2o.nrjOK5KFkeoT2wN4JZG','2026-01-17 01:52:17',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-01-17 01:51:50','2026-01-17 01:52:17'),('01kf9v7kznv9wcmc1adpad8afg','M. IRFAN RIDHANI, S.Kom','Hilyahanan22@gmail.com','082754635963','$2y$12$a5rQcyDOQ5HdJLmji35JmOcQHNsrnXoXOt9gJ3pp5QWSFUZglh3zG','2026-01-19 07:42:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'6307061008170003','6307061705920009','Barabai','1992-05-17','Menikah','Jl. Murakata, Komplek Pemda, RT 009, RW 003, Kelurahan Barabai Barat, Kecamatan Barabai','Jl. Murakata, Komplek Pemda, RT 009, RW 003, Kelurahan Barabai Barat, Kecamatan Barabai','Barabai Barat','Barabai','Hulu Sungai Tengah','Kalimantan Selatan','Hj. Rusdiana','S1','Teknik Informatika','Universitas Islam Kalimantan Muhammad Arsyad Al Banjari Banjarmasin',2014,'0000124583229','Dr. Mahdi Aldha Mahashin','22042819882','22042819882','-','candidate-profiles/01kf9v7kznv9wcmc1adpad8afg//01KF9WM7X5S012FZT1NWM25ZR7.jpg',1,'new',NULL,'2026-01-19 07:42:15','2026-01-19 08:09:17'),('01kfje3ek46y3npmp0pyqyr16f','Cika Salita Azharia Putri','salitacika11@gmail.com','087815724660','$2y$12$zdxR1hTW7XG/R1CwwrjuTeUy6rjkMX5P3KQn2cVFZPWGWt8DqtskG','2026-01-22 15:46:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-01-22 15:45:57','2026-01-22 15:46:25'),('01kfjrrf812ap2vtmzjvhjm4b0','Rahmadina','rd8865201@gmail.com','0838-2246-4563','$2y$12$H7e8tPMmJ0MhnadfxxwwYuefOUOzjwX0WR1.j5UU/3K/6rSI.ZKMa',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-01-22 18:52:11','2026-01-22 18:52:11'),('01kg6zxyczpp6j993s1gpz44x8','Agung Prasetyo','agung.prasetyo.code@gmail.com','085212341234','$2y$12$/3cu/JBkvomYblGW6Uzpjug9oemZpvNNyNrWkb4gkqqSpCdNRvPJC','2026-01-30 15:30:48',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'3376012508900001','3376012508900001','Tegal','1990-08-25','Menikah','Sleman, Yogyakarta','Sleman, Yogyakarta','Tajem','Maguwoharjo','Sleman','D. I Yogyakarta','Lusdiani','S2','Teknik Informatika','Amikom Yogyakarta',2018,'-','-','-','-','-','candidate-profiles/01kg6zxyczpp6j993s1gpz44x8//01KG70RYKH0G46FXDRXAXPBFR3.jpeg',1,'new',NULL,'2026-01-30 15:22:19','2026-01-30 15:37:07'),('01kg8qr741wddtajywrd4zcbwc','ANU ANU','anu@gmail.com','0812','$2y$12$/MIyOMKqGVYd/QvGmmS3vuw5d5fVzvkj7ZBA45nRObolg2HF7sTRm',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-01-31 07:37:52','2026-01-31 07:37:52'),('01kg8qtmdh6dft6e6k2ezpnjkh','ANU ANU','lutfisalisaset3@gmail.com','0812','$2y$12$GXXdC8Sj3a3a0KtdKAEtXeJtWoL4xNdOk6eaTone9IJvGcB9mRRWC','2026-01-31 07:40:36',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-01-31 07:39:11','2026-01-31 07:40:36'),('01kh0n2tr2tf5rtcwnz4hw9eh4','M. Immawan Nuryahdi','immawannuryahdi1101@gmail.com','085705881776','$2y$12$8n89yvXVWmHUnu4fkaghJu8Wi8/EkvlEJxj8w9pL6H9kL05y9q5.m','2026-02-09 14:33:27','HPaIvgqs90YJUPyJ1lLqYKbUyYkFeGkxsnRpVCv2S8z7ODhmRdvSUdxJldqp',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-02-09 14:33:00','2026-02-26 13:40:49'),('01kh0n872evxe9n1a727hc35vn','BintangMakruf','bintangsutarjo@gmail.com','0895700828958','$2y$12$/VYksi/lU4D1zpXmrQAlW.GQ0hyzHnntPHD08jvfchOBB3GGrgMMu','2026-02-09 14:36:14','vq9nm24KdpUN2mGFz5i4b0E6IUYTp1ePA6PGu2BnWNGEn8j6vllqcoqKoxqQ',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-02-09 14:35:57','2026-05-04 09:21:03'),('01kn1bzbc8gec30n3150w27ew5','Muhammad Khairil Amin','khairilamin1217@gmail.com','085722305978','$2y$12$YbudNNtNbWYKqrQ5iXmIyOq77og.Jbj3YpmlQHuQYkFQta.ci7OEy','2026-03-31 14:19:34',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-03-31 14:15:51','2026-03-31 14:19:34'),('01knbarech0qygq7jg48sestyz','Air minum','immawan.riyadi@gmail.com','09009090909','$2y$12$nzqAPcy6vnfaxmIYPSVr6uQEEVICWumLPKMa/HJK4DJk4XpBRQztW','2026-04-04 11:07:27','4BWoLzJuuWmC24zSqJ89B6yMppI6rxTQBkPU89rv4XRLmtcpTtxH06uE2YOf',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,NULL,'1111111111111111','rgtr','2026-04-14','Menikah','efeef','efefeef','efefe','hrthr','thrt','thth','dfdfdg','efef','efef','yj',2045,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-04-04 11:07:00','2026-04-07 14:17:58'),('01knk8bdakasyvbjjrab624kkn','DANDUNG MARIYANTO','dandungm.dm@gmail.com','082153694765','$2y$12$PxrVtV79UmY3rU6e0/JwR./Q6GcwFyugQULhms8WI1paykXZFjzt.','2026-04-07 13:00:54','z297qPXLnsx7usaANx6poGccwi5EViwV0wAX38abbwlQnmqh9zafgXAdaS1Y',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'6304020802210001','6304021005960002','Tinggiran II Luar','1996-05-10','Menikah','TINGGIRAN II LUAR, RT. 08, KECAMATAN. TAMBAN, KABUPATEN. BARITO KUALA','TINGGIRAN II LUAR, RT. 08, KECAMATAN. TAMBAN, KABUPATEN. BARITO KUALA','TINGGIRAN II LUAR','TAMBAN','BARITO KUALA','KALIMANTAN SELATAN','Zainah','DIPLOMA 3','SIPIL','POLITEKNIK NEGERI BANJARMASIN',2017,'0','0','0','0','0','candidate-profiles/01knk8bdakasyvbjjrab624kkn//01KNKB7MJ8EFG1ZAPFMBGWPZM8.jpg',1,'new',NULL,'2026-04-07 12:58:51','2026-04-07 13:50:24'),('01knk977snfpv4gkhydkzbhz1g','Muhammad Taufik Hidayat','anjayking43@gmail.com','085385237471','$2y$12$jmXZ5s3WNPYOgODJb7JkuuezHC9hMLLfuGHZ8IEFO/kSNI0qP72Jq','2026-04-07 13:16:10',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'6304061612150001','6304062607030002','Tabing Rimbah','2003-07-26','Belum Menikah','Ray 6 Tabing Rimbah','Ray 6 Tabing Rimbah','Tabing Rimbah','Mandastana','Barito Kuala','Kalimantan Selatan','Samikem','D3','Teknik Sipil','Politeknik Negeri Banjarmasin',2024,NULL,NULL,NULL,NULL,NULL,'candidate-profiles/01knk977snfpv4gkhydkzbhz1g//01KNKC2R6M8J8D6FR0HC6MC5XG.jpg',1,'new',NULL,'2026-04-07 13:14:03','2026-04-07 14:05:21'),('01knkjgaefmzwhcpk1kzeft8n8','Dias Fahmi','victfahmi100@gmail.com','082157297878','$2y$12$.5LyY8fE6Xrp2TA/t1EJ4.PLdGq5Rc8u.yMsiPmWQ3Q9H5AYSJNAq','2026-04-07 15:56:57',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'6371010202080459','6371011010010008','Banjarmasin','2001-10-10','Belum Menikah','Komplek mantuil raya blok k no180','Komp.wengga mantuil raya blok k','Mantuil','Banjarmasin selatan','Banjarmasin','Kalimantan Selatan','Noor Bani','D3','Teknik sipil','Politeknik negeri banjarmasin',2023,'0001790578719','.....','.......','......','.......','candidate-profiles/01knkjgaefmzwhcpk1kzeft8n8//01KNMD461914W9HKTC4B2HRYVB.jpg',1,'new',NULL,'2026-04-07 15:56:18','2026-04-07 23:41:32'),('01knrsepq4jj6jq9d5461p78yd','FADHI TRIFANO PANGESTU','fadhipangestu@gmail.com','0896 9227 4795','$2y$12$x8dPupOJHgB852rZMZyVTeFSdeWUf8TlKGyP21QOSxvI6GnampVK6','2026-04-10 08:48:04','iG4zyDKV1axnvKe2OoxWYmf1eQPhPkCFV4Wmqlg4654ogdMYjV75Nuf7hJCe','Fadhi','O','170/78','2023-06-25','@fadhitrifano_','Sarjana S1','Indonesia','laki_laki','Islam','6372022209230005','6271010311970005','Palangka Raya','1997-11-03','Menikah','Jl. Golf Komp. Wengga 3 Blok G No. 63 RT.014/RW.003 Kel. Syamsudin Noor, Kec. Landasan Ulin, Kota Banjarbaru','Jl. Sukamaju Komp. Citra Mandiri Residence No. 67, Kel. Landasan Ulin Barat, Kec. Liang Anggang, Kota Banjarbaru','Landasan Ulin Barat','Liang Anggang','Kota Banjarbaru','Kalimantan Selatan','Nunuk Hatiningsih','Sarjana S1 ','Arsitektur','Universitas Palangka Raya',2021,'0002206631327','Klinik Dr. H. M. Hidayatullah',NULL,NULL,NULL,'[]',1,'new',NULL,'2026-04-09 16:33:57','2026-04-10 09:39:16'),('01kpwe32a23fgqaaph272mp5nw','amin','aminstarned@gmail.com','085722305978','$2y$12$BUZTigrzX7.TOngrYEwSJeaHQolSMmQhKti8NSQZ2Ol0rmdHPO3oC','2026-04-23 12:49:37',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-04-23 12:48:01','2026-04-23 12:49:37'),('01kpyxtvgyzd66p5vwpj2varye','Salsa Hany Saputri','salsahanysaputri10@gmail.com','082269335741','$2y$12$IEhdKf1nv/W.s2aSPiRL1OnR9b8n8.xBI99rLXlWqVTltmqP27sgC','2026-04-24 12:02:04',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-04-24 12:01:38','2026-04-24 12:02:04'),('01kq447e2q54j8vm9nwqaq7884','Muhammad Noor Azmi ','azmi22097@gmail.com','085753115646','$2y$12$CCQntH3aaWtmSg.J0dkuFuACBYjLMV/3LMI0YeFgNoyDp92JF59qe','2026-04-26 12:33:48','Pah5ryLPfKTZle3MMl58UEoNWa6ZPhGOjzLGZDKWZgitPi4abTx6OAkBnk9s',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'laki_laki',NULL,'6307031610240003','6307030208080001','HULU SUNGAI TENGAH ','2008-08-02','Belum Menikah','TABUDARAT HILIR ','Kalimantan Selatan, kabupaten hulu sungai Tengah ','TABUDARAT HILIR ','labuan Amas Selatan ','Barabai, kabupaten hulu sungai Tengah ','Kalimantan Selatan ','Jalipah','SMA ','Ips','SMA NEGERI 2 BARABAI',2026,'0002657037894',NULL,NULL,NULL,'7354213553','candidate-profiles/01kq447e2q54j8vm9nwqaq7884//01KQ479EEC7Y94Y4P58S269E79.jpg',1,'new',NULL,'2026-04-26 12:29:34','2026-05-04 16:30:56'),('01kqgp0497stzpwpy683nqm0ce','M Fihri Aziz Assuyuti','fihriaziz@gmail.com','08978450348','$2y$12$W4qd0TQmngdcsgjXE9ADaOfTnBnqmwXkIGwORwQtbqzMNDCKQMMEu','2026-05-01 09:32:23',NULL,'Aziz','B','155 / 59',NULL,NULL,'Sarjana ','Indonesia','laki_laki','Islam',NULL,NULL,'Cirebon','1996-03-25','Belum Menikah',NULL,'Blok belakang desa no 008 RT 003 RW 002, Desa Kalitengah, Kec. Tengah Tani, Kab. Cirebon',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[]',0,'new',NULL,'2026-05-01 09:31:02','2026-05-01 09:34:03'),('01kqgpaf8zcx093vv8843jn3wv','Danni Adhyatma Rachman','danniadhyatmarachman@gmail.com','085651287724','$2y$12$jSZxVGYYDNEZIezduWCxTudEMGf2Sx0yWZ.O5iBhPjYTsq7ufdfuC',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-05-01 09:36:41','2026-05-01 09:36:41'),('01kqkx8fxpez9sh4j4qbj57w5s','Muhammad wahyu','mw982577@gmail.com','081251215383','$2y$12$olTh/NjRg5SFD2NeuKM0ROJEdygjFs/DquGE3kGO1Cw11aP0oZrIm','2026-05-02 15:37:01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-05-02 15:35:40','2026-05-02 15:37:01'),('01kqkxxjmj5vrvevvcrhyptzck','Yazid kurnia ramadhan','yazidkurniaramadhan@gmail.com','082219984104','$2y$12$xpZAPg33bX2fiGkTR5eyqucQJnTU9yqmf14OdngIyk9CMc6.xSkw2','2026-05-02 15:49:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-05-02 15:47:10','2026-05-02 15:49:33'),('01kqrf7tj1dfz4c8ptxa03z30x','MUHAMMAD AHYA GHIFARI','ahyaghifari288@gmail.com','0895413505336','$2y$12$MkKU4YYfFaZ2Zl3piuFTZuenzCXmuwLAT4AFdx7/lF4RU8gLvAGIK','2026-05-04 10:13:35',NULL,'Ahya','B','161/82.65',NULL,'Instagram : @thenavystone','S1','Indonesia','laki_laki','Islam','6372052612070028','6372052808030001','Martapura','2003-08-28','Belum Menikah','KOMP. KENCANA PERMAI NO.01 BLOK.B','Jl. Karang Anyar 3 Komp. Kencana Permai No. 1 Blok. B RT/25 RW/11 Loktabat Utara, Banjarbaru Utara, Kota Banjarbaru, Kalimantan Selatan','LOKTABAT UTARA','BANJARBARU UTARA','BANJARBARU','KALIMANTAN SELATAN','Rahmaniah','S1','Teknik Informatika','Universitas Islam Kalimantan Muhammad Arsyad Al - Banjari Banjarmasin',2025,'0001822563977',NULL,NULL,NULL,'7355356021','candidate-profiles/01kqrf7tj1dfz4c8ptxa03z30x//01KQRGQJ2YYZSSDDH8MNPF20X0.jpg',1,'new',NULL,'2026-05-04 10:06:50','2026-05-05 16:31:50'),('01kr2mvam517cb5yfh609wd8ja','Handoko','handoko207@gmail.com','081257335755','$2y$12$65hCDZ0pJoXfGRN8VGHYF.NyjWVMB1z.7hRGPm6Kgeh3PsR8gjL2.','2026-05-08 09:57:32',NULL,'Handoko','B','173','2020-05-02','https://www.linkedin.com/in/handoko-sre-devops/','S1 - Ilmu Komputer','Indonesia','laki_laki','Islam',NULL,'6372053112910001','Banjar','1994-02-17','Menikah',NULL,'Jl. Bina Putra No 50',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[]',0,'new',NULL,'2026-05-08 09:57:16','2026-05-08 10:05:37'),('01kracp7rk1rw8gm97s2738yp1','Abdurrahman Musyaffa, S.T.','aduduu345@gmail.com','08125607651','$2y$12$7St/E5CoTiXZ5CBpILHx1uj7QobmOjSMJx.4xee5.P4Pl52au.MdO',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-05-11 10:08:36','2026-05-11 10:08:36'),('01kx0cdv54j1bq2we29vf7yg8z','Mukhammad Rezarudin Yusuf','muhrezarudinyusuf@gmail.com','087884168923','$2y$12$tewIupx4F/GGuZFn7152Au14TqSchUnVExWAjlqJwNcklu5UujtEK',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-07-08 08:10:06','2026-07-08 08:10:06'),('01kx0f9y2mna5mkswx0ynfxa69','Mukhammad Rezarudin Yusuf','muhrezarudinyusuf358@webmail.umm.ac.id','087884168923','$2y$12$dlP8knGyfyHi00W5dfglfuUlpT882EBPorGmCyAXwf6nauy4GLimm',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-07-08 09:00:24','2026-07-08 09:00:24'),('01kx7f6g6fmnhz3h3e36287w82','PMKP','pokjapmkp088@gmail.com','082154763889','$2y$12$8SgVUkka6LOGRjVdjkoiQuw7.MaNH/9Ye4HyxaRPzw4357xGFzi12',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,'new',NULL,'2026-07-11 02:13:12','2026-07-11 02:13:12');
/*!40000 ALTER TABLE `candidates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_applications`
--

DROP TABLE IF EXISTS `job_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_applications` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `job_vacancy_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_stage` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'onboarding',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `approval_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_applications_candidate_id_job_vacancy_id_unique` (`candidate_id`,`job_vacancy_id`),
  KEY `job_applications_job_vacancy_id_foreign` (`job_vacancy_id`),
  KEY `job_applications_approved_by_foreign` (`approved_by`),
  CONSTRAINT `job_applications_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_applications_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_applications_job_vacancy_id_foreign` FOREIGN KEY (`job_vacancy_id`) REFERENCES `job_openings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_applications`
--

LOCK TABLES `job_applications` WRITE;
/*!40000 ALTER TABLE `job_applications` DISABLE KEYS */;
INSERT INTO `job_applications` VALUES ('01kck05pd5d776q22cgeecc1vz','01kcjtkc12q4cabb9z5r423eaq','01kajadkpzc1mpb9mrx3hr8a1f','Kandidat Diterima','hired',NULL,NULL,NULL,'2025-12-16 14:14:31','2026-03-16 09:14:07',NULL,'2025-12-16 14:14:39','2026-03-16 09:14:07',NULL),('01keczk3mpyfznyhm2j0reg9g3','01kecynm59a9cxth8yb7h94n7q','01kajadkpzc1mpb9mrx3hr8a1f','intv','in_selection',NULL,NULL,NULL,'2026-01-08 02:40:10','2026-02-26 13:08:16',NULL,'2026-01-08 02:40:27','2026-02-26 13:09:43',NULL),('01kecznkwef59m48at58zp1eag','01kecynm59a9cxth8yb7h94n7q','01kbm2k40dbae57vjjtzk6v96h','submitted','submitted',NULL,NULL,NULL,'2026-01-08 02:41:41',NULL,NULL,'2026-01-08 02:41:49','2026-01-08 02:41:49',NULL),('01kexrf1qvhp34w5h1sv1ekmb1','01kexqgq1qvgp2rq2p693qyfhc','01kajadkpzc1mpb9mrx3hr8a1f','submitted','submitted',NULL,NULL,NULL,'2026-01-14 15:02:46',NULL,NULL,'2026-01-14 15:02:59','2026-01-14 15:02:59',NULL),('01kf5afqax52s68dg4w75z1nsz','01kexqgq1qvgp2rq2p693qyfhc','01kbm2k40dbae57vjjtzk6v96h','submitted','submitted',NULL,NULL,NULL,'2026-01-17 13:32:31',NULL,NULL,'2026-01-17 13:32:37','2026-01-17 13:32:37',NULL),('01kf9wntjcftvv7n2yvx7r91ha','01kf9v7kznv9wcmc1adpad8afg','01kajadkpzc1mpb9mrx3hr8a1f','submitted','submitted',NULL,NULL,NULL,'2026-01-19 08:07:18',NULL,NULL,'2026-01-19 08:07:29','2026-01-19 08:07:29',NULL),('01kh0n5pftncfsjhcn53a8mqw2','01kh0n2tr2tf5rtcwnz4hw9eh4','01kajadkpzc1mpb9mrx3hr8a1f','wawancara hr','in_selection',NULL,NULL,NULL,'2026-02-09 14:34:21',NULL,NULL,'2026-02-09 14:34:34','2026-03-17 09:12:47',NULL),('01kh0nc60s28y4862vsngd76mz','01kh0n872evxe9n1a727hc35vn','01kajadkpzc1mpb9mrx3hr8a1f','mcu','in_selection',NULL,NULL,NULL,'2026-02-09 14:38:02','2026-02-09 14:52:06',NULL,'2026-02-09 14:38:07','2026-02-09 14:56:49',NULL),('01knkcy7thw55f3g62jgs5w83e','01knbarech0qygq7jg48sestyz','01kngvmfy8jhxe2v81cnqehv76','Interview HR','rejected',NULL,NULL,NULL,'2026-04-07 14:18:53','2026-04-14 09:35:00',NULL,'2026-04-07 14:19:03','2026-04-14 09:35:00',NULL),('01knkdeqqsnztdct1z5w69tvp4','01knk8bdakasyvbjjrab624kkn','01kngvmfy8jhxe2v81cnqehv76','Interview User 1','in_selection',NULL,NULL,NULL,'2026-04-07 14:27:50',NULL,NULL,'2026-04-07 14:28:03','2026-04-14 09:31:40',NULL),('01knkeb965azeassb48fddmj73','01knk977snfpv4gkhydkzbhz1g','01kngvmfy8jhxe2v81cnqehv76','Interview HR','rejected',NULL,NULL,NULL,'2026-04-07 14:43:24','2026-04-14 10:57:31',NULL,'2026-04-07 14:43:39','2026-04-14 10:57:31',NULL),('01knmdtrc2pkyba7w4xkk9fbdt','01knkjgaefmzwhcpk1kzeft8n8','01kngvmfy8jhxe2v81cnqehv76','Interview HR','rejected',NULL,NULL,NULL,'2026-04-07 23:53:42','2026-04-14 10:57:56',NULL,'2026-04-07 23:53:52','2026-04-14 10:57:56',NULL),('01kntm43g8evz2c2yrmpqxn4ad','01knrsepq4jj6jq9d5461p78yd','01kngvmfy8jhxe2v81cnqehv76','Interview User 1','in_selection',NULL,NULL,NULL,'2026-04-10 09:14:44',NULL,NULL,'2026-04-10 09:39:16','2026-04-14 09:32:28',NULL),('01kqgp5mkq38e0ky1ajd9qeasn','01kqgp0497stzpwpy683nqm0ce','01kqbxtx4e3m5ra95cnyxsye1s','submitted','submitted',NULL,NULL,NULL,'2026-05-01 09:32:31',NULL,NULL,'2026-05-01 09:34:03','2026-05-01 09:34:03',NULL),('01kqrgbf5nhnbd9ca8ahpvq3pt','01kqrf7tj1dfz4c8ptxa03z30x','01kqbxtx4e3m5ra95cnyxsye1s','Kandidat Diterima','hired',NULL,NULL,NULL,'2026-05-04 10:13:47','2026-05-12 13:09:55',NULL,'2026-05-04 10:26:18','2026-05-12 13:09:55',NULL),('01kr2nakkzg0cnd64wggcj7m3p','01kr2mvam517cb5yfh609wd8ja','01kqbxtx4e3m5ra95cnyxsye1s','submitted','submitted',NULL,NULL,NULL,'2026-05-08 09:57:41',NULL,NULL,'2026-05-08 10:05:37','2026-05-08 10:05:37',NULL);
/*!40000 ALTER TABLE `job_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recruitment_processes`
--

DROP TABLE IF EXISTS `recruitment_processes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recruitment_processes` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `application_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `process_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `process_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sequence` int unsigned NOT NULL DEFAULT '1',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `score` int unsigned DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachments` json DEFAULT NULL,
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recruitment_processes_application_id_process_type_index` (`application_id`,`process_type`),
  CONSTRAINT `recruitment_processes_application_id_foreign` FOREIGN KEY (`application_id`) REFERENCES `job_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recruitment_processes`
--

LOCK TABLES `recruitment_processes` WRITE;
/*!40000 ALTER TABLE `recruitment_processes` DISABLE KEYS */;
INSERT INTO `recruitment_processes` VALUES ('01kh0ngnvvtr8qkm659655ppfy','01kh0nc60s28y4862vsngd76mz','test','Tes Online',1,'completed','www.google.com',1,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-02-09\", \"submitted_at\": \"2026-02-09T07:40:59.291600Z\", \"maximum_completion_at\": \"2026-02-09\"}','2026-02-09 14:40:34','2026-02-09 14:41:40'),('01kh0nt1d65tph9rbw9c7zy9qq','01kh0nc60s28y4862vsngd76mz','interview','wawancara hr',1,'failed','Tempat ; RS Syifa Medika\nJam : 10.00 WITA',NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-02-09\", \"submitted_at\": \"2026-02-09T07:46:59.202693Z\", \"maximum_completion_at\": \"2026-02-09\"}','2026-02-09 14:45:41','2026-02-09 14:47:20'),('01kh0p8yem518242rccnd7hayk','01kh0nc60s28y4862vsngd76mz','offering','offering',1,'assigned',NULL,NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-02-09\", \"maximum_completion_at\": \"2026-02-09\"}','2026-02-09 14:53:49','2026-02-09 14:53:49'),('01kh0pedt2v0bjz6mr901m92xv','01kh0nc60s28y4862vsngd76mz','mcu','mcu',1,'request_rescheduled',NULL,NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-02-09\", \"rescheduled_at\": \"2026-02-14\", \"reschedule_notes\": \"Kerja\", \"maximum_completion_at\": \"2026-02-09\"}','2026-02-09 14:56:49','2026-02-09 14:58:02'),('01kjc8y46wq2nr59wkxen0ney4','01keczk3mpyfznyhm2j0reg9g3','test','tes',1,'assigned',NULL,NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-02-26\", \"maximum_completion_at\": \"2026-02-26\"}','2026-02-26 13:07:18','2026-02-26 13:07:18'),('01kjc92hhxf2mhq2h54137w9fv','01keczk3mpyfznyhm2j0reg9g3','interview','intv',1,'failed',NULL,NULL,NULL,'[]','bjbkjhk','{\"scheduled_at\": null, \"maximum_completion_at\": null}','2026-02-26 13:09:43','2026-02-26 13:10:06'),('01kjcazns9fn7pvnrj587fwd3q','01kh0n5pftncfsjhcn53a8mqw2','interview','wawancara hr',1,'completed',NULL,NULL,NULL,'[\"recruitment-process-attachments/01KJCAZNS19N5578SDHRR2RG75.png\"]','Lolos','{\"scheduled_at\": \"2026-02-26\", \"maximum_completion_at\": \"2026-02-26\"}','2026-02-26 13:43:06','2026-02-26 13:44:02'),('01kjcb3521tss2n3kb0c9srdgs','01kh0n5pftncfsjhcn53a8mqw2','offering','offering',1,'accepted',NULL,NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-02-27\", \"submitted_at\": \"2026-02-26T06:46:55.663019Z\", \"maximum_completion_at\": \"2026-02-27\", \"offer_acceptance_notes\": null}','2026-02-26 13:45:00','2026-02-26 13:46:55'),('01kkt6nqz1r42kcg8prvmtr6bd','01kck05pd5d776q22cgeecc1vz','test','Tes Psikotes dan Teknis',1,'assigned','https://google.com',NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-03-16\", \"maximum_completion_at\": \"2026-03-17\"}','2026-03-16 09:12:50','2026-03-16 09:12:50'),('01kkws2bf4rxqng9cm3ns3eg7r','01kh0n5pftncfsjhcn53a8mqw2','interview','wawancara hr',1,'assigned',NULL,NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-03-17\", \"maximum_completion_at\": \"2026-03-17\"}','2026-03-17 09:12:47','2026-03-17 09:12:47'),('01knp10j6fbp7ymc0qqn5d2kep','01knkdeqqsnztdct1z5w69tvp4','interview','Interview HR',1,'completed',NULL,NULL,'Waktu : 10.00 WITA\nTempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru)','[]',NULL,'{\"scheduled_at\": \"2026-04-09\", \"submitted_at\": \"2026-04-08T09:22:16.367775Z\", \"maximum_completion_at\": \"2026-04-09\"}','2026-04-08 14:48:19','2026-04-14 09:30:23'),('01knp11xg8zgr260gbwxvb905g','01knkeb965azeassb48fddmj73','interview','Interview HR',1,'assigned',NULL,NULL,'Waktu : 10.00 WITA\nTempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru)','[]',NULL,'{\"scheduled_at\": \"2026-04-09\", \"maximum_completion_at\": \"2026-04-09\"}','2026-04-08 14:49:04','2026-04-08 14:49:04'),('01knp12wwywnvtzvgnbydz15x1','01knmdtrc2pkyba7w4xkk9fbdt','interview','Interview HR',1,'assigned',NULL,NULL,'Waktu : 10.00 WITA\nTempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru)','[]',NULL,'{\"scheduled_at\": \"2026-04-09\", \"maximum_completion_at\": \"2026-04-09\"}','2026-04-08 14:49:36','2026-04-08 14:49:36'),('01knp13pvsp34xnme5fwge7eas','01knkcy7thw55f3g62jgs5w83e','interview','Interview HR',1,'failed',NULL,NULL,'Waktu : 10.00 WITA\nTempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru)','[]',NULL,'{\"scheduled_at\": \"2026-04-09\", \"maximum_completion_at\": \"2026-04-09\"}','2026-04-08 14:50:02','2026-04-14 09:33:27'),('01knxfjhz3ymb38yf9cpd13172','01knkeb965azeassb48fddmj73','interview','Interview HR',1,'submitted',NULL,NULL,'Waktu : 10.00 WITA Tempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru). Harap konfirmasi satpam dengan menunjukkan email undangan interview.','[]',NULL,'{\"scheduled_at\": \"2026-04-13\", \"submitted_at\": \"2026-04-13T02:35:10.124646Z\", \"maximum_completion_at\": \"2026-04-12\"}','2026-04-11 12:17:30','2026-04-13 09:35:10'),('01knxfmav47ahyd4jh7kjyajrr','01kntm43g8evz2c2yrmpqxn4ad','interview','Interview HR',1,'completed',NULL,NULL,'Waktu : 10.00 WITA Tempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru). Harap konfirmasi satpam dengan menunjukkan email undangan interview.','[]',NULL,'{\"scheduled_at\": \"2026-04-13\", \"maximum_completion_at\": \"2026-04-12\"}','2026-04-11 12:18:28','2026-04-14 09:32:00'),('01kp4x928eac6e9100qmjbavac','01knkdeqqsnztdct1z5w69tvp4','interview','Interview User 1',1,'submitted',NULL,NULL,'Waktu : 10.00 WITA Tempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru)','[]',NULL,'{\"scheduled_at\": \"2026-04-15\", \"submitted_at\": \"2026-04-14T02:45:20.139508Z\", \"maximum_completion_at\": \"2026-04-14\"}','2026-04-14 09:31:40','2026-04-14 09:45:20'),('01kp4xagxvvh1efarxmds40ecs','01kntm43g8evz2c2yrmpqxn4ad','interview','Interview User 1',1,'assigned',NULL,NULL,'Waktu : 10.00 WITA Tempat : PT Syifa Global Group / RSU Syifa Medika Banjarbaru (Jl R.O Ulin No. 93, Banjarbaru)','[]',NULL,'{\"scheduled_at\": \"2026-04-15\", \"maximum_completion_at\": \"2026-04-14\"}','2026-04-14 09:32:28','2026-04-14 09:32:28'),('01kqrgsjh5vwdhnmhmcww5mndm','01kqrgbf5nhnbd9ca8ahpvq3pt','signing','TTD Kontrak & Onboarding',1,'accepted',NULL,NULL,NULL,'[]',NULL,'{\"scheduled_at\": \"2026-05-06\", \"submitted_at\": \"2026-05-04T05:35:47.778692Z\", \"maximum_completion_at\": \"2026-05-05\"}','2026-05-04 10:34:00','2026-05-04 12:35:47');
/*!40000 ALTER TABLE `recruitment_processes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidate_documents`
--

DROP TABLE IF EXISTS `candidate_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candidate_documents` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `doc_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_label` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candidate_documents_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candidate_documents_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidate_documents`
--

LOCK TABLES `candidate_documents` WRITE;
/*!40000 ALTER TABLE `candidate_documents` DISABLE KEYS */;
INSERT INTO `candidate_documents` VALUES ('01kcjx9e25jb1hpd27zg9sa55k','01kcjtkc12q4cabb9z5r423eaq','ktp','KTP','candidate-documents/ktp/01kcjtkc12q4cabb9z5r423eaq/01KCJX9DM4YW3K9M3CASJ4QCNQ.pdf','2025-12-16 13:24:16','2025-12-16 13:24:16'),('01kcjx9e5jak7hrpkdhatjkc5p','01kcjtkc12q4cabb9z5r423eaq','kk','KK','candidate-documents/kk/01kcjtkc12q4cabb9z5r423eaq/01KCJX9DM4YW3K9M3CASJ4QCNR.pdf','2025-12-16 13:24:16','2025-12-16 13:24:16'),('01kcjx9ebf0s37ye7r9dgv1yby','01kcjtkc12q4cabb9z5r423eaq','ijazah','Ijazah','candidate-documents/ijazah/01kcjtkc12q4cabb9z5r423eaq/01KCJX9DM5N5AH9BK1AD9FJ3KT.pdf','2025-12-16 13:24:16','2025-12-16 13:24:16'),('01keczhhay2d0jd4p6fsr4mkdd','01kecynm59a9cxth8yb7h94n7q','ktp','KTP','candidate-documents/ktp/01kecynm59a9cxth8yb7h94n7q/01KECZHGSFFT2JQENQBM42N60Q.png','2026-01-08 02:39:36','2026-01-08 02:39:36'),('01keczhhfydws9aes3vxq0skkr','01kecynm59a9cxth8yb7h94n7q','kk','KK','candidate-documents/kk/01kecynm59a9cxth8yb7h94n7q/01KECZHGSJ7MXNCHVQ7B1R9RM6.png','2026-01-08 02:39:36','2026-01-08 02:39:36'),('01keczhhp40r7m9tj8ygy7cf7v','01kecynm59a9cxth8yb7h94n7q','ijazah','Ijazah','candidate-documents/ijazah/01kecynm59a9cxth8yb7h94n7q/01KECZHGSN5XBJ9AWYXN46N88R.png','2026-01-08 02:39:36','2026-01-08 02:39:36'),('01kexrd18x483gayaxe9ced9xa','01kexqgq1qvgp2rq2p693qyfhc','ktp','KTP','candidate-documents/ktp/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD14RFHZAVZ3CBT72503V.jpeg','2026-01-14 15:01:53','2026-01-14 15:01:53'),('01kexrd1jyh6xvfwtjvpp17dht','01kexqgq1qvgp2rq2p693qyfhc','kk','KK','candidate-documents/kk/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD14VN36XCCKQZR9YWG0K.jpeg','2026-01-14 15:01:54','2026-01-14 15:01:54'),('01kexrd1tm3byfxj215a1b7w44','01kexqgq1qvgp2rq2p693qyfhc','ijazah','Ijazah','candidate-documents/ijazah/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD14X7EBK5FG93P4QDN9R.pdf','2026-01-14 15:01:54','2026-01-14 15:01:54'),('01kexrd20yh53ezwpnqcf9btfa','01kexqgq1qvgp2rq2p693qyfhc','additional','STR Aktif','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD14ZTRP98S71WJWSWN8E.pdf','2026-01-14 15:01:54','2026-01-14 15:01:54'),('01kexrd24c8ckcw7csmnkn2hhz','01kexqgq1qvgp2rq2p693qyfhc','additional','CV','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD150GC50XW4C1G4SFK6Y.pdf','2026-01-14 15:01:54','2026-01-14 15:01:54'),('01kexrd280wxww0kvcf5jvb5bx','01kexqgq1qvgp2rq2p693qyfhc','additional','SK Magang','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD151JH3QTF5F2F8YK7H4.pdf','2026-01-14 15:01:54','2026-01-14 15:01:54'),('01kexrd2fmmk7jp6ebk6knr3w1','01kexqgq1qvgp2rq2p693qyfhc','additional','SKL','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD15250V4RZK8KWH656RA.pdf','2026-01-14 15:01:55','2026-01-14 15:01:55'),('01kexrd2kms60qjvk6fn7z8nb2','01kexqgq1qvgp2rq2p693qyfhc','additional','Sertifikat UKOMNAS','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD153RZTPS8W3Q7J98YMF.pdf','2026-01-14 15:01:55','2026-01-14 15:01:55'),('01kexrd2qpb543p1qpgsee4g9g','01kexqgq1qvgp2rq2p693qyfhc','additional','Sumpah Profesi','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD153RZTPS8W3Q7J98YMG.pdf','2026-01-14 15:01:55','2026-01-14 15:01:55'),('01kexrd2v9cbyr3erw176t9qx0','01kexqgq1qvgp2rq2p693qyfhc','additional','Transkrip Nilai','candidate-documents/additional/01kexqgq1qvgp2rq2p693qyfhc/01KEXRD15448E7R7CZK497RK1X.pdf','2026-01-14 15:01:55','2026-01-14 15:01:55'),('01kf9wmavfncms4xzm5t1f5ga5','01kf9v7kznv9wcmc1adpad8afg','ktp','KTP','candidate-documents/ktp/01kf9v7kznv9wcmc1adpad8afg/01KF9WM7XFG0HTTZQM841EDBA5.pdf','2026-01-19 08:06:40','2026-01-19 08:06:40'),('01kf9wmb19r4r3x73tmmatemem','01kf9v7kznv9wcmc1adpad8afg','kk','KK','candidate-documents/kk/01kf9v7kznv9wcmc1adpad8afg/01KF9WM7XJ8WKSMJCCVS3ZMQC1.pdf','2026-01-19 08:06:40','2026-01-19 08:06:40'),('01kf9wmb39qgnfkx1xsx71s1xf','01kf9v7kznv9wcmc1adpad8afg','bpjstk','BPJS Ketenagakerjaan','candidate-documents/bpjstk/01kf9v7kznv9wcmc1adpad8afg/01KF9WM7XM80EPSYM8W30DB61H.jpg','2026-01-19 08:06:40','2026-01-19 08:06:40'),('01kf9wmb4n5xpgha4xpcbyzxrp','01kf9v7kznv9wcmc1adpad8afg','ijazah','Ijazah','candidate-documents/ijazah/01kf9v7kznv9wcmc1adpad8afg/01KF9WM7XQ9AB9EY8FM8XMSMPP.pdf','2026-01-19 08:06:40','2026-01-19 08:06:40'),('01kf9wmb612snez7gy5c3d2wbh','01kf9v7kznv9wcmc1adpad8afg','additional','CV','candidate-documents/additional/01kf9v7kznv9wcmc1adpad8afg/01KF9WM7XTZEGD4V2MHGHT3RQP.pdf','2026-01-19 08:06:40','2026-01-19 08:09:49'),('01kf9wmb7xajhyv348dhr43dz5','01kf9v7kznv9wcmc1adpad8afg','additional','Sertifikat Pelatihan','candidate-documents/additional/01kf9v7kznv9wcmc1adpad8afg/01KF9WM7XWFGY2TTE6JV8VM2HW.jpg','2026-01-19 08:06:41','2026-01-19 08:09:49'),('01kg70s03ec42bj31ak0zaa0bp','01kg6zxyczpp6j993s1gpz44x8','ktp','KTP','candidate-documents/ktp/01kg6zxyczpp6j993s1gpz44x8/01KG70RYKRY2F8WZ9N0M3T0E80.jpg','2026-01-30 15:37:06','2026-01-30 15:37:06'),('01kg70s094xqp2gvcr5pbnqzzj','01kg6zxyczpp6j993s1gpz44x8','kk','KK','candidate-documents/kk/01kg6zxyczpp6j993s1gpz44x8/01KG70RYKTPQQZYB5JD4V7DWYA.jpg','2026-01-30 15:37:06','2026-01-30 15:37:06'),('01kg70s11qzsg0npdjvqb512a6','01kg6zxyczpp6j993s1gpz44x8','ijazah','Ijazah','candidate-documents/ijazah/01kg6zxyczpp6j993s1gpz44x8/01KG70RYKWVV8DDS8DMP4H043A.jpg','2026-01-30 15:37:07','2026-01-30 15:37:07'),('01knkb7mr4gymcg077zj01xh9r','01knk8bdakasyvbjjrab624kkn','ktp','KTP','candidate-documents/ktp/01knk8bdakasyvbjjrab624kkn/01KNKB7MJDF8GYSYNHZ9WX7VGJ.jpg','2026-04-07 13:49:14','2026-04-07 13:49:14'),('01knkb7mvm42x2mj521hpc1092','01knk8bdakasyvbjjrab624kkn','kk','KK','candidate-documents/kk/01knk8bdakasyvbjjrab624kkn/01KNKB7MJJA5YYF3JW6HZSB2EQ.jpg','2026-04-07 13:49:14','2026-04-07 13:49:14'),('01knkb7mzxphz3bgqbxtb7wvck','01knk8bdakasyvbjjrab624kkn','ijazah','Ijazah','candidate-documents/ijazah/01knk8bdakasyvbjjrab624kkn/01KNKB7MJM6T3K6N6AKB88AG20.jpg','2026-04-07 13:49:14','2026-04-07 13:49:14'),('01kntjmydt6xhdsazms7wgcnvf','01knrsepq4jj6jq9d5461p78yd','ktp','KTP','candidate-documents/ktp/01knrsepq4jj6jq9d5461p78yd/01KNTJMY67Z1V0MY2R1R5E6EC3.jpg','2026-04-10 09:13:31','2026-04-10 09:13:31'),('01kntjmyhb226y9zsfrr4g118t','01knrsepq4jj6jq9d5461p78yd','kk','KK','candidate-documents/kk/01knrsepq4jj6jq9d5461p78yd/01KNTJMY6BCHC69K63592DDQAD.jpg','2026-04-10 09:13:31','2026-04-10 09:13:31'),('01kntjmyn9g1dtqmm94nrtgbjm','01knrsepq4jj6jq9d5461p78yd','ijazah','Ijazah','candidate-documents/ijazah/01knrsepq4jj6jq9d5461p78yd/01KNTJMY6EQY8C1B3FZZD8SYM6.jpg','2026-04-10 09:13:31','2026-04-10 09:13:31'),('01kntjmys3cqyx0d9q1mxmvttv','01knrsepq4jj6jq9d5461p78yd','additional','CV','candidate-documents/additional/01knrsepq4jj6jq9d5461p78yd/01KNTJMY6FGXC19Q5TM9ZXKRMH.pdf','2026-04-10 09:13:31','2026-04-10 09:13:31'),('01kntjmyw32t4fwv3d5ba2dsh4','01knrsepq4jj6jq9d5461p78yd','additional','Sertifikat Kursus RAB','candidate-documents/additional/01knrsepq4jj6jq9d5461p78yd/01KNTJMY6GN116FWW8MSZV4CVQ.pdf','2026-04-10 09:13:31','2026-04-10 09:13:31'),('01kntjmz1nczhsee3v4wg9zh07','01knrsepq4jj6jq9d5461p78yd','additional','SKCK','candidate-documents/additional/01knrsepq4jj6jq9d5461p78yd/01KNTJMY6H6Q76SH0DYYXRD99X.jpg','2026-04-10 09:13:31','2026-04-10 09:13:31'),('01kq47sdca21rxd7abvdbfsm3b','01kq447e2q54j8vm9nwqaq7884','ktp','KTP','candidate-documents/ktp/01kq447e2q54j8vm9nwqaq7884/01KQ47SDBXPZZY9ADXZ5Z84YZA.jpg','2026-04-26 13:31:49','2026-04-26 13:31:49'),('01kqrgqj7yjmq70t7phkpkfnt9','01kqrf7tj1dfz4c8ptxa03z30x','ktp','KTP','candidate-documents/ktp/01kqrf7tj1dfz4c8ptxa03z30x/01KQRGQJ37Y6CJ6Y9BWKA91A3J.jpg','2026-05-04 10:32:54','2026-05-04 10:32:54'),('01kqrgqjp6rjf0a8rag06bwhhp','01kqrf7tj1dfz4c8ptxa03z30x','kk','KK','candidate-documents/kk/01kqrf7tj1dfz4c8ptxa03z30x/01KQRGQJ38FKQBCSM8NXCCKSRV.pdf','2026-05-04 10:32:55','2026-05-04 10:32:55'),('01kqrgqjt65vr8vj530bhvmdmh','01kqrf7tj1dfz4c8ptxa03z30x','ijazah','Ijazah','candidate-documents/ijazah/01kqrf7tj1dfz4c8ptxa03z30x/01KQRGQJ3DSX7QS3HWNHSP78J5.pdf','2026-05-04 10:32:55','2026-05-04 10:32:55'),('01kqvrqmtapwwaseym2t5s2n1e','01kq447e2q54j8vm9nwqaq7884','kk','KK','candidate-documents/kk/01kq447e2q54j8vm9nwqaq7884/01KQVRQMSSMGWPRV1G8WHN8VF1.jpg','2026-05-05 17:50:29','2026-05-05 17:50:29');
/*!40000 ALTER TABLE `candidate_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_family_members`
--

DROP TABLE IF EXISTS `candt_family_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_family_members` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `relation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `education` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_family_members_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_family_members_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_family_members`
--

LOCK TABLES `candt_family_members` WRITE;
/*!40000 ALTER TABLE `candt_family_members` DISABLE KEYS */;
INSERT INTO `candt_family_members` VALUES ('01kntm43ge7ky8qbztfe3sxmyh','01knrsepq4jj6jq9d5461p78yd','Istri','Perempuan','Tiara Nauli Mustika Eddra Naustion','1997-06-25','Sarjana S1','Karyawan Swasta','0896 5755 1412','2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kqrgbf5sdc6mxc1m8qytxn9f','01kqrf7tj1dfz4c8ptxa03z30x','Ayah','Laki - Laki','Ahmad Sofwan','1975-03-25','S1','PNS','081349940677','2026-05-04 10:26:18','2026-05-04 10:26:18'),('01kqrgbf5ttzpsxfgpdvfrxfva','01kqrf7tj1dfz4c8ptxa03z30x','Ibu','Perempuan','Rahmaniah','1979-04-18','SMA','-','089505775559','2026-05-04 10:26:18','2026-05-04 10:26:18'),('01kr2nakm3hkx9rq3evdst8a4g','01kr2mvam517cb5yfh609wd8ja','Istri','Perempuan','Riza Ananda Magfirah','1992-05-08','S1 - Ilmu Komputer','Ibu Rumat Tangga',NULL,'2026-05-08 10:05:37','2026-05-08 10:05:37');
/*!40000 ALTER TABLE `candt_family_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_educations`
--

DROP TABLE IF EXISTS `candt_educations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_educations` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `school_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `major` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gpa` int DEFAULT NULL,
  `start_year` year DEFAULT NULL,
  `end_year` year DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_educations_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_educations_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_educations`
--

LOCK TABLES `candt_educations` WRITE;
/*!40000 ALTER TABLE `candt_educations` DISABLE KEYS */;
INSERT INTO `candt_educations` VALUES ('01kntm43gf619gs0rbx9ysskn0','01knrsepq4jj6jq9d5461p78yd','Sarjana','Universitas Palangka Raya','Arsitektur',3,2015,2021,'2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kqrgbf5v2epfhk8x0jbz9pk5','01kqrf7tj1dfz4c8ptxa03z30x','SMK','SMK NEGERI 1 BANJARBARU','Desain Komunikasi Visual',NULL,2018,2021,'2026-05-04 10:26:18','2026-05-04 10:26:18'),('01kqrgbf5w1x92h5mzs8zrb05e','01kqrf7tj1dfz4c8ptxa03z30x','Sarjana','Universitas Islam Kalimantan Muhammad Arsyad Al - Banjari','Teknik Informatika',4,2021,2025,'2026-05-04 10:26:18','2026-05-04 10:26:18'),('01kr2nakm5qc19vv4hc96gc0vb','01kr2mvam517cb5yfh609wd8ja','Sarjana','Universitas Lambung Mangkurat','Ilmu Komputer',3,2011,2017,'2026-05-08 10:05:37','2026-05-08 10:05:37');
/*!40000 ALTER TABLE `candt_educations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_trainings`
--

DROP TABLE IF EXISTS `candt_trainings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_trainings` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_trainings_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_trainings_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_trainings`
--

LOCK TABLES `candt_trainings` WRITE;
/*!40000 ALTER TABLE `candt_trainings` DISABLE KEYS */;
/*!40000 ALTER TABLE `candt_trainings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_organizations`
--

DROP TABLE IF EXISTS `candt_organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_organizations` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `organization_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `field` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_organizations_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_organizations_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_organizations`
--

LOCK TABLES `candt_organizations` WRITE;
/*!40000 ALTER TABLE `candt_organizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `candt_organizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_work_experiences`
--

DROP TABLE IF EXISTS `candt_work_experiences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_work_experiences` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `salary` decimal(50,2) DEFAULT NULL,
  `resign_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_current` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_work_experiences_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_work_experiences_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_work_experiences`
--

LOCK TABLES `candt_work_experiences` WRITE;
/*!40000 ALTER TABLE `candt_work_experiences` DISABLE KEYS */;
INSERT INTO `candt_work_experiences` VALUES ('01kntm43ghwqytqe3kzn0k83nm','01knrsepq4jj6jq9d5461p78yd','Dearti Desain Persada','Kontraktor Swasta','Jl. Golf','Junior Arsitek','2021-03-01','2026-03-31',6000000.00,'Digantung Atasan',0,'2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kntm43gjq68hvxc6p8rpnvc0','01knrsepq4jj6jq9d5461p78yd','Huma studio Arsitek','Studio Arsitek','Jl. Jend. Sudirman Kota Palangka Raya','Drafter','2019-11-30','2021-02-28',NULL,NULL,0,'2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kntm43gkd7bgtfzwwx1vyk00','01knrsepq4jj6jq9d5461p78yd','ABATA Arsitek','Studio Arsitek','Jl. Sapan Kota Palangka Raya','Drafter','2019-03-01','2020-01-01',NULL,NULL,0,'2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kntm43gm1s1xq1x0pymwe8md','01knrsepq4jj6jq9d5461p78yd','PT. Cipta Bina Katuluhni','Konsultan Proyek','Jl. Batu Suli kota Palangka Raya','Drafter','2013-08-01','2014-04-01',NULL,NULL,0,'2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kr2nakm6g3935zxdadpv0fnj','01kr2mvam517cb5yfh609wd8ja','CV Arafah Komputer','IT','Jl. A. Yani, Guntung Payung, Kec. Landasan Ulin, Kota Banjar Baru, Kalimantan Selatan 70714','Teknisi Komputer',NULL,'2015-01-10',1000000.00,'Karena mau fokus kuliah',0,'2026-05-08 10:05:37','2026-05-08 10:05:37'),('01kr2nakm7hbf7tmr8rdr2ccye','01kr2mvam517cb5yfh609wd8ja','Universitas Lambung Mangkurat','IT','Loktabat Sel., Kec. Banjarbaru Selatan, Kota Banjar Baru, Kalimantan Selatan 70714','Network & System administrator, programmer','2016-01-01','2023-10-31',2700000.00,'',0,'2026-05-08 10:05:37','2026-05-08 10:05:37'),('01kr2nakm863v93kzg799tq1bd','01kr2mvam517cb5yfh609wd8ja','PT. Ruang Algo Multimatics','IT','JL. RP. Soeparto no 6','Network & System administrator, programmer','2023-11-01',NULL,6000000.00,NULL,1,'2026-05-08 10:05:37','2026-05-08 10:05:37');
/*!40000 ALTER TABLE `candt_work_experiences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_references`
--

DROP TABLE IF EXISTS `candt_references`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_references` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `job` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_references_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_references_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_references`
--

LOCK TABLES `candt_references` WRITE;
/*!40000 ALTER TABLE `candt_references` DISABLE KEYS */;
INSERT INTO `candt_references` VALUES ('01kntm43gn6wtbv8vgnzpx6tv7','01knrsepq4jj6jq9d5461p78yd','Tiara Nauli Mustika E. N.','Jl. Sukamaju Komp. Citra mandiri residence','Karyawan Swasta','0896 5755 1412','Istri','2026-04-10 09:39:16','2026-04-10 09:39:16');
/*!40000 ALTER TABLE `candt_references` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_additional_infos`
--

DROP TABLE IF EXISTS `candt_additional_infos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_additional_infos` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allow_reference_check` tinyint(1) DEFAULT NULL,
  `has_disease` tinyint(1) DEFAULT NULL,
  `disease_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hobbies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `languages` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_facilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `expected_salary_facilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `available_start_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_additional_infos_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_additional_infos_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_additional_infos`
--

LOCK TABLES `candt_additional_infos` WRITE;
/*!40000 ALTER TABLE `candt_additional_infos` DISABLE KEYS */;
INSERT INTO `candt_additional_infos` VALUES ('01kntm43gs7gtmr6d1mcbppjbb','01knrsepq4jj6jq9d5461p78yd',1,1,'Covid-19','Menonton, Mencari Referensi tentang desain arsitektur terkini','Inggris (Basic)','Tidak Ada','Saya berharap mendapatkan gaji yang sesuai dengan tanggung jawab pekerjaan dan standar di perusahaan ini. Untuk fasilitas, saya mengharapkan benefit umum seperti BPJS, tunjangan, serta lingkungan kerja yang mendukung. Namun saya tetap terbuka untuk menyesuaikan dengan kebijakan perusahaan.','2026-04-13','2026-04-10 09:39:16','2026-04-10 09:39:16'),('01kqgp5mkw5dga11c47tj94y1y','01kqgp0497stzpwpy683nqm0ce',0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-01 09:34:03','2026-05-01 09:34:03'),('01kqrgbf5zxtb2b03wacw7nv7x','01kqrf7tj1dfz4c8ptxa03z30x',0,1,'-','Untuk mengisi waktu luang pada saat ini adalah dengan membuat atau mengembangkan aplikasi berbasis web','-','-','Gaji yang saya harapkan adalah Rp.5.000.000, namun dapat didiskusikan kembali. Fasilitas yang saya harapkan adalah perengkat memadai, BPJS, dan pengembangan skill','2026-05-06','2026-05-04 10:26:18','2026-05-04 10:26:18'),('01kr2nakmaes9h3z9pz1kv8b3e','01kr2mvam517cb5yfh609wd8ja',0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-05-08 10:05:37','2026-05-08 10:05:37');
/*!40000 ALTER TABLE `candt_additional_infos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candt_self_assessments`
--

DROP TABLE IF EXISTS `candt_self_assessments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `candt_self_assessments` (
  `id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `candidate_id` char(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `strengths` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `weaknesses` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `satisfaction_level` enum('very_unsatisfied','unsatisfied','less_satisfied','satisfied','very_satisfied') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `candt_self_assessments_candidate_id_foreign` (`candidate_id`),
  CONSTRAINT `candt_self_assessments_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candt_self_assessments`
--

LOCK TABLES `candt_self_assessments` WRITE;
/*!40000 ALTER TABLE `candt_self_assessments` DISABLE KEYS */;
INSERT INTO `candt_self_assessments` VALUES ('01kqrgbf610pf9nvw1ay5xexa5','01kqrf7tj1dfz4c8ptxa03z30x','Ingin mempelajari dan menguasai hal baru, mengerjakan suatu hal dengan lebih cepat, cepat memahami sesuatu yang baru, sangat fokus untuk mengerjakan sesuatu, disiplin dan tanggung jawab, menyukai pemecahan masalah','Karena kebiasaan mengerjakan sesuatu dengan cepat, terkadang ada hal yang tertinggal atau dilewatkan, kurang terbiasa multitasking','satisfied','Saya merasa dapat menjadi lebih baik lagi dari apa yang saya kerjakan maupun kebiasaan yang saya lakukan.','2026-05-04 10:26:18','2026-05-04 10:26:18');
/*!40000 ALTER TABLE `candt_self_assessments` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-29 12:27:17
