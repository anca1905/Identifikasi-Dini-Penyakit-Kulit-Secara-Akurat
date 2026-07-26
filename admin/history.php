<?php
// admin/history.php
require_once 'includes/header.php';

// Hapus Riwayat
if (isset($_GET['del'])) {
    $id = (int) $_GET['del'];
    mysqli_query($koneksi, "DELETE FROM riwayat_konsultasi WHERE id_riwayat=$id");
    echo "<script>alert('Riwayat konsultasi berhasil dihapus!'); window.location='history.php';</script>";
    exit;
}

// Reset Semua Data
if (isset($_GET['reset'])) {
    mysqli_query($koneksi, "TRUNCATE TABLE riwayat_konsultasi");
    echo "<script>alert('Semua data riwayat konsultasi telah dikosongkan!'); window.location='history.php';</script>";
    exit;
}

// --- Pagination & Per-page ---
$per_page_options = [5, 10, 25, 50];
$per_page = (isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $per_page_options)) ? (int)$_GET['per_page'] : 5;
$total_query = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM riwayat_konsultasi");
$total_row    = mysqli_fetch_assoc($total_query);
$total_data   = (int)$total_row['total'];
$total_pages  = (int)ceil($total_data / $per_page);
$current_page_num = max(1, min($total_pages ?: 1, (int)($_GET['page'] ?? 1)));
$offset       = ($current_page_num - 1) * $per_page;

