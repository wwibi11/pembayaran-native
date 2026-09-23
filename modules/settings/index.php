<?php
// modules/settings/index.php

require_once __DIR__ . '/../../config/functions.php';

// ============================================
// DEFINISIKAN KONSTANTA JIKA BELUM ADA
// ============================================
if (!defined('APP_NAME')) {
    define('APP_NAME', 'Sistem Inventaris Barang');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

// Redirect jika bukan super admin
if (!isSuperAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Hanya Super Admin yang dapat mengakses pengaturan.';
    redirect('index.php?url=dashboard');
}

// Ambil semua pengaturan
$settings = fetchAll("SELECT * FROM settings ORDER BY `key`");

// Group settings by category
$grouped = [];
foreach ($settings as $s) {
    $category = explode('_', $s['key'])[0] ?? 'general';
    if (!isset($grouped[$category])) {
        $grouped[$category] = [];
    }
    $grouped[$category][] = $s;
}

// Flash messages
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = 0;
    $errors = [];
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0) {
            $setting_key = substr($key, 8);
            $setting_value = trim($value);
            
            // Update setting
            $result = update('settings', ['value' => $setting_value], '`key` = ?', [$setting_key]);
            if ($result !== false) {
                $updated++;
            } else {
                $errors[] = "Gagal update: $setting_key";
            }
        }
    }
    
    if ($updated > 0) {
        $_SESSION['success'] = "$updated pengaturan berhasil diperbarui!";
    }
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
    
    redirect('index.php?url=settings');
}

// Categories label
$categoryLabels = [
    'general' => 'Umum',
    'app' => 'Aplikasi',
    'loan' => 'Peminjaman',
    'notification' => 'Notifikasi',
    'security' => 'Keamanan'
];
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN SETTINGS
   ============================================ */
.card-settings {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
}

.card-settings .card-header {
    background: #ffffff;
    border-bottom: 1px solid #eef2f7;
    padding: 14px 20px;
    border-radius: 12px 12px 0 0 !important;
}

.card-settings .card-header h6 {
    font-weight: 600;
    color: #1a2634;
    margin: 0;
    font-size: 14px;
}

.card-settings .card-body {
    padding: 20px;
}

.alert-custom {
    border-radius: 10px;
    border: none;
    padding: 12px 18px;
    font-size: 13px;
    border-left: 4px solid transparent;
}

.alert-custom.alert-success {
    background: #dcfce7 !important;
    color: #166534 !important;
    border-left-color: #22c55e !important;
}

.alert-custom.alert-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border-left-color: #dc2626 !important;
}

.btn-custom {
    border-radius: 8px;
    padding: 8px 20px;
    font-size: 13px;
    font-weight: 500;
}

.btn-custom-primary {
    background: #2563eb;
    color: #fff;
    border: none;
}

.btn-custom-primary:hover {
    background: #1d4ed8;
    color: #fff;
}

.btn-custom-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-custom-secondary:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.form-label-custom {
    font-weight: 600;
    font-size: 12px;
    color: #475569;
    margin-bottom: 4px;
    display: block;
}

.form-control-custom,
.form-select-custom {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    font-size: 13px;
    background: #fafbfc;
    transition: all 0.2s;
    width: 100%;
}

.form-control-custom:focus,
.form-select-custom:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    background: #ffffff;
    outline: none;
}

.form-text-custom {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
}

.form-control-custom[disabled] {
    background: #f1f5f9;
    cursor: not-allowed;
}

.settings-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #ffffff;
    flex-shrink: 0;
}

