<?php
// modules/users/edit.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
if (!$row) { setFlash('error', 'User tidak ditemukan.'); redirect('users'); }

$isSelf = ($row['id'] == currentUser()['id']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $data = [
        'name'      => trim($_POST['name'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'role'      => $_POST['role'] ?? $row['role'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($data['name'] === '')  $errors[] = 'Nama wajib diisi.';
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (!in_array($data['role'], ['admin','kepala','wali'])) $errors[] = 'Role tidak valid.';

    // Cek duplikat email
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $id]);
        if ($dup) $errors[] = 'Email sudah dipakai user lain.';
    }

    // Proteksi: tidak bisa ubah role/nonaktifkan diri sendiri
    if ($isSelf) {
        if ($data['role'] !== $row['role']) {
            $errors[] = 'Tidak bisa mengubah role sendiri.';
        }
        if (!$data['is_active']) {
            $errors[] = 'Tidak bisa menonaktifkan akun sendiri.';
        }
    }

    if (!$errors) {
        try {
            update('users', $data, 'id = ?', [$id]);

            // Update phone di orang_tua juga kalau ada
            if ($data['role'] === 'wali') {
                execute("UPDATE orang_tua SET no_hp = ? WHERE user_id = ?", 
                        [$data['phone'] ?: null, $id]);
            }

            setFlash('success', 'User berhasil diperbarui.');
            redirect('users/detail/' . $id);
        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    $row = array_merge($row, $data);
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/users" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-user-edit text-warning"></i> Edit User
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($row['name']) ?> <?= $isSelf ? '(Anda sendiri)' : '' ?>
            </p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($isSelf): ?>
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i>
            Anda sedang mengedit akun sendiri. Role &amp; status tidak bisa diubah untuk mencegah lockout.
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                        <div class="form-row">
                            <div class="form-group" style="flex:2;">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                       value="<?= e($row['name']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>No. HP</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?= e($row['phone']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($row['email']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Role</label>
                            <div class="row">
                                <?php
                                $roles = [
                                    'admin'  => ['label' => '🔴 Admin',  'desc' => 'Akses penuh'],
                                    'kepala' => ['label' => '🟡 Kepala', 'desc' => 'Lihat & laporan'],
                                    'wali'   => ['label' => '🟢 Wali',   'desc' => 'Lihat anak & upload'],
                                ];
                                foreach ($roles as $key => $r):
                                ?>
                                    <div class="col-md-4">
                                        <label class="d-block border rounded p-3"
                                               style="cursor:pointer; <?= $isSelf ? 'opacity:0.5;' : '' ?>"
                                               id="role_<?= $key ?>">
                                            <input type="radio" name="role" value="<?= $key ?>"
                                                   <?= $row['role']===$key?'checked':'' ?>
                                                   <?= $isSelf ? 'disabled' : '' ?>
                                                   onchange="pilihRole(this)">
                                            <strong class="ml-1"><?= $r['label'] ?></strong>
                                            <div class="small text-muted mt-1 ml-3"><?= $r['desc'] ?></div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input"
                                       id="is_active" name="is_active" value="1"
                                       <?= $row['is_active'] ? 'checked' : '' ?>
                                       <?= $isSelf ? 'disabled' : '' ?>>
                                <label class="custom-control-label" for="is_active">
                                    <strong>Aktif</strong>
                                </label>
                            </div>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Perbarui
                        </button>
                        <a href="<?= BASE_URL ?>/users" class="btn btn-light">Batal</a>
                        <a href="<?= BASE_URL ?>/users/reset_password/<?= $id ?>"
                           class="btn btn-warning float-right">
                            <i class="fas fa-key"></i> Reset Password
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-3 border-info">
                <div class="card-header py-2 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle"></i> Ringkasan
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">ID</td>
                            <td>#<?= $row['id'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Role</td>
                            <td>
                                <span class="badge bg-<?= 
                                    $row['role']==='admin' ? 'danger' : 
                                    ($row['role']==='kepala' ? 'warning text-dark' : 'success') ?>">
                                    <?= ucfirst($row['role']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td><small><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Update</td>
                            <td><small><?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></small></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function pilihRole(el) {
    document.querySelectorAll('[id^="role_"]').forEach(l => {
        l.classList.remove('border-primary', 'bg-light');
    });
    el.closest('label').classList.add('border-primary', 'bg-light');
}
document.addEventListener('DOMContentLoaded', function() {
    const checked = document.querySelector('input[name="role"]:checked');
    if (checked) pilihRole(checked);
});
</script>