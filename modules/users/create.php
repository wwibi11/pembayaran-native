<?php
// modules/users/create.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$errors = [];
$old = [
    'name'      => '',
    'email'     => '',
    'phone'     => '',
    'role'      => 'wali',
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $old = [
        'name'      => trim($_POST['name'] ?? ''),
        'email'     => trim($_POST['email'] ?? ''),
        'phone'     => trim($_POST['phone'] ?? ''),
        'role'      => $_POST['role'] ?? 'wali',
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    $password = $_POST['password'] ?? '';

    // Validasi
    if ($old['name'] === '')  $errors[] = 'Nama wajib diisi.';
    if ($old['email'] === '') $errors[] = 'Email wajib diisi.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
    if (!in_array($old['role'], ['admin','kepala','wali'])) $errors[] = 'Role tidak valid.';

    // Cek duplikat email
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM users WHERE email = ?", [$old['email']]);
        if ($dup) $errors[] = 'Email sudah terdaftar.';
    }

    if (!$errors) {
        try {
            $userId = insert('users', [
                'name'      => $old['name'],
                'email'     => $old['email'],
                'password'  => password_hash($password, PASSWORD_DEFAULT),
                'role'      => $old['role'],
                'phone'     => $old['phone'] ?: null,
                'is_active' => $old['is_active'],
            ]);

            setFlash('success', 'User "' . $old['name'] . '" berhasil ditambahkan.');
            redirect('users/detail/' . $userId);

        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/users" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-user-plus text-primary"></i> Tambah User Baru
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Buat akun login admin, kepala, atau wali
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
                                       value="<?= e($old['name']) ?>" required autofocus>
                            </div>
                            <div class="form-group">
                                <label>No. HP</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?= e($old['phone']) ?>"
                                       placeholder="08123456789">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group" style="flex:2;">
                                <label>Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= e($old['email']) ?>"
                                       placeholder="nama@email.com" required>
                            </div>
                            <div class="form-group">
                                <label>Password <span class="text-danger">*</span></label>
                                <input type="text" name="password" class="form-control"
                                       placeholder="Minimal 6 karakter" required>
                                <small class="text-muted">
                                    Akan ditampilkan plaintext supaya mudah diinfokan ke user.
                                </small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Role <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="d-block border rounded p-3"
                                           style="cursor:pointer;" id="role_admin">
                                        <input type="radio" name="role" value="admin"
                                               <?= $old['role']==='admin'?'checked':'' ?>
                                               onchange="pilihRole(this)">
                                        <strong class="ml-1">🔴 Admin</strong>
                                        <div class="small text-muted mt-1 ml-3">
                                            Akses penuh semua fitur
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="d-block border rounded p-3"
                                           style="cursor:pointer;" id="role_kepala">
                                        <input type="radio" name="role" value="kepala"
                                               <?= $old['role']==='kepala'?'checked':'' ?>
                                               onchange="pilihRole(this)">
                                        <strong class="ml-1">🟡 Kepala</strong>
                                        <div class="small text-muted mt-1 ml-3">
                                            Hanya lihat &amp; laporan
                                        </div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="d-block border rounded p-3"
                                           style="cursor:pointer;" id="role_wali">
                                        <input type="radio" name="role" value="wali"
                                               <?= $old['role']==='wali'?'checked':'' ?>
                                               onchange="pilihRole(this)">
                                        <strong class="ml-1">🟢 Wali</strong>
                                        <div class="small text-muted mt-1 ml-3">
                                            Lihat anak &amp; upload bukti
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input"
                                       id="is_active" name="is_active" value="1"
                                       <?= $old['is_active'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="is_active">
                                    <strong>Aktifkan user ini</strong>
                                </label>
                            </div>
                            <small class="text-muted ml-4">
                                User nonaktif tidak bisa login
                            </small>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Simpan User
                        </button>
                        <a href="<?= BASE_URL ?>/users" class="btn btn-light">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow border-info">
                <div class="card-header py-2 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle"></i> Info Role
                    </h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>🔴 Admin</strong></p>
                    <ul class="pl-3 mb-3">
                        <li>Kelola semua data</li>
                        <li>Generate tagihan</li>
                        <li>Verifikasi pembayaran</li>
                        <li>Kelola user</li>
                    </ul>
                    <p class="mb-2"><strong>🟡 Kepala</strong></p>
                    <ul class="pl-3 mb-3">
                        <li>Lihat data santri, tagihan</li>
                        <li>Lihat laporan</li>
                        <li>Tidak bisa ubah data</li>
                    </ul>
                    <p class="mb-2"><strong>🟢 Wali</strong></p>
                    <ul class="pl-3 mb-0">
                        <li>Lihat tagihan anaknya</li>
                        <li>Upload bukti bayar</li>
                        <li>Tidak bisa akses data lain</li>
                    </ul>
                    <div class="alert alert-warning py-2 mt-3 mb-0" style="font-size:11px;">
                        <i class="fas fa-lightbulb"></i>
                        Untuk role <strong>wali</strong>, setelah user dibuat, jangan lupa hubungkan ke data orang tua via menu <strong>Data Orang Tua → Kelola Akun</strong>.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function pilihRole(el) {
    // Reset semua
    document.querySelectorAll('[id^="role_"]').forEach(l => {
        l.classList.remove('border-primary', 'bg-light');
    });
    // Highlight terpilih
    el.closest('label').classList.add('border-primary', 'bg-light');
}
document.addEventListener('DOMContentLoaded', function() {
    const checked = document.querySelector('input[name="role"]:checked');
    if (checked) pilihRole(checked);
});
</script>