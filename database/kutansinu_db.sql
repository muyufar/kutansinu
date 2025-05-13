-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 06, 2025 at 05:02 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kutansinu_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `akun`
--

CREATE TABLE `akun` (
  `id` int(11) NOT NULL,
  `kode_akun` varchar(10) NOT NULL,
  `nama_akun` varchar(100) NOT NULL,
  `kategori` enum('aktiva','pasiva','modal','pendapatan','beban') NOT NULL,
  `sub_kategori` varchar(250) NOT NULL,
  `tipe_akun` enum('debit','kredit') NOT NULL,
  `saldo` decimal(15,2) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_perusahaan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `akun`
--

INSERT INTO `akun` (`id`, `kode_akun`, `nama_akun`, `kategori`, `sub_kategori`, `tipe_akun`, `saldo`, `deskripsi`, `created_at`, `id_perusahaan`) VALUES
(103, '1-10001', 'Kas', 'aktiva', 'Kas & Bank', 'debit', 12431000.00, 'Uang tunai yang dimiliki oleh perusahaan dan siap digunakan untuk transaksi sehari-hari.', '2025-04-23 02:04:45', 1),
(104, '1-10002', 'Rekening Bank', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di rekening bank.', '2025-04-23 02:04:45', 2),
(105, '1-10003', 'Bank Mandiri', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di Bank Mandiri.', '2025-04-23 02:04:45', NULL),
(106, '1-10004', 'Bank Negara Indonesia (BNI)', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di Bank Negara Indonesia.', '2025-04-23 02:04:45', NULL),
(107, '1-10005', 'Bank Rakyat Indonesia (BRI)', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di Bank Rakyat Indonesia.', '2025-04-23 02:04:45', NULL),
(108, '1-10006', 'Bank Tabungan Negara (BTN)', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di Bank Tabungan Negara.', '2025-04-23 02:04:45', NULL),
(109, '1-10007', 'Bank Central Asia (BCA)', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di Bank Central Asia.', '2025-04-23 02:04:45', NULL),
(110, '1-10008', 'GoPay', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di dompet digital GoPay.', '2025-04-23 02:04:45', NULL),
(111, '1-10009', 'OVO', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di dompet digital OVO.', '2025-04-23 02:04:45', NULL),
(112, '1-10010', 'Dana', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di dompet digital Dana.', '2025-04-23 02:04:45', NULL),
(113, '1-10011', 'Link Aja', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di dompet digital Link Aja.', '2025-04-23 02:04:45', NULL),
(114, '1-10012', 'Cashlez', 'aktiva', 'Kas & Bank', 'debit', 0.00, 'Saldo uang perusahaan yang disimpan di layanan pembayaran Cashlez.', '2025-04-23 02:04:45', NULL),
(115, '1-10100', 'Piutang Usaha', 'aktiva', 'Akun Piutang', 'debit', 0.00, 'Jumlah uang yang harus diterima perusahaan dari pelanggan sebagai hasil penjualan kredit.', '2025-04-23 02:04:45', NULL),
(116, '1-10101', 'Piutang Belum Ditagih', 'aktiva', 'Akun Piutang', 'debit', 0.00, 'Jumlah piutang yang belum diselesaikan atau ditagih kepada pelanggan.', '2025-04-23 02:04:45', NULL),
(117, '1-10200', 'Persediaan Barang', 'aktiva', 'Persediaan', 'debit', 0.00, 'Nilai barang dagangan atau bahan baku yang tersedia untuk dijual atau digunakan dalam produksi.', '2025-04-23 02:04:45', NULL),
(118, '1-10300', 'Piutang Lainnya', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Piutang yang tidak termasuk dalam piutang usaha, seperti piutang kepada pihak ketiga.', '2025-04-23 02:04:45', NULL),
(119, '1-10301', 'Piutang Karyawan', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Jumlah uang yang harus dibayar kembali oleh karyawan kepada perusahaan.', '2025-04-23 02:04:45', NULL),
(120, '1-10400', 'Dana Belum Disetor', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Dana yang sudah diterima tetapi belum disetorkan ke pihak terkait.', '2025-04-23 02:04:45', NULL),
(121, '1-10401', 'Aset Lancar Lainnya', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Aset lain yang dapat dikonversi menjadi kas dalam jangka waktu kurang dari satu tahun.', '2025-04-23 02:04:45', NULL),
(122, '1-10402', 'Biaya Dibayar Di Muka', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Biaya yang sudah dibayar tetapi manfaatnya akan diterima di masa mendatang.', '2025-04-23 02:04:45', NULL),
(123, '1-10403', 'Uang Muka', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Uang muka yang diberikan kepada pihak lain untuk keperluan tertentu.', '2025-04-23 02:04:45', NULL),
(124, '1-10500', 'PPN Masukan', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Pajak Pertambahan Nilai yang dapat dikreditkan karena pembelian barang/jasa.', '2025-04-23 02:04:45', NULL),
(125, '1-10501', 'Pajak Penghasilan Dibayar Di Muka - PPh 22', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Pajak penghasilan yang sudah dibayar tetapi dapat dikreditkan nanti.', '2025-04-23 02:04:45', NULL),
(126, '1-10502', 'Pajak Penghasilan Dibayar Di Muka - PPh 23', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Pajak penghasilan atas pendapatan pasif yang sudah dibayar.', '2025-04-23 02:04:45', NULL),
(127, '1-10503', 'Pajak Penghasilan Dibayar Di Muka - PPh 25', 'aktiva', 'Harta Lancar Lainnya', 'debit', 0.00, 'Pajak penghasilan angsuran yang sudah dibayar.', '2025-04-23 02:04:45', NULL),
(128, '1-10700', 'Aktiva Tetap - Tanah', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Nilai tanah yang dimiliki oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(129, '1-10701', 'Aset Tetap - Bangunan', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Nilai bangunan yang dimiliki oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(130, '1-10702', 'Aset Tetap - Pengembangan Bangunan', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Biaya tambahan untuk pengembangan bangunan.', '2025-04-23 02:04:45', NULL),
(131, '1-10703', 'Aset Tetap - Kendaraan', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Nilai kendaraan yang dimiliki oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(132, '1-10704', 'Aset Tetap - Mesin & Peralatan', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Nilai mesin dan peralatan yang digunakan dalam operasional perusahaan.', '2025-04-23 02:04:45', NULL),
(133, '1-10705', 'Aset Tetap - Peralatan Kantor', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Nilai peralatan kantor yang dimiliki oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(134, '1-10706', 'Aset Tetap - Aset Sewaan', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Aset tetap yang disewa oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(135, '1-10707', 'Aset Tidak Berwujud', 'aktiva', 'Harta Tetap', 'debit', 0.00, 'Aset yang tidak memiliki bentuk fisik, seperti hak paten atau merek dagang.', '2025-04-23 02:04:45', NULL),
(136, '1-10751', 'Akumulasi Penyusutan - Bangunan', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi penyusutan nilai bangunan.', '2025-04-23 02:04:45', NULL),
(137, '1-10752', 'Akumulasi Penyusutan - Pengembangan Bangunan', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi penyusutan nilai pengembangan bangunan.', '2025-04-23 02:04:45', NULL),
(138, '1-10753', 'Akumulasi Penyusutan - Kendaraan', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi penyusutan nilai kendaraan.', '2025-04-23 02:04:45', NULL),
(139, '1-10754', 'Akumulasi Penyusutan - Mesin & Peralatan', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi penyusutan nilai mesin dan peralatan.', '2025-04-23 02:04:45', NULL),
(140, '1-10755', 'Akumulasi Penyusutan - Peralatan Kantor', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi penyusutan nilai peralatan kantor.', '2025-04-23 02:04:45', NULL),
(141, '1-10756', 'Akumulasi Penyusutan - Aset Sewaan', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi penyusutan nilai aset sewaan.', '2025-04-23 02:04:45', NULL),
(142, '1-10757', 'Akumulasi Armotisasi', 'aktiva', 'Depresiasi & Armotisasi', 'kredit', 0.00, 'Akumulasi amortisasi nilai aset tidak berwujud.', '2025-04-23 02:04:45', NULL),
(143, '1-10800', 'Investasi', 'aktiva', 'Investasi', 'debit', 0.00, 'Investasi jangka panjang yang dilakukan oleh perusahaan, seperti saham atau obligasi.', '2025-04-23 02:04:45', NULL),
(144, '2-20100', 'Hutang Usaha', 'pasiva', 'Akun Hutang', 'kredit', 0.00, 'Jumlah uang yang harus dibayar kepada pemasok sebagai hasil pembelian barang/jasa secara kredit.', '2025-04-23 02:04:45', NULL),
(145, '2-20101', 'Hutang Belum Ditagih', 'pasiva', 'Akun Hutang', 'debit', 0.00, 'Hutang yang belum diselesaikan atau ditagih kepada perusahaan.', '2025-04-23 02:04:45', NULL),
(146, '2-20200', 'Hutang Lainnya', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Hutang yang tidak termasuk dalam hutang usaha.', '2025-04-23 02:04:45', NULL),
(147, '2-20201', 'Hutang Gaji', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Gaji yang sudah jatuh tempo tetapi belum dibayar kepada karyawan.', '2025-04-23 02:04:45', NULL),
(148, '2-20202', 'Hutang Deviden', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Dividen yang sudah diumumkan tetapi belum dibayarkan kepada pemegang saham.', '2025-04-23 02:04:45', NULL),
(149, '2-20203', 'Pendapatan Diterima Di Muka', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pendapatan yang sudah diterima tetapi belum direalisasi.', '2025-04-23 02:04:45', NULL),
(150, '2-20301', 'Sarana Kantor Terhutang', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Biaya sarana kantor yang belum dibayar.', '2025-04-23 02:04:45', NULL),
(151, '2-20302', 'Bunga Terhutang', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Bunga pinjaman yang belum dibayar.', '2025-04-23 02:04:45', NULL),
(152, '2-20399', 'Biaya Terhutang lainnya', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Biaya lainnya yang belum dibayar.', '2025-04-23 02:04:45', NULL),
(153, '2-20400', 'Hutang Bank', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pinjaman yang diperoleh dari bank.', '2025-04-23 02:04:45', NULL),
(154, '2-20500', 'PPN Keluaran', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pajak Pertambahan Nilai yang harus dibayar kepada negara.', '2025-04-23 02:04:45', NULL),
(155, '2-20501', 'Hutang Pajak - PPh 21', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pajak penghasilan karyawan yang harus dibayar.', '2025-04-23 02:04:45', NULL),
(156, '2-20502', 'Hutang Pajak - PPh 22', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pajak penghasilan impor yang harus dibayar.', '2025-04-23 02:04:45', NULL),
(157, '2-20503', 'Hutang Pajak - PPh 23', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pajak penghasilan atas pendapatan pasif yang harus dibayar.', '2025-04-23 02:04:45', NULL),
(158, '2-20504', 'Hutang Pajak - PPh 29', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pajak penghasilan tambahan yang harus dibayar.', '2025-04-23 02:04:45', NULL),
(159, '2-20599', 'Hutang Pajak Lainnya', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Hutang pajak lainnya yang harus dibayar.', '2025-04-23 02:04:45', NULL),
(160, '2-20600', 'Hutang Dari Pemegang Saham', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Pinjaman yang diperoleh dari pemegang saham.', '2025-04-23 02:04:45', NULL),
(161, '2-20601', 'Kewajiban Lancar Lainnya', 'pasiva', 'Kewajiban Lancar Lainnya', 'kredit', 0.00, 'Kewajiban jangka pendek lainnya.', '2025-04-23 02:04:45', NULL),
(162, '2-20700', 'Kewajiban Manfaat Karyawan', 'pasiva', 'Kewajiban Jangka Panjang', 'kredit', 0.00, 'Kewajiban jangka panjang kepada karyawan, seperti tunjangan pensiun.', '2025-04-23 02:04:45', NULL),
(163, '3-30000', 'Modal Saham', 'modal', 'Modal', 'kredit', 0.00, 'Modal yang diberikan oleh pemegang saham melalui pembelian saham.', '2025-04-23 02:04:45', NULL),
(164, '3-30001', 'Modal Tambahan', 'modal', 'Modal', 'kredit', 0.00, 'Tambahan modal dari pemegang saham selain modal saham.', '2025-04-23 02:04:45', NULL),
(165, '3-30100', 'Laba Ditahan', 'modal', 'Modal', 'kredit', 0.00, 'Laba bersih yang tidak dibagikan sebagai dividen tetapi disimpan untuk keperluan perusahaan.', '2025-04-23 02:04:45', NULL),
(166, '3-30200', 'Deviden', 'modal', 'Modal', 'kredit', 0.00, 'Bagian laba yang dibagikan kepada pemegang saham.', '2025-04-23 02:04:45', NULL),
(167, '3-30300', 'Pendapatan Komprehensif Lainnya', 'modal', 'Modal', 'kredit', 0.00, 'Pendapatan lainnya yang tidak termasuk dalam pendapatan operasional.', '2025-04-23 02:04:45', NULL),
(168, '3-30999', 'Saldo Awal', 'modal', 'Modal', 'kredit', 0.00, 'Saldo awal modal pada periode akuntansi.', '2025-04-23 02:04:45', NULL),
(169, '4-40000', 'Pendapatan', 'pendapatan', 'Pendapatan', 'kredit', 12781000.00, 'Pendapatan utama perusahaan dari penjualan barang/jasa.', '2025-04-23 02:04:45', NULL),
(170, '4-40100', 'Diskon Penjualan', 'pendapatan', 'Pendapatan', 'kredit', 0.00, 'Diskon yang diberikan kepada pelanggan atas penjualan barang/jasa.', '2025-04-23 02:04:45', NULL),
(171, '4-40200', 'Pengembalian Penjualan', 'pendapatan', 'Pendapatan', 'kredit', 0.00, 'Pengembalian barang dari pelanggan yang mengurangi pendapatan karena barang yang telah dijual dikembalikan.', '2025-04-23 02:04:45', NULL),
(172, '5-50000', 'Beban Pokok Pendapatan', 'beban', 'Harga Pokok Penjualan', 'debit', 0.00, 'Biaya langsung yang terkait dengan produksi barang atau jasa yang dijual.', '2025-04-23 02:04:45', NULL),
(173, '5-50100', 'Diskon Pembelian', 'beban', 'Harga Pokok Penjualan', 'debit', 0.00, 'Diskon yang diterima dari pemasok atas pembelian barang atau jasa.', '2025-04-23 02:04:45', NULL),
(174, '5-50200', 'Pengembalian Pembelian', 'beban', 'Harga Pokok Penjualan', 'debit', 0.00, 'Pengembalian barang kepada pemasok yang mengurangi biaya pembelian.', '2025-04-23 02:04:45', NULL),
(175, '5-50300', 'Pengiriman / Pengangkutan', 'beban', 'Harga Pokok Penjualan', 'debit', 0.00, 'Biaya pengiriman barang dari pemasok ke perusahaan atau dari perusahaan ke pelanggan.', '2025-04-23 02:04:45', NULL),
(176, '5-50400', 'Biaya Import', 'beban', 'Harga Pokok Penjualan', 'debit', 0.00, 'Biaya tambahan yang timbul dari impor barang, seperti bea masuk dan pajak impor.', '2025-04-23 02:04:45', NULL),
(177, '5-50500', 'Biaya Produksi', 'beban', 'Harga Pokok Penjualan', 'debit', 0.00, 'Biaya yang dikeluarkan untuk memproduksi barang atau jasa, termasuk bahan baku, tenaga kerja, dan overhead.', '2025-04-23 02:04:45', NULL),
(178, '6-60000', 'Biaya Penjualan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya yang terkait dengan kegiatan penjualan, seperti promosi, distribusi, dan layanan pelanggan.', '2025-04-23 02:04:45', NULL),
(179, '6-60001', 'Iklan & Promosi', 'beban', 'Biaya Operasional', 'debit', -350000.00, 'Biaya yang dikeluarkan untuk iklan dan promosi produk/jasa perusahaan.', '2025-04-23 02:04:45', NULL),
(180, '6-60002', 'Komisi & Fee', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Komisi atau fee yang dibayarkan kepada pihak ketiga atas penjualan atau layanan tertentu.', '2025-04-23 02:04:45', NULL),
(181, '6-60003', 'Bensin - Toll - dan Parkir - Penjualan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya transportasi yang terkait dengan kegiatan penjualan, termasuk bahan bakar, tol, dan parkir.', '2025-04-23 02:04:45', NULL),
(182, '6-60004', 'Perjalanan (Travelling) - Penjualan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya perjalanan yang terkait dengan kegiatan penjualan, seperti kunjungan ke pelanggan.', '2025-04-23 02:04:45', NULL),
(183, '6-60005', 'Komunikasi - Penjualan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya komunikasi yang digunakan untuk mendukung kegiatan penjualan, seperti telepon dan internet.', '2025-04-23 02:04:45', NULL),
(184, '6-60006', 'Pemasaran Lainnya', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya pemasaran lainnya yang tidak termasuk dalam kategori sebelumnya.', '2025-04-23 02:04:45', NULL),
(185, '6-60100', 'Biaya Umum & Administratif', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya operasional umum dan administratif yang mendukung kegiatan perusahaan sehari-hari.', '2025-04-23 02:04:45', NULL),
(186, '6-60101', 'Gaji', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya gaji karyawan yang dibayarkan oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(187, '6-60102', 'Upah', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya upah yang dibayarkan kepada pekerja harian atau kontrak.', '2025-04-23 02:04:45', NULL),
(188, '6-60103', 'Konsumsi & Transport', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya konsumsi dan transportasi yang dikeluarkan untuk keperluan operasional karyawan.', '2025-04-23 02:04:45', NULL),
(189, '6-60104', 'Lembur', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya lembur yang dibayarkan kepada karyawan atas jam kerja tambahan.', '2025-04-23 02:04:45', NULL),
(190, '6-60105', 'Kesehatan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya kesehatan yang diberikan kepada karyawan, seperti asuransi kesehatan atau tunjangan medis.', '2025-04-23 02:04:45', NULL),
(191, '6-60106', 'THR dan Bonus', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya Tunjangan Hari Raya (THR) dan bonus yang diberikan kepada karyawan.', '2025-04-23 02:04:45', NULL),
(192, '6-60107', 'Jamsostek', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya program Jaminan Sosial Tenaga Kerja (Jamsostek) yang dibayarkan oleh perusahaan untuk karyawan.', '2025-04-23 02:04:45', NULL),
(193, '6-60108', 'Insentif', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya insentif tambahan yang diberikan kepada karyawan sebagai bentuk apresiasi atau motivasi.', '2025-04-23 02:04:45', NULL),
(194, '6-60109', 'Pesangon', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Pesangon adalah pembayaran kompensasi kepada karyawan yang mengundurkan diri, diberhentikan, atau pensiun.', '2025-04-23 02:04:45', NULL),
(195, '6-60110', 'Tunjangan Lainnya', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Tunjangan tambahan yang diberikan kepada karyawan di luar gaji pokok, seperti tunjangan transportasi, makan, atau kesehatan.', '2025-04-23 02:04:45', NULL),
(196, '6-60200', 'Donasi', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya donasi atau sumbangan yang diberikan oleh perusahaan kepada pihak eksternal, seperti organisasi amal atau kegiatan sosial.', '2025-04-23 02:04:45', NULL),
(197, '6-60201', 'Hiburan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya hiburan yang dikeluarkan untuk menjalin hubungan bisnis atau acara perusahaan, seperti makan malam atau pertemuan informal.', '2025-04-23 02:04:45', NULL),
(198, '6-60202', 'Bensin - Toll - dan Parkir - Umum', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya transportasi umum yang dikeluarkan untuk keperluan operasional perusahaan, termasuk bahan bakar, tol, dan parkir.', '2025-04-23 02:04:45', NULL),
(199, '6-60203', 'Perbaikan dan Perawatan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya perbaikan dan perawatan aset tetap, seperti kendaraan, bangunan, atau peralatan, untuk menjaga kondisinya tetap optimal.', '2025-04-23 02:04:45', NULL),
(200, '6-60204', 'Perjalanan (Travelling) - Umum', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya perjalanan umum yang dikeluarkan untuk keperluan operasional perusahaan, seperti kunjungan ke mitra bisnis atau pelanggan.', '2025-04-23 02:04:45', NULL),
(201, '6-60205', 'Konsumsi', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya konsumsi yang dikeluarkan untuk rapat, acara, atau kegiatan perusahaan, seperti makanan dan minuman.', '2025-04-23 02:04:45', NULL),
(202, '6-60206', 'Komunikasi - Umum', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya komunikasi umum, seperti telepon, internet, atau pos, yang digunakan untuk mendukung operasional perusahaan.', '2025-04-23 02:04:45', NULL),
(203, '6-60207', 'Iuran & Berlangganan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Iuran atau biaya berlangganan untuk layanan tertentu, seperti software, majalah, atau jurnal.', '2025-04-23 02:04:45', NULL),
(204, '6-60208', 'Asuransi', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Premi asuransi yang dibayarkan untuk melindungi aset atau operasional perusahaan, seperti asuransi kendaraan, bangunan, atau karyawan.', '2025-04-23 02:04:45', NULL),
(205, '6-60209', 'Biaya Hukum & Professional', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya jasa hukum atau profesional, seperti konsultan, auditor, atau pengacara, yang digunakan untuk mendukung operasional perusahaan.', '2025-04-23 02:04:45', NULL),
(206, '6-60210', 'Beban Tunjangan Karyawan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Tunjangan tambahan yang diberikan kepada karyawan, seperti tunjangan kesehatan, transportasi, atau makan.', '2025-04-23 02:04:45', NULL),
(207, '6-60211', 'Sarana Kantor', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya sarana kantor, seperti listrik, air, keamanan, atau kebersihan, yang mendukung operasional sehari-hari.', '2025-04-23 02:04:45', NULL),
(208, '6-60212', 'Pelatihan & Pengembangan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya pelatihan dan pengembangan karyawan untuk meningkatkan keterampilan dan produktivitas mereka.', '2025-04-23 02:04:45', NULL),
(209, '6-60213', 'Beban Hutang Buruk', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Kerugian yang timbul dari piutang yang tidak dapat ditagih atau hutang buruk dari pelanggan.', '2025-04-23 02:04:45', NULL),
(210, '6-60214', 'Pajak & Lisensi', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya pajak dan lisensi yang harus dibayar oleh perusahaan, seperti pajak daerah atau izin usaha.', '2025-04-23 02:04:45', NULL),
(211, '6-60215', 'Denda', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Denda atau sanksi yang dikenakan kepada perusahaan atas pelanggaran tertentu, seperti keterlambatan pembayaran atau pelanggaran aturan.', '2025-04-23 02:04:45', NULL),
(212, '6-60216', 'Pengeluaran Barang Rusak', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya penggantian atau perbaikan barang yang rusak selama proses operasional.', '2025-04-23 02:04:45', NULL),
(213, '6-60300', 'Beban Kantor', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya operasional kantor, seperti ATK, listrik, air, atau kebersihan.', '2025-04-23 02:04:45', NULL),
(214, '6-60301', 'ATK & Print', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya alat tulis kantor (ATK) dan cetak dokumen yang digunakan dalam operasional sehari-hari.', '2025-04-23 02:04:45', NULL),
(215, '6-60302', 'Materai', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya materai yang digunakan untuk dokumen resmi, seperti kontrak atau faktur.', '2025-04-23 02:04:45', NULL),
(216, '6-60303', 'Keamanan & Kebersihan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya keamanan dan kebersihan kantor untuk menciptakan lingkungan kerja yang nyaman dan aman.', '2025-04-23 02:04:45', NULL),
(217, '6-60304', 'Persediaan Material', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya material yang digunakan untuk keperluan operasional kantor, seperti peralatan atau perlengkapan.', '2025-04-23 02:04:45', NULL),
(218, '6-60305', 'Sub Kontraktor', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya yang dibayarkan kepada subkontraktor untuk pekerjaan tertentu, seperti proyek atau layanan khusus.', '2025-04-23 02:04:45', NULL),
(219, '6-60400', 'Beban Sewa - Bangunan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya sewa bangunan yang digunakan untuk operasional perusahaan.', '2025-04-23 02:04:45', NULL),
(220, '6-60401', 'Beban Sewa - Kendaraan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya sewa kendaraan yang digunakan untuk operasional perusahaan.', '2025-04-23 02:04:45', NULL),
(221, '6-60402', 'Beban Sewa - Sewa Operasional', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya sewa operasional lainnya yang dikeluarkan oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(222, '6-60403', 'Beban Sewa - Lainnya', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Biaya sewa lainnya yang tidak termasuk dalam kategori sebelumnya.', '2025-04-23 02:04:45', NULL),
(223, '6-60500', 'Depresiasi - Bangunan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Penyusutan nilai bangunan yang dimiliki oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(224, '6-60501', 'Depresiasi - Pengembangan Bangunan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Penyusutan nilai pengembangan bangunan, seperti renovasi atau perluasan.', '2025-04-23 02:04:45', NULL),
(225, '6-60502', 'Depresiasi - Kendaraan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Penyusutan nilai kendaraan yang dimiliki oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(226, '6-60503', 'Depresiasi - Mesin & Peralatan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Penyusutan nilai mesin dan peralatan yang digunakan dalam operasional.', '2025-04-23 02:04:45', NULL),
(227, '6-60504', 'Depresiasi - Peralatan Kantor', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Penyusutan nilai peralatan kantor, seperti komputer atau printer.', '2025-04-23 02:04:45', NULL),
(228, '6-60599', 'Depresiasi - Aset Sewaan', 'beban', 'Biaya Operasional', 'debit', 0.00, 'Penyusutan nilai aset tetap yang disewa oleh perusahaan.', '2025-04-23 02:04:45', NULL),
(229, '7-70000', 'Pendapatan Bunga - Bank', 'pendapatan', 'Pendapatan Lainnya', 'kredit', 0.00, 'Pendapatan bunga yang diperoleh dari simpanan di bank.', '2025-04-23 02:04:45', NULL),
(230, '7-70001', 'Pendapatan Bunga - Waktu Deposit', 'pendapatan', 'Pendapatan Lainnya', 'kredit', 0.00, 'Pendapatan bunga dari deposito berjangka.', '2025-04-23 02:04:45', NULL),
(231, '7-70099', 'Pendapatan Lainnya', 'pendapatan', 'Pendapatan Lainnya', 'kredit', 0.00, 'Pendapatan lainnya yang tidak termasuk dalam pendapatan utama, seperti keuntungan dari penjualan aset.', '2025-04-23 02:04:45', NULL),
(232, '8-80000', 'Beban Bunga', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Bunga yang harus dibayar oleh perusahaan atas pinjaman atau utang.', '2025-04-23 02:04:45', NULL),
(233, '8-80001', 'Persediaan', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Penyesuaian nilai persediaan yang mencerminkan perubahan harga, kerusakan, atau kehilangan barang.', '2025-04-23 02:04:45', NULL),
(234, '8-80002', '(Keuntungan) / Kerugian Pembuangan Aset Tetap', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Keuntungan atau kerugian yang timbul dari penjualan atau pembuangan aset tetap.', '2025-04-23 02:04:45', NULL),
(235, '8-80100', 'Penyesuaian Persediaan', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Penyesuaian yang dilakukan untuk menyelaraskan catatan persediaan dengan kondisi fisik barang.', '2025-04-23 02:04:45', NULL),
(236, '8-80999', 'Biaya Lainnya', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Biaya-biaya lain yang tidak termasuk dalam kategori beban operasional atau biaya langsung.', '2025-04-23 02:04:45', NULL),
(237, '9-90000', 'Pajak Penghasilan - Saat Ini', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Pajak penghasilan yang harus dibayar oleh perusahaan untuk periode akuntansi saat ini.', '2025-04-23 02:04:45', NULL),
(238, '9-90001', 'Pajak Penghasilan - Ditangguhkan', 'beban', 'Beban Lainnya', 'debit', 0.00, 'Pajak penghasilan yang ditangguhkan pembayarannya ke periode mendatang.', '2025-04-23 02:04:45', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `backup`
--

CREATE TABLE `backup` (
  `id` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `ukuran_file` int(11) NOT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_user` int(11) NOT NULL,
  `id_perusahaan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `karyawan`
--

CREATE TABLE `karyawan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `id_perusahaan` int(11) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `role` enum('admin','manager','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive','pending') NOT NULL DEFAULT 'pending',
  `type` enum('internal','external') NOT NULL DEFAULT 'internal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `perusahaan`
--

CREATE TABLE `perusahaan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL COMMENT 'URL website perusahaan',
  `logo` varchar(255) DEFAULT NULL,
  `jenis` enum('regular','premium') NOT NULL DEFAULT 'regular' COMMENT 'Jenis akun perusahaan',
  `deskripsi` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `perusahaan`
--

INSERT INTO `perusahaan` (`id`, `nama`, `alamat`, `telepon`, `email`, `website`, `logo`, `jenis`, `deskripsi`, `created_at`) VALUES
(1, 'NUMART', 'DUKUN', '082340715548', 'numart@gmail.com', 'https://www.numart.com', 'uploads/perusahaan/1745944801_logobumnupacnu.png', 'regular', NULL, '2025-04-29 16:39:39'),
(2, 'NUGROSIR', 'MUNTILAN', '+6287848287808', 'nugrosir@gmail.com', 'https://www.nugrosir.com', 'uploads/perusahaan/1745947006_WhatsApp Image 2024-11-29 at 21.08.20.jpeg', 'regular', NULL, '2025-04-29 17:16:46');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi`
--

CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `id_akun_debit` int(11) NOT NULL,
  `id_akun_kredit` int(11) NOT NULL,
  `keterangan` text NOT NULL,
  `jenis` enum('pemasukan','pengeluaran','hutang','piutang','tanam_modal','tarik_modal','transfer_uang','pemasukan_piutang','transfer_hutang') NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `pajak` decimal(5,2) NOT NULL,
  `bunga` decimal(5,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `penanggung_jawab` text DEFAULT NULL,
  `tag` varchar(50) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `id_perusahaan` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transaksi`
--

INSERT INTO `transaksi` (`id`, `tanggal`, `id_akun_debit`, `id_akun_kredit`, `keterangan`, `jenis`, `jumlah`, `pajak`, `bunga`, `total`, `file_lampiran`, `penanggung_jawab`, `tag`, `created_by`, `id_perusahaan`, `created_at`) VALUES
(19, '2025-04-23', 103, 169, 'tes', 'pemasukan', 12000000.00, 0.00, 0.00, 12000000.00, '', 'aryo', '', 1, 1, '2025-04-23 18:08:05'),
(20, '2025-04-23', 179, 103, 'tes ayok', 'pengeluaran', 350000.00, 0.00, 0.00, 350000.00, '', 'aryo', 'koi', 1, 1, '2025-04-23 18:11:42'),
(21, '2025-04-29', 103, 169, 'ini aja deh', 'pemasukan', 781000.00, 0.00, 0.00, 781000.00, 'uploads/transaksi/1745938819_Invoice CCTV 1842583654.pdf', 'kus', 'ks', 1, 1, '2025-04-29 15:00:19');

--
-- Triggers `transaksi`
--
DELIMITER $$
CREATE TRIGGER `update_saldo_after_transaksi` AFTER INSERT ON `transaksi` FOR EACH ROW BEGIN
    -- Update saldo akun debit
    IF NEW.jenis IN ('pemasukan', 'hutang', 'piutang', 'tanam_modal', 'transfer_uang', 'pemasukan_piutang') THEN
        UPDATE akun SET saldo = saldo + NEW.total WHERE id = NEW.id_akun_debit;
    ELSE
        UPDATE akun SET saldo = saldo - NEW.total WHERE id = NEW.id_akun_debit;
    END IF;

    -- Update saldo akun kredit
    IF NEW.jenis IN ('pengeluaran', 'tarik_modal', 'transfer_uang', 'transfer_hutang') THEN
        UPDATE akun SET saldo = saldo - NEW.total WHERE id = NEW.id_akun_kredit;
    ELSE
        UPDATE akun SET saldo = saldo + NEW.total WHERE id = NEW.id_akun_kredit;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL,
  `id_perusahaan` int(11) DEFAULT NULL,
  `default_company` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `no_hp`, `alamat`, `foto_profil`, `id_perusahaan`, `default_company`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Yusuf', 'agi@gmail.com', '082340715549', 'dukun', 'uploads/profil/1745943775_IMG_2969 3x4_11zon.jpg', NULL, 1, '2025-04-21 20:59:16'),
(3, 'cuco@gmail.com', '$2y$10$AI3sic/FnC06XIymxbPNTeJ6vktlZM0uQ5c2o0cjRRSL4Xpv48Pdm', 'cuco', 'cuco@gmail.com', NULL, NULL, NULL, NULL, 2, '2025-04-29 16:51:22');

-- --------------------------------------------------------

--
-- Table structure for table `user_perusahaan`
--

CREATE TABLE `user_perusahaan` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `perusahaan_id` int(11) NOT NULL,
  `role` enum('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabel untuk menyimpan relasi antara user dan perusahaan dengan role dan status';

--
-- Dumping data for table `user_perusahaan`
--

INSERT INTO `user_perusahaan` (`id`, `user_id`, `perusahaan_id`, `role`, `status`, `created_at`) VALUES
(1, 1, 1, 'admin', 'active', '2025-04-29 16:39:39'),
(2, 3, 2, 'admin', 'active', '2025-04-29 16:51:22'),
(3, 1, 2, 'admin', 'active', '2025-04-29 17:16:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `akun`
--
ALTER TABLE `akun`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_akun` (`kode_akun`),
  ADD KEY `akun_ibfk_1` (`id_perusahaan`);

--
-- Indexes for table `backup`
--
ALTER TABLE `backup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_perusahaan` (`id_perusahaan`);

--
-- Indexes for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `id_perusahaan` (`id_perusahaan`),
  ADD KEY `id_user` (`id_user`);

--
-- Indexes for table `perusahaan`
--
ALTER TABLE `perusahaan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jenis` (`jenis`);

--
-- Indexes for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `id_akun_debit` (`id_akun_debit`) USING BTREE,
  ADD KEY `id_akun_kredit` (`id_akun_kredit`),
  ADD KEY `transaksi_ibfk_4` (`id_perusahaan`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `users_ibfk_1` (`id_perusahaan`),
  ADD KEY `users_ibfk_2` (`default_company`);

--
-- Indexes for table `user_perusahaan`
--
ALTER TABLE `user_perusahaan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_perusahaan_unique` (`user_id`,`perusahaan_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `perusahaan_id` (`perusahaan_id`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `akun`
--
ALTER TABLE `akun`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `backup`
--
ALTER TABLE `backup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `karyawan`
--
ALTER TABLE `karyawan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `perusahaan`
--
ALTER TABLE `perusahaan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transaksi`
--
ALTER TABLE `transaksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_perusahaan`
--
ALTER TABLE `user_perusahaan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `akun`
--
ALTER TABLE `akun`
  ADD CONSTRAINT `akun_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `backup`
--
ALTER TABLE `backup`
  ADD CONSTRAINT `backup_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `backup_ibfk_2` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `karyawan`
--
ALTER TABLE `karyawan`
  ADD CONSTRAINT `karyawan_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `karyawan_ibfk_2` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transaksi`
--
ALTER TABLE `transaksi`
  ADD CONSTRAINT `transaksi_ibfk_4` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id_perusahaan`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`default_company`) REFERENCES `perusahaan` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_perusahaan`
--
ALTER TABLE `user_perusahaan`
  ADD CONSTRAINT `user_perusahaan_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_perusahaan_ibfk_2` FOREIGN KEY (`perusahaan_id`) REFERENCES `perusahaan` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
