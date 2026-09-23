<?php
// modules/categories/view.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID kategori tidak valid!';
    redirect('index.php?url=categories');
}

$category = fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);

if (!$category) {
    $_SESSION['error'] = 'Data kategori tidak ditemukan!';
    redirect('index.php?url=categories');
}

// Ambil barang dalam kategori ini
$items = fetchAll(
    "SELECT id, code, name, quantity, `condition`, `status` 
     FROM items 
     WHERE category_id = ? 
     ORDER BY name ASC",
    [$id]
);

$totalItems = count($items);
?>

<style>
.card-view {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-view .card-body {
    padding: 24px 20px;
}

.info-item .label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item .value {
    font-size: 14px;
    font-weight: 500;
    color: #1e293b;
    margin-top: 2px;
}

.icon-large {
    font-size: 48px;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    border-radius: 12px;
    color: #2c6b9e;
}

.table-items-category {
    font-size: 13px;
}

.table-items-category thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px;
    border-bottom: 2px solid #eef2f7;
}

.table-items-category tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.table-items-category tbody tr:last-child td {
    border-bottom: none;
}

.btn-custom-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 8px 20px;
    font-size: 13px;
    font-weight: 500;
}

.btn-custom-secondary:hover {
    background: #e2e8f0;
    color: #1e293b;
}
</style>

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-tag text-primary me-2"></i>Detail Kategori
            </h4>
            <p class="text-muted small mt-1">
                Kode: <span class="badge bg-secondary"><?= htmlspecialchars($category['code']) ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?url=categories/edit&id=<?= $category['id'] ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="index.php?url=categories" class="btn btn-custom-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- Info Kategori -->
    <div class="card card-view mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <div class="icon-large">
                        <i class="<?= htmlspecialchars($category['icon'] ?? 'fas fa-tag') ?>"></i>
                    </div>
                </div>
                <div class="col-md-10">
                    <div class="row g-3 info-item">
                        <div class="col-md-4">
                            <div class="label">Nama Kategori</div>
                            <div class="value"><?= htmlspecialchars($category['name']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="label">Kode</div>
                            <div class="value"><?= htmlspecialchars($category['code']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="label">Total Barang</div>
                            <div class="value"><span class="badge bg-primary"><?= $totalItems ?></span></div>
                        </div>
                        <div class="col-12">
                            <div class="label">Deskripsi</div>
                            <div class="value"><?= nl2br(htmlspecialchars($category['description'] ?? '-')) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Barang -->
    <?php if (!empty($items)): ?>
    <div class="card card-view">
        <div class="card-body">
            <h5 class="fw-bold mb-3">
                <i class="fas fa-boxes text-primary me-2"></i>Daftar Barang dalam Kategori Ini
            </h5>
            <div class="table-responsive">
                <table class="table table-items-category">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th class="text-center">Stok</th>
                            <th class="text-center">Kondisi</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['code']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-center"><?= getConditionBadge($item['condition']) ?></td>
                            <td class="text-center"><?= getStatusBadge($item['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card card-view">
        <div class="card-body text-center py-4">
            <i class="fas fa-inbox fa-3x text-muted d-block mb-3"></i>
            <p class="text-muted">Belum ada barang dalam kategori ini</p>
        </div>
    </div>
    <?php endif; ?>
</div>