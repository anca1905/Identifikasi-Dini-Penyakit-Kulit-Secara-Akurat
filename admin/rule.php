<?php
// admin/rule.php
require_once 'includes/header.php';

// Hapus Data Rule
if (isset($_GET['del'])) {
    $id = (int) $_GET['del'];
    mysqli_query($koneksi, "DELETE FROM basis_pengetahuan WHERE id_rule=$id");
    echo "<script>alert('Rule pakar berhasil dihapus!'); window.location='rule.php';</script>";
    exit;
}

// Tambah Data Rule Baru
if (isset($_POST['add'])) {
    $kode_p = mysqli_real_escape_string($koneksi, $_POST['kode_penyakit']);
    $kode_g = mysqli_real_escape_string($koneksi, $_POST['kode_gejala']);
    $bobot = (float) $_POST['nilai_densitas'];
    
    // Validasi rule ganda
    $cek = mysqli_query($koneksi, "SELECT * FROM basis_pengetahuan WHERE kode_penyakit='$kode_p' AND kode_gejala='$kode_g'");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Error: Basis Pengetahuan untuk kombinasi ini sudah ada!');</script>";
    } else {
        mysqli_query($koneksi, "INSERT INTO basis_pengetahuan (kode_penyakit, kode_gejala, nilai_densitas) VALUES ('$kode_p', '$kode_g', $bobot)");
        echo "<script>alert('Rule berhasil ditambahkan!'); window.location='rule.php';</script>";
        exit;
    }
}

// Edit Data Rule
if (isset($_POST['edit'])) {
    $id_rule = (int) $_POST['id_rule'];
    $kode_p = mysqli_real_escape_string($koneksi, $_POST['kode_penyakit']);
    $kode_g = mysqli_real_escape_string($koneksi, $_POST['kode_gejala']);
    $bobot = (float) $_POST['nilai_densitas'];
    
    // Cek duplikasi jika diubah ke relasi lain
    $cek = mysqli_query($koneksi, "SELECT * FROM basis_pengetahuan WHERE kode_penyakit='$kode_p' AND kode_gejala='$kode_g' AND id_rule != $id_rule");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Gagal! Kombinasi rule sudah ada!');</script>";
    } else {
        mysqli_query($koneksi, "UPDATE basis_pengetahuan SET kode_penyakit='$kode_p', kode_gejala='$kode_g', nilai_densitas=$bobot WHERE id_rule=$id_rule");
        echo "<script>alert('Rule berhasil diperbarui!'); window.location='rule.php';</script>";
        exit;
    }
}

