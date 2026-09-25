<?php
// modules/orang_tua/akun.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$ortu = fetchOne("SELECT ot.*, u.id AS uid, u.email AS uemail, u.is_active AS uaktif, u.name AS uname
                  FROM orang_tua ot
                  LEFT JOIN users u ON u.id = ot.user_id
                  WHERE ot.id = ?", [$id]);
if (!$ortu) { setFlash('error','Data tidak ditemukan.'); redirect('orang_tua'); }

$errors = [];

// ============================================
// PROSES
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);
    $aksi = $_POST['aksi'] ?? '';

    // ---- Aksi: BUAT AKUN BARU ----
    if ($aksi === 'buat') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email tidak valid.';
        if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';

        if (!$errors) {
            if (fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
                $errors[] = 'Email sudah dipakai user lain.';
            }
        }

        if (!$errors) {
            db()->beginTransaction();
            try {
                $userId = insert('users', [
                    'name'      => $ortu['nama_lengkap'],
                    'email'     => $email,
                    'password'  => password_hash($password, PASSWORD_DEFAULT),
                    'role'      => 'wali',
                    'phone'     => $ortu['no_hp'] ?: null,
                    'is_active' => 1,
                ]);

                update('orang_tua', ['user_id' => $userId], 'id = ?', [$id]);

                db()->commit();
                setFlash('success', 'Akun login berhasil dibuat untuk ' . $ortu['nama_lengkap'] . '.');
                redirect("orang_tua/akun/$id");
            } catch (Exception $e) {
                db()->rollBack();
                $errors[] = 'Gagal: ' . $e->getMessage();
            }
        }
    }

    // ---- Aksi: UPDATE EMAIL ----
    if ($aksi === 'update_email') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid.';
        } else {
            $dup = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $ortu['uid']]);
            if ($dup) $errors[] = 'Email sudah dipakai user lain.';
        }

        if (!$errors) {
            update('users', ['email' => $email], 'id = ?', [$ortu['uid']]);
            setFlash('success', 'Email berhasil diubah.');
            redirect("orang_tua/akun/$id");
        }
    }

    // ---- Aksi: RESET PASSWORD ----
    if ($aksi === 'reset_password') {
        $password = $_POST['password_baru'] ?? '';
        if (strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';

        if (!$errors) {
            update('users', [
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ], 'id = ?', [$ortu['uid']]);
            setFlash('success', 'Password berhasil direset. Beritahu wali password barunya.');
            redirect("orang_tua/akun/$id");
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/orang_tua/detail/<?= $id ?>" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-cog text-primary"></i> Kelola Akun Login
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($ortu['nama_lengkap']) ?> — <?= ucfirst($ortu['tipe']) ?>
            </p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- KIRI: Info -->
        <div class="col-md-5 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Info Orang Tua
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" width="40%">Nama</td><td><strong><?= e($ortu['nama_lengkap']) ?></strong></td></tr>
                        <tr><td class="text-muted">Tipe</td><td><span class="badge bg-primary"><?= ucfirst($ortu['tipe']) ?></span></td></tr>
                        <tr><td class="text-muted">NIK</td><td><?= e($ortu['nik'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">No. HP</td><td><?= e($ortu['no_hp'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Email</td><td><?= e($ortu['email'] ?: '-') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- KANAN: Form -->
        <div class="col-md-7 mb-3">

            <?php if (!$ortu['uid']): ?>
            <!-- BUAT AKUN BARU -->
            <div class="card shadow border-success">
                <div class="card-header py-2 bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-plus-circle"></i> Buat Akun Login Baru
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Belum ada akun login. Buat akun supaya wali bisa login dan upload bukti pembayaran.
                        </small>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                        <input type="hidden" name="aksi" value="buat">

                        <div class="form-group">
                            <label>Email Login <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($ortu['email'] ?: '') ?>"
                                   placeholder="nama@email.com" required>
                        </div>

                        <div class="form-group">
                            <label>Password <span class="text-danger">*</span></label>
                            <input type="text" name="password" class="form-control"
                                   placeholder="Minimal 6 karakter"
                                   minlength="6" required>
                            <small class="text-muted">
                                Password akan ditampilkan plaintext supaya mudah diinfokan ke wali.
                            </small>
                        </div>

                        <button class="btn btn-success">
                            <i class="fas fa-user-plus"></i> Buat Akun
                        </button>
                    </form>
                </div>
            </div>

            <?php else: ?>
            <!-- AKUN SUDAH ADA -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-user-check"></i> Akun Login Aktif
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <tr>
                            <td width="40%" class="text-muted">Email</td>
                            <td><strong><?= e($ortu['uemail']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <?php if ($ortu['uaktif']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check-circle"></i> Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-ban"></i> Nonaktif</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <div class="d-flex flex-wrap" style="gap:6px;">
                        <a href="<?= BASE_URL ?>/orang_tua/toggle_akun/<?= $id ?>"
                           class="btn btn-sm btn-<?= $ortu['uaktif'] ? 'warning' : 'success' ?>"
                           data-confirm="<?= $ortu['uaktif'] ? 'Nonaktifkan akun ini?' : 'Aktifkan akun ini?' ?>">
                            <i class="fas fa-power-off"></i>
                            <?= $ortu['uaktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- UPDATE EMAIL -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-envelope"></i> Ubah Email
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST" class="d-flex" style="gap:8px;">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                        <input type="hidden" name="aksi" value="update_email">
                        <input type="email" name="email" class="form-control"
                               value="<?= e($ortu['uemail']) ?>" required>
                        <button class="btn btn-primary" style="white-space:nowrap;">
                            <i class="fas fa-save"></i> Simpan
                        </button>
                    </form>
                </div>
            </div>

            <!-- RESET PASSWORD -->
            <div class="card shadow border-warning">
                <div class="card-header py-2 bg-warning text-dark">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-key"></i> Reset Password
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning py-2 mb-3">
                        <small>
                            <i class="fas fa-exclamation-triangle"></i>
                            Password lama akan diganti. Beritahu wali password barunya.
                        </small>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                        <input type="hidden" name="aksi" value="reset_password">

                        <div class="form-group">
                            <label>Password Baru <span class="text-danger">*</span></label>
                            <input type="text" name="password_baru" class="form-control"
                                   placeholder="Minimal 6 karakter"
                                   minlength="6" required>
                        </div>

                        <button class="btn btn-warning"
                                data-confirm="Yakin reset password?">
                            <i class="fas fa-redo"></i> Reset Password
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>