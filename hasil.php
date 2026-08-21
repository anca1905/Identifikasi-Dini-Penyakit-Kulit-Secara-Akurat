<?php
// hasil.php
require_once 'includes/koneksi.php';
require_once 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Akses ditolak!'); window.location='konsultasi.php';</script>";
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
    echo "<script>alert('Harap pilih minimal satu gejala!'); window.history.back();</script>";
    exit;
}

if (count($selected_gejala) > 4) {
    echo "<script>alert('Maksimal gejala yang dapat dipilih adalah 4!'); window.history.back();</script>";
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
    $data['belief'] = $expert_belief * $user_belief;
}
unset($data);

// =====================================================================
// Langkah B: Kombinasikan semua mass function secara berurutan
// Tiap gejala = satu mass function: m({penyakit_grup}) = belief
// =====================================================================
$combined_mass = null; // Akan berisi hasil gabungan akhir
$calculation_steps = []; // Array untuk menyimpan proses perhitungan langkah demi langkah

foreach ($evidence_per_gejala as $kode_gejala => $evidence) {
    // Bentuk mass function dari satu gejala:
    //   m({P01, P02, P03}) = belief       ← himpunan penyakit sebagai satu set
    //   m(THETA)           = 1 - belief
    $m_baru = buildMassFunction($evidence['penyakit'], $evidence['belief']);

    if ($combined_mass === null) {
        // Inisialisasi: mulai dari gejala pertama
        $combined_mass = $m_baru;
        $calculation_steps[] = [
            'type' => 'init',
            'gejala' => $kode_gejala,
            'm_baru' => $m_baru
        ];
    } else {
        // Gabungkan dengan Dempster's Rule (termasuk normalisasi konflik K)
        $m_lama = $combined_mass;
        $combined_mass = kombinasiDS($combined_mass, $m_baru);
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

    <!-- Button Hasil Perhitungan -->
    <div class="text-center mb-4">
        <button type="button" class="btn btn-outline-primary rounded-pill px-5 py-3 fw-semibold shadow-sm border-2" data-bs-toggle="modal" data-bs-target="#modalPerhitungan">
            <i class="fa-solid fa-calculator me-2"></i> Hasil Perhitungan
        </button>
    </div>

    <!-- Modal Hasil Perhitungan -->
    <div class="modal fade" id="modalPerhitungan" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header border-bottom py-3 px-4 bg-light rounded-top-4">
                    <h5 class="modal-title fw-bold text-primary"><i class="fa-solid fa-calculator me-2"></i> Hasil Perhitungan Dempster-Shafer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 p-md-5">
                    
                    <!-- Detail Distribusi Massa Dempster-Shafer -->
                    <h5 class="fw-bold border-bottom pb-2 mb-3">
                        <i class="fa-solid fa-atom me-2 text-primary"></i>Detail Distribusi Massa Dempster-Shafer
                    </h5>
                    <div class="table-responsive mb-5">
                        <table class="table table-hover align-middle border shadow-sm rounded-3 overflow-hidden" style="font-size:0.95rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 ps-4">Himpunan Hipotesis</th>
                                    <th class="py-3">Penyakit</th>
                                    <th class="py-3 text-center">Nilai Massa <em>m(A)</em></th>
                                    <th class="py-3 pe-4">Visualisasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
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
                                    <td class="ps-4">
                                        <?php if ($is_theta): ?>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25 px-2 py-1">Θ (THETA)</span>
                                        <?php elseif ($is_multi): ?>
                                            <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 px-2 py-1">
                                                {<?= htmlspecialchars(implode(', ', $kodes)); ?>}
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                                {<?= htmlspecialchars($kodes[0]); ?>}
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted" style="font-size:0.9rem;">
                                        <?php if ($is_theta): ?>
                                            <em>Ketidakpastian</em>
                                        <?php else: ?>
                                            <?= implode(' / ', $label_parts); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center fw-bold <?= $is_theta ? 'text-secondary' : 'text-primary' ?>">
                                        <?= number_format($mv, 4); ?>
                                    </td>
                                    <td class="pe-4" style="min-width:150px;">
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1 me-2" style="height:8px;">
                                                <div class="progress-bar <?= $bar_color ?>"
                                                    role="progressbar"
                                                    style="width:<?= round($mv * 100, 2) ?>%;"
                                                    aria-valuenow="<?= round($mv * 100, 2) ?>"
                                                    aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                            <small class="text-muted fw-semibold" style="width: 40px; text-align: right;"><?= round($mv * 100, 2) ?>%</small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold border-top">
                                    <td class="ps-4" colspan="2">Total Massa</td>
                                    <td class="text-center text-success fs-6"><?= number_format(totalMass($combined_mass), 4); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Proses Perhitungan -->
                    <h5 class="fw-bold border-bottom pb-2 mb-4 mt-4">
                        <i class="fa-solid fa-list-ol me-2 text-primary"></i>Langkah Proses Perhitungan
                    </h5>
                    <div class="accordion shadow-sm" id="accordionPerhitungan">
                        <?php foreach ($calculation_steps as $index => $step): ?>
                            <div class="accordion-item border-0 border-bottom">
                                <h2 class="accordion-header" id="heading<?= $index ?>">
                                    <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?> bg-light text-dark fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $index ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="collapse<?= $index ?>">
                                        Langkah <?= $index + 1 ?>: <?= $step['type'] === 'init' ? 'Inisialisasi Gejala ' . $step['gejala'] : 'Kombinasi dengan Gejala ' . $step['gejala'] ?>
                                    </button>
                                </h2>
                                <div id="collapse<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="heading<?= $index ?>" data-bs-parent="#accordionPerhitungan">
                                    <div class="accordion-body p-4">
                                        <?php if ($step['type'] === 'init'): ?>
                                            <h6 class="text-primary fw-bold mb-3">Massa Awal (m1):</h6>
                                            <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($step['m_baru'] as $k => $v): ?>
                                                <span class="badge bg-white text-dark border p-2 shadow-sm">m({<?= htmlspecialchars($k) ?>}) = <span class="text-primary"><?= number_format($v, 4) ?></span></span>
                                            <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="row g-4">
                                                <div class="col-md-4">
                                                    <div class="p-3 bg-white border rounded-3 h-100">
                                                        <h6 class="text-secondary border-bottom pb-2 mb-3 fw-bold">Massa Sebelumnya (m_lama)</h6>
                                                        <ul class="list-unstyled mb-0">
                                                        <?php foreach ($step['m_lama'] as $k => $v): ?>
                                                            <li class="mb-1">m({<?= htmlspecialchars($k) ?>}) = <?= number_format($v, 4) ?></li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="p-3 bg-white border rounded-3 h-100">
                                                        <h6 class="text-primary border-bottom pb-2 mb-3 fw-bold">Massa Gejala <?= $step['gejala'] ?> (m_baru)</h6>
                                                        <ul class="list-unstyled mb-0">
                                                        <?php foreach ($step['m_baru'] as $k => $v): ?>
                                                            <li class="mb-1">m({<?= htmlspecialchars($k) ?>}) = <?= number_format($v, 4) ?></li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="p-3 bg-primary bg-opacity-10 border border-primary border-opacity-25 rounded-3 h-100">
                                                        <h6 class="text-success border-bottom border-success border-opacity-25 pb-2 mb-3 fw-bold">Hasil Kombinasi (m_gabungan)</h6>
                                                        <ul class="list-unstyled mb-0">
                                                        <?php foreach ($step['combined'] as $k => $v): ?>
                                                            <li class="mb-1"><span class="fw-bold">m({<?= htmlspecialchars($k) ?>})</span> = <span class="text-primary fw-bold"><?= number_format($v, 4) ?></span></li>
                                                        <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
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