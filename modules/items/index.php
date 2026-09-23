<?php
// modules/items/index.php

require_once __DIR__ . '/../../config/functions.php';

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Search & Filter
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(name LIKE ? OR code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where[] = "category_id = ?";
    $params[] = $category;
}

if ($condition) {
    $where[] = "`condition` = ?";
    $params[] = $condition;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Get total
$total = fetchColumn("SELECT COUNT(*) FROM items $whereClause", $params);

// Get items
$items = fetchAll(
    "SELECT i.*, c.name as category_name 
     FROM items i 
     LEFT JOIN categories c ON i.category_id = c.id 
     $whereClause 
     ORDER BY i.created_at DESC 
     LIMIT ? OFFSET ?",
    array_merge($params, [$perPage, $offset])
);

// Get categories for filter
$categories = fetchAll("SELECT id, name FROM categories ORDER BY name");

// Cek role
$isAdmin = isAdmin();

// Flash messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN ITEMS
   ============================================ */
.card-items {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-items .card-header {
    background: #ffffff;
    border-bottom: 1px solid #eef2f7;
    padding: 16px 20px;
    border-radius: 12px 12px 0 0 !important;
}

.card-items .card-body {
    padding: 16px 20px;
}

/* Table */
.table-items {
    margin-bottom: 0;
    font-size: 13px;
}

.table-items thead th {
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

.table-items tbody td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

.table-items tbody tr:hover {
    background: #f8fafc;
}

.table-items tbody tr:last-child td {
    border-bottom: none;
}

/* Badge */
.badge-custom {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-custom.bg-success { background: #dcfce7 !important; color: #166534 !important; }
.badge-custom.bg-danger { background: #fee2e2 !important; color: #991b1b !important; }
.badge-custom.bg-warning { background: #fef3c7 !important; color: #92400e !important; }
.badge-custom.bg-info { background: #dbeafe !important; color: #1e40af !important; }
.badge-custom.bg-secondary { background: #f1f5f9 !important; color: #475569 !important; }

/* Stok badge */
.badge-stok {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    min-width: 40px;
    display: inline-block;
    text-align: center;
}
.badge-stok.habis { background: #fee2e2; color: #991b1b; }
.badge-stok.menipis { background: #fef3c7; color: #92400e; }
.badge-stok.cukup { background: #dcfce7; color: #166534; }

/* Foto */
.thumbnail {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid #eef2f7;
    background: #f8fafc;
}

.thumbnail-placeholder {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 1px solid #eef2f7;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 16px;
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

/* Alert */
.alert-custom {
    border-radius: 10px;
    border: none;
    padding: 12px 18px;
    font-size: 13px;
}

.alert-custom.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-custom.alert-danger {
    background: #fee2e2;
    color: #991b1b;
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

/* Responsive */
@media (max-width: 768px) {
    .table-items thead th {
        font-size: 10px;
        padding: 8px 10px;
    }
    .table-items tbody td {
        padding: 8px 10px;
        font-size: 12px;
    }
    .btn-action {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    .thumbnail,
    .thumbnail-placeholder {
        width: 32px;
        height: 32px;
    }
}

/* Tambahkan di style section index.php */

/* Alert Success - Lebih jelas */
.alert-custom.alert-success {
    background: #dcfce7 !important;
    color: #166534 !important;
    border-left: 4px solid #22c55e !important;
}

.alert-custom.alert-success i {
    color: #22c55e;
}

/* Alert Danger */
.alert-custom.alert-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border-left: 4px solid #dc2626 !important;
}

.alert-custom.alert-danger i {
    color: #dc2626;
}

</style>

<div class="container-fluid px-4">
    <!-- ============================================
    HEADER
    ============================================ -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-boxes text-primary me-2"></i>Data Barang
            </h4>
            <p class="text-muted small mt-1">Kelola semua data barang inventaris</p>
        </div>
        <?php if ($isAdmin): ?>
        <a href="index.php?url=items/create" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Tambah Barang
        </a>
        <?php endif; ?>
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
    SEARCH & FILTER
    ============================================ -->
    <div class="card card-items mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end form-filter">
                <input type="hidden" name="url" value="items">
                
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted mb-1">Cari</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Cari nama/kode..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted mb-1">Kategori</label>
                    <select name="category" class="form-select">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted mb-1">Kondisi</label>
                    <select name="condition" class="form-select">
                        <option value="">Semua</option>
                        <option value="baik" <?= $condition == 'baik' ? 'selected' : '' ?>>Baik</option>
                        <option value="rusak" <?= $condition == 'rusak' ? 'selected' : '' ?>>Rusak</option>
                        <option value="perbaikan" <?= $condition == 'perbaikan' ? 'selected' : '' ?>>Perbaikan</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-filter">
                        <i class="fas fa-search me-1"></i> Cari
                    </button>
                    <a href="index.php?url=items" class="btn btn-outline-secondary btn-filter">
                        <i class="fas fa-sync me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================
    TABLE
    ============================================ -->
    <div class="card card-items">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-items">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Foto</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Kondisi</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted d-block mb-3"></i>
                                <p class="text-muted mb-2">Belum ada data barang</p>
                                <?php if ($isAdmin): ?>
                                <a href="index.php?url=items/create" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Tambah Barang
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <span class="badge bg-secondary"><?= htmlspecialchars($item['code']) ?></span>
                            </td>
                            <td>
                                <?php if ($item['photo']): ?>
                                <img src="<?= $item['photo'] ?>" alt="Foto" class="thumbnail">
                                <?php else: ?>
                                <div class="thumbnail-placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-medium"><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if ($item['quantity'] == 0): ?>
                                <span class="badge-stok habis">0</span>
                                <?php elseif ($item['quantity'] <= $item['min_quantity'] && $item['status'] == 'tersedia'): ?>
                                <span class="badge-stok menipis"><?= $item['quantity'] ?></span>
                                <?php else: ?>
                                <span class="badge-stok cukup"><?= $item['quantity'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['condition'] == 'baik'): ?>
                                <span class="badge-custom bg-success">Baik</span>
                                <?php elseif ($item['condition'] == 'rusak'): ?>
                                <span class="badge-custom bg-danger">Rusak</span>
                                <?php else: ?>
                                <span class="badge-custom bg-warning">Perbaikan</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($item['status'] == 'tersedia'): ?>
                                <span class="badge-custom bg-success">Tersedia</span>
                                <?php elseif ($item['status'] == 'dipinjam'): ?>
                                <span class="badge-custom bg-warning">Dipinjam</span>
                                <?php elseif ($item['status'] == 'perbaikan'): ?>
                                <span class="badge-custom bg-info">Perbaikan</span>
                                <?php else: ?>
                                <span class="badge-custom bg-danger">Hilang</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center gap-1">
                                    <a href="index.php?url=items/view&id=<?= $item['id'] ?>" 
                                       class="btn-action view" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($isAdmin): ?>
                                    <a href="index.php?url=items/edit&id=<?= $item['id'] ?>" 
                                       class="btn-action edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <!-- Tombol Hapus dengan data-id -->
                                    <button type="button" 
                                            class="btn-action delete" 
                                            title="Hapus"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-id="<?= $item['id'] ?>"
                                            data-code="<?= htmlspecialchars($item['code']) ?>"
                                            data-name="<?= htmlspecialchars($item['name']) ?>"
                                            data-category="<?= htmlspecialchars($item['category_name'] ?? '-') ?>"
                                            data-quantity="<?= $item['quantity'] ?>"
                                            data-condition="<?= $item['condition'] ?>"
                                            data-photo="<?= $item['photo'] ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                        $queryParams = http_build_query([
                            'search' => $search,
                            'category' => $category,
                            'condition' => $condition
                        ]);
                        ?>
                        
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=items&page=<?= $page - 1 ?>&<?= $queryParams ?>">
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
                            <a class="page-link" href="index.php?url=items&page=1&<?= $queryParams ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="index.php?url=items&page=<?= $i ?>&<?= $queryParams ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=items&page=<?= $totalPages ?>&<?= $queryParams ?>">
                                <?= $totalPages ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="index.php?url=items&page=<?= $page + 1 ?>&<?= $queryParams ?>">
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

<!-- ============================================
MODAL DELETE
============================================ -->
<div class="modal fade modal-delete" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Icon -->
                <div class="icon-delete-modal">
                    <i class="fas fa-trash-alt"></i>
                </div>
                
                <!-- Judul -->
                <h5 class="fw-bold text-dark mb-1">Konfirmasi Hapus</h5>
                <p class="text-muted small mb-3">
                    Apakah Anda yakin ingin menghapus data barang ini?
                    <br>
                    <span class="text-danger fw-bold">Tindakan ini tidak dapat dibatalkan!</span>
                </p>
                
                <!-- Info Barang -->
                <div class="info-barang-modal">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="label">Kode Barang</div>
                            <div class="value" id="deleteCode">-</div>
                        </div>
                        <div class="col-6">
                            <div class="label">Kategori</div>
                            <div class="value" id="deleteCategory">-</div>
                        </div>
                        <div class="col-12">
                            <div class="label">Nama Barang</div>
                            <div class="value" id="deleteName">-</div>
                        </div>
                        <div class="col-6">
                            <div class="label">Stok</div>
                            <div class="value" id="deleteQuantity">-</div>
                        </div>
                        <div class="col-6">
                            <div class="label">Kondisi</div>
                            <div class="value" id="deleteCondition">-</div>
                        </div>
                    </div>
                </div>
                
                <!-- Peringatan Foto -->
                <div class="alert alert-warning text-start small" id="deletePhotoWarning" style="display:none;">
                    <i class="fas fa-image me-2"></i> 
                    Foto barang juga akan dihapus secara permanen.
                </div>
            </div>
            <div class="modal-footer justify-content-center gap-2">
                <button type="button" class="btn btn-custom btn-custom-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Batal
                </button>
                <form id="deleteForm" method="POST" action="index.php?url=items/delete">
                    <input type="hidden" name="id" id="deleteId" value="">
                    <button type="submit" class="btn btn-custom btn-custom-danger">
                        <i class="fas fa-trash me-1"></i> Ya, Hapus
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
SCRIPT MODAL DELETE
============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteModal = document.getElementById('deleteModal');
    
    deleteModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        
        // Ambil data dari atribut button
        const id = button.getAttribute('data-id');
        const code = button.getAttribute('data-code');
        const name = button.getAttribute('data-name');
        const category = button.getAttribute('data-category');
        const quantity = button.getAttribute('data-quantity');
        const condition = button.getAttribute('data-condition');
        const photo = button.getAttribute('data-photo');
        
        // Isi ke modal
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteCode').textContent = code;
        document.getElementById('deleteName').textContent = name;
        document.getElementById('deleteCategory').textContent = category;
        document.getElementById('deleteQuantity').textContent = quantity;
        
        // Kondisi
        const conditionMap = {
            'baik': '<span class="badge bg-success">Baik</span>',
            'rusak': '<span class="badge bg-danger">Rusak</span>',
            'perbaikan': '<span class="badge bg-warning text-dark">Perbaikan</span>'
        };
        document.getElementById('deleteCondition').innerHTML = conditionMap[condition] || condition;
        
        // Peringatan foto
        const warning = document.getElementById('deletePhotoWarning');
        if (photo) {
            warning.style.display = 'block';
        } else {
            warning.style.display = 'none';
        }
    });
});
</script>