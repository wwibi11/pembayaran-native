<?php
// modules/profil/index.php
require_once __DIR__ . '/../../config/functions.php';

if (!isLoggedIn()) {
    redirect('auth/login.php');
}

$userId = currentUser()['id'];
$user   = fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);

if (!$user) {
    setFlash('error', 'User tidak ditemukan.');
    redirect('dashboard');
}

$errors = [];
$success = null;

// ============================================
// PROSES EDIT PROFIL
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '')  $errors[] = 'Nama wajib diisi.';
    if ($email === '') $errors[] = 'Email wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';

    // Cek duplikat email
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
        if ($dup) $errors[] = 'Email sudah dipakai user lain.';
    }

    if (!$errors) {
        try {
            update('users', [
                'name'  => $name,
                'email' => $email,
                'phone' => $phone ?: null,
            ], 'id = ?', [$userId]);

            // Update session
            $_SESSION['user']['name']  = $name;
            $_SESSION['user']['email'] = $email;

            setFlash('success', 'Profil berhasil diperbarui.');
            redirect('profil');
        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    // Merge ke $user untuk display
    $user = array_merge($user, [
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
    ]);
}

// ============================================
// DATA TAMBAHAN
// ============================================

// Kalau wali, tampilkan daftar anak
$anakList = [];
if ($user['role'] === 'wali') {
    $anakList = getAnakByWali($userId);
}

// Statistik
$stat = [];

if ($user['role'] === 'admin') {
    $stat = [
        'verifikasi' => (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE verified_by = ?", [$userId]),
        'santri'     => (int) fetchColumn("SELECT COUNT(*) FROM santri WHERE status='aktif'"),
    ];
} elseif ($user['role'] === 'wali') {
    $stat = [
        'anak'    => count($anakList),
        'upload'  => (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE uploaded_by = ?", [$userId]),
    ];
}

$roleLabels = [
    'admin'  => 'Administrator',
    'kepala' => 'Kepala Madin',
    'wali'   => 'Wali Santri',
];

$roleColor = [
    'admin'  => '#2c6b9e',
    'kepala' => '#b45309',
    'wali'   => '#15803d',
];

$badgeColor = $roleColor[$user['role']] ?? '#4a5568';
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/dashboard" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-circle text-primary"></i> Profil Saya
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Kelola informasi akun & password Anda
            </p>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>
    <?php if ($err = getFlash('error')): ?>
        <div class="alert alert-danger alert-auto-close">
            <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">

        <!-- ============================================
             KIRI: Kartu Profil
             ============================================ -->
        <div class="col-lg-4 mb-3">

            <div class="card shadow mb-3">
                <div class="card-body text-center">
                    <div class="rounded-circle mx-auto mb-3"
                         style="width:100px;height:100px;background:<?= e($badgeColor) ?>;
                                display:flex;align-items:center;justify-content:center;
                                color:#fff;font-weight:700;font-size:40px;">
                        <?= e(strtoupper(substr($user['name'], 0, 1))) ?>
                    </div>

                    <h5 class="mb-1"><?= e($user['name']) ?></h5>
                    <p class="text-muted mb-2" style="word-break:break-all; font-size:13px;">
                        <?= e($user['email']) ?>
                    </p>

                    <span class="badge"
                          style="background:<?= e($badgeColor) ?>;color:#fff;
                                 font-size:12px;padding:5px 14px;">
                        <?= e($roleLabels[$user['role']] ?? ucfirst($user['role'])) ?>
                    </span>

                    <div class="mt-3">
                        <?php if ($user['is_active']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle"></i> Aktif
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-ban"></i> Nonaktif
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistik -->
            <?php if (!empty($stat)): ?>
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-bar"></i> Aktivitas
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($user['role'] === 'admin'): ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4 mb-0 font-weight-bold text-primary">
                                    <?= number_format($stat['verifikasi']) ?>
                                </div>
                                <div class="small text-muted">Verifikasi</div>
                            </div>
                            <div class="col-6">
                                <div class="h4 mb-0 font-weight-bold text-info">
                                    <?= number_format($stat['santri']) ?>
                                </div>
                                <div class="small text-muted">Santri Aktif</div>
                            </div>
                        </div>
                    <?php elseif ($user['role'] === 'wali'): ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h4 mb-0 font-weight-bold text-success">
                                    <?= number_format($stat['anak']) ?>
                                </div>
                                <div class="small text-muted">Anak</div>
                            </div>
                            <div class="col-6">
                                <div class="h4 mb-0 font-weight-bold text-primary">
                                    <?= number_format($stat['upload']) ?>
                                </div>
                                <div class="small text-muted">Upload Bukti</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Link ke Ganti Password -->
            <div class="card shadow border-warning">
                <div class="card-body">
                    <h6 class="font-weight-bold text-warning mb-2">
                        <i class="fas fa-shield-alt"></i> Keamanan Akun
                    </h6>
                    <p class="small text-muted mb-3">
                        Ganti password secara berkala untuk keamanan akun Anda.
                    </p>
                    <a href="<?= BASE_URL ?>/profil/ganti_password"
                       class="btn btn-warning btn-block">
                        <i class="fas fa-key"></i> Ganti Password
                    </a>
                </div>
            </div>
        </div>

        <!-- ============================================
             KANAN: Form Edit Profil + Anak (kalau wali)
             ============================================ -->
        <div class="col-lg-8 mb-3">

            <!-- Edit Profil -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-user-edit"></i> Edit Informasi Pribadi
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                        <div class="form-group">
                            <label>Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   value="<?= e($user['name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($user['email']) ?>" required>
                            <small class="text-muted">
                                Email ini juga dipakai untuk login
                            </small>
                        </div>

                        <div class="form-group">
                            <label>No. HP</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= e($user['phone']) ?>"
                                   placeholder="08123456789">
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary"
                                data-confirm="Simpan perubahan profil?">
                            <i class="fas fa-save"></i> Simpan Perubahan
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Role (non-editable) -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Informasi Akun
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td width="35%" class="text-muted">Role</td>
                            <td>
                                <span class="badge"
                                      style="background:<?= e($badgeColor) ?>;color:#fff;">
                                    <?= e($roleLabels[$user['role']] ?? ucfirst($user['role'])) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">ID User</td>
                            <td>#<?= e($user['id']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Terdaftar Sejak</td>
                            <td><?= date('d F Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Update Terakhir</td>
                            <td><?= date('d F Y H:i', strtotime($user['updated_at'])) ?></td>
                        </tr>
                    </table>

                    <div class="alert alert-info py-2 mt-3 mb-0">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Untuk mengubah <strong>role</strong> atau <strong>status akun</strong>,
                            hubungi admin.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Daftar Anak (khusus wali) -->
            <?php if ($user['role'] === 'wali' && !empty($anakList)): ?>
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-child"></i> Anak Saya (<?= count($anakList) ?>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>NIS</th>
                                    <th>Nama</th>
                                    <th>Kelas</th>
                                    <th class="text-center">Status</th>
                                    <th style="width:60px;" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($anakList as $a): ?>
                                <tr>
                                    <td><small class="text-muted"><?= e($a['nis']) ?></small></td>
                                    <td>
                                        <strong><?= e($a['nama']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?= e($a['nama_kelas'] ?: '-') ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= 
                                            $a['status']==='aktif' ? 'success' : 
                                            ($a['status']==='lulus' ? 'primary' : 'secondary') ?>">
                                            <?= ucfirst($a['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $a['id'] ?>"
                                           class="btn btn-sm btn-info" title="Lihat Tagihan">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<style>
.table-hover tbody tr:hover { background: #f8fafc; }
</style>