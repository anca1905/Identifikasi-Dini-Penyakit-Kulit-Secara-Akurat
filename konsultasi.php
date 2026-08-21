<?php
// konsultasi.php
require_once 'includes/koneksi.php';
require_once 'includes/header.php';

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
                    <h4 class="mb-4 fs-5 border-bottom pb-2">1. Biodata Pasien</h4>
                    <div class="row g-3 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama_pasien" class="form-control form-control-lg" placeholder="Masukkan nama..." required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Umur (Tahun)</label>
                            <input type="number" name="umur" class="form-control form-control-lg" placeholder="Contoh: 25" min="1" max="120" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Jenis Kelamin</label>
                            <div class="d-flex gap-4 border p-3 rounded-3 bg-light">
                                <div class="form-check cursor-pointer">
                                    <input class="form-check-input mt-1" type="radio" name="jenis_kelamin" id="jl_l" value="Laki-laki" required>
                                    <label class="form-check-label ps-1" for="jl_l" style="cursor: pointer;">Laki-laki</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input mt-1" type="radio" name="jenis_kelamin" id="jl_p" value="Perempuan" required>
                                    <label class="form-check-label ps-1" for="jl_p" style="cursor: pointer;">Perempuan</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Alamat Lengkap</label>
                            <input type="text" name="alamat" class="form-control form-control-lg" placeholder="Contoh: Jl. Merdeka No. 10, Jakarta" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Foto Kondisi Kulit <span class="text-muted fw-normal">(Opsional)</span></label>
                            <div class="upload-area border rounded-3 p-4 text-center bg-light position-relative" id="uploadArea" style="cursor: pointer; border-style: dashed !important; border-color: #0d6efd !important;">
                                <input type="file" name="foto_pasien" id="fotoInput" accept="image/*" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 2;">
                                <div id="uploadPlaceholder">
                                    <i class="fa-solid fa-camera fs-2 text-primary mb-2"></i>
                                    <p class="mb-1 fw-semibold text-dark">Klik atau seret foto ke sini</p>
                                    <p class="text-muted small mb-0">Format: JPG, PNG, WEBP &bull; Maks. 2MB</p>
                                </div>
                                <div id="uploadPreview" class="d-none">
                                    <img id="previewImg" src="" alt="Preview" class="img-fluid rounded-3" style="max-height: 220px; object-fit: cover;">
                                    <p class="mt-2 mb-0 text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i> <span id="previewName"></span></p>
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
                                            <option value="0.2">Tidak Tahu</option>
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
                alert("Maksimal gejala yang dapat dipilih adalah " + maxAllowed + "!");
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

    if (fotoInput) {
        fotoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const maxSize = 2 * 1024 * 1024; // 2MB
                if (file.size > maxSize) {
                    alert('Ukuran foto melebihi 2MB. Silakan pilih foto yang lebih kecil.');
                    this.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    previewName.textContent = file.name;
                    uploadPlaceholder.classList.add('d-none');
                    uploadPreview.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
