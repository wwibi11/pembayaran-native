<?php
// modules/pembayaran/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'pembayaran';

// ============================================
// Filter
// ============================================
$search   = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$metode   = $_GET['metode'] ?? '';
$santriId = (int) ($_GET['santri'] ?? 0);
$periode  = trim($_GET['periode'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

// ============================================
// Query Builder
// ============================================
$where  = "WHERE 1=1";
$params = [];

if ($santriId > 0) {
    $where .= " AND t.santri_id = ?";
    $params[] = $santriId;
}
if ($search) {
    $where .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR u.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status && in_array($status, ['menunggu','diverifikasi','ditolak'])) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}
if ($metode && in_array($metode, ['cash','transfer','qris'])) {
    $where .= " AND p.metode = ?";
    $params[] = $metode;
}
if ($periode) {
    $where .= " AND DATE_FORMAT(p.tanggal_bayar, '%Y-%m') = ?";
    $params[] = $periode;
}

$total = (int) fetchOne("
    SELECT COUNT(*) c
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN users u ON u.id = p.uploaded_by
    $where
", $params)['c'];

$totalPages = max(1, ceil($total / $perPage));

$list = fetchAll("
    SELECT p.*, 
           t.nominal AS nominal_tagihan, t.periode AS periode_tagihan, t.santri_id,
           s.nama AS nama_santri, s.nis, k.nama_kelas,
           jp.nama AS jenis_nama,
           u.name AS nama_wali,
           v.name AS nama_verifikator
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    LEFT JOIN users u ON u.id = p.uploaded_by
    LEFT JOIN users v ON v.id = p.verified_by
    $where
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Statistik
$stat = [
    'total'        => (int) fetchOne("SELECT COUNT(*) c FROM pembayaran")['c'],
    'menunggu'     => (int) fetchOne("SELECT COUNT(*) c FROM pembayaran WHERE status='menunggu'")['c'],
    'diverifikasi' => (int) fetchOne("SELECT COUNT(*) c FROM pembayaran WHERE status='diverifikasi'")['c'],
    'ditolak'      => (int) fetchOne("SELECT COUNT(*) c FROM pembayaran WHERE status='ditolak'")['c'],
];

$totalNominalBulanIni = (float) fetchOne("
    SELECT COALESCE(SUM(nominal_bayar),0) c FROM pembayaran
    WHERE status='diverifikasi'
      AND MONTH(tanggal_bayar) = MONTH(CURDATE())
      AND YEAR(tanggal_bayar) = YEAR(CURDATE())
")['c'];
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-cash-register text-primary"></i> Data Pembayaran
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Semua transaksi pembayaran santri
            </p>
        </div>

        <?php if (hasRole('admin')): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/pembayaran/create" class="btn btn-success btn-sm">
                <i class="fas fa-plus"></i> Input Manual
            </a>
            <a href="<?= BASE_URL ?>/pembayaran/verifikasi" class="btn btn-primary btn-sm">
                <i class="fas fa-check-circle"></i> Verifikasi
                <?php if ($stat['menunggu'] > 0): ?>
                    <span class="badge bg-light text-dark ml-1"><?= $stat['menunggu'] ?></span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>
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
                        Total Transaksi
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Menunggu
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['menunggu']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Diverifikasi
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['diverifikasi']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Bulan Ini
                    </div>
                    <div class="h6 mb-0 font-weight-bold">
                        <?= rupiah($totalNominalBulanIni) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter + List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:200px;" placeholder="Cari santri / wali..."
                       value="<?= e($search) ?>">

                <select name="status" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="">Semua Status</option>
                    <option value="menunggu"     <?= $status==='menunggu'?'selected':'' ?>>Menunggu</option>
                    <option value="diverifikasi" <?= $status==='diverifikasi'?'selected':'' ?>>Diverifikasi</option>
                    <option value="ditolak"      <?= $status==='ditolak'?'selected':'' ?>>Ditolak</option>
                </select>

                <select name="metode" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="">Semua Metode</option>
                    <option value="cash"     <?= $metode==='cash'?'selected':'' ?>>Cash</option>
                    <option value="transfer" <?= $metode==='transfer'?'selected':'' ?>>Transfer</option>
                    <option value="qris"     <?= $metode==='qris'?'selected':'' ?>>QRIS</option>
                </select>

                <input type="text" name="periode" class="form-control form-control-sm"
                       style="max-width:120px;" placeholder="2025-10"
                       value="<?= e($periode) ?>">

                <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>

                <?php if ($search || $status || $metode || $periode): ?>
                    <a href="<?= BASE_URL ?>/pembayaran" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada data pembayaran</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Santri</th>
                                <th>Jenis</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-center">Metode</th>
                                <th>Diinput oleh</th>
                                <th class="text-center">Status</th>
                                <th style="width:120px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $p):
                            $sc = [
                                'diverifikasi' => 'bg-success',
                                'menunggu'     => 'bg-warning text-dark',
                                'ditolak'      => 'bg-danger',
                            ];
                            $mc = [
                                'transfer' => 'bg-info text-dark',
                                'cash'     => 'bg-secondary',
                                'qris'     => 'bg-primary',
                            ];
                        ?>
                            <tr>
                                <td>
                                    <small><?= tanggalIndo($p['tanggal_bayar']) ?></small>
                                    <div class="small text-muted" style="font-size:10px;">
                                        <?= date('H:i', strtotime($p['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $p['santri_id'] ?>"
                                       class="text-decoration-none font-weight-bold">
                                        <?= e($p['nama_santri']) ?>
                                    </a>
                                    <div class="small text-muted">
                                        <?= e($p['nis']) ?>
                                        <?php if ($p['nama_kelas']): ?>
                                            · <?= e($p['nama_kelas']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= e($p['jenis_nama']) ?></span>
                                    <?php if ($p['periode_tagihan']): ?>
                                        <div class="small text-muted"><?= e($p['periode_tagihan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <strong class="text-primary">
                                        <?= rupiah($p['nominal_bayar']) ?>
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $mc[$p['metode']] ?? 'bg-secondary' ?>">
                                        <?= strtoupper($p['metode']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?= e($p['nama_wali'] ?: '-') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $sc[$p['status']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                    <?php if ($p['nama_verifikator']): ?>
                                        <div class="small text-muted" style="font-size:9px;">
                                            oleh <?= e($p['nama_verifikator']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/pembayaran/detail/<?= (int) $p['id'] ?>"
                                       class="btn btn-sm btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasRole('admin') && $p['status'] === 'menunggu'): ?>
                                        <a href="<?= BASE_URL ?>/pembayaran/proses/<?= (int) $p['id'] ?>?aksi=approve"
                                           class="btn btn-sm btn-success"
                                           data-confirm="Setujui pembayaran ini?"
                                           title="Setujui">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center flex-wrap">
                    <small class="text-muted">
                        Hal <strong><?= $page ?></strong> dari <?= $totalPages ?>
                        (<?= number_format($total) ?> total)
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $qs = $_GET;
                            for ($i = 1; $i <= $totalPages; $i++):
                                if ($i > 3 && $i < $totalPages - 2 && abs($i - $page) > 1) {
                                    if ($i == 4) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    continue;
                                }
                                $qs['page'] = $i;
                            ?>
                                <li class="page-item <?= $i==$page?'active':'' ?>">
                                    <a class="page-link" href="?<?= http_build_query($qs) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }
</style>