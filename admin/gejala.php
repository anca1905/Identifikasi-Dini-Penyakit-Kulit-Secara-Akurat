<?php
// admin/gejala.php
require_once 'includes/header.php';

// Hapus Data
if (isset($_GET['del'])) {
    $kode = mysqli_real_escape_string($koneksi, $_GET['del']);
    mysqli_query($koneksi, "DELETE FROM gejala WHERE kode_gejala='$kode'");
    echo "<script>alert('Data berhasil dihapus!'); window.location='gejala.php';</script>";
    exit;
}

// Tambah Data
if (isset($_POST['add'])) {
    $kode = mysqli_real_escape_string($koneksi, $_POST['kode_gejala']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_gejala']);
    
    $cek = mysqli_query($koneksi, "SELECT * FROM gejala WHERE kode_gejala='$kode'");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Error: Kode Gejala tersebut sudah digunakan!');</script>";
    } else {
        mysqli_query($koneksi, "INSERT INTO gejala (kode_gejala, nama_gejala) VALUES ('$kode', '$nama')");
        echo "<script>alert('Data gejala berhasil ditambahkan!'); window.location='gejala.php';</script>";
        exit;
    }
}

// Edit Data
if (isset($_POST['edit'])) {
    $kode = mysqli_real_escape_string($koneksi, $_POST['kode_gejala_lama']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_gejala']);
    
    mysqli_query($koneksi, "UPDATE gejala SET nama_gejala='$nama' WHERE kode_gejala='$kode'");
    echo "<script>alert('Data gejala berhasil diperbarui!'); window.location='gejala.php';</script>";
    exit;
}

$query = mysqli_query($koneksi, "SELECT * FROM gejala ORDER BY kode_gejala ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark">Data Gejala</h3>
    <button type="button" class="btn btn-warning rounded-pill px-4 shadow-sm text-dark fw-medium" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa-solid fa-plus me-2"></i> Tambah Gejala
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4 text-muted fw-semibold" width="15%">Kode Gejala</th>
                        <th class="py-3 text-muted fw-semibold" width="65%">Nama / Indikator Gejala</th>
                        <th class="py-3 text-muted fw-semibold text-center" width="20%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-warning"><?= $row['kode_gejala']; ?></td>
                            <td class="fw-medium text-dark"><?= htmlspecialchars($row['nama_gejala']); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary rounded-circle me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['kode_gejala']; ?>" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <a href="gejala.php?del=<?= $row['kode_gejala']; ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('Yakin ingin menghapus <?=$row['nama_gejala'];?>?');" title="Hapus">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $row['kode_gejala']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form action="" method="POST">
                                    <div class="modal-content border-0 shadow rounded-4">
                                        <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                                            <h5 class="modal-title fw-bold">Edit Data Gejala</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body px-4 py-4">
                                            <input type="hidden" name="kode_gejala_lama" value="<?= $row['kode_gejala']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Kode Gejala</label>
                                                <input type="text" class="form-control form-control-lg bg-light" value="<?= $row['kode_gejala']; ?>" disabled>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Rincian Gejala Terlihat</label>
                                                <textarea name="nama_gejala" class="form-control form-control-lg" rows="3" required><?= htmlspecialchars($row['nama_gejala']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top-0 pb-4 px-4">
                                            <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="edit" class="btn btn-warning rounded-pill px-4 fw-medium shadow-sm">Simpan Perubahan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada data gejala.</td></tr>
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
                    <h5 class="modal-title fw-bold">Tambah Data Gejala</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode Gejala <span class="text-danger">*</span></label>
                        <input type="text" name="kode_gejala" class="form-control form-control-lg" placeholder="Contoh: G01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama / Indikator Gejala <span class="text-danger">*</span></label>
                        <textarea name="nama_gejala" class="form-control form-control-lg" rows="3" placeholder="Tuliskan gejala secara deskriptif..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-warning rounded-pill px-4 fw-medium shadow-sm"><i class="fa-solid fa-plus me-1"></i> Simpan Gejala</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