.settings-icon.general { background: #2563eb; }
.settings-icon.app { background: #8b5cf6; }
.settings-icon.loan { background: #f59e0b; }
.settings-icon.notification { background: #22c55e; }
.settings-icon.security { background: #dc2626; }

.settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.settings-header .header-title h4 {
    font-size: 18px;
    font-weight: 700;
    color: #1a2634;
    margin: 0;
}

.settings-header .header-title h4 i {
    color: #2563eb;
    margin-right: 10px;
}

.settings-header .header-title .sub-title {
    font-size: 13px;
    color: #8a94a6;
    margin-top: 2px;
}

@media (max-width: 768px) {
    .settings-header {
        flex-direction: column;
        align-items: stretch;
    }
    .btn-custom {
        width: 100%;
        text-align: center;
    }
}
</style>

<div class="container-fluid px-4">
    <!-- ============================================
    HEADER
    ============================================ -->
    <div class="settings-header">
        <div class="header-title">
            <h4>
                <i class="fas fa-cog"></i>
                Pengaturan Sistem
            </h4>
            <div class="sub-title">
                <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                Kelola pengaturan dan konfigurasi sistem
            </div>
        </div>
        <a href="index.php?url=dashboard" class="btn btn-custom btn-custom-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- ============================================
    ALERT
    ============================================ -->
    <?php if ($success): ?>
    <div class="alert alert-custom alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ============================================
    FORM SETTINGS
    ============================================ -->
    <form method="POST">
        <?php foreach ($grouped as $category => $items): ?>
        <div class="card card-settings mb-4">
            <div class="card-header">
                <div class="d-flex align-items-center gap-3">
                    <div class="settings-icon <?= $category ?>">
                        <i class="fas fa-<?= $category == 'general' ? 'cog' : ($category == 'app' ? 'desktop' : ($category == 'loan' ? 'hand-holding' : ($category == 'notification' ? 'bell' : 'shield-alt'))) ?>"></i>
                    </div>
                    <h6 class="mb-0">
                        <?= $categoryLabels[$category] ?? ucfirst($category) ?>
                    </h6>
                    <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($items as $setting): ?>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                <?= ucfirst(str_replace('_', ' ', $setting['key'])) ?>
                            </label>
                            <?php if (in_array($setting['key'], ['maintenance_mode', 'enable_notifications'])): ?>
                            <select name="setting_<?= $setting['key'] ?>" class="form-select-custom">
                                <option value="true" <?= $setting['value'] == 'true' ? 'selected' : '' ?>>Ya</option>
                                <option value="false" <?= $setting['value'] == 'false' ? 'selected' : '' ?>>Tidak</option>
                            </select>
                            <?php elseif (strpos($setting['key'], 'duration') !== false || strpos($setting['key'], 'max_') !== false): ?>
                            <input type="number" name="setting_<?= $setting['key'] ?>" class="form-control-custom" 
                                   value="<?= htmlspecialchars($setting['value']) ?>" min="0">
                            <?php elseif (strpos($setting['key'], 'email') !== false): ?>
                            <input type="email" name="setting_<?= $setting['key'] ?>" class="form-control-custom" 
                                   value="<?= htmlspecialchars($setting['value']) ?>">
                            <?php else: ?>
                            <input type="text" name="setting_<?= $setting['key'] ?>" class="form-control-custom" 
                                   value="<?= htmlspecialchars($setting['value']) ?>">
                            <?php endif; ?>
                            <?php if ($setting['description']): ?>
                            <div class="form-text-custom"><?= htmlspecialchars($setting['description']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ============================================
        TOMBOL SIMPAN
        ============================================ -->
        <div class="card card-settings">
            <div class="card-body text-center py-4">
                <button type="submit" class="btn btn-custom btn-custom-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i> Simpan Semua Pengaturan
                </button>
                <p class="text-muted small mt-2">
                    <i class="fas fa-info-circle me-1"></i> 
                    Perubahan akan langsung berlaku setelah disimpan
                </p>
            </div>
        </div>
    </form>

    <!-- ============================================
    INFORMASI SISTEM
    ============================================ -->
    <div class="card card-settings mt-4">
        <div class="card-header">
            <h6><i class="fas fa-info-circle text-primary me-2"></i>Informasi Sistem</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <div class="text-center">
                        <div class="h5 mb-0 fw-bold text-primary">
                            <?= defined('APP_NAME') ? APP_NAME : 'Sistem Inventaris Barang' ?>
                        </div>
                        <div class="small text-muted">Nama Aplikasi</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-center">
                        <div class="h5 mb-0 fw-bold text-success">
                            <?= defined('APP_VERSION') ? APP_VERSION : '2.0.0' ?>
                        </div>
                        <div class="small text-muted">Versi</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-center">
                        <div class="h5 mb-0 fw-bold text-info"><?= date('Y-m-d') ?></div>
                        <div class="small text-muted">Tanggal</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="text-center">
                        <div class="h5 mb-0 fw-bold text-warning">
                            <?= PHP_VERSION ?>
                        </div>
                        <div class="small text-muted">PHP Version</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>