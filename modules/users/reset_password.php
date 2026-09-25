<?php
// modules/users/reset_password.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user) { setFlash('error', 'User tidak ditemukan.'); redirect('users'); }

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $mode = $_POST['mode'] ?? 'auto';

    if ($mode === 'auto') {
        // Auto-generate password
        $password = 'madin' . rand(1000, 9999);
    } else {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter.';
        }
    }

    if (!$errors) {
        try {
            update('users', [
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ], 'id = ?', [$id]);

            $success = $password;

        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/users/detail/<?= $id ?>" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-key text-warning"></i> Reset Password
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($user['name']) ?> · <?= e($user['email']) ?>
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

    <?php if ($success): ?>
        <div class="alert alert-success">
            <h5 class="mb-2"><i class="fas fa-check-circle"></i> Password berhasil direset!</h5>
            <p class="mb-2">Password baru untuk <strong><?= e($user['name']) ?></strong>:</p>
            <div style="background:#fff; padding:12px 16px; border-radius:8px; border:2px dashed #16a34a; display:inline-block;">
                <code style="font-size:20px; font-weight:700; color:#15803d; letter-spacing:1px;">
                    <?= e($success) ?>
                </code>
            </div>
            <p class="mt-2 mb-0 small">
                <i class="fas fa-info-circle"></i>
                <strong>Catat / kirim password ini ke user.</strong>
                Password tidak akan ditampilkan lagi.
            </p>
            <hr>
            <a href="<?= BASE_URL ?>/users/detail/<?= $id ?>" class="btn btn-primary">
                Kembali ke Detail User
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                            <div class="form-group">
                                <label>Mode Reset</label>
                                <div class="custom-control custom-radio mb-2">
                                    <input type="radio" name="mode" value="auto" id="mode_auto"
                                           class="custom-control-input" checked
                                           onchange="toggleMode()">
                                    <label class="custom-control-label" for="mode_auto">
                                        <strong>Auto-generate</strong>
                                        <div class="small text-muted">
                                            Sistem buat password otomatis (format: madin1234)
                                        </div>
                                    </label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" name="mode" value="manual" id="mode_manual"
                                           class="custom-control-input"
                                           onchange="toggleMode()">
                                    <label class="custom-control-label" for="mode_manual">
                                        <strong>Manual</strong>
                                        <div class="small text-muted">
                                            Admin tentukan password sendiri
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="wrapManual" style="display:none;">
                                <label>Password Baru <span class="text-danger">*</span></label>
                                <input type="text" name="password" class="form-control"
                                       placeholder="Minimal 6 karakter" minlength="6">
                            </div>

                            <div class="alert alert-warning py-2">
                                <small>
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Password lama akan diganti. User yang sedang login akan tetap bisa akses sampai logout.
                                </small>
                            </div>

                            <hr>
                            <button type="submit" class="btn btn-warning"
                                    data-confirm="Reset password user ini?">
                                <i class="fas fa-redo"></i> Reset Password
                            </button>
                            <a href="<?= BASE_URL ?>/users/detail/<?= $id ?>" class="btn btn-light">Batal</a>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow border-info">
                    <div class="card-header py-2 bg-info text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-info-circle"></i> Info
                        </h6>
                    </div>
                    <div class="card-body small">
                        <p class="mb-2"><strong>Kapan pakai fitur ini?</strong></p>
                        <ul class="pl-3 mb-3">
                            <li>User lupa password</li>
                            <li>Akun dicurigai diakses orang lain</li>
                            <li>Onboarding user baru (kalau password awal kurang aman)</li>
                        </ul>
                        <p class="mb-2"><strong>Setelah reset:</strong></p>
                        <ul class="pl-3 mb-0">
                            <li>Catat password yang muncul</li>
                            <li>Kirim ke user via WhatsApp/email</li>
                            <li>Sarankan user ganti password di profil</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleMode() {
    const mode = document.querySelector('input[name="mode"]:checked').value;
    document.getElementById('wrapManual').style.display = mode === 'manual' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', toggleMode);
</script>