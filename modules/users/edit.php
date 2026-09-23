<?php
// modules/users/edit.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan super admin
if (!isSuperAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Hanya Super Admin yang dapat mengedit user.';
    redirect('index.php?url=dashboard');
}

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID user tidak valid!';
    redirect('index.php?url=users');
}

// Ambil data user
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

if (!$user) {
    $_SESSION['error'] = 'Data user tidak ditemukan!';
    redirect('index.php?url=users');
}

$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $errors = [];
    if (empty($name)) $errors[] = 'Nama wajib diisi';
    if (empty($username)) $errors[] = 'Username wajib diisi';
    if (empty($email)) $errors[] = 'Email wajib diisi';
    if ($password && $password !== $password_confirm) $errors[] = 'Konfirmasi password tidak cocok';
    if ($password && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter';
    if (!isValidEmail($email)) $errors[] = 'Format email tidak valid';
    if (!empty($phone) && !isValidPhone($phone)) $errors[] = 'Format telepon tidak valid';
    
    // Cek username unik (kecuali dirinya sendiri)
    if (empty($errors)) {
        $check = fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
        if ($check) $errors[] = 'Username "' . $username . '" sudah digunakan';
    }
    
    if (empty($errors)) {
        $check = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $id]);
        if ($check) $errors[] = 'Email "' . $email . '" sudah digunakan';
    }
    
    if (empty($errors)) {
        $data = [
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'phone' => $phone,
            'address' => $address,
            'is_active' => $is_active
        ];
        
        if ($password) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $updated = updateData('users', $data, 'id', $id);
        
        if ($updated !== false) {
            $_SESSION['success'] = 'User "' . $name . '" berhasil diperbarui!';
            redirect('index.php?url=users');
        } else {
            $_SESSION['error'] = 'Gagal menyimpan data. Silakan coba lagi.';
            redirect('index.php?url=users/edit&id=' . $id);
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
        redirect('index.php?url=users/edit&id=' . $id);
    }
}

ob_end_flush();
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN EDIT USER
   ============================================ */
.card-form {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-form .card-body {
    padding: 24px 20px;
}

.card-form .card-header {
    background: #ffffff;
    border-bottom: 1px solid #eef2f7;
    padding: 14px 20px;
    border-radius: 12px 12px 0 0 !important;
}

.card-form .card-header h6 {
    font-weight: 600;
    color: #1a2634;
    margin: 0;
    font-size: 14px;
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

.users-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.users-header .header-title h4 {
    font-size: 18px;
    font-weight: 700;
    color: #1a2634;
    margin: 0;
}

.users-header .header-title h4 i {
    color: #2563eb;
    margin-right: 10px;
}

.users-header .header-title .sub-title {
    font-size: 13px;
    color: #8a94a6;
    margin-top: 2px;
}

@media (max-width: 768px) {
    .users-header {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="container-fluid px-4">
    <!-- ============================================
    HEADER
    ============================================ -->
    <div class="users-header">
        <div class="header-title">
            <h4>
                <i class="fas fa-user-edit"></i>
                Edit User
            </h4>
            <div class="sub-title">
                <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                Edit data user: <span class="badge bg-secondary"><?= htmlspecialchars($user['username']) ?></span>
            </div>
        </div>
        <a href="index.php?url=users" class="btn btn-custom btn-custom-secondary btn-sm">
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
        <div class="card-header">
            <h6><i class="fas fa-user me-2"></i>Form Edit User</h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Nama Lengkap <span class="required">*</span>
                            </label>
                            <input type="text" name="name" class="form-control-custom" 
                                   value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Username <span class="required">*</span>
                            </label>
                            <input type="text" name="username" class="form-control-custom" 
                                   value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">
                                Email <span class="required">*</span>
                            </label>
                            <input type="email" name="email" class="form-control-custom" 
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Telepon</label>
                            <input type="text" name="phone" class="form-control-custom" 
                                   value="<?= htmlspecialchars($user['phone']) ?>" 
                                   placeholder="08xxxxxxxxxx">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Password Baru</label>
                            <input type="password" name="password" class="form-control-custom" 
                                   placeholder="Kosongkan jika tidak diubah">
                            <div class="form-text-custom">Minimal 6 karakter</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Konfirmasi Password</label>
                            <input type="password" name="password_confirm" class="form-control-custom" 
                                   placeholder="Ketik ulang password baru">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Role <span class="required">*</span></label>
                            <select name="role" class="form-select-custom" required>
                                <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="staff" <?= $user['role'] == 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="super_admin" <?= $user['role'] == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3 form-check mt-4">
                            <input type="checkbox" name="is_active" class="form-check-input" id="is_active" 
                                   <?= $user['is_active'] == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Akun Aktif</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label class="form-label-custom">Alamat</label>
                            <textarea name="address" class="form-control-custom" rows="2"><?= htmlspecialchars($user['address']) ?></textarea>
                        </div>
                    </div>
                    <div class="col-12">
                        <hr>
                        <button type="submit" class="btn btn-custom btn-custom-primary">
                            <i class="fas fa-save me-1"></i> Update
                        </button>
                        <a href="index.php?url=users" class="btn btn-custom btn-custom-secondary">
                            <i class="fas fa-times me-1"></i> Batal
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>