<?php
// hasil.php
require_once 'includes/koneksi.php';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>Swal.fire({title: 'Akses Ditolak!', text: 'Silahkan isi form konsultasi terlebih dahulu.', icon: 'error', confirmButtonText: 'OK'}).then(()=> { window.location='konsultasi.php'; });</script>";
    exit;
}

$gejala_raw = $_POST['gejala'] ?? [];
$selected_gejala = [];
$user_weights = [];

foreach ($gejala_raw as $kode => $bobot) {
    if ($bobot !== '') {
        $selected_gejala[] = $kode;
        $user_weights[$kode] = (float) $bobot;
    }
}

if (count($selected_gejala) === 0) {
    echo "<script>Swal.fire({title: 'Peringatan', text: 'Harap pilih minimal satu gejala!', icon: 'warning', confirmButtonText: 'OK'}).then(()=> { window.history.back(); });</script>";
    exit;
}

if (count($selected_gejala) > 4) {
    echo "<script>Swal.fire({title: 'Peringatan', text: 'Maksimal gejala yang dapat dipilih adalah 4!', icon: 'warning', confirmButtonText: 'OK'}).then(()=> { window.history.back(); });</script>";
    exit;
}

// Tangkap Data Biodata
$nama_pasien = mysqli_real_escape_string($koneksi, $_POST['nama_pasien']);
$umur = (int) $_POST['umur'];
$jenis_kelamin = mysqli_real_escape_string($koneksi, $_POST['jenis_kelamin']);
$alamat = mysqli_real_escape_string($koneksi, $_POST['alamat'] ?? '');
$gejala_json = json_encode($selected_gejala);

// Proses Upload Foto Pasien
$foto_pasien = '';
if (isset($_FILES['foto_pasien']) && $_FILES['foto_pasien']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'assets/img/pasien/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $ext = strtolower(pathinfo($_FILES['foto_pasien']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    if (in_array($ext, $allowed_ext) && $_FILES['foto_pasien']['size'] <= $max_size) {
        $nama_file = 'pasien_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        if (move_uploaded_file($_FILES['foto_pasien']['tmp_name'], $upload_dir . $nama_file)) {
            $foto_pasien = $nama_file;
        }
    }
}

// Format array string (untuk referensi query SQL)
$gejala_in = "'" . implode("','", array_map(function ($val) use ($koneksi) {
    return mysqli_real_escape_string($koneksi, $val);
}, $selected_gejala)) . "'";

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

// Gunakan nilai densitas MAKSIMUM sebagai belief pakar, lalu kalikan dengan bobot user
foreach ($evidence_per_gejala as $g => &$data) {
    $expert_belief = max($data['beliefs']);
    $user_belief = $user_weights[$g] ?? 1.0;
    
    // Batasi belief maksimal menjadi 0.99 untuk menghindari konflik total (pembagian dengan nol)
    // pada Dempster's Rule ketika ada dua gejala dengan kepastian mutlak (1.0) yang saling bertentangan.
    $data['belief'] = min($expert_belief * $user_belief, 0.99);
}
unset($data);

// =====================================================================
// Langkah B: Hitung Dempster-Shafer dengan merekam detail langkah,
//            matriks kombinasi, nilai konflik K, dan pembuktian rumus
// =====================================================================
$gejala_nama_map = [];
foreach ($gejala_terpilih_list as $g) {
    $gejala_nama_map[$g['kode_gejala']] = $g['nama_gejala'];
}

// Pastikan urutan evidence sesuai pilihan user
$evidence_ordered = [];
foreach ($selected_gejala as $kg) {
    if (isset($evidence_per_gejala[$kg])) {
        $evidence_ordered[$kg] = $evidence_per_gejala[$kg];
    }
}

$evidences_detail = [];
$combination_steps_detail = [];
$combined_mass = null;
$calculation_steps = [];
$current_m_label = 'm1';
$gejala_idx = 0;

