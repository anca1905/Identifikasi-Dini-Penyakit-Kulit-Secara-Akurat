<?php
// admin/includes/header.php
session_start();
require_once '../includes/koneksi.php';

// Cek autentikasi session
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Puskesmas Siompu</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #1e293b; overflow-x: hidden;}
        
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            min-height: 100vh;
            background: #ffffff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.03);
            transition: all 0.3s;
            position: sticky;
            top: 0;
            z-index: 99;
        }
        
        @media (max-width: 991.98px) {
            #sidebar {
                margin-left: -250px;
                position: fixed;
            }
            #sidebar.active { margin-left: 0; }
        }
        
        .sidebar-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { padding: 4px 16px; align-items: center; }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #64748b;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #f0f9ff;
            color: #0ea5e9;
        }
        
        .sidebar-menu i { margin-right: 12px; font-size: 1.1em; width: 22px; text-align: center;}
        
        #content { flex: 1; min-height: 100vh; padding-bottom: 30px; display: flex; flex-direction: column;}
        .top-navbar {
            background: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            z-index: 98;
        }
        .stat-card { border: none; border-radius: 16px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
    </style>
</head>
<body class="d-flex">

    <!-- Sidebar -->
    <nav id="sidebar">
        <div class="sidebar-header d-flex align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-stethoscope text-primary me-2"></i> Puskesmas Siompu</h5>
        </div>
        
        <div class="p-0">
            <div class="text-center mb-4 mt-4">
                <div class="rounded-circle bg-primary bg-opacity-10 d-inline-flex align-items-center justify-content-center text-primary mb-2" style="width: 55px; height: 55px; font-size: 20px;">
                    <i class="fa-regular fa-user"></i>
                </div>
                <h6 class="fw-bold mb-0"><?= htmlspecialchars($_SESSION['admin_nama']); ?></h6>
                <small class="text-muted fw-medium">Administrator</small>
            </div>
            
            <p class="text-muted small text-uppercase fw-bold px-4 mb-2 mt-4" style="letter-spacing: 0.5px; font-size: 0.75rem;">Menu Beranda</p>
            <ul class="sidebar-menu">
                <li><a href="index.php" class="<?= ($current_page == 'index.php') ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            </ul>

            <p class="text-muted small text-uppercase fw-bold px-4 mb-2 mt-4" style="letter-spacing: 0.5px; font-size: 0.75rem;">Master Data</p>
            <ul class="sidebar-menu">
                <li><a href="penyakit.php" class="<?= ($current_page == 'penyakit.php' || $current_page == 'action_penyakit.php') ? 'active' : ''; ?>"><i class="fa-solid fa-virus"></i> Data Penyakit</a></li>
                <li><a href="gejala.php" class="<?= ($current_page == 'gejala.php' || $current_page == 'action_gejala.php') ? 'active' : ''; ?>"><i class="fa-solid fa-head-side-cough"></i> Data Gejala</a></li>
                <li><a href="rule.php" class="<?= ($current_page == 'rule.php' || $current_page == 'action_rule.php') ? 'active' : ''; ?>"><i class="fa-solid fa-brain"></i> Basis Pengetahuan</a></li>
            </ul>

            <p class="text-muted small text-uppercase fw-bold px-4 mb-2 mt-4" style="letter-spacing: 0.5px; font-size: 0.75rem;">Laporan</p>
            <ul class="sidebar-menu">
                <li><a href="history.php" class="<?= ($current_page == 'history.php') ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Konsultasi</a></li>
            </ul>
        </div>
    </nav>

    <!-- Page Content -->
    <div id="content" class="w-100">
        <div class="top-navbar">
            <div class="d-lg-none d-flex align-items-center">
                <button type="button" id="sidebarCollapse" class="btn btn-light d-lg-none me-2">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-stethoscope text-primary"></i> Puskesmas Siompu</h5>
            </div>
            <div class="d-none d-lg-block">
                <span class="text-muted fw-medium"><i class="fa-regular fa-calendar me-2"></i> <?= date('d F Y'); ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="../index.php" class="btn btn-sm btn-light border d-none d-md-inline-flex align-items-center" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square me-2 text-muted"></i> Website</a>
                <a href="#" class="btn btn-sm btn-danger d-inline-flex align-items-center px-3" data-bs-toggle="modal" data-bs-target="#logoutModal"><i class="fa-solid fa-right-from-bracket me-2"></i> Keluar</a>
            </div>
        </div>
        
        <!-- Logout Modal -->
        <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
              <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center pb-4">
                <div class="mb-3 text-warning">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 4rem;"></i>
                </div>
                <h4 class="mb-3 fw-bold">Konfirmasi Logout</h4>
                <p class="text-muted mb-4">Apakah Anda yakin ingin mengakhiri sesi ini dan keluar?</p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Batal</button>
                    <a href="logout.php" class="btn btn-danger px-4">Ya, Keluar</a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="container-fluid px-4 px-md-5">
