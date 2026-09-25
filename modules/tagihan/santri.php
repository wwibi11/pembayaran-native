<?php
// modules/tagihan/santri.php
require_once __DIR__ . '/../../config/functions.php';

// ============================================
// AMBIL SANTRI
// ============================================
$santriId = (int) ($id ?? 0);
if ($santriId <= 0) {
    setFlash('error', 'ID santri tidak valid.');
    redirect('tagihan');
}

$santri = fetchOne("
    SELECT s.*, k.nama_kelas
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE s.id = ?
", [$santriId]);

if (!$santri) {
    setFlash('error', 'Santri tidak ditemukan.');
    redirect('tagihan');
}

// ============================================
// CEK AKSES WALI
// ============================================
if (currentRole() === 'wali') {
    if (!isAnakDariWali($santriId, currentUser()['id'])) {
        http_response_code(403);
        exit('403 - Akses ditolak.');
    }
}

// ============================================
// TAB AKTIF
// ============================================
$tab = $_GET['tab'] ?? 'semua';
if (!in_array($tab, ['semua', 'belum', 'lunas', 'menunggu'])) {
    $tab = 'semua';
}

// ============================================
// STATISTIK
// ============================================
$stat = fetchOne("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='belum_lunas' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status='menunggu_verifikasi' THEN 1 ELSE 0 END) AS menunggu,
        SUM(CASE WHEN status='lunas' THEN 1 ELSE 0 END) AS lunas,
        SUM(CASE WHEN status='ditolak' THEN 1 ELSE 0 END) AS ditolak,
        COALESCE(SUM(CASE WHEN status='belum_lunas' THEN nominal END),0) AS nominal_belum,
        COALESCE(SUM(CASE WHEN status='lunas' THEN nominal END),0) AS nominal_lunas
    FROM tagihan WHERE santri_id = ?
", [$santriId]);

// ============================================
// QUERY TAGIHAN (sesuai tab)
// ============================================
$where = "WHERE t.santri_id = ?";
$params = [$santriId];

if ($tab === 'belum') {
    $where .= " AND t.status = 'belum_lunas'";
} elseif ($tab === 'lunas') {
    $where .= " AND t.status = 'lunas'";
} elseif ($tab === 'menunggu') {
    $where .= " AND t.status = 'menunggu_verifikasi'";
}

$tagihanList = fetchAll("
    SELECT t.*, jp.nama AS jenis_nama,
           (SELECT COALESCE(SUM(p.nominal_bayar),0) FROM pembayaran p 
            WHERE p.tagihan_id = t.id AND p.status='diverifikasi') AS total_dibayar,
           (SELECT COUNT(*) FROM pembayaran p WHERE p.tagihan_id = t.id) AS jml_pembayaran
    FROM tagihan t
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    $where
    ORDER BY t.created_at DESC, t.id DESC
", $params);

// Riwayat pembayaran terbaru
$pembayaranList = fetchAll("
    SELECT p.*, t.id AS tagihan_id, jp.nama AS jenis_nama,
           u.name AS verified_by_name
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    LEFT JOIN users u ON u.id = p.verified_by
    WHERE t.santri_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
", [$santriId]);
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/tagihan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-file-invoice-dollar text-primary"></i>
                Tagihan — <?= e($santri['nama']) ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                NIS: <?= e($santri['nis']) ?>
                · Kelas: <?= e($santri['nama_kelas'] ?: 'Belum ada') ?>
                ·
                <a href="<?= BASE_URL ?>/santri/detail/<?= $santriId ?>">
                    <i class="fas fa-user"></i> Lihat Biodata
                </a>
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <?php if (hasRole('admin')): ?>
                <a href="<?= BASE_URL ?>/tagihan/generate?santri=<?= $santriId ?>"
                   class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Buat Tagihan
                </a>
            <?php endif; ?>
            <?php if (hasRole('wali')): ?>
                <a href="<?= BASE_URL ?>/pembayaran/upload?anak=<?= $santriId ?>"
                   class="btn btn-sm btn-primary">
                    <i class="fas fa-upload"></i> Upload Bukti
                </a>
            <?php endif; ?>
        </div>
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

    <!-- Profil singkat -->
    <div class="card shadow mb-3">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-6 d-flex align-items-center mb-2 mb-md-0">
                    <div class="rounded-circle mr-3 flex-shrink-0"
                         style="width:56px;height:56px;background:#e8f0fe;
                                display:flex;align-items:center;justify-content:center;
                                color:#2c6b9e;font-weight:700;font-size:22px;">
                        <?= e(strtoupper(substr($santri['nama'], 0, 1))) ?>
                    </div>
                    <div>
                        <div class="font-weight-bold" style="font-size:15px;">
                            <?= e($santri['nama']) ?>
                        </div>
                        <div class="small text-muted">
                            <?= e($santri['nama_panggilan'] ?: '-') ?>
                            · <?= $santri['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?>
                            <?php if ($santri['tanggal_lahir']): ?>
                                ·
                                <?= (new DateTime($santri['tanggal_lahir']))->diff(new DateTime())->y ?> th
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="row text-center">
                        <div class="col-3">
                            <div class="small text-muted">Total</div>
                            <div class="font-weight-bold"><?= $stat['total'] ?></div>
                        </div>
                        <div class="col-3">
                            <div class="small text-muted">Lunas</div>
                            <div class="font-weight-bold text-success"><?= $stat['lunas'] ?></div>
                        </div>
                        <div class="col-3">
                            <div class="small text-muted">Belum</div>
                            <div class="font-weight-bold text-danger"><?= $stat['belum'] ?></div>
                        </div>
                        <div class="col-3">
                            <div class="small text-muted">Menunggu</div>
                            <div class="font-weight-bold text-warning"><?= $stat['menunggu'] ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="row mb-3">
        <div class="col-md-4 col-6 mb-2">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Total Tunggakan
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-danger">
                        <?= rupiah($stat['nominal_belum']) ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        <?= $stat['belum'] ?> tagihan belum lunas
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Total Sudah Dibayar
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-success">
                        <?= rupiah($stat['nominal_lunas']) ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        <?= $stat['lunas'] ?> tagihan lunas
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-12 mb-2">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Menunggu Verifikasi
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-warning">
                        <?= $stat['menunggu'] ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        bukti bayar belum divalidasi
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs + Tabel -->
    <div class="card shadow mb-4">
        <div class="card-header py-0 bg-white">
            <ul class="nav nav-tabs card-header-tabs mt-2">
                <?php
                $tabs = [
                    'semua'    => ['label' => 'Semua',    'count' => $stat['total'],    'color' => 'primary'],
                    'belum'    => ['label' => 'Belum',    'count' => $stat['belum'],    'color' => 'danger'],
                    'menunggu' => ['label' => 'Menunggu', 'count' => $stat['menunggu'], 'color' => 'warning'],
                    'lunas'    => ['label' => 'Lunas',    'count' => $stat['lunas'],    'color' => 'success'],
                ];
                foreach ($tabs as $key => $t):
                    $isActive = $tab === $key;
                ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $isActive ? 'active' : '' ?>"
                           href="?tab=<?= $key ?>"
                           style="<?= $isActive ? 'font-weight:600;color:#2c6b9e;' : 'color:#6b7280;' ?>">
                            <?= $t['label'] ?>
                            <span class="badge bg-<?= $t['color'] ?> ml-1">
                                <?= $t['count'] ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card-body p-0">
            <?php if (empty($tagihanList)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>
                        <?php if ($tab === 'semua'): ?>
                            Belum ada tagihan untuk santri ini.
                        <?php else: ?>
                            Tidak ada tagihan dengan status "<?= $tab ?>".
                        <?php endif; ?>
                    </p>
                    <?php if (hasRole('admin') && $tab === 'semua'): ?>
                        <a href="<?= BASE_URL ?>/tagihan/generate?santri=<?= $santriId ?>"
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-magic"></i> Buat Tagihan Pertama
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Jenis</th>
                                <th>Periode</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-right">Dibayar</th>
                                <th class="text-center">Status</th>
                                <th style="width:170px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tagihanList as $t):
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
                                <td>
                                    <span class="badge bg-primary"><?= e($t['jenis_nama']) ?></span>
                                    <?php if ($t['jml_pembayaran'] > 0): ?>
                                        <div class="small text-muted" style="font-size:10px;">
                                            <i class="fas fa-receipt"></i>
                                            <?= $t['jml_pembayaran'] ?> pembayaran
                                        </div>
                                    <?php endif; ?>
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
                                    <?php elseif ($sisa <= 0 && $t['total_dibayar'] > 0): ?>
                                        <div class="small text-success" style="font-size:10px;">
                                            <i class="fas fa-check"></i> Lunas
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

                                        <?php if ($t['status'] === 'belum_lunas'): ?>
                                            <?php if (hasRole('admin')): ?>
                                                <a href="<?= BASE_URL ?>/tagihan/edit/<?= (int) $t['id'] ?>"
                                                   class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (hasRole('wali') || hasRole('admin')): ?>
                                                <a href="<?= BASE_URL ?>/pembayaran/upload/<?= (int) $t['id'] ?>"
                                                   class="btn btn-sm btn-primary" title="Upload Bukti Bayar">
                                                    <i class="fas fa-upload"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if (hasRole('admin') && $t['status'] === 'belum_lunas'): ?>
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
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($tagihanList) ?></strong> tagihan
                        <?= $tab !== 'semua' ? "($tab)" : '' ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Riwayat Pembayaran -->
    <?php if (!empty($pembayaranList)): ?>
    <div class="card shadow mb-4">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-history"></i> Pembayaran Terbaru
            </h6>
            <a href="<?= BASE_URL ?>/pembayaran/riwayat?santri=<?= $santriId ?>"
               class="small text-primary">
                Lihat semua →
            </a>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
            <?php foreach ($pembayaranList as $p): ?>
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1 flex-wrap" style="gap:6px;">
                                <strong><?= rupiah($p['nominal_bayar']) ?></strong>
                                <span class="badge bg-light text-dark">
                                    <?= e($p['jenis_nama']) ?>
                                </span>
                                <span class="badge <?= 
                                    $p['status']==='diverifikasi' ? 'bg-success' : 
                                    ($p['status']==='menunggu' ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </div>
                            <div class="small text-muted">
                                <i class="fas fa-calendar"></i>
                                <?= tanggalIndo($p['tanggal_bayar']) ?>
                                · <?= ucfirst($p['metode']) ?>
                                · Tagihan #<?= $p['tagihan_id'] ?>
                            </div>
                            <?php if ($p['verified_by_name']): ?>
                                <div class="small text-muted" style="font-size:11px;">
                                    <i class="fas fa-check"></i>
                                    Diverifikasi oleh <?= e($p['verified_by_name']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($p['bukti_path'] && file_exists(__DIR__ . '/../../' . $p['bukti_path'])): ?>
                            <a href="<?= BASE_URL . '/' . e($p['bukti_path']) ?>"
                               target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-image"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }

.nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 16px;
    font-size: 13px;
}
.nav-tabs .nav-link.active {
    border-bottom-color: #2c6b9e;
    background: transparent;
}
.nav-tabs .nav-link:hover {
    border-bottom-color: #cbd5e1;
}
</style>