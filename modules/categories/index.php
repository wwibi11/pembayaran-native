<?php
// modules/categories/index.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search
$search = $_GET['search'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR code LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total
$total = fetchColumn("SELECT COUNT(*) FROM categories $whereClause", $params);

// Get categories
$categories = fetchAll(
    "SELECT c.*, 
            (SELECT COUNT(*) FROM items WHERE category_id = c.id) as total_items
     FROM categories c
     $whereClause
     ORDER BY c.name ASC
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
   STYLE UNTUK HALAMAN CATEGORIES
   ============================================ */
.card-categories {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-categories .card-body {
    padding: 16px 20px;
}

/* Table */
.table-categories {
    margin-bottom: 0;
    font-size: 13px;
}

.table-categories thead th {
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

.table-categories tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.table-categories tbody tr:hover {
    background: #f8fafc;
}

.table-categories tbody tr:last-child td {
    border-bottom: none;
}

/* Badge */
.badge-code {
    background: #1e293b !important;
    color: #ffffff !important;
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-block;
}

.badge-icon {
    font-size: 20px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    border-radius: 8px;
    color: #475569;
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

.alert-custom.alert-success i {
    color: #22c55e;
}

.alert-custom.alert-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border-left-color: #dc2626 !important;
}

.alert-custom.alert-danger i {
    color: #dc2626;
}

/* Tombol aksi */
.btn-action {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    transition: all 0.2s;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
}

.btn-action:hover {
    transform: translateY(-1px);
    text-decoration: none;
}

.btn-action.view { background: #dbeafe; color: #1e40af; }
.btn-action.view:hover { background: #bfdbfe; color: #1e40af; }

.btn-action.edit { background: #fef3c7; color: #92400e; }
.btn-action.edit:hover { background: #fde68a; color: #92400e; }

.btn-action.delete { background: #fee2e2; color: #991b1b; }
.btn-action.delete:hover { background: #fecaca; color: #991b1b; }

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

.pagination-custom .page-item.active .page-link:hover {
    background: #2563eb;
    color: #fff;
}

/* Form filter */
.form-filter .form-control {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    font-size: 13px;
    background: #fafbfc;
}

.form-filter .form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    background: #ffffff;
}

.btn-filter {
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 500;
}

/* Modal Delete */
.modal-delete .modal-content {
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.15);
}

.modal-delete .modal-header {
    border-bottom: none;
    padding: 24px 24px 0 24px;
}

.modal-delete .modal-body {
    padding: 16px 24px 24px 24px;
}

.modal-delete .modal-footer {
    border-top: none;
    padding: 0 24px 24px 24px;
}

.icon-delete-modal {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #fee2e2;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.icon-delete-modal i {
    font-size: 32px;
    color: #dc2626;
}

.info-barang-modal {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 12px 0;
}

.info-barang-modal .label {
    font-size: 11px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.info-barang-modal .value {
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
}

.btn-custom {
    border-radius: 8px;
    padding: 8px 20px;
    font-size: 13px;
    font-weight: 500;
    border: none;
}

.btn-custom-danger {
    background: #dc2626;
    color: #fff;
}

.btn-custom-danger:hover {
    background: #b91c1c;
    color: #fff;
}

.btn-custom-danger:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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

/* Responsive */
@media (max-width: 768px) {
    .table-categories thead th {
        font-size: 10px;
        padding: 8px 10px;
    }
    .table-categories tbody td {
        padding: 8px 10px;
        font-size: 12px;
    }
    .btn-action {
        width: 28px;
        height: 28px;
        font-size: 11px;
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
                <i class="fas fa-tags text-primary me-2"></i>Manajemen Kategori
            </h4>
            <p class="text-muted small mt-1">Kelola semua kategori barang inventaris</p>
        </div>
        <a href="index.php?url=categories/create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Tambah Kategori
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
    SEARCH
    ============================================ -->
    <div class="card card-categories mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end form-filter">
                <input type="hidden" name="url" value="categories">
                
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Cari Kategori</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari nama/kode/deskripsi..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                    <a href="index.php?url=categories" class="btn btn-outline-secondary btn-filter">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
    TABLE
    ============================================ -->
    <div class="card card-categories">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-categories">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Icon</th>
                            <th>Nama Kategori</th>
                            <th>Deskripsi</th>
                            <th class="text-center">Total Barang</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted d-block mb-3"></i>
                                <p class="text-muted mb-2">Belum ada data kategori</p>
                                <a href="index.php?url=categories/create" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Tambah Kategori
                                </a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <span class="badge-code"><?= htmlspecialchars($cat['code']) ?></span>
                            </td>
                            <td>
                                <span class="badge-icon">
                                    <i class="<?= htmlspecialchars($cat['icon'] ?? 'fas fa-tag') ?>"></i>
                                </span>
                            </td>
                            <td class="fw-medium"><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?= $cat['total_items'] ?? 0 ?></span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="index.php?url=categories/view&id=<?= $cat['id'] ?>" 
                                       class="btn-action view" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="index.php?url=categories/edit&id=<?= $cat['id'] ?>" 
                                       class="btn-action edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- PAKAI LINK LANGSUNG UNTUK DELETE -->
                                    <a href="index.php?url=categories/delete&id=<?= $cat['id'] ?>" 
                                       class="btn-action delete" 
                                       title="Hapus"
                                       onclick="return confirm('Yakin ingin menghapus kategori <?= htmlspecialchars($cat['name']) ?>?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
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
                        $queryParams = http_build_query(['search' => $search]);
                        ?>
                        
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=categories&page=<?= $page - 1 ?>&<?= $queryParams ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>
                        
                        <?php if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=categories&page=1&<?= $queryParams ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?url=categories&page=<?= $i ?>&<?= $queryParams ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=categories&page=<?= $totalPages ?>&<?= $queryParams ?>">
                                <?= $totalPages ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=categories&page=<?= $page + 1 ?>&<?= $queryParams ?>">
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