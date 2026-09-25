<?php
// modules/tagihan/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'tagihan';

// ============================================
// Filter
// ============================================
$search   = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$kelasId  = (int) ($_GET['kelas'] ?? 0);
$jenisId  = (int) ($_GET['jenis'] ?? 0);
$periode  = trim($_GET['periode'] ?? '');
$santriId = (int) ($_GET['santri'] ?? 0);
$page     = max(1, (int) ($_GET['page'] ?? 1));
$perPage  = 25;
$offset   = ($page - 1) * $perPage;

// Info santri (kalau filter by santri)
$infoSantri = null;
if ($santriId > 0) {
    $infoSantri = fetchOne("
        SELECT s.id, s.nis, s.nama, s.kelas_id, k.nama_kelas
        FROM santri s
        LEFT JOIN kelas k ON k.id = s.kelas_id
        WHERE s.id = ?
    ", [$santriId]);

    if (!$infoSantri) {
        setFlash('error', 'Santri tidak ditemukan.');
        redirect('tagihan');
    }
}

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
    $where .= " AND (s.nama LIKE ? OR s.nis LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $where .= " AND t.status = ?";
    $params[] = $status;
}
if ($kelasId > 0) {
    $where .= " AND s.kelas_id = ?";
    $params[] = $kelasId;
}
if ($jenisId > 0) {
    $where .= " AND t.jenis_pembayaran_id = ?";
    $params[] = $jenisId;
}
if ($periode) {
    $where .= " AND t.periode = ?";
    $params[] = $periode;
}

