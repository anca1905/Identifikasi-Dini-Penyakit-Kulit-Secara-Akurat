CREATE DATABASE IF NOT EXISTS isdila_kulit;
USE isdila_kulit;

-- Tabel Admin
CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  PRIMARY KEY (`id_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin` (`username`, `password`, `nama_lengkap`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator Utama'); -- password: password

-- Tabel Penyakit
CREATE TABLE `penyakit` (
  `kode_penyakit` varchar(10) NOT NULL,
  `nama_penyakit` varchar(100) NOT NULL,
  `solusi` text NOT NULL,
  `gambar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`kode_penyakit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `penyakit` (`kode_penyakit`, `nama_penyakit`, `solusi`) VALUES
('P01', 'Kudis (Scabies)', 'Gunakan krim permethrin 5%, jaga kebersihan pakaian dan tempat tidur, serta hindari kontak langsung dengan penderita lain.'),
('P02', 'Kurap (Tinea Corporis)', 'Gunakan salep antijamur seperti miconazole atau ketoconazole, jaga area kulit tetap kering dan bersih.'),
('P03', 'Panu (Pityriasis Versicolor)', 'Gunakan sampo atau losion antijamur (mengandung selenium sulfida atau ketoconazole), hindari keringat berlebih.'),
('P04', 'Kusta (Lepra)', 'Segera konsultasikan ke fasilitas kesehatan (Puskesmas/Rumah Sakit) untuk mendapatkan terapi MDT (Multi Drug Therapy).'),
('P05', 'Cacar Air (Varicella)', 'Istirahat yang cukup, gunakan losion kalamin untuk mengurangi gatal, konsumsi obat penurun panas jika demam, dan jangan menggaruk ruam.'),
('P06', 'Campak (Morbili)', 'Konsumsi vitamin A, perbanyak asupan cairan, istirahat yang cukup, dan konsumsi paracetamol jika demam tinggi.');

-- Tabel Gejala
CREATE TABLE `gejala` (
  `kode_gejala` varchar(10) NOT NULL,
  `nama_gejala` varchar(255) NOT NULL,
  PRIMARY KEY (`kode_gejala`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `gejala` (`kode_gejala`, `nama_gejala`) VALUES
('G01', 'Gatal yang hebat di malam hari'),
('G02', 'Terdapat bercak kemerahan atau ruam pada kulit'),
('G03', 'Timbul bintik-bintik berisi cairan bening atau nanah'),
('G04', 'Area kulit bersisik dengan pinggiran yang lebih merah (berbentuk cincin)'),
('G05', 'Bercak kulit berwarna putih, coklat, atau kemerahan yang gatal saat berkeringat'),
('G06', 'Terdapat mati rasa pada daerah bercak kulit'),
('G07', 'Kelemahan pada otot tangan atau kaki'),
('G08', 'Demam tinggi'),
('G09', 'Lemas dan nafsu makan menurun'),
('G10', 'Mata merah dan berair (konjungtivitis)'),
('G11', 'Batuk dan pilek'),
('G12', 'Muncul ruam mulai dari wajah lalu menyebar ke seluruh tubuh');

-- Tabel Basis Pengetahuan (Rule Dempster-Shafer)
CREATE TABLE `basis_pengetahuan` (
  `id_rule` int(11) NOT NULL AUTO_INCREMENT,
  `kode_penyakit` varchar(10) NOT NULL,
  `kode_gejala` varchar(10) NOT NULL,
  `nilai_densitas` float NOT NULL, -- Bobot pakar / belief
  PRIMARY KEY (`id_rule`),
  KEY `kode_penyakit` (`kode_penyakit`),
  KEY `kode_gejala` (`kode_gejala`),
  CONSTRAINT `fk_rule_penyakit` FOREIGN KEY (`kode_penyakit`) REFERENCES `penyakit` (`kode_penyakit`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rule_gejala` FOREIGN KEY (`kode_gejala`) REFERENCES `gejala` (`kode_gejala`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `basis_pengetahuan` (`kode_penyakit`, `kode_gejala`, `nilai_densitas`) VALUES
('P01', 'G01', 0.8),
('P01', 'G02', 0.6),
('P01', 'G03', 0.7),
('P02', 'G02', 0.5),
('P02', 'G04', 0.9),
('P03', 'G05', 0.85),
('P04', 'G06', 0.9),
('P04', 'G07', 0.8),
('P04', 'G02', 0.4),
('P05', 'G08', 0.6),
('P05', 'G09', 0.5),
('P05', 'G03', 0.9),
('P06', 'G08', 0.8),
('P06', 'G10', 0.7),
('P06', 'G11', 0.8),
('P06', 'G12', 0.9);

-- Tabel Riwayat Konsultasi
CREATE TABLE `riwayat_konsultasi` (
  `id_riwayat` int(11) NOT NULL AUTO_INCREMENT,
  `nama_pasien` varchar(100) NOT NULL,
  `umur` int(11) NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') NOT NULL,
  `alamat` varchar(255) NOT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `gejala_terpilih` text NOT NULL, -- JSON format untuk kode gejala
  `kode_penyakit` varchar(10) NOT NULL,
  `nilai_belief` float NOT NULL,
  PRIMARY KEY (`id_riwayat`),
  KEY `kode_penyakit_riwayat` (`kode_penyakit`),
  CONSTRAINT `fk_riwayat_penyakit` FOREIGN KEY (`kode_penyakit`) REFERENCES `penyakit` (`kode_penyakit`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
