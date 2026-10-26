<?php
// konsultasi.php
require_once 'includes/koneksi.php';
require_once 'includes/header.php';

// Reset sesi biodata jika diminta (misal ingin input pasien baru)
if (isset($_GET['reset'])) {
    unset($_SESSION['pasien_konsultasi']);
    header("Location: konsultasi.php");
    exit;
}

$pasien_lama = $_SESSION['pasien_konsultasi'] ?? [];
$nama_val = $pasien_lama['nama_pasien'] ?? '';
$umur_val = $pasien_lama['umur'] ?? '';
$jk_val = $pasien_lama['jenis_kelamin'] ?? '';
$alamat_val = $pasien_lama['alamat'] ?? '';
$foto_val = $pasien_lama['foto_pasien'] ?? '';
$has_foto = (!empty($foto_val) && file_exists('assets/img/pasien/' . $foto_val));

// Ambil daftar gejala dari database
$query_gejala = "SELECT * FROM gejala ORDER BY kode_gejala ASC";
$result_gejala = mysqli_query($koneksi, $query_gejala);
$gejalaList = [];
while ($row = mysqli_fetch_assoc($result_gejala)) {
    $gejalaList[] = $row;
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card card-custom p-4 p-md-5">
                <div class="text-center mb-5">
                    <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 rounded-pill">Formulir Konsultasi</span>
                    <h2 class="fw-bold fs-3 text-dark">Data Diri & Pemilihan Gejala</h2>
                    <p class="text-muted">Isi biodata Anda dan pilih gejala yang sedang Anda alami untuk mendapatkan hasil diagnosis awal.</p>
                </div>
                
                <form action="hasil.php" method="POST" enctype="multipart/form-data">
                    
                    <!-- Biodata Pasien -->
                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h4 class="mb-0 fs-5">1. Biodata Pasien</h4>
                        <?php if (!empty($pasien_lama)): ?>
                            <a href="konsultasi.php?reset=1" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Kosongkan biodata untuk pasien baru">
                                <i class="fa-solid fa-user-plus me-1"></i> Pasien Baru
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($pasien_lama)): ?>
                        <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 rounded-3 mb-4 p-3 small d-flex align-items-center">
                            <i class="fa-solid fa-circle-info fs-5 text-primary me-2"></i>
                            <div>
                                <strong>Biodata Tersimpan (Mode Cek Ulang):</strong> Nama, umur, jenis kelamin, alamat, dan foto otomatis terisi dari pemeriksaan sebelumnya.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama_pasien" class="form-control form-control-lg" placeholder="Masukkan nama..." value="<?= htmlspecialchars($nama_val); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Umur (Tahun)</label>
                            <input type="number" name="umur" class="form-control form-control-lg" placeholder="Contoh: 25" min="1" max="120" value="<?= htmlspecialchars((string)$umur_val); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Jenis Kelamin</label>
                            <div class="d-flex gap-4 border p-3 rounded-3 bg-light">
                                <div class="form-check cursor-pointer">
                                    <input class="form-check-input mt-1" type="radio" name="jenis_kelamin" id="jl_l" value="Laki-laki" <?= ($jk_val === 'Laki-laki') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label ps-1" for="jl_l" style="cursor: pointer;">Laki-laki</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input mt-1" type="radio" name="jenis_kelamin" id="jl_p" value="Perempuan" <?= ($jk_val === 'Perempuan') ? 'checked' : ''; ?> required>
                                    <label class="form-check-label ps-1" for="jl_p" style="cursor: pointer;">Perempuan</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Alamat Lengkap</label>
                            <input type="text" name="alamat" class="form-control form-control-lg" placeholder="Contoh: Jl. Merdeka No. 10, Jakarta" value="<?= htmlspecialchars($alamat_val); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Foto Kondisi Kulit <span class="text-muted fw-normal">(Opsional)</span></label>
                            <input type="hidden" name="foto_lama" id="fotoLamaInput" value="<?= htmlspecialchars($foto_val); ?>">
                            <div class="upload-area border rounded-3 p-4 text-center bg-light position-relative" id="uploadArea" style="cursor: pointer; border-style: dashed !important; border-color: #0d6efd !important;">
                                <input type="file" name="foto_pasien" id="fotoInput" accept="image/*" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 2;">
                                <div id="uploadPlaceholder" class="<?= $has_foto ? 'd-none' : ''; ?>">
                                    <i class="fa-solid fa-camera fs-2 text-primary mb-2"></i>
                                    <p class="mb-1 fw-semibold text-dark">Klik atau seret foto ke sini</p>
                                    <p class="text-muted small mb-0">Format: JPG, PNG, WEBP &bull; Maks. 2MB</p>
                                </div>
                                <div id="uploadPreview" class="<?= $has_foto ? '' : 'd-none'; ?>">
                                    <img id="previewImg" src="<?= $has_foto ? 'assets/img/pasien/' . htmlspecialchars($foto_val) : ''; ?>" alt="Preview" class="img-fluid rounded-3" style="max-height: 220px; object-fit: cover;">
                                    <p class="mt-2 mb-1 text-success fw-semibold">
                                        <i class="fa-solid fa-circle-check me-1"></i> 
                                        <span id="previewName"><?= $has_foto ? 'Foto tersimpan: ' . htmlspecialchars($foto_val) : ''; ?></span>
                                    </p>
                                    <div class="mt-2 position-relative" style="z-index: 3;">
                                        <span class="badge bg-primary bg-opacity-10 text-primary me-2"><i class="fa-solid fa-arrows-rotate me-1"></i> Klik untuk mengganti</span>
                                        <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3 py-0" id="btnHapusFoto" style="font-size: 0.8rem;">
                                            <i class="fa-solid fa-trash me-1"></i> Hapus Foto
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pemilihan Gejala -->
                    <h4 class="mb-4 fs-5 border-bottom pb-2">2. Pilih Gejala Penyakit</h4>
                    <p class="text-muted mb-4 small">Centang gejala-gejala di bawah ini yang paling sesuai dengan kondisi kulit Anda saat ini (dapat memilih lebih dari satu).</p>
                    
                    <div class="row g-3 mb-5">
                        <?php if (count($gejalaList) > 0): ?>
                            <?php foreach ($gejalaList as $g) : ?>
                                <div class="col-md-6">
                                    <div class="p-3 border rounded h-100 bg-white">
                                        <label class="form-label mb-2 d-block text-dark">
                                            <strong>[<?= $g['kode_gejala']; ?>]</strong> <?= htmlspecialchars($g['nama_gejala']); ?>
                                        </label>
                                        <select name="gejala[<?= $g['kode_gejala']; ?>]" class="form-select symptom-select">
                                            <option value="">-- Tidak Dipilih --</option>
                                            <option value="1">Sangat Yakin</option>
                                            <option value="0.8">Yakin</option>
                                            <option value="0.4">Kurang Yakin</option>
                                            <option value="0.2">Tidak</option>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12 text-center text-muted py-4">
                                <i class="fa-solid fa-triangle-exclamation fs-3 mb-2"></i>
                                <p>Belum ada data gejala dalam sistem. Silakan login sebagai admin untuk menambahkan gejala.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="text-center pt-3">
                        <button type="submit" class="btn btn-primary-custom w-100 text-uppercase" style="max-width: 400px; font-weight: 700; letter-spacing: 1px;">
                            <i class="fa-solid fa-microscope me-2"></i> Proses Diagnosis
                        </button>
                    </div>

                </form>

            </div>
