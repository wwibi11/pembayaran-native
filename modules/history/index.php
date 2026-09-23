<?php
// modules/history/index.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filter
$action = $_GET['action'] ?? '';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = [];
$params = [];

// Search
if ($search) {
    $where[] = "(i.name LIKE ? OR i.code LIKE ? OR h.notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Action filter
if ($action) {
    $where[] = "h.action = ?";
    $params[] = $action;
}

// Date range
if ($date_from) {
    $where[] = "h.created_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where[] = "h.created_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total
$total = fetchColumn(
    "SELECT COUNT(*) FROM item_history h
     LEFT JOIN items i ON h.item_id = i.id
     $whereClause",
    $params
);

// Get history
$history = fetchAll(
    "SELECT h.*, 
            i.code as item_code, 
            i.name as item_name,
            u.name as user_name
     FROM item_history h
     LEFT JOIN items i ON h.item_id = i.id
     LEFT JOIN users u ON h.user_id = u.id
     $whereClause
     ORDER BY h.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Action labels
$actionLabels = [
    'create' => 'Dibuat',
    'update' => 'Diperbarui',
    'delete' => 'Dihapus',
    'borrow' => 'Dipinjam',
    'return' => 'Dikembalikan',
    'repair' => 'Diperbaiki',
    'lost' => 'Hilang'
];

// Action badges
function getActionBadge($action) {
    $badges = [
        'create' => '<span class="badge bg-success">Dibuat</span>',
        'update' => '<span class="badge bg-primary">Diperbarui</span>',
        'delete' => '<span class="badge bg-danger">Dihapus</span>',
        'borrow' => '<span class="badge bg-warning text-dark">Dipinjam</span>',
        'return' => '<span class="badge bg-info">Dikembalikan</span>',
        'repair' => '<span class="badge bg-secondary">Diperbaiki</span>',
        'lost' => '<span class="badge bg-dark">Hilang</span>'
    ];
    return $badges[$action] ?? '<span class="badge bg-secondary">' . $action . '</span>';
}

// Flash messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN HISTORY
   ============================================ */
.card-history {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-history .card-body {
    padding: 16px 20px;
}

.table-history {
    margin-bottom: 0;
    font-size: 13px;
}

.table-history thead th {
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

.table-history tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.table-history tbody tr:hover {
    background: #f8fafc;
}

.table-history tbody tr:last-child td {
    border-bottom: none;
}

/* Badge */
.badge-code {
    background: #1e293b !important;
    color: #ffffff !important;
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-block;
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

.form-filter .form-control,
.form-filter .form-select {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    font-size: 13px;
    background: #fafbfc;
}

.form-filter .form-control:focus,
.form-filter .form-select:focus {
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

/* Mobile */
@media (max-width: 768px) {
    .table-history thead th {
        font-size: 10px;
        padding: 8px 10px;
    }
    .table-history tbody td {
        padding: 8px 10px;
        font-size: 12px;
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
                <i class="fas fa-history text-primary me-2"></i>Riwayat Barang
            </h4>
            <p class="text-muted small mt-1">Catatan semua perubahan dan aktivitas pada barang</p>
        </div>
        <a href="index.php?url=dashboard" class="btn btn-secondary btn-sm">
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
    FILTER
    ============================================ -->
    <div class="card card-history mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end form-filter">
                <input type="hidden" name="url" value="history">

                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control"
                           placeholder="Nama/kode/deskripsi..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Aksi</label>
                    <select name="action" class="form-select">
                        <option value="">Semua</option>
                        <option value="create" <?= $action == 'create' ? 'selected' : '' ?>>Dibuat</option>
                        <option value="update" <?= $action == 'update' ? 'selected' : '' ?>>Diperbarui</option>
                        <option value="delete" <?= $action == 'delete' ? 'selected' : '' ?>>Dihapus</option>
                        <option value="borrow" <?= $action == 'borrow' ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="return" <?= $action == 'return' ? 'selected' : '' ?>>Dikembalikan</option>
                        <option value="repair" <?= $action == 'repair' ? 'selected' : '' ?>>Diperbaiki</option>
                        <option value="lost" <?= $action == 'lost' ? 'selected' : '' ?>>Hilang</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Dari</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>

                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="index.php?url=history" class="btn btn-outline-secondary btn-filter">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
    TABLE
    ============================================ -->
    <div class="card card-history">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-history">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Barang</th>
                            <th>Aksi</th>
                            <th>Perubahan</th>
                            <th>User</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted d-block mb-3"></i>
                                <p class="text-muted mb-2">Belum ada riwayat aktivitas</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= $h['id'] ?></td>
                            <td>
                                <?php if ($h['item_id']): ?>
                                <span class="badge-code"><?= htmlspecialchars($h['item_code']) ?></span>
                                <br>
                                <small><?= htmlspecialchars($h['item_name']) ?></small>
                                <?php else: ?>
                                <span class="text-muted">Item dihapus</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getActionBadge($h['action']) ?></td>
                            <td>
                                <?php if ($h['notes']): ?>
                                <?= nl2br(htmlspecialchars($h['notes'])) ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($h['user_name'] ?? 'Sistem') ?></td>
                            <td>
                                <small><?= formatDateTime($h['created_at']) ?></small>
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
                        $queryParams = http_build_query([
                            'search' => $search,
                            'action' => $action,
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        ]);
                        ?>

                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=history&page=<?= $page - 1 ?>&<?= $queryParams ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>

                        <?php if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="index.php?url=history&page=1&<?= $queryParams ?>">1</a></li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?url=history&page=<?= $i ?>&<?= $queryParams ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=history&page=<?= $totalPages ?>&<?= $queryParams ?>"><?= $totalPages ?></a>
                        </li>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=history&page=<?= $page + 1 ?>&<?= $queryParams ?>">
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

    <!-- ============================================
    STATISTIK RINGKAS
    ============================================ -->
    <div class="row g-3 mt-2">
        <div class="col-md-3 col-6">
            <div class="card card-history">
                <div class="card-body text-center py-3">
                    <div class="h5 mb-0 fw-bold text-primary">
                        <?= fetchColumn("SELECT COUNT(*) FROM item_history WHERE action = 'create'") ?>
                    </div>
                    <div class="small text-muted">Barang Dibuat</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-history">
                <div class="card-body text-center py-3">
                    <div class="h5 mb-0 fw-bold text-warning">
                        <?= fetchColumn("SELECT COUNT(*) FROM item_history WHERE action = 'borrow'") ?>
                    </div>
                    <div class="small text-muted">Peminjaman</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-history">
                <div class="card-body text-center py-3">
                    <div class="h5 mb-0 fw-bold text-success">
                        <?= fetchColumn("SELECT COUNT(*) FROM item_history WHERE action = 'return'") ?>
                    </div>
                    <div class="small text-muted">Pengembalian</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card card-history">
                <div class="card-body text-center py-3">
                    <div class="h5 mb-0 fw-bold text-danger">
                        <?= fetchColumn("SELECT COUNT(*) FROM item_history WHERE action = 'update'") ?>
                    </div>
                    <div class="small text-muted">Perubahan Data</div>
                </div>
            </div>
        </div>
    </div>
</div>