$query = mysqli_query($koneksi, "
    SELECT b.*, p.nama_penyakit, g.nama_gejala 
    FROM basis_pengetahuan b
    JOIN penyakit p ON b.kode_penyakit = p.kode_penyakit
    JOIN gejala g ON b.kode_gejala = g.kode_gejala
    ORDER BY b.kode_penyakit ASC, b.kode_gejala ASC
");

// Ambil list dropdown
$list_p = mysqli_query($koneksi, "SELECT * FROM penyakit ORDER BY kode_penyakit ASC");
$list_g = mysqli_query($koneksi, "SELECT * FROM gejala ORDER BY kode_gejala ASC");

// Simpan list di array untuk dipakai di modal
$arr_p = []; while ($r = mysqli_fetch_assoc($list_p)) $arr_p[] = $r;
$arr_g = []; while ($r = mysqli_fetch_assoc($list_g)) $arr_g[] = $r;
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Basis Pengetahuan</h3>
        <p class="text-muted mb-0 small">Manajemen Bobot/Densitas Pakar Metode Dempster-Shafer</p>
    </div>
    <button type="button" class="btn btn-success rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa-solid fa-plus me-2"></i> Tambah Rule Baru
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.95rem;">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4 text-muted fw-semibold" width="5%">No</th>
                        <th class="py-3 text-muted fw-semibold" width="25%">Penyakit</th>
                        <th class="py-3 text-muted fw-semibold" width="40%">Gejala Terkait</th>
                        <th class="py-3 text-muted fw-semibold text-center" width="15%">Nilai Densitas (Belief)</th>
                        <th class="py-3 text-muted fw-semibold text-center" width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): $no=1; ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td class="ps-4 text-muted"><?= $no++; ?></td>
                            <td><span class="fw-bold text-dark">[<?= $row['kode_penyakit']; ?>]</span> <?= htmlspecialchars($row['nama_penyakit']); ?></td>
                            <td class="text-muted"><span class="fw-medium text-dark">[<?= $row['kode_gejala']; ?>]</span> <?= htmlspecialchars($row['nama_gejala']); ?></td>
                            <td class="text-center">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 fs-6 rounded-pill">
                                    <?= number_format($row['nilai_densitas'], 2); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary rounded-circle me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id_rule']; ?>" title="Edit Rule">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <a href="rule.php?del=<?= $row['id_rule']; ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('Yakin ingin menghapus Rule ini?');" title="Hapus Rule">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $row['id_rule']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form action="" method="POST">
                                    <div class="modal-content border-0 shadow rounded-4">
                                        <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                                            <h5 class="modal-title fw-bold">Edit Basis Pengetahuan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body px-4 py-4">
                                            <input type="hidden" name="id_rule" value="<?= $row['id_rule']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Pilih Penyakit</label>
                                                <select name="kode_penyakit" class="form-select form-select-lg" required>
                                                    <?php foreach($arr_p as $p): ?>
                                                        <option value="<?= $p['kode_penyakit']; ?>" <?= ($row['kode_penyakit'] == $p['kode_penyakit']) ? 'selected':''; ?>><?= $p['kode_penyakit']; ?> - <?= $p['nama_penyakit']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label fw-semibold">Pilih Gejala Terkait</label>
                                                <select name="kode_gejala" class="form-select form-select-lg" required>
                                                    <?php foreach($arr_g as $g): ?>
                                                        <option value="<?= $g['kode_gejala']; ?>" <?= ($row['kode_gejala'] == $g['kode_gejala']) ? 'selected':''; ?>><?= $g['kode_gejala']; ?> - <?= $g['nama_gejala']; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="p-3 bg-light rounded-3 border">
                                                <label class="form-label fw-bold text-success mb-2"><i class="fa-solid fa-scale-balanced me-1"></i> Nilai Densitas (Belief Pakar)</label>
                                                <input type="number" name="nilai_densitas" class="form-control form-control-lg fw-bold text-center text-success" style="font-size: 1.5rem;" step="0.01" min="0" max="1" value="<?= $row['nilai_densitas']; ?>" required>
                                                <small class="text-muted text-center d-block mt-2">Masukan nilai desimal dari rentang <strong>0.00</strong> hingga <strong>1.00</strong>. (Contoh: 0.85)</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top-0 pb-4 px-4">
                                            <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="edit" class="btn btn-success rounded-pill px-4 fw-medium shadow-sm">Simpan Rule</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="fa-solid fa-box-open fs-2 mb-2"></i><br>Belum ada relasi basis pengetahuan/rule.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="POST">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Tambah Basis Pengetahuan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pilih Penyakit <span class="text-danger">*</span></label>
                        <select name="kode_penyakit" class="form-select form-select-lg" required>
                            <option value="" hidden>Pilih Penyakit...</option>
                            <?php foreach($arr_p as $p): ?>
                                <option value="<?= $p['kode_penyakit']; ?>"><?= $p['kode_penyakit']; ?> - <?= $p['nama_penyakit']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Pilih Gejala Terjadi <span class="text-danger">*</span></label>
                        <select name="kode_gejala" class="form-select form-select-lg" required>
                            <option value="" hidden>Pilih Gejala...</option>
                            <?php foreach($arr_g as $g): ?>
                                <option value="<?= $g['kode_gejala']; ?>"><?= $g['kode_gejala']; ?> - <?= $g['nama_gejala']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="p-3 bg-light rounded-3 border border-success-subtle">
                        <label class="form-label fw-bold text-success mb-2"><i class="fa-solid fa-scale-balanced me-1"></i> Nilai Densitas Pakar <span class="text-danger">*</span></label>
                        <input type="number" name="nilai_densitas" class="form-control form-control-lg fw-bold text-center text-success" style="font-size: 1.5rem;" step="0.01" min="0" max="1" placeholder="Misal: 0.8" required>
                        <small class="text-muted text-center d-block mt-2">Nilai probabilitas keyakinan pakar terhadap aturan ini. (0.01 s/d 1.00)</small>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-success rounded-pill px-4 fw-medium shadow-sm"><i class="fa-solid fa-plus me-1"></i> Tambahkan Rule</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
