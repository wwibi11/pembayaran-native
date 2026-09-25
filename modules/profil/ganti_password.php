<?php
// modules/profil/ganti_password.php
require_once __DIR__ . '/../../config/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$userId = currentUser()['id'];
$user   = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    redirect('dashboard');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $passwordLama  = $_POST['password_lama'] ?? '';
    $passwordBaru  = $_POST['password_baru'] ?? '';
    $konfirmasi    = $_POST['konfirmasi'] ?? '';

    // Validasi
    if ($passwordLama === '')  $errors[] = 'Password lama wajib diisi.';
    if ($passwordBaru === '')  $errors[] = 'Password baru wajib diisi.';
    if (strlen($passwordBaru) < 6) $errors[] = 'Password baru minimal 6 karakter.';
    if ($passwordBaru !== $konfirmasi) $errors[] = 'Konfirmasi password tidak cocok.';
    if ($passwordLama === $passwordBaru) $errors[] = 'Password baru tidak boleh sama dengan password lama.';

    // Cek password lama
    if (!$errors) {
        if (!password_verify($passwordLama, $user['password'])) {
            $errors[] = 'Password lama salah.';
        }
    }

    if (!$errors) {
        try {
            update('users', [
                'password' => password_hash($passwordBaru, PASSWORD_DEFAULT)
            ], 'id = ?', [$userId]);

            setFlash('success', 'Password berhasil diubah. Gunakan password baru saat login berikutnya.');
            redirect('profil');

        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/profil" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-key text-warning"></i> Ganti Password
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Ubah password akun Anda
            </p>
        </div>
    </div>

    <!-- Error -->
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-body">
                    <form method="POST" id="formGantiPassword">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                        <!-- Password Lama -->
                        <div class="form-group">
                            <label>Password Lama <span class="text-danger">*</span></label>
                            <div style="position:relative;">
                                <input type="password" name="password_lama"
                                       id="inputLama" class="form-control"
                                       placeholder="Masukkan password lama" required>
                                <button type="button"
                                        class="toggle-pass"
                                        onclick="togglePass('inputLama', 'eyeLama')"
                                        style="position:absolute; right:12px; top:50%;
                                               transform:translateY(-50%); border:none;
                                               background:none; color:#8a94a6; cursor:pointer;">
                                    <i class="fas fa-eye" id="eyeLama"></i>
                                </button>
                            </div>
                        </div>

                        <hr>

                        <!-- Password Baru -->
                        <div class="form-group">
                            <label>Password Baru <span class="text-danger">*</span></label>
                            <div style="position:relative;">
                                <input type="password" name="password_baru"
                                       id="inputBaru" class="form-control"
                                       placeholder="Minimal 6 karakter"
                                       minlength="6" required
                                       oninput="checkStrength(this.value)">
                                <button type="button"
                                        class="toggle-pass"
                                        onclick="togglePass('inputBaru', 'eyeBaru')"
                                        style="position:absolute; right:12px; top:50%;
                                               transform:translateY(-50%); border:none;
                                               background:none; color:#8a94a6; cursor:pointer;">
                                    <i class="fas fa-eye" id="eyeBaru"></i>
                                </button>
                            </div>

                            <!-- Strength bar -->
                            <div id="strengthBar" style="height:5px; background:#e5e7eb; border-radius:3px; margin-top:8px; overflow:hidden;">
                                <div id="strengthFill" style="height:100%; width:0; transition:all 0.3s; background:#dc2626;"></div>
                            </div>
                            <small id="strengthText" class="text-muted" style="font-size:11px;"></small>
                        </div>

                        <!-- Konfirmasi -->
                        <div class="form-group">
                            <label>Konfirmasi Password Baru <span class="text-danger">*</span></label>
                            <div style="position:relative;">
                                <input type="password" name="konfirmasi"
                                       id="inputKonfirm" class="form-control"
                                       placeholder="Ulangi password baru"
                                       required>
                                <button type="button"
                                        class="toggle-pass"
                                        onclick="togglePass('inputKonfirm', 'eyeKonfirm')"
                                        style="position:absolute; right:12px; top:50%;
                                               transform:translateY(-50%); border:none;
                                               background:none; color:#8a94a6; cursor:pointer;">
                                    <i class="fas fa-eye" id="eyeKonfirm"></i>
                                </button>
                            </div>
                            <small id="matchText" style="font-size:11px;"></small>
                        </div>

                        <div class="alert alert-warning py-2">
                            <small>
                                <i class="fas fa-exclamation-triangle"></i>
                                Setelah ganti password, sesi login Anda tetap aktif.
                                Gunakan password baru saat login berikutnya.
                            </small>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-warning"
                                data-confirm="Yakin ganti password?">
                            <i class="fas fa-key"></i> Ganti Password
                        </button>
                        <a href="<?= BASE_URL ?>/profil" class="btn btn-light">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tips -->
        <div class="col-lg-6">
            <div class="card shadow border-info">
                <div class="card-header py-2 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-lightbulb"></i> Tips Password Aman
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Password yang baik:</strong></p>
                    <ul class="small pl-3">
                        <li>Minimal 8 karakter (lebih panjang lebih baik)</li>
                        <li>Kombinasi huruf besar &amp; kecil</li>
                        <li>Ada angka</li>
                        <li>Ada simbol (!@#$% dll)</li>
                        <li>Tidak berisi nama/email Anda</li>
                    </ul>

                    <p class="small mb-2 mt-3"><strong>❌ Hindari:</strong></p>
                    <ul class="small pl-3 mb-0">
                        <li><code>123456</code>, <code>password</code></li>
                        <li>Tanggal lahir</li>
                        <li>Nama anak/sekolah</li>
                        <li>Password yang sama dengan akun lain</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
// ============================================
// TOGGLE PASSWORD
// ============================================
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);

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

// ============================================
// CEK KEKUATAN PASSWORD
// ============================================
function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');

    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const level = Math.min(score, 5);

    const config = [
        { w: '0%',   color: '#e5e7eb', text: '' },
        { w: '20%',  color: '#dc2626', text: 'Sangat lemah' },
        { w: '40%',  color: '#f59e0b', text: 'Lemah' },
        { w: '60%',  color: '#facc15', text: 'Cukup' },
        { w: '80%',  color: '#22c55e', text: 'Kuat' },
        { w: '100%', color: '#16a34a', text: 'Sangat kuat' },
    ];

    const c = config[level];
    fill.style.width = c.w;
    fill.style.background = c.color;
    text.textContent = c.text;
    text.style.color = c.color;

    checkMatch();
}

// ============================================
// CEK KONFIRMASI
// ============================================
function checkMatch() {
    const baru     = document.getElementById('inputBaru').value;
    const konfirm  = document.getElementById('inputKonfirm').value;
    const text     = document.getElementById('matchText');

    if (!konfirm) {
        text.textContent = '';
        return;
    }

    if (baru === konfirm) {
        text.textContent = '✓ Password cocok';
        text.style.color = '#16a34a';
    } else {
        text.textContent = '✗ Password tidak cocok';
        text.style.color = '#dc2626';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const konfirm = document.getElementById('inputKonfirm');
    if (konfirm) konfirm.addEventListener('input', checkMatch);
});
</script>

<style>
.toggle-pass:hover i { color: #2c6b9e !important; }
</style>