<?php
// modules/users/index.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$current_module = 'users';

// Filter
$search = trim($_GET['q'] ?? '');
$role   = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

// Query
$sql = "SELECT u.*,
        (SELECT COUNT(*) FROM orang_tua ot WHERE ot.user_id = u.id) AS jml_link_ortu,
        (SELECT COUNT(*) FROM pembayaran p WHERE p.uploaded_by = u.id) AS jml_upload,
        (SELECT COUNT(*) FROM pembayaran p WHERE p.verified_by = u.id) AS jml_verifikasi
        FROM users u
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role && in_array($role, ['admin','kepala','wali'])) {
    $sql .= " AND u.role = ?";
    $params[] = $role;
}
if ($status === 'aktif') {
    $sql .= " AND u.is_active = 1";
} elseif ($status === 'nonaktif') {
    $sql .= " AND u.is_active = 0";
}
$sql .= " ORDER BY 
        CASE u.role 
            WHEN 'admin' THEN 1 
            WHEN 'kepala' THEN 2 
            ELSE 3 
        END,
        u.name ASC";

$list = fetchAll($sql, $params);

// Statistik
$stat = [
    'total'   => (int) fetchColumn("SELECT COUNT(*) FROM users"),
    'admin'   => (int) fetchColumn("SELECT COUNT(*) FROM users WHERE role='admin'"),
    'kepala'  => (int) fetchColumn("SELECT COUNT(*) FROM users WHERE role='kepala'"),
    'wali'    => (int) fetchColumn("SELECT COUNT(*) FROM users WHERE role='wali'"),
    'nonaktif'=> (int) fetchColumn("SELECT COUNT(*) FROM users WHERE is_active=0"),
];
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-users-cog text-primary"></i> Manajemen User
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Kelola akun login admin, kepala, dan wali santri
            </p>
        </div>
        <a href="<?= BASE_URL ?>/users/create" class="btn btn-primary btn-sm mt-2 mt-md-0">
            <i class="fas fa-plus"></i> Tambah User
        </a>
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

    <!-- Statistik -->
    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total User
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stat['total'] ?></div>
                    <?php if ($stat['nonaktif'] > 0): ?>
                        <div class="small text-muted" style="font-size:10px;">
                            <?= $stat['nonaktif'] ?> nonaktif
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Admin
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stat['admin'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Kepala
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stat['kepala'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Wali
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stat['wali'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:220px;" placeholder="Cari nama / email / HP..."
                       value="<?= e($search) ?>">

                <select name="role" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="">Semua Role</option>
                    <option value="admin"  <?= $role==='admin' ?'selected':'' ?>>Admin</option>
                    <option value="kepala" <?= $role==='kepala'?'selected':'' ?>>Kepala</option>
                    <option value="wali"   <?= $role==='wali'  ?'selected':'' ?>>Wali</option>
                </select>

                <select name="status" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="">Semua Status</option>
                    <option value="aktif"    <?= $status==='aktif'   ?'selected':'' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                </select>

                <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>

                <?php if ($search || $role || $status): ?>
                    <a href="<?= BASE_URL ?>/users" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Tidak ada user yang cocok</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>User</th>
                                <th style="width:100px;">Role</th>
                                <th>Kontak</th>
                                <th class="text-center">Aktivitas</th>
                                <th class="text-center">Status</th>
                                <th style="width:180px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $u):
                            $roleColor = [
                                'admin'  => 'bg-danger',
                                'kepala' => 'bg-warning text-dark',
                                'wali'   => 'bg-success',
                            ];
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle mr-2 flex-shrink-0"
                                             style="width:38px;height:38px;
                                                    background:<?= $u['role']==='admin' ? '#2c6b9e' : ($u['role']==='kepala' ? '#b45309' : '#15803d') ?>;
                                                    display:flex;align-items:center;justify-content:center;
                                                    color:#fff;font-weight:700;font-size:14px;">
                                            <?= e(strtoupper(substr($u['name'], 0, 1))) ?>
                                        </div>
                                        <div>
                                            <div class="font-weight-bold">
                                                <?= e($u['name']) ?>
                                                <?php if ($u['id'] == currentUser()['id']): ?>
                                                    <span class="badge bg-info text-dark" style="font-size:9px;">Anda</span>
                                                <?php endif; ?>
                                            </a></div>
                                            <div class="small text-muted" style="word-break:break-all;">
                                                <?= e($u['email']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $roleColor[$u['role']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($u['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($u['phone']): ?>
                                        <a href="tel:<?= e($u['phone']) ?>" class="text-decoration-none">
                                            <i class="fas fa-phone text-success" style="font-size:11px;"></i>
                                            <?= e($u['phone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($u['role'] === 'wali'): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-child" style="font-size:10px;"></i>
                                            <?= $u['jml_link_ortu'] ?> anak
                                        </div>
                                        <div class="small text-muted">
                                            <i class="fas fa-upload" style="font-size:10px;"></i>
                                            <?= $u['jml_upload'] ?> upload
                                        </div>
                                    <?php elseif ($u['role'] === 'admin'): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-check" style="font-size:10px;"></i>
                                            <?= $u['jml_verifikasi'] ?> verifikasi
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($u['is_active']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-ban"></i> Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:3px;">
                                        <a href="<?= BASE_URL ?>/users/detail/<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/users/edit/<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/users/reset_password/<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-primary" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        <?php if ($u['id'] != currentUser()['id']): ?>
                                            <a href="<?= BASE_URL ?>/users/toggle/<?= (int) $u['id'] ?>"
                                               class="btn btn-sm btn-<?= $u['is_active'] ? 'secondary' : 'success' ?>"
                                               data-confirm="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> user ini?"
                                               title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/users/delete/<?= (int) $u['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               data-confirm="Yakin hapus user '<?= e($u['name']) ?>'?"
                                               title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($list) ?></strong> user
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }
</style>