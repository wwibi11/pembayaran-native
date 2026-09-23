<?php
// views/sidebar.php

$app_name    = function_exists('setting') ? setting('nama_madin', 'TPQ MADIN') : 'TPQ MADIN';
$logoPath    = function_exists('setting') ? setting('logo', 'assets/img/logo.png') : 'assets/img/logo.png';
$current_url = $_GET['url'] ?? 'dashboard';
$current_mod = explode('/', trim($current_url, '/'))[0];
$user_role   = $_SESSION['user']['role'] ?? 'wali';
?>

<ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">

    <!-- ============================================
         BRAND
         ============================================ -->
    <div class="sidebar-brand d-flex align-items-center justify-content-center"
         style="padding: 20px 16px; border-bottom: 1px solid #edf2f7; min-height: 80px; width: 100%;">
        <a class="d-flex align-items-center" href="<?= BASE_URL ?>/dashboard"
           style="text-decoration: none; gap: 14px;">
            <div class="sidebar-brand-icon" style="flex-shrink: 0;">
                <?php if ($logoPath && file_exists(__DIR__ . '/../' . $logoPath)): ?>
                    <img src="<?= BASE_URL ?>/<?= e($logoPath) ?>" alt="Logo"
                         style="width: 40px; height: 40px; object-fit: contain;">
                <?php else: ?>
                    <i class="fas fa-mosque" style="font-size: 32px; color: #2c6b9e;"></i>
                <?php endif; ?>
            </div>
            <div class="sidebar-brand-text" style="color: #1a2634; font-weight: 700; font-size: 18px; line-height: 1.2; white-space: nowrap;">
                <?= e($app_name) ?>
                <small style="display: block; font-weight: 400; font-size: 11px; color: #8a94a6;">
                    Sistem Pembayaran
                </small>
            </div>
        </a>
    </div>

    <hr class="sidebar-divider my-0" style="margin: 0;">

    <!-- ============================================
         DASHBOARD (semua role)
         ============================================ -->
    <li class="nav-item" style="margin-top: 12px;">
        <a class="nav-link <?= $current_mod == 'dashboard' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/dashboard"
           style="padding: 14px 20px; margin: 6px 14px; border-radius: 10px;">
            <i class="fas fa-fw fa-tachometer-alt" style="width: 24px; font-size: 16px;"></i>
            <span style="font-size: 14px;">Dashboard</span>
        </a>
    </li>

    <?php if ($user_role === 'admin'): ?>
    <!-- ============================================
         ADMIN — MASTER DATA
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Data Master
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'kelas' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/kelas"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-layer-group" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Kelas / Level</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'santri' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/santri"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-user-graduate" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Data Santri</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'orang_tua' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/orang_tua"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-users" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Data Orang Tua</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'jenis_pembayaran' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/jenis_pembayaran"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-money-bill-wave" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Jenis Pembayaran</span>
        </a>
    </li>

    <!-- ============================================
         ADMIN — TRANSAKSI
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Transaksi
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'tagihan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/tagihan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-file-invoice-dollar" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Tagihan</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'pembayaran' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/pembayaran/verifikasi"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-check-circle" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Verifikasi Bayar</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'kenaikan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/kenaikan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-arrow-up" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Kenaikan Kelas</span>
        </a>
    </li>

    <!-- ============================================
         ADMIN — LAPORAN
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Laporan
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'laporan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/laporan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-chart-line" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Laporan & Export</span>
        </a>
    </li>

    <!-- ============================================
         ADMIN — SISTEM
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Sistem
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'users' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/users"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-users-cog" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Manajemen User</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'settings' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/settings"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-cog" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Pengaturan</span>
        </a>
    </li>
    <?php endif; ?>

    <?php if ($user_role === 'kepala'): ?>
    <!-- ============================================
         KEPALA — VIEW ONLY
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Monitoring
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'santri' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/santri"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-user-graduate" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Data Santri</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'kelas' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/kelas"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-layer-group" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Kelas / Level</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'tagihan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/tagihan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-file-invoice-dollar" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Data Tagihan</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'pembayaran' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/pembayaran"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-cash-register" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Data Pembayaran</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'kenaikan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/kenaikan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-book-reader" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Perjalanan Belajar</span>
        </a>
    </li>

    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Laporan
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'laporan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/laporan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-chart-line" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Laporan & Export</span>
        </a>
    </li>
    <?php endif; ?>

    <?php if ($user_role === 'wali'): ?>
    <!-- ============================================
         WALI SANTRI
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <div class="sidebar-heading" style="padding: 12px 20px 6px; font-size: 11px; color: #8a94a6; text-transform: uppercase; letter-spacing: 0.8px; font-weight: 700;">
        Anak Saya
    </div>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'anak' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/anak"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-child" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Daftar Anak</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'tagihan' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/tagihan"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-file-invoice-dollar" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Tagihan</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= ($current_mod == 'pembayaran' && strpos($current_url, 'upload') !== false) ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/pembayaran/upload"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-upload" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Upload Bukti Bayar</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= ($current_mod == 'pembayaran' && strpos($current_url, 'riwayat') !== false) ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/pembayaran/riwayat"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-history" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Riwayat Bayar</span>
        </a>
    </li>

    <li class="nav-item">
        <a class="nav-link <?= $current_mod == 'riwayat_kelas' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/riwayat_kelas"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px;">
            <i class="fas fa-book-reader" style="width: 24px; font-size: 15px;"></i>
            <span style="font-size: 14px;">Perjalanan Belajar</span>
        </a>
    </li>
    <?php endif; ?>

    <!-- Spacer -->
    <div style="flex: 1; min-height: 30px;"></div>

    <!-- ============================================
         PROFIL / LOGOUT (semua role)
         ============================================ -->
    <hr class="sidebar-divider" style="margin: 10px 20px;">

    <li class="nav-item">
        <a class="nav-link"
           href="<?= BASE_URL ?>/auth/logout.php"
           onclick="return confirm('Yakin ingin logout?');"
           style="padding: 13px 20px; margin: 4px 14px; border-radius: 10px; color: #dc2626 !important;">
            <i class="fas fa-sign-out-alt" style="width: 24px; font-size: 15px; color: #dc2626;"></i>
            <span style="font-size: 14px;">Logout</span>
        </a>
    </li>

</ul>