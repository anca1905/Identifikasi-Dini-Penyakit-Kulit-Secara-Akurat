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
                
                <form action="hasil.php" method="POST">
                    
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
                    </div>

                    <!-- Pemilihan Gejala -->
                    <h4 class="mb-4 fs-5 border-bottom pb-2">2. Pilih Gejala Penyakit</h4>
                    <p class="text-muted mb-4 small">Centang gejala-gejala di bawah ini yang paling sesuai dengan kondisi kulit Anda saat ini (dapat memilih lebih dari satu).</p>
                    
                    <div class="row g-3 mb-5">
                        <?php if (count($gejalaList) > 0): ?>
                            <?php foreach ($gejalaList as $g) : ?>
                                <div class="col-md-6">
                                    <input type="checkbox" name="gejala[]" value="<?= $g['kode_gejala']; ?>" id="gejala_<?= $g['kode_gejala']; ?>" class="symptom-checkbox">
                                    <label for="gejala_<?= $g['kode_gejala']; ?>" class="symptom-label w-100 h-100">
                                        <div class="checkbox-circle flex-shrink-0"></div>
                                        <span class="ps-1"><strong>[<?= $g['kode_gejala']; ?>]</strong> <?= htmlspecialchars($g['nama_gejala']); ?></span>
                                    </label>
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
    const checkboxes = document.querySelectorAll('.symptom-checkbox');
    const maxAllowed = 4;
    
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.symptom-checkbox:checked').length;
            if (checkedCount > maxAllowed) {
                this.checked = false;
                alert("Maksimal gejala yang dapat dipilih adalah " + maxAllowed + "!");
            }
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
