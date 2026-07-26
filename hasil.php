<?php
// hasil.php
require_once 'includes/koneksi.php';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Akses ditolak!'); window.location='konsultasi.php';</script>";
    exit;
}

if (empty($_POST['gejala'])) {
    echo "<script>alert('Harap pilih minimal satu gejala!'); window.history.back();</script>";
    exit;
}

// Tangkap Data Biodata
$nama_pasien = mysqli_real_escape_string($koneksi, $_POST['nama_pasien']);
$umur = (int) $_POST['umur'];
$jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
$alamat = mysqli_real_escape_string($koneksi, $_POST['alamat'] ?? '');
$selected_gejala = $_POST['gejala']; 
$gejala_json = json_encode($selected_gejala);

// Format array string (untuk referensi query SQL)
$gejala_in = "'" . implode("','", array_map(function($val) use ($koneksi) { return mysqli_real_escape_string($koneksi, $val); }, $selected_gejala)) . "'";

// 1. Ambil Nama Gejala Terpilih
$query_nama_gejala = "SELECT * FROM gejala WHERE kode_gejala IN ($gejala_in)";
$res_gejala = mysqli_query($koneksi, $query_nama_gejala);
$gejala_terpilih_list = [];
while ($g = mysqli_fetch_assoc($res_gejala)) {
    $gejala_terpilih_list[] = $g;
}

// 2. Ambil Semua Penyakit
$query_penyakit = "SELECT * FROM penyakit";
$res_penyakit = mysqli_query($koneksi, $query_penyakit);
$semua_penyakit = [];
while ($p = mysqli_fetch_assoc($res_penyakit)) {
    $semua_penyakit[] = $p;
}

// 3. Implementasi Metode Dempster-Shafer (Dempster's Rule of Combination)
require_once 'includes/dempster_shafer.php';

// =====================================================================
// Langkah A: Ambil semua evidence dari basis_pengetahuan dan
//            KELOMPOKKAN per gejala.
//
// Sesuai literatur DS: satu GEJALA menghasilkan SATU mass function
// yang melingkupi SEMUA penyakit yang didukung gejala tersebut
// sebagai SATU HIMPUNAN.
//
// Contoh:
//   Gejala G2 mendukung P01, P02, P03 (masing-masing belief=0.8)
//   → m({P01,P02,P03}) = 0.8,  m(Θ) = 0.2   ← SATU mass function
//   (bukan tiga mass function terpisah)
// =====================================================================
$query_rules = "SELECT kode_gejala, kode_penyakit, nilai_densitas
                FROM basis_pengetahuan
                WHERE kode_gejala IN ($gejala_in)
                ORDER BY kode_gejala ASC, nilai_densitas DESC";
$res_rules = mysqli_query($koneksi, $query_rules);

// Kelompokkan semua penyakit per gejala
// Struktur: [ kode_gejala => ['penyakit' => [...], 'beliefs' => array] ]
$evidence_per_gejala = [];
while ($row = mysqli_fetch_assoc($res_rules)) {
    $g = $row['kode_gejala'];
    if (!isset($evidence_per_gejala[$g])) {
        $evidence_per_gejala[$g] = [
            'penyakit' => [],
            'beliefs'  => [],
        ];
    }
    // Kumpulkan semua penyakit yang didukung gejala ini
    $evidence_per_gejala[$g]['penyakit'][] = $row['kode_penyakit'];
    $evidence_per_gejala[$g]['beliefs'][]  = (float) $row['nilai_densitas'];
}

// Gunakan nilai densitas MAKSIMUM sebagai belief per gejala
// (sesuai pendekatan DS: satu gejala = satu mass function dengan belief = max densitas pakar)
// Catatan: nilai 1.0 diizinkan; perlindungan K=1 sudah ada di fungsi kombinasiDS
foreach ($evidence_per_gejala as $g => &$data) {
    $data['belief'] = max($data['beliefs']);
}
unset($data);

// =====================================================================
// Langkah B: Kombinasikan semua mass function secara berurutan
// Tiap gejala = satu mass function: m({penyakit_grup}) = belief
// =====================================================================
$combined_mass = null; // Akan berisi hasil gabungan akhir