$total = (int) fetchOne("
    SELECT COUNT(*) c
    FROM tagihan t
    JOIN santri s ON s.id = t.santri_id
    $where
", $params)['c'];

$totalPages = max(1, ceil($total / $perPage));

$list = fetchAll("
    SELECT 
        t.*,
        s.nama AS nama_santri, s.nis, s.kelas_id,
        k.nama_kelas,
        jp.nama AS jenis_nama,
        (SELECT COALESCE(SUM(p.nominal_bayar),0) FROM pembayaran p 
         WHERE p.tagihan_id = t.id AND p.status='diverifikasi') AS total_dibayar
    FROM tagihan t
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    $where
    ORDER BY t.created_at DESC, t.id DESC
    LIMIT $perPage OFFSET $offset
", $params);

// Data filter
$kelasList = fetchAll("SELECT id, nama_kelas FROM kelas WHERE is_active=1 ORDER BY urutan");
$jenisList = fetchAll("SELECT id, nama FROM jenis_pembayaran WHERE is_active=1 ORDER BY nama");

// ============================================
// Statistik
// ============================================
if ($santriId > 0) {
    $stat = fetchOne("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status='belum_lunas' THEN 1 ELSE 0 END) AS belum,
            SUM(CASE WHEN status='menunggu_verifikasi' THEN 1 ELSE 0 END) AS menunggu,
            SUM(CASE WHEN status='lunas' THEN 1 ELSE 0 END) AS lunas,
            COALESCE(SUM(CASE WHEN status='belum_lunas' THEN nominal END),0) AS total_tunggakan,
            COALESCE(SUM(CASE WHEN status='lunas' THEN nominal END),0) AS total_lunas
        FROM tagihan WHERE santri_id = ?
    ", [$santriId]);
} else {
    $stat = [
        'total'           => (int) fetchOne("SELECT COUNT(*) c FROM tagihan")['c'],
        'belum'           => (int) fetchOne("SELECT COUNT(*) c FROM tagihan WHERE status='belum_lunas'")['c'],
        'menunggu'        => (int) fetchOne("SELECT COUNT(*) c FROM tagihan WHERE status='menunggu_verifikasi'")['c'],
        'lunas'           => (int) fetchOne("SELECT COUNT(*) c FROM tagihan WHERE status='lunas'")['c'],
        'total_tunggakan' => (float) fetchOne("SELECT COALESCE(SUM(nominal),0) c FROM tagihan WHERE status='belum_lunas'")['c'],
        'total_lunas'     => (float) fetchOne("SELECT COALESCE(SUM(nominal),0) c FROM tagihan WHERE status='lunas'")['c'],
    ];
}
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-file-invoice-dollar text-primary"></i> Tagihan
                <?php if ($infoSantri): ?>
                    <small class="text-muted">— <?= e($infoSantri['nama']) ?></small>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?php if ($infoSantri): ?>
                    <i class="fas fa-user-graduate"></i>
                    NIS: <?= e($infoSantri['nis']) ?>
                    · Kelas: <?= e($infoSantri['nama_kelas'] ?: '-') ?>
                    · <a href="<?= BASE_URL ?>/tagihan">Lihat semua tagihan</a>
                <?php else: ?>
                    Kelola tagihan santri & generate SPP massal
                <?php endif; ?>
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
        <div class="mt-2 mt-md-0">
            <?php if ($infoSantri): ?>
                <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $infoSantri['id'] ?>"
                   class="btn btn-info btn-sm">
                    <i class="fas fa-user"></i> Detail Per Anak
                </a>
                <a href="<?= BASE_URL ?>/tagihan/generate?santri=<?= (int) $infoSantri['id'] ?>"
                   class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Buat Tagihan
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/tagihan/generate" class="btn btn-primary btn-sm">
                    <i class="fas fa-magic"></i> Generate Tagihan
                </a>
            <?php endif; ?>
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
                        Total Tagihan
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Belum Lunas
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['belum']) ?></div>
                    <div class="small text-muted" style="font-size:10px;">
                        <?= rupiah($stat['total_tunggakan']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Menunggu Verifikasi
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['menunggu']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Lunas
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['lunas']) ?></div>
                    <div class="small text-muted" style="font-size:10px;">
                        <?= rupiah($stat['total_lunas']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter + List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <?php if ($santriId): ?>
                    <input type="hidden" name="santri" value="<?= $santriId ?>">
                <?php endif; ?>

                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:200px;" placeholder="Cari nama / NIS..."
                       value="<?= e($search) ?>">

                <?php if (!$santriId): ?>
                    <select name="kelas" class="form-control form-control-sm" style="max-width:150px;">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $kelasId==$k['id']?'selected':'' ?>>
                                <?= e($k['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select name="jenis" class="form-control form-control-sm" style="max-width:160px;">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenisList as $j): ?>
                        <option value="<?= $j['id'] ?>" <?= $jenisId==$j['id']?'selected':'' ?>>
                            <?= e($j['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="">Semua Status</option>
                    <option value="belum_lunas"         <?= $status==='belum_lunas'?'selected':'' ?>>Belum Lunas</option>
                    <option value="menunggu_verifikasi" <?= $status==='menunggu_verifikasi'?'selected':'' ?>>Menunggu</option>
                    <option value="lunas"               <?= $status==='lunas'?'selected':'' ?>>Lunas</option>
                    <option value="ditolak"             <?= $status==='ditolak'?'selected':'' ?>>Ditolak</option>
                </select>

                <input type="text" name="periode" class="form-control form-control-sm"
                       style="max-width:120px;" placeholder="2025-10"
                       value="<?= e($periode) ?>">

                <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>

                <?php if ($search || $kelasId || $jenisId || $status || $periode || $santriId): ?>
                    <a href="<?= BASE_URL ?>/tagihan" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada tagihan</p>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/tagihan/generate<?= $santriId ? '?santri='.$santriId : '' ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-magic"></i> Generate Tagihan
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <?php if (!$santriId): ?>
                                <th>Santri</th>
                                <?php endif; ?>
                                <th>Jenis</th>
                                <th>Periode</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-right">Dibayar</th>
                                <th class="text-center">Status</th>
                                <th style="width:140px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $t):
                            $sisa = (float) $t['nominal'] - (float) $t['total_dibayar'];
                            $tc = [
                                'lunas'               => 'bg-success',
                                'menunggu_verifikasi' => 'bg-warning text-dark',
                                'belum_lunas'         => 'bg-danger',
                                'ditolak'             => 'bg-secondary',
                            ];
                            $terlambat = $t['jatuh_tempo'] && strtotime($t['jatuh_tempo']) < time()
                                         && in_array($t['status'], ['belum_lunas','ditolak']);
                        ?>
                            <tr>
                                <?php if (!$santriId): ?>
                                <td>
                                    <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $t['santri_id'] ?>"
                                       class="text-decoration-none font-weight-bold">
                                        <?= e($t['nama_santri']) ?>
                                    </a>
                                    <div class="small text-muted">
                                        <?= e($t['nis']) ?>
                                        <?php if ($t['nama_kelas']): ?>
                                            · <?= e($t['nama_kelas']) ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-primary"><?= e($t['jenis_nama']) ?></span>
                                </td>
                                <td><small><?= e($t['periode'] ?: '-') ?></small></td>
                                <td>
                                    <small><?= $t['jatuh_tempo'] ? tanggalIndo($t['jatuh_tempo']) : '-' ?></small>
                                    <?php if ($terlambat): ?>
                                        <span class="badge bg-danger ml-1">Lewat</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <strong><?= rupiah($t['nominal']) ?></strong>
                                </td>
                                <td class="text-right">
                                    <?= rupiah($t['total_dibayar']) ?>
                                    <?php if ($sisa > 0 && $t['total_dibayar'] > 0): ?>
                                        <div class="small text-danger" style="font-size:10px;">
                                            Sisa <?= rupiah($sisa) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $tc[$t['status']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst(str_replace('_',' ',$t['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:3px;">
                                        <a href="<?= BASE_URL ?>/tagihan/detail/<?= (int) $t['id'] ?>"
                                           class="btn btn-sm btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasRole('admin') && $t['status'] === 'belum_lunas'): ?>
                                            <a href="<?= BASE_URL ?>/tagihan/edit/<?= (int) $t['id'] ?>"
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/tagihan/delete/<?= (int) $t['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               data-confirm="Hapus tagihan ini?"
                                               title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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