</div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // === Gejala max 4 ===
    const selects = document.querySelectorAll('.symptom-select');
    const maxAllowed = 4;
    
    selects.forEach(function(select) {
        select.dataset.prev = select.value;
        
        select.addEventListener('change', function() {
            let selectedCount = 0;
            selects.forEach(s => {
                if (s.value !== "") selectedCount++;
            });
            if (selectedCount > maxAllowed) {
                Swal.fire({
                    title: 'Peringatan',
                    text: 'Maksimal gejala yang dapat dipilih adalah ' + maxAllowed + '!',
                    icon: 'warning',
                    confirmButtonText: 'Mengerti'
                });
                this.value = this.dataset.prev;
            } else {
                this.dataset.prev = this.value;
            }
        });
    });

    // === Preview Foto Upload ===
    const fotoInput = document.getElementById('fotoInput');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const uploadPreview = document.getElementById('uploadPreview');
    const previewImg = document.getElementById('previewImg');
    const previewName = document.getElementById('previewName');
    const btnHapusFoto = document.getElementById('btnHapusFoto');
    const fotoLamaInput = document.getElementById('fotoLamaInput');

    if (btnHapusFoto) {
        btnHapusFoto.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            if (fotoInput) fotoInput.value = '';
            if (fotoLamaInput) fotoLamaInput.value = '';
            if (previewImg) previewImg.src = '';
            if (previewName) previewName.textContent = '';
            if (uploadPreview) uploadPreview.classList.add('d-none');
            if (uploadPlaceholder) uploadPlaceholder.classList.remove('d-none');
        });
    }

    if (fotoInput) {
        fotoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 2 * 1024 * 1024; // 2MB
                if (file.size > maxSize) {
                    Swal.fire({
                        title: 'Peringatan',
                        text: 'Ukuran foto melebihi 2MB. Silakan pilih foto yang lebih kecil.',
                        icon: 'warning',
                        confirmButtonText: 'Mengerti'
                    });
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewName.textContent = file.name + ' (Foto baru dipilih)';
                    uploadPlaceholder.classList.add('d-none');
                    uploadPreview.classList.remove('d-none');
                    if (fotoLamaInput) fotoLamaInput.value = '';
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
