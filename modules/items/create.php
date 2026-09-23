<?php
// modules/items/create.php

// ============================================
// LOAD FUNCTIONS
// ============================================
require_once __DIR__ . '/../../config/functions.php';

// ============================================
// PROSES FORM - DILAKUKAN DI ATAS
// ============================================

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=items');
}

$categories = fetchAll("SELECT id, name FROM categories ORDER BY name");

// Flash messages
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
$old = $_SESSION['old'] ?? [];
unset($_SESSION['error'], $_SESSION['success'], $_SESSION['old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $brand = trim($_POST['brand'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $min_quantity = (int)($_POST['min_quantity'] ?? 5);
    $condition = $_POST['condition'] ?? 'baik';
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? null;
    $purchase_price = (float)($_POST['purchase_price'] ?? 0);
    
    // Validasi
    $errors = [];
    if (empty($name)) $errors[] = 'Nama barang wajib diisi';
    if (empty($category_id)) $errors[] = 'Kategori wajib dipilih';
    if ($quantity < 0) $errors[] = 'Stok tidak boleh negatif';
    
    // Upload foto
    $photo = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo = uploadFile($_FILES['photo']);
        if ($photo === false) {
            $errors[] = 'Gagal upload foto. Pastikan format file benar (JPG, PNG, GIF, WebP) dan ukuran maksimal 2MB.';
        }
    }
    
    if (empty($errors)) {
        $code = generateItemCode();
        
        $id = insert('items', [
            'code' => $code,
            'name' => $name,
            'category_id' => $category_id,
            'brand' => $brand,
            'quantity' => $quantity,
            'min_quantity' => $min_quantity,
            'condition' => $condition,
            'location' => $location,
            'photo' => $photo,
            'description' => $description,
            'purchase_date' => $purchase_date,
            'purchase_price' => $purchase_price,
            'created_by' => currentUserId()
        ]);
        
        if ($id) {
            $_SESSION['success'] = 'Barang berhasil ditambahkan! Kode: ' . $code;
            redirect('index.php?url=items');
        } else {
            $_SESSION['error'] = 'Gagal menyimpan data. Silakan coba lagi.';
            redirect('index.php?url=items/create');
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
        $_SESSION['old'] = $_POST;
        redirect('index.php?url=items/create');
    }
}
?>

<!-- ============================================
STYLE
============================================ -->
<style>
/* ============================================
   STYLE UNTUK HALAMAN CREATE/EDIT
   ============================================ */
.card-form {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-form .card-header {
    background: #ffffff;
    border-bottom: 1px solid #eef2f7;
    padding: 16px 20px;
    border-radius: 12px 12px 0 0 !important;
}

.card-form .card-body {
    padding: 24px 20px;
}

/* Form */
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

/* Alert */
.alert-custom {
    border-radius: 10px;
    border: none;
    padding: 12px 18px;
    font-size: 13px;
}

.alert-custom.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-custom.alert-danger {
    background: #fee2e2;
    color: #991b1b;
}

/* Button */
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

/* Foto preview */
.photo-preview {
    width: 100px;
    height: 100px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid #eef2f7;
    background: #f8fafc;
}

/* Responsive */
@media (max-width: 768px) {
    .card-form .card-body {
        padding: 16px;
    }
    .photo-preview {
        width: 80px;
        height: 80px;
    }
}
</style>

<div class="container-fluid px-4">
    <!-- ============================================
    HEADER
    ============================================ -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-plus-circle text-success me-2"></i>Tambah Barang
            </h4>
            <p class="text-muted small mt-1">Tambahkan data barang baru ke inventaris</p>
        </div>
        <a href="index.php?url=items" class="btn btn-custom btn-custom-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
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
    FORM
    ============================================ -->
    <div class="card card-form">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <!-- Kolom Kiri -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Nama Barang <span class="required">*</span>
                            </label>
                            <input type="text" name="name" class="form-control-custom" 
                                   value="<?= htmlspecialchars($old['name'] ?? '') ?>" 
                                   placeholder="Masukkan nama barang" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Kategori <span class="required">*</span>
                            </label>
                            <select name="category_id" class="form-select-custom" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" 
                                    <?= ($old['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-custom">Merk / Brand</label>
                            <input type="text" name="brand" class="form-control-custom" 
                                   value="<?= htmlspecialchars($old['brand'] ?? '') ?>" 
                                   placeholder="Masukkan merk barang">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-custom">Lokasi</label>
                            <input type="text" name="location" class="form-control-custom" 
                                   value="<?= htmlspecialchars($old['location'] ?? '') ?>" 
                                   placeholder="Contoh: Ruang ICT Lt.2">
                        </div>
                    </div>
                    
                    <!-- Kolom Kanan -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Jumlah Stok</label>
                            <input type="number" name="quantity" class="form-control-custom" 
                                   value="<?= $old['quantity'] ?? 0 ?>" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-custom">Stok Minimal</label>
                            <input type="number" name="min_quantity" class="form-control-custom" 
                                   value="<?= $old['min_quantity'] ?? 5 ?>" min="0">
                            <div class="form-text-custom">
                                Peringatan akan muncul jika stok di bawah angka ini
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-custom">Kondisi</label>
                            <select name="condition" class="form-select-custom">
                                <option value="baik" <?= ($old['condition'] ?? '') == 'baik' ? 'selected' : '' ?>>Baik</option>
                                <option value="rusak" <?= ($old['condition'] ?? '') == 'rusak' ? 'selected' : '' ?>>Rusak</option>
                                <option value="perbaikan" <?= ($old['condition'] ?? '') == 'perbaikan' ? 'selected' : '' ?>>Perbaikan</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-custom">Foto Barang</label>
                            <input type="file" name="photo" class="form-control-custom" accept="image/*">
                            <div class="form-text-custom">
                                Format: JPG, PNG, GIF, WebP (Max 2MB)
                            </div>
                        </div>
                    </div>
                    
                    <!-- Full Width -->
                    <div class="col-12">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Tanggal Pembelian</label>
                                    <input type="date" name="purchase_date" class="form-control-custom" 
                                           value="<?= $old['purchase_date'] ?? '' ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Harga Pembelian</label>
                                    <input type="number" name="purchase_price" class="form-control-custom" 
                                           value="<?= $old['purchase_price'] ?? '' ?>" 
                                           step="0.01" min="0" placeholder="0">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label-custom">Deskripsi</label>
                            <textarea name="description" class="form-control-custom" rows="3" 
                                      placeholder="Deskripsi barang..."><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Tombol -->
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-custom btn-custom-primary">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <a href="index.php?url=items" class="btn btn-custom btn-custom-secondary">
                            <i class="fas fa-times me-1"></i> Batal
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>