foreach ($evidence_per_gejala as $kode_gejala => $evidence) {
    // Bentuk mass function dari satu gejala:
    //   m({P01, P02, P03}) = belief       ← himpunan penyakit sebagai satu set
    //   m(THETA)           = 1 - belief
    $m_baru = buildMassFunction($evidence['penyakit'], $evidence['belief']);

    if ($combined_mass === null) {
        // Inisialisasi: mulai dari gejala pertama
        $combined_mass = $m_baru;
    } else {
        // Gabungkan dengan Dempster's Rule (termasuk normalisasi konflik K)
        $combined_mass = kombinasiDS($combined_mass, $m_baru);
    }
}

// Fallback: tidak ada gejala yang cocok dengan basis pengetahuan
if ($combined_mass === null) {
    $combined_mass = ['THETA' => 1.0];
}


// Simpan nilai THETA untuk ditampilkan di bagian detail
$theta_value = $combined_mass['THETA'] ?? 0.0;

// =====================================================================
// Langkah C: Ekstrak hasil diagnosis dari combined mass function
// Pisahkan: single-disease sets vs multi-disease sets vs THETA
// =====================================================================

// Buat lookup data penyakit
$nama_penyakit_map = [];
foreach ($semua_penyakit as $p) {
    $nama_penyakit_map[$p['kode_penyakit']] = $p;
}

$hasil_diagnosis  = []; // Semua himpunan (untuk tabel "Lainnya")
$single_diagnosis = []; // Hanya himpunan tunggal (untuk diagnosis utama & simpan DB)

foreach ($combined_mass as $set_key => $mass_value) {
    // Lewati THETA dan nilai yang sangat kecil
    if ($set_key === 'THETA' || $mass_value < 0.0001) continue;

    $kode_list = explode('|', $set_key);
    sort($kode_list);

    // Bangun label nama & ambil solusi (hanya jika single)
    $nama_list = [];
    $solusi    = '';
    foreach ($kode_list as $kp) {
        if (isset($nama_penyakit_map[$kp])) {
            $nama_list[] = $nama_penyakit_map[$kp]['nama_penyakit'];
            if (count($kode_list) === 1) {
                $solusi = $nama_penyakit_map[$kp]['solusi'];
            }
        }
    }

    $persentase = round($mass_value * 100, 2);
    $entry = [
        'kode_penyakit' => $kode_list[0],                  // Kode utama (untuk FK di DB)
        'set_key'       => $set_key,                        // Key lengkap himpunan DS
        'nama_penyakit' => implode(' / ', $nama_list),     // Label tampilan
        'solusi'        => $solusi,
        'nilai_belief'  => $mass_value,
        'persentase'    => $persentase,
        'is_single'     => count($kode_list) === 1,
    ];

    $hasil_diagnosis[] = $entry;

    if (count($kode_list) === 1) {
        $single_diagnosis[] = $entry;
    }
}

// 4. Urutkan berdasarkan nilai belief tertinggi (DESC)
usort($hasil_diagnosis, fn($a, $b) => $b['nilai_belief'] <=> $a['nilai_belief']);
usort($single_diagnosis, fn($a, $b) => $b['nilai_belief'] <=> $a['nilai_belief']);

// Tentukan penyakit tertinggi:
// → Utamakan himpunan tunggal (lebih spesifik); jika tidak ada, ambil himpunan tertinggi
$penyakit_tertinggi = !empty($single_diagnosis)
    ? $single_diagnosis[0]
    : (!empty($hasil_diagnosis) ? $hasil_diagnosis[0] : null);

$id_riwayat = 0;

// 5. Simpan Hasil Konsultasi jika ada hasil
if ($penyakit_tertinggi) {
    // Simpan kode penyakit tunggal yang valid sebagai FK
    $kp = mysqli_real_escape_string($koneksi, $penyakit_tertinggi['kode_penyakit']);
    $nb = $penyakit_tertinggi['persentase'];
    $sql_insert = "INSERT INTO riwayat_konsultasi (nama_pasien, umur, jenis_kelamin, alamat, gejala_terpilih, kode_penyakit, nilai_belief)
                   VALUES ('$nama_pasien', $umur, '$jenis_kelamin', '$alamat', '$gejala_json', '$kp', $nb)";
    if (mysqli_query($koneksi, $sql_insert)) {
        $id_riwayat = mysqli_insert_id($koneksi);
    }
}
?>

