<?php
// modules/users/index.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan super admin
if (!isSuperAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Hanya Super Admin yang dapat mengelola user.';
    redirect('index.php?url=dashboard');
}

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total
$total = fetchColumn("SELECT COUNT(*) FROM users $whereClause", $params);

// Get users
$users = fetchAll(
    "SELECT id, name, username, email, role, phone, address, is_active, last_login, created_at 
     FROM users 
     $whereClause 
     ORDER BY created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Flash messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN USERS
   ============================================ */
.card-users {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-users .card-body {
    padding: 16px 20px;
}

/* Header */
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
    color: #2c6b9e;
    margin-right: 10px;
}

.users-header .header-title .sub-title {
    font-size: 13px;
    color: #8a94a6;
    margin-top: 2px;
}

.btn-tambah {
    background: #2563eb;
    color: #ffffff;
    border: none;
    padding: 8px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-tambah:hover {
    background: #1d4ed8;
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
    text-decoration: none;
}

/* Stat Cards */
.stat-card-users {
    background: #ffffff;
    border-radius: 12px;
    padding: 14px 18px;
    border: 1px solid #eef2f7;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    height: 100%;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-card-users:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.stat-card-users .stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #ffffff;
    flex-shrink: 0;
}

.stat-card-users .stat-icon.primary { background: #2563eb; }
.stat-card-users .stat-icon.success { background: #22c55e; }
.stat-card-users .stat-icon.warning { background: #f59e0b; }
.stat-card-users .stat-icon.purple { background: #8b5cf6; }

.stat-card-users .stat-info .stat-number {
    font-size: 22px;
    font-weight: 700;
    color: #1a2634;
    line-height: 1.2;
}

.stat-card-users .stat-info .stat-label {
    font-size: 11px;
    color: #94a3b8;
}

/* Table */
.table-users {
    margin-bottom: 0;
    font-size: 13px;
}

.table-users thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 14px;
    border-bottom: 2px solid #eef2f7;
    white-space: nowrap;
}

.table-users tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.table-users tbody tr:hover {
    background: #f8fafc;
}

.table-users tbody tr:last-child td {
    border-bottom: none;
}

/* Badge Role */
.badge-role {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-role.super_admin { background: #fef3c7; color: #92400e; }
.badge-role.admin { background: #dbeafe; color: #1d4ed8; }
.badge-role.staff { background: #dcfce7; color: #15803d; }

/* Search */
.search-box {
    position: relative;
    flex: 1;
    max-width: 340px;
}

.search-box .search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 14px;
}

.search-box .form-control {
    padding: 8px 16px 8px 40px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 13px;
    background: #fafbfc;
    transition: all 0.2s ease;
    height: 40px;
}

.search-box .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    background: #ffffff;
}

/* Button Aksi */
.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: none;
    font-size: 13px;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-action.edit {
    background: #fef3c7;
    color: #92400e;
}

.btn-action.edit:hover {
    background: #92400e;
    color: #ffffff;
}

.btn-action.delete {
    background: #fee2e2;
    color: #b91c1c;
}

.btn-action.delete:hover {
    background: #b91c1c;
    color: #ffffff;
}

/* Pagination */
.pagination-custom .page-item .page-link {
    color: #475569;
    border: none;
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 13px;
    margin: 0 2px;
}

.pagination-custom .page-item.active .page-link {
    background: #2563eb;
    color: #fff;
}

.pagination-custom .page-item .page-link:hover {
    background: #f1f5f9;
    color: #2563eb;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state i {
    font-size: 48px;
    color: #d1d5db;
    margin-bottom: 12px;
    display: block;
}

.empty-state h6 {
    color: #475569;
    font-weight: 600;
    margin-bottom: 4px;
}

.empty-state p {
    color: #94a3b8;
    font-size: 13px;
}

/* Alert */
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

/* Responsive */
@media (max-width: 768px) {
    .users-header {
        flex-direction: column;
        align-items: stretch;
    }
    .btn-tambah {
        width: 100%;
        justify-content: center;
    }
    .search-box {
        max-width: 100%;
    }
    .table-users thead th {
        font-size: 10px;
        padding: 8px 10px;
    }
    .table-users tbody td {
        padding: 8px 10px;
        font-size: 12px;
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
                <i class="fas fa-users-cog"></i>
                Manajemen User
            </h4>
            <div class="sub-title">
                <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                Kelola semua user yang memiliki akses ke sistem
            </div>
        </div>
        <a href="index.php?url=users/create" class="btn-tambah">
            <i class="fas fa-plus-circle"></i> Tambah User
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
    STATISTIK
    ============================================ -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card-users">
                <div class="stat-icon primary"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-number"><?= number_format($total) ?></div>
                    <div class="stat-label">Total User</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card-users">
                <div class="stat-icon purple"><i class="fas fa-user-cog"></i></div>
                <div class="stat-info">
                    <div class="stat-number">
                        <?= fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'super_admin'") ?>
                    </div>
                    <div class="stat-label">Super Admin</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card-users">
                <div class="stat-icon success"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <div class="stat-number">
                        <?= fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'admin'") ?>
                    </div>
                    <div class="stat-label">Admin</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card-users">
                <div class="stat-icon warning"><i class="fas fa-user"></i></div>
                <div class="stat-info">
                    <div class="stat-number">
                        <?= fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'staff'") ?>
                    </div>
                    <div class="stat-label">Staff</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
    SEARCH & FILTER
    ============================================ -->
    <div class="card card-users mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="url" value="users">
                
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Cari</label>
                    <div class="search-box">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Nama/Username/Email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Role</label>
                    <select name="role" class="form-select">
                        <option value="">Semua Role</option>
                        <option value="super_admin" <?= $role == 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                        <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="staff" <?= $role == 'staff' ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                    <a href="index.php?url=users" class="btn btn-outline-secondary">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
    TABLE
    ============================================ -->
    <div class="card card-users">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-users">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <h6>Belum Ada Data User</h6>
                                    <p>Klik tombol "Tambah User" untuk menambahkan pengguna baru</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($users as $index => $user): ?>
                        <tr>
                            <td><?= $offset + $index + 1 ?></td>
                            <td class="fw-medium"><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <?php
                                $roleClass = 'staff';
                                if ($user['role'] == 'super_admin') $roleClass = 'super_admin';
                                if ($user['role'] == 'admin') $roleClass = 'admin';
                                ?>
                                <span class="badge-role <?= $roleClass ?>">
                                    <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['is_active'] == 1): ?>
                                <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="index.php?url=users/edit&id=<?= $user['id'] ?>" 
                                       class="btn-action edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                    <a href="index.php?url=users/delete&id=<?= $user['id'] ?>" 
                                       class="btn-action delete" 
                                       title="Hapus"
                                       onclick="return confirm('Yakin ingin menghapus user <?= htmlspecialchars($user['name']) ?>?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ============================================
            PAGINATION
            ============================================ -->
            <?php if ($total > $perPage): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-top">
                <span class="text-muted small">
                    Menampilkan <?= $offset + 1 ?> - <?= min($offset + $perPage, $total) ?> 
                    dari <?= $total ?> data
                </span>
                <nav>
                    <ul class="pagination pagination-custom mb-0">
                        <?php
                        $totalPages = ceil($total / $perPage);
                        $queryParams = http_build_query(['search' => $search, 'role' => $role]);
                        ?>
                        
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=users&page=<?= $page - 1 ?>&<?= $queryParams ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>
                        
                        <?php if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="index.php?url=users&page=1&<?= $queryParams ?>">1</a></li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?url=users&page=<?= $i ?>&<?= $queryParams ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=users&page=<?= $totalPages ?>&<?= $queryParams ?>"><?= $totalPages ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=users&page=<?= $page + 1 ?>&<?= $queryParams ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>