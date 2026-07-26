<?php
// cetak.php
require_once 'includes/koneksi.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "ID Riwayat Tidak Ditemukan!";
    exit;
}

$id_riwayat = (int) $_GET['id'];
$query = "SELECT r.*, p.nama_penyakit, p.solusi 
          FROM riwayat_konsultasi r 
          JOIN penyakit p ON r.kode_penyakit = p.kode_penyakit
          WHERE r.id_riwayat = $id_riwayat";
$res = mysqli_query($koneksi, $query);

if (mysqli_num_rows($res) === 0) {
    echo "Data Tidak Ditemukan!";
    exit;
}

$data = mysqli_fetch_assoc($res);

// Ambil List Gejala Terpilih
$gejala_arr = json_decode($data['gejala_terpilih'], true);
$gejala_list = [];

if (is_array($gejala_arr)) {
    $gejala_in = "'" . implode("','", array_map(function($val) use ($koneksi) { return mysqli_real_escape_string($koneksi, $val); }, $gejala_arr)) . "'";
    $query_gejala = "SELECT * FROM gejala WHERE kode_gejala IN ($gejala_in)";
    $res_g = mysqli_query($koneksi, $query_gejala);
    while ($g = mysqli_fetch_assoc($res_g)) {
        $gejala_list[] = $g;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Hasil Diagnosis #<?= $data['id_riwayat']; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Times New Roman', Times, serif; color: #000; background: #fff;}
        .kop-surat { border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 20px;}
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="container my-4">
        
        <div class="no-print text-end mb-3">
            <button onclick="window.print()" class="btn btn-primary"><i class="fa fa-print"></i> Cetak</button>
            <button onclick="window.close()" class="btn btn-secondary">Tutup</button>
        </div>

        <div class="kop-surat text-center position-relative">
            <h2 class="fw-bold mb-1">KLINIK SPESIALIS KULIT SKINEXPERT</h2>
            <p class="mb-0">Sistem Pakar Deteksi Dini Penyakit Kulit (Metode Dempster-Shafer)</p>
            <p class="mb-0 text-muted" style="font-size: 0.9rem;">Jl. Raya Teknologi No. 123, Kota Informatika</p>
        </div>

        <h4 class="text-center text-uppercase fw-bold text-decoration-underline mb-4">HASIL DIAGNOSIS AWAL</h4>

        <table class="table table-borderless mb-4" style="width: auto;">
            <tr>
                <td width="150" class="fw-bold">No. Registrasi</td>
                <td width="20">:</td>
                <td>#<?= sprintf("%05d", $data['id_riwayat']); ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Tanggal Periksa</td>
                <td>:</td>
                <td><?= date('d M Y - H:i', strtotime($data['tanggal'])); ?> WIB</td>
            </tr>
            <tr>
                <td class="fw-bold">Nama Pasien</td>
                <td>:</td>
                <td class="text-uppercase"><?= htmlspecialchars($data['nama_pasien']); ?></td>
            </tr>
            <tr>
                <td class="fw-bold">Umur / Kelamin</td>
                <td>:</td>
                <td><?= $data['umur']; ?> Tahun / <?= $data['jenis_kelamin']; ?></td>
            </tr>
        </table>

        <!-- Gejala Section -->
        <h6 class="fw-bold mb-2">A. Gejala Yang Dilaporkan:</h6>
        <ul class="mb-4">
            <?php foreach($gejala_list as $g): ?>
                <li>[<?= $g['kode_gejala']; ?>] <?= htmlspecialchars($g['nama_gejala']); ?></li>
            <?php endforeach; ?>
        </ul>

        <!-- Hasil Section -->
        <h6 class="fw-bold mb-2">B. Hasil Analisis Dempster-Shafer:</h6>
        <div class="border border-dark p-3 mb-4 text-center">
            Berdasarkan gejala yang dipilih, kemungkinan besar pasien mengalami:
            <h3 class="fw-bold text-uppercase mt-2"><?= htmlspecialchars($data['nama_penyakit']); ?></h3>
            <p class="mb-0 fw-bold">Tingkat Keyakinan Sistem: <?= $data['nilai_belief']; ?>%</p>
        </div>

        <!-- Solusi Section -->
        <h6 class="fw-bold mb-2">C. Rekomendasi Penanganan Pertama:</h6>
        <div class="border border-dark p-3 mb-5" style="text-align: justify;">
            <?= nl2br(htmlspecialchars($data['solusi'])); ?>
        </div>

        <div class="row">
            <div class="col-8">
                <small class="text-muted">
                    <em>* Hasil ini adalah diagnosis awal berbasis pengetahuan pakar komputer.<br>
                    * Harap untuk periksa langsung ke dokter medis bila kondisi memburuk.</em>
                </small>
            </div>
            <div class="col-4 text-center">
                <p class="mb-5">Dokter Pemeriksa,</p>
                <p class="mb-0"><strong>(Sistem Pakar AI)</strong></p>
                <small>SkinExpert Dept.</small>
            </div>
        </div>

    </div>
</body>
</html>
