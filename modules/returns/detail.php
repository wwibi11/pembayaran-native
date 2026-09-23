<?php
// modules/returns/detail.php

require_once __DIR__ . '/../../config/functions.php';

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID pengembalian tidak valid!';
    redirect('index.php?url=returns');
}

$return = fetchOne(
    "SELECT r.*, 
            l.code as loan_code,
            l.loan_date,
            l.expected_return_date,
            b.name as borrower_name,
            b.institution,
            b.phone,
            u.name as received_by_name
     FROM returns r
     LEFT JOIN loans l ON r.loan_id = l.id
     LEFT JOIN borrowers b ON l.borrower_id = b.id
     LEFT JOIN users u ON r.received_by = u.id
     WHERE r.id = ?",
    [$id]
);

if (!$return) {
    $_SESSION['error'] = 'Data pengembalian tidak ditemukan!';
    redirect('index.php?url=returns');
}

$details = fetchAll(
    "SELECT rd.*, 
            i.code as item_code, 
            i.name as item_name,
            i.photo,
            c.name as category_name,
            ld.condition_before
     FROM return_details rd
     LEFT JOIN items i ON rd.item_id = i.id
     LEFT JOIN categories c ON i.category_id = c.id
     LEFT JOIN loan_details ld ON rd.loan_detail_id = ld.id
     WHERE rd.return_id = ?
     ORDER BY rd.id",
    [$id]
);

$totalItems = array_sum(array_column($details, 'quantity'));
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
                <i class="fas fa-file-invoice text-success me-2"></i>Detail Pengembalian
            </h4>
            <p class="text-muted small mt-1">
                Kode: <span class="badge-code"><?= htmlspecialchars($return['code']) ?></span>
            </p>
        </div>
        <a href="index.php?url=returns" class="btn btn-custom-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <div class="card card-view mb-4">
        <div class="card-body">
            <div class="row g-3 info-item">
                <div class="col-md-3">
                    <div class="label">Kode Pengembalian</div>
                    <div class="value"><?= htmlspecialchars($return['code']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Kode Peminjaman</div>
                    <div class="value"><?= htmlspecialchars($return['loan_code'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Total Item</div>
                    <div class="value"><span class="badge bg-primary"><?= $return['total_items'] ?></span></div>
                </div>
                <div class="col-md-3">
                    <div class="label">Tanggal Kembali</div>
                    <div class="value"><?= formatDate($return['return_date']) ?></div>
                </div>
                <div class="col-md-4">
                    <div class="label">Peminjam</div>
                    <div class="value"><?= htmlspecialchars($return['borrower_name'] ?? '-') ?></div>
                </div>
                <div class="col-md-4">
                    <div class="label">Instansi</div>
                    <div class="value"><?= htmlspecialchars($return['institution'] ?? '-') ?></div>
                </div>
                <div class="col-md-4">
                    <div class="label">Diterima oleh</div>
                    <div class="value"><?= htmlspecialchars($return['received_by_name'] ?? '-') ?></div>
                </div>
                <div class="col-md-6">
                    <div class="label">Tanggal Pinjam</div>
                    <div class="value"><?= formatDate($return['loan_date']) ?></div>
                </div>
                <div class="col-md-6">
                    <div class="label">Tanggal Harus Kembali</div>
                    <div class="value"><?= formatDate($return['expected_return_date']) ?></div>
                </div>
                <?php if ($return['notes']): ?>
                <div class="col-12">
                    <div class="label">Catatan</div>
                    <div class="value"><?= nl2br(htmlspecialchars($return['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card card-view">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-list text-primary me-2"></i>Daftar Barang Dikembalikan</h5>
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
                            <th>Kondisi Akhir</th>
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
                            <td><?= getConditionBadge($d['condition_before'] ?? 'baik') ?></td>
                            <td><?= getConditionBadge($d['condition']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>