$query = mysqli_query($koneksi, "
    SELECT r.*, p.nama_penyakit 
    FROM riwayat_konsultasi r 
    LEFT JOIN penyakit p ON r.kode_penyakit = p.kode_penyakit 
    ORDER BY r.id_riwayat DESC
    LIMIT $per_page OFFSET $offset
");

// Helper: ambil nama-nama gejala dari JSON kode gejala
function getGejalaNames($koneksi, $gejala_json) {
    $arr = json_decode($gejala_json, true);
    if (!is_array($arr) || empty($arr)) return '-';
    $escaped = array_map(fn($v) => "'" . mysqli_real_escape_string($koneksi, $v) . "'", $arr);
    $in = implode(',', $escaped);
    $res = mysqli_query($koneksi, "SELECT nama_gejala FROM gejala WHERE kode_gejala IN ($in) ORDER BY kode_gejala ASC");
    $names = [];
    while ($g = mysqli_fetch_assoc($res)) $names[] = $g['nama_gejala'];
    return implode(', ', $names);
}

// Helper: warna badge presentase
function getBadgeClass($nilai) {
    if ($nilai >= 70) return 'success';
    if ($nilai >= 40) return 'warning';
    return 'danger';
}
function getKategori($nilai) {
    if ($nilai >= 70) return 'Tinggi';
    if ($nilai >= 40) return 'Sedang';
    return 'Rendah';
}

// Build query string for pagination links
function buildQuery($overrides = []) {
    $params = array_merge($_GET, $overrides);
    unset($params['del'], $params['reset']);
    return http_build_query($params);
}
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Data Riwayat Konsultasi</h3>
        <p class="text-muted mb-0 small">Menampilkan semua arsip diagnosa penyakit kulit dari pasien/user yang menggunakan aplikasi.</p>
    </div>
    <?php if($total_data > 0): ?>
    <a href="history.php?reset=1" onclick="return confirm('Peringatan: Aksi ini akan menghapus SELURUH riwayat pasien. Lanjutkan?')" class="btn btn-outline-danger rounded-pill px-4">
        <i class="fa-solid fa-triangle-exclamation me-1"></i> Bersihkan Semua Data
    </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th class="py-3 px-3 text-muted fw-semibold text-center" style="width:50px;">No</th>
                        <th class="py-3 px-3 text-muted fw-semibold" style="min-width:130px;">Nama Pasien</th>
                        <th class="py-3 px-3 text-muted fw-semibold" style="min-width:160px;">Alamat</th>
                        <th class="py-3 px-3 text-muted fw-semibold" style="min-width:200px;">Gejala yang Dipilih</th>
                        <th class="py-3 px-3 text-muted fw-semibold" style="min-width:150px;">Hasil Diagnosa Penyakit</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-center" style="min-width:120px;">Presentase Hasil (%)</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-center" style="min-width:120px;">Tanggal</th>
                        <th class="py-3 px-3 text-muted fw-semibold text-center" style="width:90px;">Info</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($total_data > 0 && mysqli_num_rows($query) > 0): ?>
                        <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td class="px-3 text-center fw-semibold text-muted"><?= $no++; ?></td>
                            <td class="px-3">
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_pasien']); ?></div>
                                <div class="small text-muted">
                                    <i class="fa-solid <?= ($row['jenis_kelamin']=='Laki-laki') ? 'fa-mars text-primary':'fa-venus text-danger'; ?> me-1"></i>
                                    <?= $row['jenis_kelamin']; ?>, <?= $row['umur']; ?> Thn
                                </div>
                            </td>
                            <td class="px-3 text-muted small"><?= htmlspecialchars($row['alamat'] ?: '-'); ?></td>
                            <td class="px-3">
                                <div class="small text-dark" style="line-height: 1.6;">
                                    <?php
                                    $gejala_names = getGejalaNames($koneksi, $row['gejala_terpilih']);
                                    $gejala_short = mb_strlen($gejala_names) > 80 ? mb_substr($gejala_names, 0, 80) . '…' : $gejala_names;
                                    ?>
                                    <span title="<?= htmlspecialchars($gejala_names); ?>"><?= htmlspecialchars($gejala_short); ?></span>
                                </div>
                            </td>
                            <td class="px-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1 rounded-pill fw-semibold" style="font-size:0.8rem;">
                                    <?= htmlspecialchars($row['nama_penyakit']); ?>
                                </span>
                            </td>
                            <td class="px-3 text-center">
                                <?php $badge = getBadgeClass($row['nilai_belief']); ?>
                                <span class="badge bg-<?= $badge; ?> bg-opacity-15 text-<?= $badge; ?> border border-<?= $badge; ?> border-opacity-25 px-2 py-1 rounded-pill fw-bold" style="font-size:0.85rem;">
                                    <?= $row['nilai_belief']; ?>%
                                </span>
                                <div class="small text-muted mt-1"><?= getKategori($row['nilai_belief']); ?></div>
                            </td>
                            <td class="px-3 text-center small text-muted">
                                <div class="fw-semibold text-dark"><?= date('d/m/Y', strtotime($row['tanggal'])); ?></div>
                                <div><?= date('H:i', strtotime($row['tanggal'])); ?></div>
                            </td>
                            <td class="px-3 text-center">
                                <a href="../cetak.php?id=<?= $row['id_riwayat']; ?>" target="_blank" 
                                   class="btn btn-sm btn-outline-secondary rounded-2 me-1 mb-1" 
                                   title="Lihat Detail" style="width:32px;height:32px;padding:0;line-height:30px;">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <a href="../cetak.php?id=<?= $row['id_riwayat']; ?>" target="_blank" 
                                   class="btn btn-sm btn-outline-info rounded-2 mb-1" 
                                   title="Cetak" style="width:32px;height:32px;padding:0;line-height:30px;">
                                    <i class="fa-solid fa-print"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-inbox fs-1 mb-3 d-block text-secondary opacity-25"></i>
                                Data Riwayat Konsultasi Kosong.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Footer: Legend + Pagination -->
<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-5">
    
    <!-- Keterangan Presentase -->
    <div class="card border shadow-sm px-4 py-3 rounded-3" style="min-width: 260px; background:#fff;">
        <div class="fw-semibold small text-dark mb-2">Keterangan Presentase Hasil</div>
        <div class="d-flex align-items-center gap-2 small mb-1">
            <span class="badge bg-danger bg-opacity-15 text-danger border border-danger border-opacity-25 px-2 rounded-pill fw-bold">0% – 39%</span>
            <span class="text-muted">: Kemungkinan rendah</span>
        </div>
        <div class="d-flex align-items-center gap-2 small mb-1">
            <span class="badge bg-warning bg-opacity-15 text-warning border border-warning border-opacity-25 px-2 rounded-pill fw-bold">40% – 69%</span>
            <span class="text-muted">: Kemungkinan sedang</span>
        </div>
        <div class="d-flex align-items-center gap-2 small">
            <span class="badge bg-success bg-opacity-15 text-success border border-success border-opacity-25 px-2 rounded-pill fw-bold">70% – 100%</span>
            <span class="text-muted">: Kemungkinan tinggi</span>
        </div>
    </div>

    <!-- Tampilkan + Pagination -->
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <!-- Per-page selector -->
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-medium">Tampilkan</span>
            <form method="GET" class="d-inline">
                <input type="hidden" name="page" value="1">
                <select name="per_page" id="per_page_select" class="form-select form-select-sm" style="width:75px;" onchange="this.form.submit()">
                    <?php foreach ($per_page_options as $opt): ?>
                        <option value="<?= $opt; ?>" <?= ($per_page == $opt) ? 'selected' : ''; ?>><?= $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <!-- Pagination buttons -->
        <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm mb-0 gap-1">
                <!-- Prev -->
                <li class="page-item <?= ($current_page_num <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-2 border" href="?<?= buildQuery(['page' => $current_page_num - 1]); ?>">&lsaquo;</a>
                </li>
                <?php
                $start_p = max(1, $current_page_num - 2);
                $end_p   = min($total_pages, $current_page_num + 2);
                for ($p = $start_p; $p <= $end_p; $p++): ?>
                <li class="page-item <?= ($p == $current_page_num) ? 'active' : ''; ?>">
                    <a class="page-link rounded-2 border <?= ($p == $current_page_num) ? 'text-white' : ''; ?>" 
                       href="?<?= buildQuery(['page' => $p]); ?>"
                       style="<?= ($p == $current_page_num) ? 'background:#0ea5e9;border-color:#0ea5e9;' : ''; ?>">
                        <?= $p; ?>
                    </a>
                </li>
                <?php endfor; ?>
                <!-- Next -->
                <li class="page-item <?= ($current_page_num >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link rounded-2 border" href="?<?= buildQuery(['page' => $current_page_num + 1]); ?>">&rsaquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
