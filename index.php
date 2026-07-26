<?php 
// index.php
require_once 'includes/koneksi.php';
require_once 'includes/header.php'; 
?>

<div class="container">
    <div class="hero-section px-4 px-md-5 text-center text-md-start mb-5">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0 pe-lg-5">
                <span class="badge bg-primary bg-opacity-10 text-primary mb-3 px-3 py-2 rounded-pill">Sistem Pakar Kulit AI</span>
                <h1 class="hero-title">Identifikasi Dini Penyakit Kulit Secara Akurat</h1>
                <p class="hero-text">Ketahui potensi penyakit kulit berdasarkan gejala yang Anda alami secara cepat dan tepat menggunakan teknologi kecerdasan buatan berbasis <strong>Metode Dempster-Shafer</strong>.</p>
                <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-md-start">
                    <a href="konsultasi.php" class="btn btn-primary-custom">
                        Mulai Konsultasi <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-6 text-center">
                <img src="https://images.unsplash.com/photo-1606902631526-9d3e524ca865?q=80&w=1000&auto=format&fit=crop" alt="Dokter Dermatologi" class="img-fluid rounded-4 shadow-lg" style="object-fit: cover; aspect-ratio: 4/3; border: 8px solid white;">
            </div>
        </div>
    </div>

    <!-- Keunggulan Section -->
    <div class="row mb-5 g-4 py-4">
        <div class="col-12 text-center mb-4">
            <h2 class="fw-bold fs-3 text-dark">Mengapa Memilih Kami?</h2>
            <p class="text-muted">Keunggulan menggunakan platform deteksi dini kami.</p>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-4 text-center">
                <div class="icon-box mx-auto">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <h4 class="mb-3 fs-5 fw-bold">Cepat & Real-time</h4>
                <p class="text-muted mb-0">Proses diagnosis dilakukan secara instan tanpa perlu waktu yang lama.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-4 text-center">
                <div class="icon-box mx-auto">
                    <i class="fa-solid fa-brain"></i>
                </div>
                <h4 class="mb-3 fs-5 fw-bold">Teori Dempster-Shafer</h4>
                <p class="text-muted mb-0">Sistem mengkalkulasi probabilitas dan keakuratan dari setiap gejala yang Anda pilih berdasarkan rasio penalaran pakar.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-custom p-4 text-center">
                <div class="icon-box mx-auto">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <h4 class="mb-3 fs-5 fw-bold">Akses Kapan Saja</h4>
                <p class="text-muted mb-0">Platform responsif dan mudah diakses melalui Web dan Mobile Browser dimana pun Anda berada.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
