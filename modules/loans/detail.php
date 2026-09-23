<?php
// modules/loans/detail.php

require_once __DIR__ . '/../../config/functions.php';

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID peminjaman tidak valid!';
    redirect('index.php?url=loans');
}

$loan = fetchOne(
    "SELECT l.*, b.name as borrower_name, b.institution, b.phone, b.address, b.code as borrower_code,
            u.name as staff_name
     FROM loans l
     LEFT JOIN borrowers b ON l.borrower_id = b.id
     LEFT JOIN users u ON l.created_by = u.id
     WHERE l.id = ?",
    [$id]
);

if (!$loan) {
    $_SESSION['error'] = 'Data peminjaman tidak ditemukan!';
    redirect('index.php?url=loans');
}

$details = fetchAll(
    "SELECT ld.*, i.code as item_code, i.name as item_name, i.photo,
            c.name as category_name
     FROM loan_details ld
     LEFT JOIN items i ON ld.item_id = i.id
     LEFT JOIN categories c ON i.category_id = c.id
     WHERE ld.loan_id = ?
     ORDER BY ld.id",
    [$id]
);

$isReturned = $loan['status'] == 'dikembalikan';
?>

<style>
.card-view { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.card-view .card-body { padding: 24px 20px; }
.info-item .label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.info-item .value { font-size: 14px; font-weight: 500; color: #1e293b; margin-top: 2px; }
.badge-code { background: #1e293b !important; color: #fff !important; font-size: 12px; padding: 4px 12px; border-radius: 6px; font-family: monospace; }
.btn-custom-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 20px; font-size: 13px; font-weight: 500; }
.btn-custom-secondary:hover { background: #e2e8f0; color: #1e293b; }
.table-detail { font-size: 13px; }
.table-detail thead th { background: #f8fafc; color: #475569; font-size: 11px; text-transform: uppercase; padding: 10px 14px; border-bottom: 2px solid #eef2f7; }
.table-detail tbody td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.thumbnail-sm { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #eef2f7; background: #f8fafc; }
</style>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-file-invoice text-primary me-2"></i>Detail Peminjaman
            </h4>
            <p class="text-muted small mt-1">
                Kode: <span class="badge-code"><?= htmlspecialchars($loan['code']) ?></span>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!$isReturned && ($loan['status'] == 'dipinjam' || $loan['status'] == 'terlambat')): ?>
            <a href="index.php?url=loans/return&id=<?= $loan['id'] ?>" class="btn btn-success btn-sm">
                <i class="fas fa-undo-alt me-1"></i> Kembalikan
            </a>
            <?php endif; ?>
            <a href="index.php?url=loans" class="btn btn-custom-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card card-view mb-4">
        <div class="card-body">
            <div class="row g-3 info-item">
                <div class="col-md-3">
                    <div class="label">Status</div>
                    <div class="value"><?= getStatusBadge($loan['status']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Peminjam</div>
                    <div class="value"><?= htmlspecialchars($loan['borrower_name'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Instansi</div>
                    <div class="value"><?= htmlspecialchars($loan['institution'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Total Item</div>
                    <div class="value"><span class="badge bg-primary"><?= $loan['total_items'] ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Tanggal Pinjam</div>
                    <div class="value"><?= formatDate($loan['loan_date']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Tanggal Kembali</div>
                    <div class="value"><?= formatDate($loan['expected_return_date']) ?></div>
                </div>
                <?php if ($loan['actual_return_date']): ?>
                <div class="col-md-3">
                    <div class="label">Tanggal Kembali Aktual</div>
                    <div class="value"><?= formatDate($loan['actual_return_date']) ?></div>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <div class="label">Petugas</div>
                    <div class="value"><?= htmlspecialchars($loan['staff_name'] ?? '-') ?></div>
                </div>
                <?php if ($loan['notes']): ?>
                <div class="col-12">
                    <div class="label">Catatan</div>
                    <div class="value"><?= nl2br(htmlspecialchars($loan['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card card-view">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-list text-primary me-2"></i>Daftar Barang Dipinjam</h5>
            <div class="table-responsive">
                <table class="table table-detail">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Kategori</th>
                            <th class="text-center">Jumlah</th>
                            <th>Kondisi Awal</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $d): ?>
                        <tr>
                            <td>
                                <?php if ($d['photo']): ?>
                                <img src="<?= $d['photo'] ?>" class="thumbnail-sm">
                                <?php else: ?>
                                <div class="thumbnail-placeholder-sm"><i class="fas fa-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($d['item_code']) ?></td>
                            <td><?= htmlspecialchars($d['item_name']) ?></td>
                            <td><?= htmlspecialchars($d['category_name'] ?? '-') ?></td>
                            <td class="text-center"><?= $d['quantity'] ?></td>
                            <td><?= getConditionBadge($d['condition_before']) ?></td>
                            <td><?= getStatusBadge($d['status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>