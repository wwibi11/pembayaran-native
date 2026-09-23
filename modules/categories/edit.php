<?php
// modules/categories/edit.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID kategori tidak valid!';
    redirect('index.php?url=categories');
}

$category = fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);

if (!$category) {
    $_SESSION['error'] = 'Data kategori tidak ditemukan!';
    redirect('index.php?url=categories');
}

$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $code = trim(strtoupper($_POST['code'] ?? ''));
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fas fa-tag');
    
    $errors = [];
    if (empty($name)) $errors[] = 'Nama kategori wajib diisi';
    if (empty($code)) $errors[] = 'Kode kategori wajib diisi';
    if (strlen($code) > 20) $errors[] = 'Kode maksimal 20 karakter';
    
    // Cek kode unik (kecuali untuk dirinya sendiri)
    if (empty($errors)) {
        $existing = fetchOne("SELECT id FROM categories WHERE code = ? AND id != ?", [$code, $id]);
        if ($existing) {
            $errors[] = 'Kode "' . $code . '" sudah digunakan. Gunakan kode lain.';
        }
    }
    
    if (empty($errors)) {
        // ============================================
        // PERBAIKAN: Pakai updateData() bukan update()
        // ============================================
        $updated = updateData('categories', [
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'icon' => $icon
        ], 'id', $id);
        
        if ($updated !== false) {
            $_SESSION['success'] = 'Kategori "' . $name . '" berhasil diperbarui!';
            redirect('index.php?url=categories');
        } else {
            $_SESSION['error'] = 'Gagal menyimpan data. Silakan coba lagi.';
            redirect('index.php?url=categories/edit&id=' . $id);
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
        redirect('index.php?url=categories/edit&id=' . $id);
    }
}

ob_end_flush();
?>

<!-- ============================================
STYLE - SAMA SEPERTI SEBELUMNYA
============================================ -->
<style>
/* Sama seperti create.php */
.card-form {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-form .card-body {
    padding: 24px 20px;
}

.form-label-custom {
    font-weight: 600;
    font-size: 12px;
    color: #475569;
    margin-bottom: 4px;
    display: block;
}

.form-label-custom .required {
    color: #dc2626;
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

.form-control-custom[disabled] {
    background: #f1f5f9;
    cursor: not-allowed;
}

.form-text-custom {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
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
</style>

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-edit text-warning me-2"></i>Edit Kategori
            </h4>
            <p class="text-muted small mt-1">
                Edit kategori: <span class="badge bg-secondary"><?= htmlspecialchars($category['code']) ?></span>
            </p>
        </div>
        <a href="index.php?url=categories" class="btn btn-custom btn-custom-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Alert -->
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

    <!-- Form -->
    <div class="card card-form">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Nama Kategori <span class="required">*</span>
                            </label>
                            <input type="text" name="name" class="form-control-custom" 
                                   value="<?= htmlspecialchars($category['name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Kode <span class="required">*</span>
                            </label>
                            <input type="text" name="code" class="form-control-custom" 
                                   value="<?= htmlspecialchars($category['code']) ?>" 
                                   required maxlength="20">
                            <div class="form-text-custom">Kode unik, maksimal 20 karakter</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Icon</label>
                            <input type="text" name="icon" class="form-control-custom" 
                                   value="<?= htmlspecialchars($category['icon'] ?? 'fas fa-tag') ?>" 
                                   placeholder="Contoh: fas fa-laptop">
                            <div class="form-text-custom">Class icon Font Awesome</div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label-custom">Deskripsi</label>
                            <textarea name="description" class="form-control-custom" rows="3"><?= htmlspecialchars($category['description']) ?></textarea>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-custom btn-custom-primary">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="index.php?url=categories" class="btn btn-custom btn-custom-secondary">
                            <i class="fas fa-times me-1"></i> Batal
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>