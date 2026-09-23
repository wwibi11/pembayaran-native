<?php
// modules/items/view.php

// ============================================
// LOAD FUNCTIONS
// ============================================
require_once __DIR__ . '/../../config/functions.php';

// Ambil ID dari URL
$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID barang tidak valid!';
    redirect('index.php?url=items');
}

// Ambil data barang
$item = fetchOne(
    "SELECT i.*, c.name as category_name, u.name as created_by_name 
     FROM items i 
     LEFT JOIN categories c ON i.category_id = c.id 
     LEFT JOIN users u ON i.created_by = u.id 
     WHERE i.id = ?",
    [$id]
);

if (!$item) {
    $_SESSION['error'] = 'Data barang tidak ditemukan!';
    redirect('index.php?url=items');
}

// Ambil riwayat peminjaman barang
$loan_history = fetchAll(
    "SELECT l.code, l.loan_date, l.expected_return_date, l.status, 
            b.name as borrower_name, b.institution
     FROM loan_details ld
     LEFT JOIN loans l ON ld.loan_id = l.id
     LEFT JOIN borrowers b ON l.borrower_id = b.id
     WHERE ld.item_id = ?
     ORDER BY l.created_at DESC
     LIMIT 10",
    [$id]
);

$isAdmin = isAdmin();
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN VIEW
   ============================================ */
.card-view {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}

.card-view .card-body {
    padding: 24px 20px;
}

/* Info Item */
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

/* Foto */
.photo-detail {
    width: 100%;
    max-height: 300px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #eef2f7;
    background: #f8fafc;
}

.photo-placeholder {
    width: 100%;
    height: 200px;
    border-radius: 8px;
    border: 1px solid #eef2f7;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 48px;
}

/* Button */
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

.table-custom {
    font-size: 13px;
}

.table-custom thead th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px;
    border-bottom: 2px solid #eef2f7;
}

.table-custom tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.table-custom tbody tr:last-child td {
    border-bottom: none;
}

/* Responsive */
@media (max-width: 768px) {
    .card-view .card-body {
        padding: 16px;
    }
    .photo-placeholder {
        height: 150px;
        font-size: 36px;
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
                <i class="fas fa-box text-primary me-2"></i>Detail Barang
            </h4>
            <p class="text-muted small mt-1">
                Kode: <span class="badge bg-secondary"><?= htmlspecialchars($item['code']) ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($isAdmin): ?>
            <a href="index.php?url=items/edit&id=<?= $item['id'] ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="index.php?url=items/delete&id=<?= $item['id'] ?>" class="btn btn-danger btn-sm" 
               onclick="return confirm('Yakin ingin menghapus data ini?')">
                <i class="fas fa-trash me-1"></i> Hapus
            </a>
            <?php endif; ?>
            <a href="index.php?url=items" class="btn btn-custom-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <!-- ============================================
    DETAIL BARANG
    ============================================ -->
    <div class="row g-4">
        <!-- Foto -->
        <div class="col-lg-4">
            <div class="card card-view">
                <div class="card-body">
                    <?php if ($item['photo']): ?>
                    <img src="<?= $item['photo'] ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="photo-detail">
                    <?php else: ?>
                    <div class="photo-placeholder">
                        <i class="fas fa-image"></i>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Informasi -->
        <div class="col-lg-8">
            <div class="card card-view">
                <div class="card-body">
                    <div class="row g-3 info-item">
                        <div class="col-md-6">
                            <div class="label">Nama Barang</div>
                            <div class="value"><?= htmlspecialchars($item['name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Kode Barang</div>
                            <div class="value"><?= htmlspecialchars($item['code']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Kategori</div>
                            <div class="value"><?= htmlspecialchars($item['category_name'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Merk / Brand</div>
                            <div class="value"><?= htmlspecialchars($item['brand'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="label">Jumlah Stok</div>
                            <div class="value">
                                <?php if ($item['quantity'] == 0): ?>
                                <span class="badge bg-danger"><?= $item['quantity'] ?></span>
                                <?php elseif ($item['quantity'] <= $item['min_quantity'] && $item['status'] == 'tersedia'): ?>
                                <span class="badge bg-warning text-dark"><?= $item['quantity'] ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary"><?= $item['quantity'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="label">Stok Minimal</div>
                            <div class="value"><?= $item['min_quantity'] ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="label">Kondisi</div>
                            <div class="value"><?= getConditionBadge($item['condition']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Lokasi</div>
                            <div class="value"><?= htmlspecialchars($item['location'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Status</div>
                            <div class="value"><?= getStatusBadge($item['status']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Tanggal Pembelian</div>
                            <div class="value"><?= $item['purchase_date'] ? formatDate($item['purchase_date']) : '-' ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="label">Harga Pembelian</div>
                            <div class="value"><?= $item['purchase_price'] ? formatCurrency($item['purchase_price']) : '-' ?></div>
                        </div>
                        <div class="col-12">
                            <div class="label">Deskripsi</div>
                            <div class="value"><?= nl2br(htmlspecialchars($item['description'] ?? '-')) ?></div>
                        </div>
                        <div class="col-12">
                            <div class="label">Dibuat oleh</div>
                            <div class="value"><?= htmlspecialchars($item['created_by_name'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
    RIWAYAT PEMINJAMAN
    ============================================ -->
    <?php if (!empty($loan_history)): ?>
    <div class="card card-view mt-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3">
                <i class="fas fa-history text-primary me-2"></i>Riwayat Peminjaman
            </h5>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Peminjam</th>
                            <th>Instansi</th>
                            <th>Tanggal Pinjam</th>
                            <th>Tanggal Kembali</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loan_history as $loan): ?>
                        <tr>
                            <td><?= htmlspecialchars($loan['code']) ?></td>
                            <td><?= htmlspecialchars($loan['borrower_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($loan['institution'] ?? '-') ?></td>
                            <td><?= formatDate($loan['loan_date']) ?></td>
                            <td><?= formatDate($loan['expected_return_date']) ?></td>
                            <td><?= getStatusBadge($loan['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>