foreach ($evidence_ordered as $kode_gejala => $evidence) {
    $gejala_idx++;
    if ($gejala_idx === 1) {
        $m_label = 'm1';
    } else {
        $m_label = 'm' . (2 * $gejala_idx - 2);
    }

    $m_baru = buildMassFunction($evidence['penyakit'], $evidence['belief']);

    $evidences_detail[] = [
        'index' => $gejala_idx,
        'kode_gejala' => $kode_gejala,
        'nama_gejala' => $gejala_nama_map[$kode_gejala] ?? $kode_gejala,
        'penyakit' => $evidence['penyakit'],
        'belief' => $evidence['belief'],
        'plausibility' => 1.0 - $evidence['belief'],
        'm_label' => $m_label,
        'mass_function' => $m_baru
    ];

    if ($combined_mass === null) {
        $combined_mass = $m_baru;
        $calculation_steps[] = [
            'type' => 'init',
            'gejala' => $kode_gejala,
            'm_baru' => $m_baru
        ];
    } else {
        $res_label = 'm' . (2 * $gejala_idx - 1);
        $m_lama = $combined_mass;
        $step_detail = kombinasiDSDetail($combined_mass, $m_baru, $current_m_label, $m_label, $res_label);
        $step_detail['gejala_ke'] = $gejala_idx;
        $step_detail['kode_gejala'] = $kode_gejala;
        $step_detail['nama_gejala'] = $gejala_nama_map[$kode_gejala] ?? $kode_gejala;
        $combination_steps_detail[] = $step_detail;

        $combined_mass = $step_detail['result_mass'];
        $current_m_label = $res_label;

        $calculation_steps[] = [
            'type' => 'combine',
            'gejala' => $kode_gejala,
            'm_lama' => $m_lama,
            'm_baru' => $m_baru,
            'combined' => $combined_mass
        ];
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
    $gambar_db = '';
    foreach ($kode_list as $kp) {
        if (isset($nama_penyakit_map[$kp])) {
            $nama_list[] = $nama_penyakit_map[$kp]['nama_penyakit'];
            if (count($kode_list) === 1) {
                $solusi = $nama_penyakit_map[$kp]['solusi'];
                $gambar_db = $nama_penyakit_map[$kp]['gambar'] ?? '';
            }
        }
    }

    $persentase = round($mass_value * 100, 2);
    $entry = [
        'kode_penyakit' => $kode_list[0],                  // Kode utama (untuk FK di DB)
        'set_key'       => $set_key,                        // Key lengkap himpunan DS
        'nama_penyakit' => implode(' / ', $nama_list),     // Label tampilan
        'solusi'        => $solusi,
        'gambar'        => $gambar_db,
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
    $fp = mysqli_real_escape_string($koneksi, $foto_pasien);
    $sql_insert = "INSERT INTO riwayat_konsultasi (nama_pasien, umur, jenis_kelamin, alamat, gejala_terpilih, kode_penyakit, nilai_belief, foto_pasien)
                   VALUES ('$nama_pasien', $umur, '$jenis_kelamin', '$alamat', '$gejala_json', '$kp', $nb, '$fp')";
    if (mysqli_query($koneksi, $sql_insert)) {
        $id_riwayat = mysqli_insert_id($koneksi);
    }
}
?>

<div class="container py-5">
    <!-- Header Area -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-5">
        <h2 class="fw-bold text-dark mb-3 mb-md-0">
            <i class="fa-solid fa-file-medical text-primary me-2"></i> Hasil Diagnosis
        </h2>
        <div class="d-flex gap-3">
            <a href="konsultasi.php" class="btn btn-primary rounded-pill px-4 shadow-sm fw-semibold">
                <i class="fa-solid fa-rotate-right me-2"></i> Cek Ulang
            </a>
            <a href="index.php" class="btn btn-outline-danger rounded-pill px-4 shadow-sm fw-semibold">
                <i class="fa-solid fa-power-off me-2"></i> Logout
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Data Pasien -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden">
                <div class="card-header bg-primary text-white border-0 py-3">
                    <h5 class="mb-0 fw-semibold"><i class="fa-solid fa-user-injured me-2"></i> Data Pasien</h5>
                </div>
                <div class="card-body p-4 bg-light">
                    <?php if (!empty($foto_pasien) && file_exists('assets/img/pasien/' . $foto_pasien)): ?>
                        <div class="text-center mb-3">
                            <img src="assets/img/pasien/<?= htmlspecialchars($foto_pasien) ?>" 
                                 alt="Foto Kondisi Kulit Pasien" 
                                 class="img-fluid rounded-3 shadow-sm border"
                                 style="max-height: 200px; width: 100%; object-fit: cover;">
                            <small class="text-muted d-block mt-1"><i class="fa-solid fa-camera me-1"></i>Foto Kondisi Kulit</small>
                        </div>
                    <?php endif; ?>
                    <ul class="list-unstyled mb-0 fs-6">
                        <li class="mb-3 border-bottom pb-2">
                            <span class="text-muted d-block mb-1">Nama Pasien</span>
                            <strong class="text-dark fs-5"><?= htmlspecialchars($nama_pasien); ?></strong>
                        </li>
                        <li class="mb-3 border-bottom pb-2">
                            <span class="text-muted d-block mb-1">Jenis Kelamin</span>
                            <strong class="text-dark fs-5"><?= htmlspecialchars($jenis_kelamin); ?></strong>
                        </li>
                        <li class="mb-0">
                            <span class="text-muted d-block mb-1">Umur</span>
                            <strong class="text-dark fs-5"><?= $umur; ?> Tahun</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Side: Hasil & Keterangan -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4 h-100">
                <div class="card-body p-4 p-md-5 d-flex flex-column justify-content-center text-center">
                    <?php
                        $nilai_belief = $penyakit_tertinggi ? $penyakit_tertinggi['nilai_belief'] : 0;
                        $persentase = $penyakit_tertinggi ? $penyakit_tertinggi['persentase'] : 0;
                        
                        $tingkat = "-";
                        $badge_color = "bg-secondary";
                        $text_color = "text-secondary";
                        
                        if ($nilai_belief >= 0.10 && $nilai_belief <= 0.40) {
                            $tingkat = "Rendah";
                            $badge_color = "bg-success bg-opacity-10 text-success";
                            $text_color = "text-success";
                        } elseif ($nilai_belief >= 0.41 && $nilai_belief <= 0.70) {
                            $tingkat = "Sedang";
                            $badge_color = "bg-warning bg-opacity-10 text-warning";
                            $text_color = "text-warning";
                        } elseif ($nilai_belief > 0.70 && $nilai_belief <= 1.00) {
                            $tingkat = "Tinggi";
                            $badge_color = "bg-danger bg-opacity-10 text-danger";
                            $text_color = "text-danger";
                        } else if ($nilai_belief > 0) {
                            $tingkat = "Sangat Rendah";
                            $badge_color = "bg-info bg-opacity-10 text-info";
                            $text_color = "text-info";
                        }
                    ?>
                    
                    <p class="text-muted text-uppercase fw-semibold mb-3 tracking-wide">Tingkat Penyakit</p>
                    <h2 class="fs-1 fw-bold mb-4 <?= $text_color ?>"><?= $tingkat ?></h2>
                    
                    <div class="d-inline-flex mx-auto align-items-center justify-content-center border rounded-pill px-4 py-2 mb-4 shadow-sm bg-white">
                        <span class="text-muted me-2">Nilai Belief Akhir:</span> 
                        <span class="text-primary fw-bold fs-5"><?= $persentase ?>%</span>
                    </div>

                    <div class="mt-2 text-start">
                        <h6 class="text-muted mb-3 fw-bold">Keterangan Tingkat Penyakit:</h6>
                        <div class="row g-2">
                            <div class="col-4">
                                <div class="p-3 border rounded-3 bg-light text-center transition-hover" style="transition: all 0.3s;">
                                    <div class="fw-bold text-success fs-5">0.10 - 0.40</div>
                                    <div class="text-muted small text-uppercase fw-semibold mt-1">Rendah</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded-3 bg-light text-center transition-hover" style="transition: all 0.3s;">
                                    <div class="fw-bold text-warning fs-5">0.41 - 0.70</div>
                                    <div class="text-muted small text-uppercase fw-semibold mt-1">Sedang</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-3 border rounded-3 bg-light text-center transition-hover" style="transition: all 0.3s;">
                                    <div class="fw-bold text-danger fs-5">0.71 - 1.00</div>
                                    <div class="text-muted small text-uppercase fw-semibold mt-1">Tinggi</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Gejala (Penyakit Yang Dialami) -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-4 px-4 d-flex align-items-center">
            <span class="bg-primary bg-opacity-10 text-primary p-2 rounded-circle me-3"><i class="fa-solid fa-list-check"></i></span>
            <h5 class="mb-0 fw-bold">Penyakit Yang Dialami</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="py-3 px-4 text-center text-muted text-uppercase" style="font-size: 0.85rem;" width="10%">Kode</th>
                            <th class="py-3 text-muted text-uppercase" style="font-size: 0.85rem;">Gejala</th>
                            <th class="py-3 text-muted text-uppercase" style="font-size: 0.85rem;">Penyakit</th>
                            <th class="py-3 text-center text-muted text-uppercase" style="font-size: 0.85rem;" width="15%">Belief</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gejala_terpilih_list as $g): ?>
                            <?php 
                                $kode = $g['kode_gejala'];
                                $evidence = $evidence_per_gejala[$kode] ?? null;
                                $penyakit_list_str = '-';
                                $belief_val = 0;
                                if ($evidence) {
                                    $belief_val = $evidence['belief'];
                                    $p_names = [];
                                    foreach ($evidence['penyakit'] as $kp) {
                                        if (isset($nama_penyakit_map[$kp])) {
                                            $p_names[] = $nama_penyakit_map[$kp]['nama_penyakit'];
                                        }
                                    }
                                    $penyakit_list_str = implode(', ', $p_names);
                                }
                            ?>
                        <tr>
                            <td class="px-4 text-center fw-semibold text-secondary"><?= $kode ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($g['nama_gejala']) ?></td>
                            <td class="text-muted"><?= $penyakit_list_str ?></td>
                            <td class="text-center fw-bold text-primary"><?= number_format($belief_val, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Deskripsi dan Solusi -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="position-absolute top-0 start-0 w-100 bg-info" style="height: 5px;"></div>
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 d-flex align-items-center">
                        <span class="bg-info bg-opacity-10 text-info p-2 rounded-circle me-3"><i class="fa-solid fa-circle-info"></i></span>
                        Deskripsi Penyakit
                    </h5>
                    <?php if ($penyakit_tertinggi): ?>
                        <?php 
                            $kode_p = $penyakit_tertinggi['kode_penyakit'];
                            $gambar_db = $penyakit_tertinggi['gambar'] ?? '';
                            $gambar = '';
                            if (!empty($gambar_db) && file_exists('assets/img/penyakit/' . $gambar_db)) {
                                $gambar = 'assets/img/penyakit/' . $gambar_db;
                            } else {
                                $gambar_path = 'assets/img/penyakit/' . $kode_p . '.jpg';
                                if (file_exists($gambar_path)) {
                                    $gambar = $gambar_path;
                                } else {
                                    $gambar = 'https://placehold.co/600x400/e9ecef/495057?text=Gambar+' . urlencode($penyakit_tertinggi['nama_penyakit']);
                                }
                            }
                        ?>
                        <div class="text-center mb-4">
                            <img src="<?= $gambar ?>" alt="<?= htmlspecialchars($penyakit_tertinggi['nama_penyakit']) ?>" class="img-fluid rounded-4 shadow-sm" style="max-height: 250px; width: 100%; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    <p class="fs-6 text-secondary" style="line-height: 1.8;">
                        <?= $penyakit_tertinggi ? "Berdasarkan gejala yang Anda rasakan dan hasil perhitungan sistem pakar, Anda didiagnosis memiliki kemungkinan mengalami penyakit <strong class='text-primary fs-5'>" . htmlspecialchars($penyakit_tertinggi['nama_penyakit']) . "</strong>." : "Tidak ada penyakit yang spesifik terdeteksi." ?>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 position-relative overflow-hidden">
                <div class="position-absolute top-0 start-0 w-100 bg-success" style="height: 5px;"></div>
                <div class="card-body p-4 p-md-5">
                    <h5 class="fw-bold mb-4 d-flex align-items-center">
                        <span class="bg-success bg-opacity-10 text-success p-2 rounded-circle me-3"><i class="fa-solid fa-stethoscope"></i></span>
                        Solusi / Pengobatan
                    </h5>
                    <p class="fs-6 text-secondary" style="line-height: 1.8;">
                        <?= $penyakit_tertinggi ? nl2br(htmlspecialchars($penyakit_tertinggi['solusi'])) : "Silakan berkonsultasi lebih lanjut dengan tenaga medis profesional." ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Style untuk Notasi Matematika & Matriks Dempster-Shafer -->
    <style>
    .math-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 18px;
        margin-bottom: 12px;
        font-family: 'Cambria Math', 'STIX Two Math', 'Times New Roman', serif;
    }
    .math-fraction {
        display: inline-flex;
        flex-direction: column;
        vertical-align: middle;
        text-align: center;
        padding: 0 4px;
        line-height: 1.15;
        font-size: 0.95em;
    }
    .math-num {
        border-bottom: 1.8px solid #334155;
        padding-bottom: 2px;
        font-weight: 500;
    }
    .math-den {
        padding-top: 2px;
        font-weight: 500;
    }
    .matrix-table {
        border-collapse: collapse;
        width: 100%;
        margin-top: 15px;
        margin-bottom: 20px;
        font-size: 0.92rem;
    }
    .matrix-table th, .matrix-table td {
        border: 1.5px solid #cbd5e1 !important;
        padding: 12px 14px;
        vertical-align: middle;
    }
    .matrix-header-col {
        background-color: #e0f2fe !important;
        color: #0369a1 !important;
        font-weight: 600;
        text-align: center;
    }
    .matrix-header-row {
        background-color: #f1f5f9 !important;
        color: #334155 !important;
        font-weight: 600;
        text-align: center;
    }
    @media print {
        body { background: #fff !important; color: #000 !important; }
        .no-print, header, footer, .btn, .modal { display: none !important; }
        .container { max-width: 100% !important; padding: 0 !important; }
        .card { border: 1px solid #ccc !important; box-shadow: none !important; margin-bottom: 20px !important; }
        .matrix-table th, .matrix-table td { border: 1px solid #000 !important; }
        .math-fraction { display: inline-flex !important; }
    }
    </style>

    <!-- Tombol Aksi / Navigasi -->
    <div class="d-flex flex-wrap justify-content-center gap-3 mb-5 no-print">
        <a href="#perhitunganDempsterShafer" class="btn btn-primary rounded-pill px-4 py-3 fw-bold shadow-sm">
            <i class="fa-solid fa-square-root-variable me-2"></i> Langkah & Rumus Perhitungan Manual
        </a>
        <button type="button" class="btn btn-outline-primary rounded-pill px-4 py-3 fw-semibold shadow-sm border-2" data-bs-toggle="modal" data-bs-target="#modalPerhitungan">
            <i class="fa-solid fa-expand me-2"></i> Buka Modal Perhitungan
        </button>
        <?php if (!empty($id_riwayat)): ?>
        <a href="cetak.php?id=<?= $id_riwayat ?>" target="_blank" class="btn btn-outline-success rounded-pill px-4 py-3 fw-semibold shadow-sm">
            <i class="fa-solid fa-print me-2"></i> Cetak Surat Diagnosis
        </a>
        <?php endif; ?>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary rounded-pill px-4 py-3 fw-semibold shadow-sm">
            <i class="fa-solid fa-file-pdf me-2"></i> Cetak / Simpan PDF Halaman
        </button>
    </div>

    <!-- Card Utama: Detail Proses & Rumus Perhitungan Dempster-Shafer -->
    <div id="perhitunganDempsterShafer" class="card border-0 shadow-sm rounded-4 mb-5 overflow-hidden">
        <div class="card-header bg-white border-bottom py-4 px-4 px-md-5 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold mb-2">
                    <i class="fa-solid fa-calculator me-1"></i> Metode Dempster-Shafer
                </span>
                <h4 class="fw-bold text-dark mb-1">Diagnosa Penyakit Kulit dengan Metode Dempster-Shafer</h4>
                <p class="text-muted small mb-0">Pembuktian rumus matematis, matriks kombinasi densitas, dan nilai keyakinan (belief) langkah demi langkah.</p>
            </div>
            <div class="no-print">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Cetak Perhitungan
                </button>
            </div>
        </div>

        <div class="card-body p-4 p-md-5">
            <!-- 1. Landasan Teori & Rumus Dempster-Shafer -->
            <div class="mb-5">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-book-bookmark text-primary me-2"></i>1. Teori & Rumus Dasar Dempster-Shafer
                </h5>
                <p class="text-secondary" style="line-height: 1.8;">
                    Metode Dempster-Shafer pertama kali diperkenalkan oleh Dempster, yang melakukan percobaan model ketidakpastian dengan rentang probabilitas daripada sebagai probabilitas tunggal. Secara umum, teori Dempster-Shafer ditulis dalam interval: <strong>[Belief, Plausibility]</strong>. <em>Belief</em> (Bel) adalah ukuran kekuatan evidence dalam mendukung hipotesis, sedangkan <em>Plausibility</em> (Pls) mengukur tingkat kemungkinan hipotesis tersebut masih dapat diterima.
                </p>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <span class="badge bg-secondary mb-2">Persamaan (2.2)</span>
                            <div class="fw-bold text-dark mb-1">Fungsi Belief (Bel):</div>
                            <div class="math-card bg-white">
                                <span class="fs-5"><em>Bel</em>(<em>X</em>) = ∑ <em>m</em>(<em>Y</em>)</span> &nbsp;<small class="text-muted">(untuk semua <em>Y</em> ⊆ <em>X</em>)</small>
                            </div>
                            <small class="text-muted d-block mt-2">Menyatakan derajat kepercayaan terhadap himpunan hipotesis <em>X</em>.</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border h-100">
                            <span class="badge bg-secondary mb-2">Persamaan (2.3)</span>
                            <div class="fw-bold text-dark mb-1">Fungsi Plausibility (Pls):</div>
                            <div class="math-card bg-white">
                                <span class="fs-5"><em>Pls</em>(<em>X</em>) = 1 − <em>Bel</em>(<em>X</em>)</span> &nbsp;<small class="text-muted">atau</small>&nbsp; <span class="fs-5"><em>m</em>(Θ) = 1 − <em>Bel</em>(<em>X</em>)</span>
                            </div>
                            <small class="text-muted d-block mt-2">Menyatakan nilai ketidakpastian semesta (Frame of Discernment Θ).</small>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 border">
                    <span class="badge bg-primary mb-2">Persamaan (2.4)</span>
                    <div class="fw-bold text-dark mb-1">Aturan Kombinasi Dempster (Dempster's Rule of Combination):</div>
                    <div class="math-card bg-white py-3">
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <span class="fs-5 fw-bold"><em>m</em><sub>gabungan</sub>(<em>Z</em>) = </span>
                            <span class="math-fraction fs-5">
                                <span class="math-num">∑<sub><em>X</em> ∩ <em>Y</em> = <em>Z</em></sub> <em>m</em><sub>1</sub>(<em>X</em>) · <em>m</em><sub>2</sub>(<em>Y</em>)</span>
                                <span class="math-den">1 − ∑<sub><em>X</em> ∩ <em>Y</em> = ∅</sub> <em>m</em><sub>1</sub>(<em>X</em>) · <em>m</em><sub>2</sub>(<em>Y</em>)</span>
                            </span>
                            <span class="fs-5 mx-1">=</span>
                            <span class="math-fraction fs-5">
                                <span class="math-num">∑<sub><em>X</em> ∩ <em>Y</em> = <em>Z</em></sub> <em>m</em><sub>1</sub>(<em>X</em>) · <em>m</em><sub>2</sub>(<em>Y</em>)</span>
                                <span class="math-den">1 − <em>K</em></span>
                            </span>
                        </div>
                    </div>
                    <div class="small text-muted mt-2">
                        <strong>Keterangan variabel:</strong><br>
                        • <em>X, Y, Z</em> = Himpunan hipotesis penyakit.<br>
                        • <em>m</em>(<em>Z</em>) = Nilai densitas (mass function) hasil kombinasi evidence.<br>
                        • <em>K</em> = Evidential conflict (jumlah perkalian pasangan himpunan dengan irisan kosong ∅).<br>
                        • 1 − <em>K</em> = Faktor normalisasi pembagi.<br>
                        • Θ (Theta) = Frame of Discernment (ketidakpastian total).
                    </div>
                </div>
            </div>

            <!-- 2. Pengujian Gejala Pasien & Nilai Belief Awal -->
            <div class="mb-5">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-clipboard-list text-primary me-2"></i>2. Pengujian Awal & Penentuan Mass Function Gejala
                </h5>
                <p class="text-secondary mb-3">
                    Dilakukan pengujian secara manual menggunakan metode Dempster-Shafer dengan menggunakan <strong><?= count($evidences_detail) ?> gejala</strong> yang dipilih oleh pasien. Nilai belief dan plausibility dihitung berdasarkan Persamaan (2.2) dan (2.3):
                </p>

                <?php foreach ($evidences_detail as $ev): ?>
                    <?php
                        $penyakit_names_arr = [];
                        foreach ($ev['penyakit'] as $kp) {
                            $pname = $nama_penyakit_map[$kp]['nama_penyakit'] ?? $kp;
                            $penyakit_names_arr[] = "$kp ($pname)";
                        }
                        $penyakit_names_str = implode(', ', $penyakit_names_arr);
                        $set_code_str = formatSetNotation(implode('|', $ev['penyakit']));
                    ?>
                    <div class="p-3 mb-3 bg-light rounded-3 border">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-primary me-2"><?= $ev['index'] ?></span>
                            <strong class="text-dark fs-6"><?= htmlspecialchars($ev['kode_gejala']) ?> : <?= htmlspecialchars($ev['nama_gejala']) ?></strong>
                        </div>
                        <p class="text-muted small mb-2 ms-4">
                            Merupakan gejala dari penyakit: <strong class="text-dark"><?= $penyakit_names_str ?></strong> dengan:
                        </p>
                        <div class="d-flex flex-wrap gap-2 ms-4">
                            <div class="math-card bg-white px-3 py-2 border rounded-pill mb-0">
                                <strong><?= $ev['m_label'] ?></strong> <?= $set_code_str ?> = <span class="text-primary fw-bold"><?= formatNumDS($ev['belief'], 2) ?></span>
                            </div>
                            <div class="math-card bg-white px-3 py-2 border rounded-pill mb-0">
                                <strong><?= $ev['m_label'] ?></strong> (Θ) = 1 − <?= formatNumDS($ev['belief'], 2) ?> = <span class="text-secondary fw-bold"><?= formatNumDS($ev['plausibility'], 2) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- 3. Aturan Kombinasi Dempster (Matriks & Pembagian Pecahan) -->
            <div class="mb-5">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-table-cells text-primary me-2"></i>3. Aturan Kombinasi Dempster (Dempster's Rule of Combination)
                </h5>

                <?php if (count($evidences_detail) < 2): ?>
                    <div class="alert alert-info border-0 rounded-3">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        Pasien hanya memilih 1 gejala. Aturan kombinasi Dempster-Shafer memerlukan minimal 2 evidence untuk proses perkalian irisan matriks. Nilai diagnosis akhir langsung diambil dari <strong>m1</strong>.
                    </div>
                <?php else: ?>
                    <?php foreach ($combination_steps_detail as $sIndex => $step): ?>
                        <div class="card border mb-4 shadow-sm rounded-4 overflow-hidden">
                            <div class="card-header bg-light py-3 px-4">
                                <span class="badge bg-primary me-2">Kombinasi Tahap <?= $sIndex + 1 ?></span>
                                <strong class="text-dark">Kombinasi <?= $step['label_lama'] ?> dan <?= $step['label_baru'] ?> Menghasilkan <?= $step['label_hasil'] ?></strong>
                                <small class="text-muted d-block mt-1">Gejala ke-<?= $step['gejala_ke'] ?>: [<?= $step['kode_gejala'] ?>] <?= htmlspecialchars($step['nama_gejala']) ?></small>
                            </div>
                            <div class="card-body p-4">
                                <p class="text-secondary mb-3" style="line-height: 1.7;">
                                    Selanjutnya, irisan antara <strong><?= $step['label_lama'] ?></strong> dan <strong><?= $step['label_baru'] ?></strong> dihitung untuk memperoleh <strong><?= $step['label_hasil'] ?></strong> dengan mengombinasikan nilai plausibility dan belief dari kedua himpunan, termasuk irisan dengan Θ, untuk mendapatkan nilai kepercayaan setiap kemungkinan.
                                </p>

                                <h6 class="fw-bold text-dark mb-2">Tabel Aturan Kombinasi untuk <?= $step['label_hasil'] ?>:</h6>
                                <div class="table-responsive mb-4">
                                    <table class="matrix-table text-center align-middle">
                                        <thead>
                                            <tr>
                                                <th class="matrix-header-col" style="min-width: 160px; background-color: #f8fafc !important;"></th>
                                                <?php foreach (reset($step['matrix']) as $colKey => $cell): ?>
                                                    <th class="matrix-header-col">
                                                        <?= $step['label_baru'] ?> <?= formatSetNotation($colKey) ?> = <?= formatNumDS($cell['valC'], ($cell['valC'] < 1 ? 4 : 2)) ?>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($step['matrix'] as $rowKey => $rowCells): ?>
                                                <tr>
                                                    <th class="matrix-header-row">
                                                        <?= $step['label_lama'] ?> <?= formatSetNotation($rowKey) ?> = <?= formatNumDS(reset($rowCells)['valR'], 4) ?>
                                                    </th>
                                                    <?php foreach ($rowCells as $cell): ?>
                                                        <td>
                                                            <?php if ($cell['intersection'] === ''): ?>
                                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 mb-1">∅ (Konflik)</span><br>
                                                                <strong>∅ = <?= formatNumDS($cell['product'], 4) ?></strong><br>
                                                                <small class="text-muted">(<?= formatNumDS($cell['valR'], 4) ?> × <?= formatNumDS($cell['valC'], ($cell['valC'] < 1 ? 4 : 2)) ?>)</small>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 mb-1"><?= formatSetNotation($cell['intersection']) ?></span><br>
                                                                <strong><?= formatSetNotation($cell['intersection']) ?> = <?= formatNumDS($cell['product'], 4) ?></strong><br>
                                                                <small class="text-muted">(<?= formatNumDS($cell['valR'], 4) ?> × <?= formatNumDS($cell['valC'], ($cell['valC'] < 1 ? 4 : 2)) ?>)</small>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <p class="text-secondary mb-3">
                                    Dari hasil kombinasi pada tabel di atas, diperoleh nilai <strong><?= $step['label_hasil'] ?></strong> yang dihitung berdasarkan Persamaan (2.4) sebagai berikut:
                                </p>

                                <!-- Nilai Konflik K -->
                                <div class="p-3 bg-light rounded-3 border mb-3">
                                    <h6 class="fw-bold text-dark mb-1">Hitung Nilai Konflik (K):</h6>
                                    <?php if ($step['K'] > 0): ?>
                                        <div class="text-secondary font-monospace mb-1">
                                            K = ∑ Perkalian Irisan Kosong (∅) = 
                                            <?= implode(' + ', array_map(fn($c) => formatNumDS($c['product'], 4), $step['conflict_cells'])) ?>
                                            = <strong class="text-danger"><?= formatNumDS($step['K'], 4) ?></strong>
                                        </div>
                                        <div class="text-secondary font-monospace">
                                            1 − K = 1 − <?= formatNumDS($step['K'], 4) ?> = <strong class="text-dark"><?= formatNumDS($step['one_minus_K'], 4) ?></strong>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-secondary font-monospace mb-1">K = <strong>0</strong> (Tidak ada kombinasi irisan kosong)</div>
                                        <div class="text-secondary font-monospace">1 − K = 1 − 0 = <strong class="text-dark">1</strong></div>
                                    <?php endif; ?>
                                </div>

                                <!-- Rumus Pecahan tiap hipotesis -->
                                <h6 class="fw-bold text-dark mb-2">Perhitungan Nilai Densitas <?= $step['label_hasil'] ?>:</h6>
                                <?php foreach ($step['equations'] as $eq): ?>
                                    <div class="math-card shadow-sm">
                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <span class="fw-bold text-dark fs-6"><?= $step['label_hasil'] ?> <?= formatSetNotation($eq['set']) ?> = </span>
                                            
                                            <span class="math-fraction">
                                                <span class="math-num"><?= implode(' + ', array_map(fn($p) => formatNumDS($p, 4), $eq['products'])) ?></span>
                                                <span class="math-den"><?= ($step['K'] > 0) ? formatNumDS($step['one_minus_K'], 4) : '1 − 0' ?></span>
                                            </span>

                                            <?php if (count($eq['products']) > 1 || $step['K'] == 0): ?>
                                            <span class="mx-1">=</span>
                                            <span class="math-fraction">
                                                <span class="math-num"><?= formatNumDS($eq['sum'], 4) ?></span>
                                                <span class="math-den"><?= ($step['K'] > 0) ? formatNumDS($step['one_minus_K'], 4) : '1' ?></span>
                                            </span>
                                            <?php endif; ?>

                                            <span class="mx-1">=</span>
                                            <span class="fw-bold text-primary fs-5"><?= formatNumDS($eq['normalized'], 4) ?></span>
                                            
                                            <span class="text-muted small ms-2">
                                                <?= ($eq['set'] === 'THETA') ? '<em>(Ketidakpastian Total)</em>' : '(' . htmlspecialchars(formatSetNotationWithNames($eq['set'], $nama_penyakit_map)) . ')' ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 4. Tabel Rekapitulasi Nilai Akhir & Kesimpulan -->
            <div>
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3">
                    <i class="fa-solid fa-square-poll-vertical text-primary me-2"></i>4. Rekapitulasi Nilai Akhir & Kesimpulan
                </h5>
                <p class="text-secondary mb-3">
                    Berdasarkan seluruh tahapan kombinasi Dempster-Shafer, diperoleh distribusi nilai densitas massa akhir sebagai berikut:
                </p>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-hover align-middle shadow-sm rounded-3 overflow-hidden text-center" style="font-size:0.95rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-3">Himpunan Hipotesis</th>
                                <th class="py-3 px-3 text-start">Keterangan / Penyakit Terkait</th>
                                <th class="py-3 px-3" width="18%">Nilai Massa (<em>m</em>)</th>
                                <th class="py-3 px-3" width="15%">Persentase</th>
                                <th class="py-3 px-3" width="22%">Status Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $all_for_rekap = [];
                            foreach ($combined_mass as $sk => $mv) {
                                if ($mv < 0.0001) continue;
                                $all_for_rekap[] = ['set_key' => $sk, 'mass' => $mv];
                            }
                            usort($all_for_rekap, fn($a, $b) => $b['mass'] <=> $a['mass']);
                            
                            $first_disease_set = null;
                            foreach ($all_for_rekap as $row_ds):
                                $sk = $row_ds['set_key'];
                                $mv = $row_ds['mass'];
                                $is_theta = ($sk === 'THETA');
                                $kodes = $is_theta ? [] : explode('|', $sk);
                                $is_highest = false;

                                if (!$is_theta && $first_disease_set === null) {
                                    $first_disease_set = $sk;
                                    $is_highest = true;
                                }

                                $label_p_arr = [];
                                foreach ($kodes as $kp) {
                                    $label_p_arr[] = isset($nama_penyakit_map[$kp]) ? $nama_penyakit_map[$kp]['nama_penyakit'] : $kp;
                                }
                            ?>
                            <tr class="<?= $is_highest ? 'table-success bg-opacity-25' : '' ?>">
                                <td class="fw-bold font-monospace"><?= formatSetNotation($sk) ?></td>
                                <td class="text-start">
                                    <?php if ($is_theta): ?>
                                        <span class="text-muted fst-italic">Semesta Ketidakpastian (Θ)</span>
                                    <?php else: ?>
                                        <span class="<?= $is_highest ? 'fw-bold text-dark' : 'text-secondary' ?>">
                                            <?= implode(' / ', $label_p_arr) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold <?= $is_highest ? 'text-success fs-6' : 'text-primary' ?>">
                                    <?= formatNumDS($mv, 4) ?>
                                </td>
                                <td class="fw-bold <?= $is_highest ? 'text-success fs-6' : 'text-dark' ?>">
                                    <?= formatNumDS($mv * 100, 2) ?>%
                                </td>
                                <td>
                                    <?php if ($is_highest): ?>
                                        <span class="badge bg-success px-3 py-2 rounded-pill">Nilai Tertinggi (Diagnosa Terpilih)</span>
                                    <?php elseif ($is_theta): ?>
                                        <span class="badge bg-secondary bg-opacity-20 text-secondary px-2 py-1 rounded-pill">Ketidakpastian Lingkungan</span>
                                    <?php elseif (count($kodes) > 1): ?>
                                        <span class="badge bg-warning bg-opacity-20 text-warning px-2 py-1 rounded-pill">Himpunan Campuran</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Lebih rendah dari <?= htmlspecialchars($penyakit_tertinggi['kode_penyakit'] ?? '') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="table-light fw-bold">
                                <td colspan="2" class="text-end py-3">Total Massa Keyakinan (∑ <em>m</em>):</td>
                                <td class="text-success fs-6 py-3"><?= formatNumDS(totalMass($combined_mass), 4) ?></td>
                                <td class="text-success fs-6 py-3"><?= formatNumDS(totalMass($combined_mass) * 100, 2) ?>%</td>
                                <td class="text-muted small py-3">Valid (Σ m = 1.0)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Box Kesimpulan Akhir -->
                <div class="p-4 rounded-4 border bg-primary bg-opacity-10 border-primary border-opacity-25">
                    <h5 class="fw-bold text-primary mb-2">
                        <i class="fa-solid fa-square-check me-2"></i>Kesimpulan Akhir Diagnosis:
                    </h5>
                    <p class="fs-6 text-dark mb-0" style="line-height: 1.8;">
                        Berdasarkan perhitungan Dempster-Shafer yang benar, nilai densitas kepercayaan tertinggi diperoleh oleh penyakit 
                        <strong class="text-primary fs-5"><?= htmlspecialchars($penyakit_tertinggi['nama_penyakit'] ?? '-') ?></strong> 
                        (Kode: <strong><?= htmlspecialchars($penyakit_tertinggi['kode_penyakit'] ?? '-') ?></strong>) 
                        dengan nilai kepercayaan sebesar <strong class="text-primary fs-5"><?= formatNumDS(($penyakit_tertinggi['nilai_belief'] ?? 0) * 100, 2) ?>%</strong>.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hasil Perhitungan (Tampilan Lengkap) -->
    <div class="modal fade" id="modalPerhitungan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom py-3 px-4 bg-light rounded-top-4">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-calculator me-2"></i> Detail & Rumus Perhitungan Dempster-Shafer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 p-md-5">
                    <div class="alert alert-primary border-0 rounded-3 mb-4">
                        <i class="fa-solid fa-circle-info me-2"></i>
                        Perhitungan ini mencakup penentuan nilai massa awal (m), tabel aturan kombinasi matriks per langkah, penanganan konflik K, serta pembagian pecahan normalisasi hingga nilai akhir diagnosis.
                    </div>

                    <!-- Distribusi Massa Akhir Modal -->
                    <h5 class="fw-bold border-bottom pb-2 mb-3 text-dark">
                        <i class="fa-solid fa-atom me-2 text-primary"></i>Distribusi Massa Akhir Dempster-Shafer
                    </h5>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle border shadow-sm rounded-3 overflow-hidden text-center" style="font-size:0.95rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 ps-3">Himpunan</th>
                                    <th class="py-3 text-start">Penyakit</th>
                                    <th class="py-3">Nilai Massa <em>m</em></th>
                                    <th class="py-3 pe-3">Persentase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_for_rekap as $row_ds): 
                                    $sk = $row_ds['set_key'];
                                    $mv = $row_ds['mass'];
                                    $is_theta = ($sk === 'THETA');
                                    $kodes = $is_theta ? [] : explode('|', $sk);
                                    $label_parts = [];
                                    foreach ($kodes as $kp) {
                                        $label_parts[] = isset($nama_penyakit_map[$kp]) ? $nama_penyakit_map[$kp]['nama_penyakit'] : $kp;
                                    }
                                ?>
                                <tr>
                                    <td class="ps-3 font-monospace fw-bold"><?= formatSetNotation($sk) ?></td>
                                    <td class="text-start text-muted">
                                        <?= $is_theta ? '<em>Semesta Ketidakpastian (Θ)</em>' : implode(' / ', $label_parts) ?>
                                    </td>
                                    <td class="fw-bold text-primary"><?= formatNumDS($mv, 4) ?></td>
                                    <td class="pe-3 fw-bold"><?= formatNumDS($mv * 100, 2) ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ringkasan Langkah Kombinasi Modal -->
                    <h5 class="fw-bold border-bottom pb-2 mb-3 text-dark">
                        <i class="fa-solid fa-list-ol me-2 text-primary"></i>Ringkasan Langkah Perhitungan
                    </h5>
                    <div class="accordion shadow-sm mb-3" id="accordionModalDS">
                        <?php foreach ($combination_steps_detail as $idx => $step): ?>
                            <div class="accordion-item border-0 border-bottom">
                                <h2 class="accordion-header" id="headingModal<?= $idx ?>">
                                    <button class="accordion-button <?= $idx === 0 ? '' : 'collapsed' ?> bg-light text-dark fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseModal<?= $idx ?>">
                                        Langkah <?= $idx + 1 ?>: Kombinasi <?= $step['label_lama'] ?> dan <?= $step['label_baru'] ?> → <?= $step['label_hasil'] ?>
                                    </button>
                                </h2>
                                <div id="collapseModal<?= $idx ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#accordionModalDS">
                                    <div class="accordion-body p-4">
                                        <p class="small text-muted mb-3">Kombinasi antara <?= $step['label_lama'] ?> dengan Gejala [<?= $step['kode_gejala'] ?>] <?= htmlspecialchars($step['nama_gejala']) ?> (<?= $step['label_baru'] ?>):</p>
                                        <div class="p-3 bg-light rounded-3 mb-3 border font-monospace small">
                                            Konflik K = <?= formatNumDS($step['K'], 4) ?> | Faktor Normalisasi (1 − K) = <?= formatNumDS($step['one_minus_K'], 4) ?>
                                        </div>
                                        <?php foreach ($step['equations'] as $eq): ?>
                                            <div class="math-card py-2 px-3 mb-2 small shadow-none">
                                                <?= $step['label_hasil'] ?> <?= formatSetNotation($eq['set']) ?> = 
                                                (<?= implode(' + ', array_map(fn($p) => formatNumDS($p, 4), $eq['products'])) ?>) / <?= formatNumDS($step['one_minus_K'], 4) ?> = 
                                                <strong class="text-primary"><?= formatNumDS($eq['normalized'], 4) ?></strong>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                </div>
            </div>
        </div>
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