<?php
// admin/index.php
require_once 'includes/header.php';

// Hitung metrik / statistik dasar
$q_penyakit = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM penyakit");
$c_penyakit = mysqli_fetch_assoc($q_penyakit)['total'];

$q_gejala = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM gejala");
$c_gejala = mysqli_fetch_assoc($q_gejala)['total'];

$q_rule = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM basis_pengetahuan");
$c_rule = mysqli_fetch_assoc($q_rule)['total'];

$q_riwayat = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM riwayat_konsultasi");
$c_riwayat = mysqli_fetch_assoc($q_riwayat)['total'];

// Data Konsultasi Terbaru (Limit 5)
$q_latest = mysqli_query($koneksi, "SELECT r.*, p.nama_penyakit FROM riwayat_konsultasi r LEFT JOIN penyakit p ON r.kode_penyakit = p.kode_penyakit ORDER BY r.id_riwayat DESC LIMIT 5");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark">Ringkasan Sistem</h3>
    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill"><i class="fa-solid fa-server me-1"></i> Mode Administrator</span>
</div>

<!-- Statistik Cards -->
<div class="row g-4 mb-5">
    <!-- Penyakit -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card bg-white shadow-sm h-100 border-0 overflow-hidden" style="border-bottom: 4px solid #0ea5e9 !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 fw-semibold" style="font-size: 0.85rem;">TOTAL PENYAKIT</p>
                        <h2 class="fw-bold mb-0 text-dark"><?= $c_penyakit; ?></h2>
                    </div>
                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-primary" style="width: 48px; height: 48px; font-size: 22px;">
                        <i class="fa-solid fa-virus"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gejala -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card bg-white shadow-sm h-100 border-0 overflow-hidden" style="border-bottom: 4px solid #f59e0b !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 fw-semibold" style="font-size: 0.85rem;">TOTAL GEJALA</p>
                        <h2 class="fw-bold mb-0 text-dark"><?= $c_gejala; ?></h2>
                    </div>
                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-warning" style="width: 48px; height: 48px; font-size: 22px;">
                        <i class="fa-solid fa-head-side-cough"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rules -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card bg-white shadow-sm h-100 border-0 overflow-hidden" style="border-bottom: 4px solid #10b981 !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 fw-semibold" style="font-size: 0.85rem;">RULE PAKAR (DS)</p>
                        <h2 class="fw-bold mb-0 text-dark"><?= $c_rule; ?></h2>
                    </div>
                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-success" style="width: 48px; height: 48px; font-size: 22px;">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Konsultasi -->
    <div class="col-sm-6 col-lg-3">
        <div class="card stat-card bg-white shadow-sm h-100 border-0 overflow-hidden" style="border-bottom: 4px solid #8b5cf6 !important;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 fw-semibold" style="font-size: 0.85rem;">RIWAYAT PASIEN</p>
                        <h2 class="fw-bold mb-0 text-dark"><?= $c_riwayat; ?></h2>
                    </div>
                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center text-info" style="width: 48px; height: 48px; font-size: 20px;">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4 p-0">
            <div class="card-header bg-white border-bottom p-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">Konsultasi Terbaru</h5>
                <a href="history.php" class="btn btn-sm btn-light border rounded-pill px-3 text-muted">Lihat Semua <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 ps-4 text-muted fw-semibold">No. Reg</th>
                                <th class="text-muted fw-semibold">Tanggal Konsultasi</th>
                                <th class="text-muted fw-semibold">Nama Pasien</th>
                                <th class="text-muted fw-semibold">Hasil Algoritme</th>
                                <th class="text-muted fw-semibold text-center">Tingkat Keyakinan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($q_latest) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($q_latest)): ?>
                                <tr>
                                    <td class="ps-4 fw-medium text-dark">#<?= sprintf("%05d", $row['id_riwayat']); ?></td>
                                    <td><?= date('d M Y, H:i', strtotime($row['tanggal'])); ?></td>
                                    <td>
                                        <div class="fw-medium text-dark"><?= htmlspecialchars($row['nama_pasien']); ?></div>
                                        <div class="small text-muted"><?= $row['umur']; ?> Thn, <?= $row['jenis_kelamin'] == 'Laki-laki' ? 'L' : 'P'; ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['alamat']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary-subtle rounded-pill px-3 py-2 fw-medium">
                                            <?= htmlspecialchars($row['nama_penyakit'] ?? 'Tidak Diketahui'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="fw-bold text-dark"><?= $row['nilai_belief']; ?>%</span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="fa-regular fa-folder-open fs-2 mb-2"></i><br>
                                        Belum ada data riwayat konsultasi yang tersimpan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
