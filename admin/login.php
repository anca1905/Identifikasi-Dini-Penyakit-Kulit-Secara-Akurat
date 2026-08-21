<?php
// admin/login.php
session_start();
require_once '../includes/koneksi.php';

// Jika sudah login, dilempar ke index admin
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM admin WHERE username='$username'";
    $result = mysqli_query($koneksi, $query);

    if (mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $row['id_admin'];
            $_SESSION['admin_nama'] = $row['nama_lengkap'];
            header("Location: index.php");
            exit;
        } else {
            $error = 'Username atau Password salah!';
        }
    } else {
        $error = 'Username atau Password salah!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin | Puskesmas Siompu</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background-color: #f1f5f9; font-family: 'Inter', sans-serif;}
        .login-card { border: none; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); overflow: hidden; }
        .bg-login-aside { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; display: flex; flex-direction: column; justify-content: center; padding: 40px;}
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-3 py-md-0">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-xl-8 col-lg-10">
            <div class="card login-card row g-0 flex-row">
                <div class="col-md-5 bg-login-aside d-none d-md-flex text-center text-md-start">
                    <h2 class="fw-bold mb-3"><i class="fa-solid fa-stethoscope me-1"></i> Puskesmas Siompu</h2>
                    <p class="mb-0 text-white-50" style="line-height: 1.6;">Sistem Pakar Identifikasi Dini Penyakit Kulit berbasis Web Menggunakan Metode Dempster-Shafer secara dinamis.</p>
                </div>
                <div class="col-md-7 p-4 p-md-5 bg-white">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold">Selamat Datang Kembali!</h4>
                        <p class="text-muted small">Silakan masukkan kredensial Anda untuk masuk sebagai Administrator.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 border-0 rounded-3 shadow-sm d-flex align-items-center">
                            <i class="fa-solid fa-triangle-exclamation minicustom mb-0 me-2"></i> <?= $error; ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="username" class="form-control form-control-lg border-start-0 bg-light" placeholder="Masukkan username" required autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control form-control-lg border-start-0 bg-light" placeholder="••••••••" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 btn-lg rounded-pill fw-bold" style="background-color: #0ea5e9; border-color: #0ea5e9; box-shadow: 0 4px 6px -1px rgba(14, 165, 233, 0.4);">Login Admin</button>
                        
                        <div class="text-center mt-4">
                            <a href="../index.php" class="text-decoration-none text-muted fw-medium"><i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Beranda</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
<!-- Logout Success Modal -->
<div class="modal fade" id="logoutSuccessModal" tabindex="-1" aria-labelledby="logoutSuccessModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center pb-4">
        <div class="mb-3 text-success">
            <i class="fa-solid fa-circle-check" style="font-size: 4rem;"></i>
        </div>
        <h4 class="mb-3 fw-bold">Logout Berhasil!</h4>
        <p class="text-muted mb-4">Anda telah berhasil keluar dari sistem admin.</p>
        <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var logoutSuccessModal = new bootstrap.Modal(document.getElementById('logoutSuccessModal'));
        logoutSuccessModal.show();
        
        if (window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('logout');
            window.history.replaceState({path: url.href}, '', url.href);
        }
    });
</script>
<?php endif; ?>

</body>
</html>
