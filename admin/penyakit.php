<?php
// admin/penyakit.php
require_once 'includes/header.php';

// Hapus Data
if (isset($_GET['del'])) {
    $kode = mysqli_real_escape_string($koneksi, $_GET['del']);
    
    // Hapus gambar jika ada
    $cek_gambar_lama = mysqli_query($koneksi, "SELECT gambar FROM penyakit WHERE kode_penyakit='$kode'");
    $row_lama = mysqli_fetch_assoc($cek_gambar_lama);
    if ($row_lama['gambar'] && file_exists('../assets/img/penyakit/' . $row_lama['gambar'])) {
        unlink('../assets/img/penyakit/' . $row_lama['gambar']);
    }
    
    mysqli_query($koneksi, "DELETE FROM penyakit WHERE kode_penyakit='$kode'");
    echo "<script>alert('Data berhasil dihapus!'); window.location='penyakit.php';</script>";
    exit;
}

// Tambah Data
if (isset($_POST['add'])) {
    $kode = mysqli_real_escape_string($koneksi, $_POST['kode_penyakit']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_penyakit']);
    $solusi = mysqli_real_escape_string($koneksi, $_POST['solusi']);
    
    // Handle File Upload
    $gambar = '';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = $kode . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], '../assets/img/penyakit/' . $gambar);
    }
    
    $cek = mysqli_query($koneksi, "SELECT * FROM penyakit WHERE kode_penyakit='$kode'");
    if (mysqli_num_rows($cek) > 0) {
        echo "<script>alert('Error: Kode Penyakit tersebut sudah digunakan!');</script>";
    } else {
        mysqli_query($koneksi, "INSERT INTO penyakit (kode_penyakit, nama_penyakit, solusi, gambar) VALUES ('$kode', '$nama', '$solusi', '$gambar')");
        echo "<script>alert('Data penyakit berhasil ditambahkan!'); window.location='penyakit.php';</script>";
        exit;
    }
}

// Edit Data
if (isset($_POST['edit'])) {
    $kode = mysqli_real_escape_string($koneksi, $_POST['kode_penyakit_lama']);
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama_penyakit']);
    $solusi = mysqli_real_escape_string($koneksi, $_POST['solusi']);
    
    // Handle File Upload
    $gambar_update = "";
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar = $kode . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['gambar']['tmp_name'], '../assets/img/penyakit/' . $gambar);
        
        // Hapus gambar lama jika ada
        $cek_gambar_lama = mysqli_query($koneksi, "SELECT gambar FROM penyakit WHERE kode_penyakit='$kode'");
        $row_lama = mysqli_fetch_assoc($cek_gambar_lama);
        if ($row_lama['gambar'] && file_exists('../assets/img/penyakit/' . $row_lama['gambar'])) {
            unlink('../assets/img/penyakit/' . $row_lama['gambar']);
        }
        
        $gambar_update = ", gambar='$gambar'";
    }
    
    mysqli_query($koneksi, "UPDATE penyakit SET nama_penyakit='$nama', solusi='$solusi' $gambar_update WHERE kode_penyakit='$kode'");
    echo "<script>alert('Data penyakit berhasil diperbarui!'); window.location='penyakit.php';</script>";
    exit;
}

$query = mysqli_query($koneksi, "SELECT * FROM penyakit ORDER BY kode_penyakit ASC");
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0 text-dark">Data Penyakit</h3>
    <!-- Tombol Tambah Modal -->
    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="fa-solid fa-plus me-2"></i> Tambah Penyakit
    </button>
</div>

<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="py-3 ps-4 text-muted fw-semibold" width="10%">Kode</th>
                        <th class="py-3 text-muted fw-semibold" width="25%">Nama Penyakit</th>
                        <th class="py-3 text-muted fw-semibold" width="50%">Solusi Pengobatan</th>
                        <th class="py-3 text-muted fw-semibold text-center" width="15%">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($query) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?= $row['kode_penyakit']; ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['nama_penyakit']); ?></td>
                            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.9rem;" class="pe-2 text-muted"><?= nl2br(htmlspecialchars($row['solusi'])); ?></div></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary rounded-circle me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['kode_penyakit']; ?>" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <a href="penyakit.php?del=<?= $row['kode_penyakit']; ?>" class="btn btn-sm btn-outline-danger rounded-circle" onclick="return confirm('Yakin ingin menghapus <?=$row['nama_penyakit'];?>? Data aturan(rule) yang berelasi juga akan terhapus!');" title="Hapus">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="editModal<?= $row['kode_penyakit']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form action="" method="POST" enctype="multipart/form-data">
                                    <div class="modal-content border-0 shadow rounded-4">
                                        <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                                            <h5 class="modal-title fw-bold">Edit Data Penyakit</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body px-4 py-4">
                                            <input type="hidden" name="kode_penyakit_lama" value="<?= $row['kode_penyakit']; ?>">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Kode Penyakit</label>
                                                <input type="text" class="form-control form-control-lg bg-light" value="<?= $row['kode_penyakit']; ?>" disabled>
                                                <small class="text-muted">Kode tidak dapat diubah (Primary Key).</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Nama Penyakit</label>
                                                <input type="text" name="nama_penyakit" class="form-control form-control-lg" value="<?= htmlspecialchars($row['nama_penyakit']); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Solusi & Penanganan</label>
                                                <textarea name="solusi" class="form-control form-control-lg" rows="4" required><?= htmlspecialchars($row['solusi']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Gambar Penyakit</label>
                                                <input type="file" name="gambar" class="form-control form-control-lg" accept="image/*">
                                                <?php if(!empty($row['gambar'])): ?>
                                                    <div class="mt-2">
                                                        <img src="../assets/img/penyakit/<?= $row['gambar'] ?>" alt="Gambar Saat Ini" class="img-thumbnail" style="max-height: 100px;">
                                                    </div>
                                                <?php endif; ?>
                                                <small class="text-muted">Biarkan kosong jika tidak ingin mengubah gambar.</small>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top-0 pb-4 px-4">
                                            <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" name="edit" class="btn btn-primary rounded-pill px-4 fw-medium shadow-sm">Simpan Perubahan</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada data penyakit.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="addModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="modal-content border-0 shadow rounded-4">
                <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                    <h5 class="modal-title fw-bold">Tambah Data Penyakit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode Penyakit <span class="text-danger">*</span></label>
                        <input type="text" name="kode_penyakit" class="form-control form-control-lg" placeholder="Contoh: P01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Penyakit <span class="text-danger">*</span></label>
                        <input type="text" name="nama_penyakit" class="form-control form-control-lg" placeholder="Contoh: Kudis (Scabies)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Solusi Pengobatan <span class="text-danger">*</span></label>
                        <textarea name="solusi" class="form-control form-control-lg" rows="4" placeholder="Tuliskan saran penanganan di sini..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Gambar Penyakit</label>
                        <input type="file" name="gambar" class="form-control form-control-lg" accept="image/*">
                    </div>
                </div>
                <div class="modal-footer border-top-0 pb-4 px-4">
                    <button type="button" class="btn btn-light rounded-pill px-4 fw-medium" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="add" class="btn btn-primary rounded-pill px-4 fw-medium shadow-sm"><i class="fa-solid fa-plus me-1"></i> Simpan Penyakit</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