<div class="container py-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-6">
            <h2 class="fw-bold fs-3 text-dark mb-0">Hasil Diagnosis Pasien</h2>
            <p class="text-muted mb-0">Identifikasi berdasarkan metode Dempster-Shafer</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <?php if($id_riwayat > 0): ?>
                <a href="cetak.php?id=<?= $id_riwayat; ?>" target="_blank" class="btn btn-outline-danger px-4 rounded-pill">
                    <i class="fa-solid fa-print me-2"></i> Cetak Hasil
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Biodata Panel -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 bg-light shadow-sm">
                <h5 class="fw-bold border-bottom pb-2 mb-3">Biodata Anda</h5>
                <ul class="list-unstyled mb-0 lh-lg">
                    <li><strong>Nama:</strong> <?= htmlspecialchars($nama_pasien); ?></li>
                    <li><strong>Umur:</strong> <?= $umur; ?> Tahun</li>
                    <li><strong>Gender:</strong> <?= $jenis_kelamin; ?></li>
                </ul>
            </div>
        </div>

        <!-- Gejala Terpilih Panel -->
        <div class="col-lg-8">
             <div class="card card-custom p-4 bg-light shadow-sm h-100">
                <h5 class="fw-bold border-bottom pb-2 mb-3">Gejala Yang Dialami</h5>
                <ul class="list-group list-group-flush bg-transparent">
                    <?php foreach($gejala_terpilih_list as $gx): ?>
                    <li class="list-group-item bg-transparent px-0 border-light-subtle text-dark">
                        <i class="fa-solid fa-check text-success me-2"></i> [<?= $gx['kode_gejala']; ?>] <?= htmlspecialchars($gx['nama_gejala']); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
             </div>
        </div>
    </div>

    <!-- Kesimpulan / Hasil -->
    <div class="card card-custom p-4 p-md-5 border-0 shadow" style="border-top: 5px solid var(--primary-color) !important;">
        <?php if ($penyakit_tertinggi): ?>
            <div class="text-center mb-4">
                <span class="badge bg-success bg-opacity-10 text-success mb-2 px-3 py-2 rounded-pill"><i class="fa-solid fa-check-circle me-1"></i> Diagnosis Berhasil</span>
                <h3 class="fw-bold mb-1">Kemungkinan Terbesar Anda Mengalami:</h3>
                <h1 class="display-5 fw-bold text-primary mb-3"><?= htmlspecialchars($penyakit_tertinggi['nama_penyakit']); ?></h1>
                
                <div class="d-inline-flex mx-auto align-items-center justify-content-center bg-primary text-white rounded-pill px-4 py-2 mt-2 shadow-sm">
                    <i class="fa-solid fa-chart-pie me-2"></i> Tingkat Keyakinan: <?= $penyakit_tertinggi['persentase']; ?>%
                </div>
            </div>

            <div class="alert alert-info border-0 rounded-4 p-4 mt-2">
                <h5 class="fw-bold d-flex align-items-center"><i class="fa-solid fa-user-doctor fs-4 me-2"></i> Solusi Penanganan:</h5>
                <p class="mb-0 ms-4 ps-1" style="font-size:1.05rem; line-height:1.6;"><?= nl2br(htmlspecialchars($penyakit_tertinggi['solusi'])); ?></p>
            </div>

            <?php if (count($hasil_diagnosis) > 1): ?>
                <div class="mt-5">
                    <h5 class="fw-bold border-bottom pb-2 mb-3">Kemungkinan Lainnya:</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle rounded-3 overflow-hidden">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3">Nama Penyakit</th>
                                    <th class="py-3">Nilai Belief</th>
                                    <th class="py-3">Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Mulai dari index ke-1 (yang tertiggi sudah ditampilkan di atas)
                                for ($i = 1; $i < count($hasil_diagnosis); $i++): 
                                ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($hasil_diagnosis[$i]['nama_penyakit']); ?></td>
                                    <td><?= number_format($hasil_diagnosis[$i]['nilai_belief'], 4); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2"><?= $hasil_diagnosis[$i]['persentase']; ?>%</span>
                                            <div class="progress flex-grow-1" style="height: 6px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $hasil_diagnosis[$i]['persentase']; ?>%;" aria-valuenow="<?= $hasil_diagnosis[$i]['persentase']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-triangle-exclamation display-3 mb-3 text-warning"></i>
                <h4 class="fw-bold text-dark">Data Tidak Ditemukan</h4>
                <p>Kombinasi gejala yang Anda pilih tidak cocok dengan basis pengetahuan pakar kami saat ini.</p>
                <a href="konsultasi.php" class="btn btn-outline-primary mt-3 rounded-pill"><i class="fa-solid fa-arrow-left me-2"></i> Konsultasi Ulang</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detail Distribusi Massa Dempster-Shafer -->
    <div class="card card-custom p-4 p-md-5 border-0 shadow mt-4">
        <h5 class="fw-bold border-bottom pb-2 mb-3">
            <i class="fa-solid fa-atom me-2 text-primary"></i>Detail Distribusi Massa Dempster-Shafer
        </h5>
        <p class="text-muted small mb-4">
            Tabel berikut menunjukkan nilai massa (<em>m</em>) dari setiap himpunan hipotesis
            hasil kombinasi <strong>Dempster's Rule of Combination</strong>.
            Total seluruh nilai massa = 1,000.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle" style="font-size:0.9rem;">
                <thead class="table-light">
                    <tr>
                        <th class="py-2 ps-3">Himpunan Hipotesis</th>
                        <th class="py-2">Penyakit</th>
                        <th class="py-2 text-center">Nilai Massa <em>m(A)</em></th>
                        <th class="py-2">Visualisasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Tampilkan semua himpunan (termasuk multi-disease) diurutkan belief DESC
                    $all_for_table = [];
                    foreach ($combined_mass as $sk => $mv) {
                        if ($mv < 0.0001) continue;
                        $all_for_table[] = ['set_key' => $sk, 'mass' => $mv];
                    }
                    usort($all_for_table, fn($a,$b) => $b['mass'] <=> $a['mass']);
                    foreach ($all_for_table as $row_ds):
                        $sk = $row_ds['set_key'];
                        $mv = $row_ds['mass'];
                        $is_theta = ($sk === 'THETA');
                        $kodes = $is_theta ? [] : explode('|', $sk);
                        $label_parts = [];
                        foreach ($kodes as $kp) {
                            $label_parts[] = isset($nama_penyakit_map[$kp])
                                ? htmlspecialchars($nama_penyakit_map[$kp]['nama_penyakit'])
                                : htmlspecialchars($kp);
                        }
                        $is_multi = count($kodes) > 1;
                        $bar_color = $is_theta ? 'bg-secondary' : ($is_multi ? 'bg-warning' : 'bg-primary');
                    ?>
                    <tr>
                        <td class="ps-3">
                            <?php if ($is_theta): ?>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">Θ (THETA)</span>
                            <?php elseif ($is_multi): ?>
                                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                                    {<?= htmlspecialchars(implode(', ', $kodes)); ?>}
                                </span>
                                <span class="ms-1 badge bg-warning text-dark" style="font-size:0.65rem;">gabungan</span>
                            <?php else: ?>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                    {<?= htmlspecialchars($kodes[0]); ?>}
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size:0.85rem;">
                            <?php if ($is_theta): ?>
                                <em>Ketidakpastian (tidak diketahui)</em>
                            <?php else: ?>
                                <?= implode(' / ', $label_parts); ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-center fw-bold <?= $is_theta ? 'text-secondary' : 'text-primary' ?>">
                            <?= number_format($mv, 4); ?>
                        </td>
                        <td style="min-width:120px;">
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar <?= $bar_color ?>"
                                     role="progressbar"
                                     style="width:<?= round($mv * 100, 2) ?>%;"
                                     aria-valuenow="<?= round($mv * 100, 2) ?>"
                                     aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted"><?= round($mv * 100, 2) ?>%</small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <!-- Baris total -->
                    <tr class="table-light fw-bold border-top">
                        <td class="ps-3" colspan="2">Total Massa</td>
                        <td class="text-center text-success"><?= number_format(totalMass($combined_mass), 4); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mt-2 mb-0">
            <i class="fa-solid fa-circle-info me-1"></i>
            <strong>Θ (Theta)</strong> = massa ketidakpastian. Semakin kecil nilainya, semakin tinggi keyakinan sistem terhadap diagnosis.
            Himpunan <strong>gabungan</strong> (warna kuning) muncul ketika dua gejala mendukung penyakit berbeda sehingga menghasilkan irisan non-tunggal.
        </p>
    </div>

    <!-- Peringatan Medis -->
    <div class="alert alert-warning border-0 mt-4 rounded-4 shadow-sm text-dark bg-warning bg-opacity-10 d-flex align-items-start gap-3 p-4">
        <i class="fa-solid fa-triangle-exclamation fs-3 text-warning mt-1"></i>
        <div>
            <strong>Disclaimer:</strong> Hasil diagnosis ini merupakan deteksi awal berdasarkan pengetahuan pakar yang terbatas. Jika keluhan berlanjut, sangat disarankan untuk melakukan pemeriksaan langsung ke fasilitas kesehatan atau dokter Spesialis Kulit terdekat untuk penanganan medis yang lebih akurat.
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
