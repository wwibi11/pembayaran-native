<?php
// auth/login.php

require_once __DIR__ . '/../config/functions.php';

// Kalau sudah login, redirect ke dashboard
if (isLoggedIn()) {
    redirect('dashboard');
}

// Ambil dari settings
$app_name     = setting('nama_madin', 'TPQ MADIN');
$app_version  = '1.0.0';
$logoPath     = setting('logo', 'assets/img/logo.png');
$alamat       = setting('alamat', '');
$telepon      = setting('telepon', '');
$emailMadin   = setting('email_madin', '');
$tahunAjaran  = setting('tahun_ajaran', '');

// Path relatif untuk aset (karena file ini di dalam /auth/)
$base = '../';

// Cek apakah logo ada
$logoExists = $logoPath && file_exists(__DIR__ . '/../' . $logoPath);

// Demo credentials (matikan di production: setting('mode_dev') === '1')
$showDemo = true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= e($app_name) ?></title>
    <link href="<?= $base ?>vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="<?= $base ?>vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e8f0fe 0%, #d4e4f7 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container { max-width: 440px; width: 100%; }
        .login-card {
            background: #fff;
            border-radius: 20px;
            padding: 50px 40px 40px;
            box-shadow: 0 20px 60px rgba(44,107,158,0.15);
            position: relative;
            overflow: hidden;
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2c6b9e, #4a90d9, #2c6b9e);
            background-size: 200% 100%;
            animation: grad 3s ease infinite;
        }
        @keyframes grad {
            0%,100% { background-position: 0% 50%; }
            50%     { background-position: 100% 50%; }
        }

        /* Icon utama */
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2c6b9e, #4a90d9);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 25px rgba(44,107,158,0.3);
            overflow: hidden;
        }
        .login-icon i { font-size: 36px; color: #fff; }
        .login-icon img {
            width: 100%; height: 100%; object-fit: cover;
        }

        .login-header { text-align: center; margin-bottom: 32px; }
        .app-badge {
            display: inline-block;
            background: #e8f0fe;
            color: #2c6b9e;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .login-header h3 {
            color: #1a2634;
            font-weight: 800;
            font-size: 24px;
            margin-bottom: 6px;
        }
        .login-header .sub-title {
            color: #6b7a8f;
            font-size: 14px;
        }
        .login-header .tahun-ajaran {
            display: inline-block;
            margin-top: 6px;
            font-size: 11px;
            color: #8a94a6;
            background: #f8fafc;
            padding: 2px 10px;
            border-radius: 10px;
        }
        .divider-line {
            width: 50px;
            height: 3px;
            background: linear-gradient(90deg, #2c6b9e, #4a90d9);
            margin: 14px auto 0;
            border-radius: 2px;
        }

        /* Form */
        .form-group { margin-bottom: 20px; }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            display: block;
            margin-bottom: 6px;
        }
        .form-control {
            height: 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0 16px;
            font-size: 14px;
            width: 100%;
            transition: all 0.3s;
            background: #fafbfc;
        }
        .form-control:focus {
            border-color: #2c6b9e;
            box-shadow: 0 0 0 4px rgba(44,107,158,0.1);
            outline: none;
            background: #fff;
        }
        .input-group-icon { position: relative; }
        .input-group-icon .form-control { padding-left: 48px; }
        .input-group-icon .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
        }

        /* Tombol toggle password */
        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
        }
        .toggle-password:hover { color: #2c6b9e; }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .form-options .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #6b7280;
            cursor: pointer;
        }
        .form-options .remember-me input[type="checkbox"] {
            width: 18px; height: 18px;
            accent-color: #2c6b9e;
            margin: 0;
        }
        .form-options .forgot-link {
            font-size: 13px;
            color: #2c6b9e;
            text-decoration: none;
        }
        .form-options .forgot-link:hover { text-decoration: underline; }

        .btn-login {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #2c6b9e, #4a90d9);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(44,107,158,0.35);
        }
        .btn-login i { margin-right: 8px; }

        /* Alert */
        .alert {
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger  { background: #fef2f2; color: #b91c1c; border-left: 4px solid #dc2626; }
        .alert-success { background: #f0fdf4; color: #166534; border-left: 4px solid #22c55e; }

        /* Demo credentials */
        .demo-credentials {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            margin-top: 20px;
            border: 1px dashed #d1d5db;
        }
        .demo-credentials .demo-title {
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            color: #1a2634;
            margin-bottom: 8px;
        }
        .demo-credentials .cred-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 12px;
            border-bottom: 1px solid #edf2f7;
            gap: 8px;
        }
        .demo-credentials .cred-row:last-child { border-bottom: none; }
        .demo-credentials .cred-row .value {
            color: #1a2634;
            font-weight: 500;
            font-family: monospace;
            font-size: 11px;
            text-align: right;
            word-break: break-all;
        }

        /* Role badge */
        .role-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
        }
        .role-badge.admin  { background: #dbeafe; color: #1d4ed8; }
        .role-badge.kepala { background: #fef3c7; color: #92400e; }
        .role-badge.wali   { background: #d1fae5; color: #047857; }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #edf2f7;
        }
        .login-footer small {
            color: #8a94a6;
            font-size: 12px;
            line-height: 1.6;
        }
        .login-footer .footer-brand {
            font-weight: 600;
            color: #2c6b9e;
        }
        .login-footer .footer-contact {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin: 0 4px;
        }

        @media (max-width: 576px) {
            .login-card { padding: 32px 20px 28px; }
            .login-header h3 { font-size: 20px; }
            .login-icon { width: 64px; height: 64px; }
            .login-icon i { font-size: 28px; }
            .demo-credentials .cred-row {
                flex-direction: column;
                gap: 2px;
                align-items: flex-start;
            }
            .demo-credentials .cred-row .value { text-align: left; }
        }
    </style>
</head>
<body>
<div class="login-container">
    <div class="login-card">

        <!-- Icon / Logo -->
        <div class="login-icon">
            <?php if ($logoExists): ?>
                <img src="<?= $base . e($logoPath) ?>" alt="Logo">
            <?php else: ?>
                <i class="fas fa-mosque"></i>
            <?php endif; ?>
        </div>

        <!-- Header -->
        <div class="login-header">
            <span class="app-badge">
                <i class="fas fa-star"></i> TPQ &amp; Madin
            </span>
            <h3><?= e($app_name) ?></h3>
            <p class="sub-title">
                <i class="fas fa-wallet"></i> Sistem Pembayaran &amp; Administrasi
            </p>
            <?php if ($tahunAjaran): ?>
                <span class="tahun-ajaran">
                    <i class="fas fa-graduation-cap"></i> T.A. <?= e($tahunAjaran) ?>
                </span>
            <?php endif; ?>
            <div class="divider-line"></div>
        </div>

        <!-- Flash messages -->
        <?php if ($err = getFlash('error')): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
            </div>
        <?php endif; ?>
        <?php if ($ok = getFlash('success')): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= e($ok) ?>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form method="POST" action="proses_login.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

            <div class="form-group">
                <label>Email <span style="color:#dc2626;">*</span></label>
                <div class="input-group-icon">
                    <input type="email" name="email" class="form-control"
                           placeholder="nama@email.com"
                           value="<?= e($_COOKIE['remember_email'] ?? '') ?>"
                           required autofocus>
                    <i class="fas fa-envelope input-icon"></i>
                </div>
            </div>

            <div class="form-group">
                <label>Password <span style="color:#dc2626;">*</span></label>
                <div class="input-group-icon">
                    <input type="password" name="password" id="inputPassword"
                           class="form-control" placeholder="Masukkan password" required>
                    <i class="fas fa-lock input-icon"></i>
                    <button type="button" class="toggle-password"
                            onclick="togglePassword()" tabindex="-1"
                            title="Lihat/sembunyikan password">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember"
                           <?= isset($_COOKIE['remember_email']) ? 'checked' : '' ?>>
                    Ingat saya
                </label>
                <a href="#" class="forgot-link"
                   onclick="alert('Hubungi admin untuk reset password.'); return false;">
                    Lupa password?
                </a>
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Masuk ke Dashboard
            </button>
        </form>

        <!-- Info Akun Demo (HAPUS di production) -->
        <?php if ($showDemo): ?>
        <div class="demo-credentials">
            <div class="demo-title"><i class="fas fa-key"></i> Akun Default</div>

            <div class="cred-row">
                <span><span class="role-badge admin">Admin</span></span>
                <span class="value">admin@madin.id / admin123</span>
            </div>

            <div class="cred-row">
                <span><span class="role-badge kepala">Kepala</span></span>
                <span class="value">kepala@madin.id / kepala123</span>
            </div>

            <div class="cred-row">
                <span><span class="role-badge wali">Wali Santri</span></span>
                <span class="value">wali@madin.id / wali123</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="login-footer">
            <small>
                <span class="footer-brand">
                    <i class="fas fa-mosque"></i> <?= e($app_name) ?>
                </span>
                <br>
                &copy; <?= date('Y'); ?> · Versi <?= e($app_version) ?>

                <?php if ($alamat): ?>
                    <br>
                    <span class="footer-contact">
                        <i class="fas fa-map-marker-alt"></i> <?= e($alamat) ?>
                    </span>
                <?php endif; ?>

                <?php if ($telepon || $emailMadin): ?>
                    <br>
                    <?php if ($telepon): ?>
                        <span class="footer-contact">
                            <i class="fas fa-phone"></i>
                            <a href="tel:<?= e($telepon) ?>" class="text-decoration-none" style="color:#8a94a6;">
                                <?= e($telepon) ?>
                            </a>
                        </span>
                    <?php endif; ?>
                    <?php if ($emailMadin): ?>
                        <span class="footer-contact">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?= e($emailMadin) ?>" class="text-decoration-none" style="color:#8a94a6;">
                                <?= e($emailMadin) ?>
                            </a>
                        </span>
                    <?php endif; ?>
                <?php endif; ?>
            </small>
        </div>

    </div>
</div>

<script src="<?= $base ?>vendor/jquery/jquery.min.js"></script>
<script src="<?= $base ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword() {
        const input = document.getElementById('inputPassword');
        const icon  = document.getElementById('eyeIcon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Auto-focus ke password kalau email sudah terisi
    document.addEventListener('DOMContentLoaded', function() {
        const email = document.querySelector('input[name="email"]');
        const pass  = document.querySelector('input[name="password"]');
        if (email && email.value && pass) pass.focus();

        // Fokus ke email kalau kosong
        if (email && !email.value) email.focus();
    });
</script>
</body>
</html>