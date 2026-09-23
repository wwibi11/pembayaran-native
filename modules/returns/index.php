<?php
// modules/returns/index.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin/staff
if (!isAdmin() && !isStaff()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search & Filter
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(r.code LIKE ? OR l.code LIKE ? OR b.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($date_from) {
    $where[] = "r.return_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where[] = "r.return_date <= ?";
    $params[] = $date_to;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total
$total = fetchColumn(
    "SELECT COUNT(*) FROM returns r
     LEFT JOIN loans l ON r.loan_id = l.id
     LEFT JOIN borrowers b ON l.borrower_id = b.id
     $whereClause",
    $params
);

// Get returns
$returns = fetchAll(
    "SELECT r.*, 
            l.code as loan_code,
            l.loan_date,
            l.expected_return_date,
            b.name as borrower_name,
            b.institution,
            u.name as received_by_name
     FROM returns r
     LEFT JOIN loans l ON r.loan_id = l.id
     LEFT JOIN borrowers b ON l.borrower_id = b.id
     LEFT JOIN users u ON r.received_by = u.id
     $whereClause
     ORDER BY r.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Flash messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
.card-returns {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-returns .card-body {
    padding: 16px 20px;
}

.table-returns {
    margin-bottom: 0;
    font-size: 13px;
}

.table-returns thead th {
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

.table-returns tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.table-returns tbody tr:hover {
    background: #f8fafc;
}

.table-returns tbody tr:last-child td {
    border-bottom: none;
}

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
.btn-action.delete { background: #fee2e2; color: #991b1b; }
.btn-action.delete:hover { background: #fecaca; color: #991b1b; }

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

@media (max-width: 768px) {
    .table-returns thead th {
        font-size: 10px;
        padding: 8px 10px;
    }
    .table-returns tbody td {
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
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-undo-alt text-success me-2"></i>Manajemen Pengembalian
            </h4>
            <p class="text-muted small mt-1">Kelola semua data pengembalian barang</p>
        </div>
    </div>

    <!-- Alert -->
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

    <!-- Search & Filter -->
    <div class="card card-returns mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end form-filter">
                <input type="hidden" name="url" value="returns">
                
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Kode pengembalian/peminjaman/peminjam..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Dari</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Sampai</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                    <a href="index.php?url=returns" class="btn btn-outline-secondary btn-filter">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card card-returns">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-returns">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Peminjaman</th>
                            <th>Peminjam</th>
                            <th>Tanggal Kembali</th>
                            <th>Total Item</th>
                            <th>Diterima oleh</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($returns)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted d-block mb-3"></i>
                                <p class="text-muted mb-2">Belum ada data pengembalian</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($returns as $return): ?>
                        <tr>
                            <td><span class="badge-code"><?= htmlspecialchars($return['code']) ?></span></td>
                            <td><?= htmlspecialchars($return['loan_code'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($return['borrower_name'] ?? '-') ?></td>
                            <td><?= formatDate($return['return_date']) ?></td>
                            <td class="text-center"><?= $return['total_items'] ?></td>
                            <td><?= htmlspecialchars($return['received_by_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="index.php?url=returns/detail&id=<?= $return['id'] ?>" 
                                       class="btn-action view" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (isAdmin()): ?>
                                    <a href="index.php?url=returns/delete&id=<?= $return['id'] ?>" 
                                       class="btn-action delete" 
                                       title="Hapus"
                                       onclick="return confirm('Yakin ingin menghapus data pengembalian ini?')">
                                        <i class="fas fa-trash"></i>
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

            <!-- Pagination -->
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
                            'date_from' => $date_from,
                            'date_to' => $date_to
                        ]);
                        ?>
                        
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=returns&page=<?= $page - 1 ?>&<?= $queryParams ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        ?>
                        
                        <?php if ($startPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="index.php?url=returns&page=1&<?= $queryParams ?>">1</a></li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?url=returns&page=<?= $i ?>&<?= $queryParams ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=returns&page=<?= $totalPages ?>&<?= $queryParams ?>"><?= $totalPages ?></a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=returns&page=<?= $page + 1 ?>&<?= $queryParams ?>">
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