<?php
// modules/laporan/tunggakan.php
require_once __DIR__ . '/../../config/functions.php';

$kelasId = (int) ($_GET['kelas'] ?? 0);
$status = $_GET['status'] ?? 'belum_lunas';

$where = "WHERE t.status IN ('belum_lunas','menunggu_verifikasi')";
$params = [];

if ($kelasId) {
    $where .= " AND s.kelas_id = ?";
    $params[] = $kelasId;
}

// Group by santri
$list = fetchAll("
    SELECT s.id AS santri_id, s.nis, s.nama, k.nama_kelas,
           COUNT(t.id) AS jml_tagihan,
           COALESCE(SUM(t.nominal),0) AS total_tunggakan,
           MIN(t.jatuh_tempo) AS jatuh_tempo_terlama
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN tagihan t ON t.santri_id = s.id
    $where
    AND s.status = 'aktif'
    GROUP BY s.id, s.nis, s.nama, k.nama_kelas
    ORDER BY total_tunggakan DESC
", $params);

$totalSemua = array_sum(array_column($list, 'total_tunggakan'));
$kelasList = fetchAll("SELECT id, nama_kelas FROM kelas ORDER BY urutan");
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/laporan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-exclamation-triangle text-danger"></i> Laporan Tunggakan
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Daftar santri dengan tagihan belum lunas
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/laporan/export_excel?type=tunggakan&kelas=<?= $kelasId ?>"
               class="btn btn-sm btn-success">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="<?= BASE_URL ?>/laporan/export_pdf?type=tunggakan&kelas=<?= $kelasId ?>"
               target="_blank" class="btn btn-sm btn-danger">
                <i class="fas fa-file-pdf"></i> Cetak PDF
            </a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-3">
        <div class="card-body py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <select name="kelas" class="form-control form-control-sm" style="max-width:200px;">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kelasId==$k['id']?'selected':'' ?>>
                            <?= e($k['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <?php if ($kelasId): ?>
                    <a href="<?= BASE_URL ?>/laporan/tunggakan" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="row mb-3">
        <div class="col-md-6 col-6">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                        Total Santri Menunggak
                    </div>
                    <div class="h4 mb-0 font-weight-bold"><?= count($list) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-6">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                        Total Nilai Tunggakan
                    </div>
                    <div class="h4 mb-0 font-weight-bold text-danger">
                        <?= rupiah($totalSemua) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-success">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <h5>Alhamdulillah! 🎉</h5>
                    <p class="mb-0">Tidak ada tunggakan</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Santri</th>
                                <th>Kelas</th>
                                <th class="text-center">Jml Tagihan</th>
                                <th>Jatuh Tempo Terlama</th>
                                <th class="text-right">Total Tunggakan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no=1; foreach ($list as $t):
                            $terlambatHari = $t['jatuh_tempo_terlama'] 
                                ? (int) ((time() - strtotime($t['jatuh_tempo_terlama'])) / 86400)
                                : 0;
                        ?>
                            <tr>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $t['santri_id'] ?>"
                                       class="text-decoration-none font-weight-bold">
                                        <?= e($t['nama']) ?>
                                    </a>
                                    <div class="small text-muted"><?= e($t['nis']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark">
                                        <?= e($t['nama_kelas'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger"><?= $t['jml_tagihan'] ?></span>
                                </td>
                                <td>
                                    <?php if ($t['jatuh_tempo_terlama']): ?>
                                        <small><?= tanggalIndo($t['jatuh_tempo_terlama']) ?></small>
                                        <?php if ($terlambatHari > 0): ?>
                                            <div class="small text-danger" style="font-size:10px;">
                                                <i class="fas fa-clock"></i> 
                                                <?= $terlambatHari ?> hari lewat
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <strong class="text-danger" style="font-size:14px;">
                                        <?= rupiah($t['total_tunggakan']) ?>
                                    </strong>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $t['santri_id'] ?>"
                                       class="btn btn-sm btn-info" title="Lihat Tagihan">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="5" class="text-right font-weight-bold">TOTAL</td>
                                <td class="text-right">
                                    <strong class="text-danger" style="font-size:15px;">
                                        <?= rupiah($totalSemua) ?>
                                    </strong>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }
</style>