<?php
// modules/kenaikan/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'kenaikan';

// Filter
$search = trim($_GET['q'] ?? '');
$kelasId = (int) ($_GET['kelas'] ?? 0);
$dari = $_GET['dari'] ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');

// Query riwayat kenaikan
$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (s.nama LIKE ? OR s.nis LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($kelasId > 0) {
    $where .= " AND (rk_prev.kelas_id = ? OR rk.kelas_id = ?)";
    $params[] = $kelasId;
    $params[] = $kelasId;
}
if ($dari) {
    $where .= " AND rk.tanggal_mulai >= ?";
    $params[] = $dari;
}
if ($sampai) {
    $where .= " AND rk.tanggal_mulai <= ?";
    $params[] = $sampai;
}

$list = fetchAll("
    SELECT 
        rk.id,
        rk.tanggal_mulai,
        rk.jenis_kenaikan,
        rk.nilai_capaian,
        rk.keterangan,
        s.id AS santri_id, s.nama AS nama_santri, s.nis,
        k.nama_kelas AS kelas_baru, k.urutan AS urutan_baru,
        kp.nama_kelas AS kelas_lama, kp.urutan AS urutan_lama,
        u.name AS dipindahkan_oleh
    FROM riwayat_kelas rk
    JOIN santri s ON s.id = rk.santri_id
    JOIN kelas k ON k.id = rk.kelas_id
    LEFT JOIN riwayat_kelas rk_prev ON rk_prev.santri_id = rk.santri_id 
         AND rk_prev.tanggal_selesai = rk.tanggal_mulai
         AND rk_prev.id != rk.id
    LEFT JOIN kelas kp ON kp.id = rk_prev.kelas_id
    LEFT JOIN users u ON u.id = rk.dipindahkan_oleh
    $where
    ORDER BY rk.tanggal_mulai DESC, rk.id DESC
    LIMIT 200
", $params);

// Statistik
$stat = [
    'total'   => (int) fetchColumn("SELECT COUNT(*) FROM riwayat_kelas WHERE tanggal_selesai IS NOT NULL"),
    'bulan_ini'=> (int) fetchColumn("
        SELECT COUNT(*) FROM riwayat_kelas 
        WHERE MONTH(tanggal_mulai) = MONTH(CURDATE()) 
          AND YEAR(tanggal_mulai) = YEAR(CURDATE())
          AND tanggal_selesai IS NOT NULL
    "),
    'hari_ini' => (int) fetchColumn("
        SELECT COUNT(*) FROM riwayat_kelas 
        WHERE DATE(tanggal_mulai) = CURDATE()
          AND tanggal_selesai IS NOT NULL
    "),
];

$kelasList = fetchAll("SELECT id, nama_kelas, urutan FROM kelas WHERE is_active=1 ORDER BY urutan");
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-arrow-up text-primary"></i> Kenaikan Kelas
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Riwayat perjalanan belajar santri berdasarkan kemampuan
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/kenaikan/create" class="btn btn-success btn-sm">
                <i class="fas fa-user-plus"></i> Naik Kelas Individual
            </a>
            <a href="<?= BASE_URL ?>/kenaikan/massal" class="btn btn-primary btn-sm">
                <i class="fas fa-users"></i> Naik Kelas Massal
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
        <div class="col-md-4 col-4 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Kenaikan
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['total']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-4 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Bulan Ini
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['bulan_ini']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-4 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Hari Ini
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= number_format($stat['hari_ini']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter + List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:200px;" placeholder="Cari nama / NIS..."
                       value="<?= e($search) ?>">

                <select name="kelas" class="form-control form-control-sm" style="max-width:170px;">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kelasId==$k['id']?'selected':'' ?>>
                            <?= e($k['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="dari" class="form-control form-control-sm"
                       style="max-width:150px;" value="<?= e($dari) ?>">
                <span class="text-muted small">s/d</span>
                <input type="date" name="sampai" class="form-control form-control-sm"
                       style="max-width:150px;" value="<?= e($sampai) ?>">

                <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>

                <?php if ($search || $kelasId || $dari !== date('Y-m-01') || $sampai !== date('Y-m-t')): ?>
                    <a href="<?= BASE_URL ?>/kenaikan" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada riwayat kenaikan kelas</p>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/kenaikan/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Naik Kelas Pertama
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Santri</th>
                                <th>Perpindahan Kelas</th>
                                <th class="text-center">Jenis</th>
                                <th class="text-center">Nilai</th>
                                <th>Keterangan</th>
                                <th>Diproses oleh</th>
                                <th style="width:80px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $r):
                            $jc = [
                                'naik'    => 'bg-success',
                                'tinggal' => 'bg-warning text-dark',
                                'pindah'  => 'bg-info text-dark',
                            ];
                            $nc = [
                                'A' => 'bg-success',
                                'B' => 'bg-info text-dark',
                                'C' => 'bg-warning text-dark',
                                'D' => 'bg-danger',
                            ];
                        ?>
                            <tr>
                                <td>
                                    <small><?= tanggalIndo($r['tanggal_mulai']) ?></small>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/kenaikan/detail/<?= (int) $r['santri_id'] ?>"
                                       class="text-decoration-none font-weight-bold">
                                        <?= e($r['nama_santri']) ?>
                                    </a>
                                    <div class="small text-muted"><?= e($r['nis']) ?></div>
                                </td>
                                <td>
                                    <?php if ($r['kelas_lama']): ?>
                                        <span class="badge bg-secondary"><?= e($r['kelas_lama']) ?></span>
                                        <i class="fas fa-arrow-right text-muted mx-1" style="font-size:10px;"></i>
                                    <?php endif; ?>
                                    <span class="badge bg-primary"><?= e($r['kelas_baru']) ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $jc[$r['jenis_kenaikan']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($r['jenis_kenaikan'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($r['nilai_capaian']): ?>
                                        <span class="badge <?= $nc[strtoupper($r['nilai_capaian'])] ?? 'bg-secondary' ?>">
                                            <?= e($r['nilai_capaian']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= e($r['keterangan'] ?: '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <small><?= e($r['dipindahkan_oleh'] ?: 'System') ?></small>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/kenaikan/detail/<?= (int) $r['santri_id'] ?>"
                                       class="btn btn-sm btn-info" title="Lihat Timeline">
                                        <i class="fas fa-stream"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($list) ?></strong> riwayat kenaikan
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }
</style>