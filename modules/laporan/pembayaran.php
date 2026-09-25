<?php
// modules/laporan/pembayaran.php
require_once __DIR__ . '/../../config/functions.php';

$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');
$jenisId = (int) ($_GET['jenis'] ?? 0);
$kelasId = (int) ($_GET['kelas'] ?? 0);
$metode  = $_GET['metode'] ?? '';

$where  = "WHERE p.status='diverifikasi' AND p.tanggal_bayar BETWEEN ? AND ?";
$params = [$dari, $sampai];

if ($jenisId) {
    $where .= " AND t.jenis_pembayaran_id = ?";
    $params[] = $jenisId;
}
if ($kelasId) {
    $where .= " AND s.kelas_id = ?";
    $params[] = $kelasId;
}
if ($metode) {
    $where .= " AND p.metode = ?";
    $params[] = $metode;
}

$list = fetchAll("
    SELECT p.*, 
           t.nominal AS nominal_tagihan, t.periode,
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
    ORDER BY p.tanggal_bayar ASC, p.id ASC
", $params);

$totalNominal = array_sum(array_column($list, 'nominal_bayar'));

$jenisList = fetchAll("SELECT id, nama FROM jenis_pembayaran ORDER BY nama");
$kelasList = fetchAll("SELECT id, nama_kelas FROM kelas ORDER BY urutan");
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/laporan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-receipt text-primary"></i> Laporan Pembayaran
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Periode <?= tanggalIndo($dari) ?> s/d <?= tanggalIndo($sampai) ?>
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/laporan/export_excel?dari=<?= $dari ?>&sampai=<?= $sampai ?>&jenis=<?= $jenisId ?>&kelas=<?= $kelasId ?>&metode=<?= $metode ?>"
               class="btn btn-sm btn-success">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
            <a href="<?= BASE_URL ?>/laporan/export_pdf?dari=<?= $dari ?>&sampai=<?= $sampai ?>&jenis=<?= $jenisId ?>&kelas=<?= $kelasId ?>&metode=<?= $metode ?>"
               target="_blank" class="btn btn-sm btn-danger">
                <i class="fas fa-file-pdf"></i> Cetak PDF
            </a>
        </div>
    </div>

    <!-- Filter -->
    <div class="card shadow mb-3">
        <div class="card-body py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="date" name="dari" class="form-control form-control-sm"
                       style="max-width:150px;" value="<?= e($dari) ?>">
                <span class="text-muted">s/d</span>
                <input type="date" name="sampai" class="form-control form-control-sm"
                       style="max-width:150px;" value="<?= e($sampai) ?>">

                <select name="jenis" class="form-control form-control-sm" style="max-width:170px;">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenisList as $j): ?>
                        <option value="<?= $j['id'] ?>" <?= $jenisId==$j['id']?'selected':'' ?>>
                            <?= e($j['nama']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="kelas" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kelasId==$k['id']?'selected':'' ?>>
                            <?= e($k['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="metode" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="">Semua Metode</option>
                    <option value="cash"     <?= $metode==='cash'?'selected':'' ?>>Cash</option>
                    <option value="transfer" <?= $metode==='transfer'?'selected':'' ?>>Transfer</option>
                    <option value="qris"     <?= $metode==='qris'?'selected':'' ?>>QRIS</option>
                </select>

                <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                <a href="<?= BASE_URL ?>/laporan/pembayaran" class="btn btn-sm btn-secondary">
                    <i class="fas fa-times"></i> Reset
                </a>
            </form>
        </div>
    </div>

    <!-- Summary -->
    <div class="alert alert-primary d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-calculator"></i>
            <strong><?= count($list) ?></strong> transaksi ·
            Total: <strong><?= rupiah($totalNominal) ?></strong>
        </div>
        <small class="text-muted d-none d-md-block">
            Periode: <?= tanggalIndo($dari) ?> – <?= tanggalIndo($sampai) ?>
        </small>
    </div>

    <!-- Tabel -->
    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Tidak ada pembayaran di periode ini</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Tanggal</th>
                                <th>Santri</th>
                                <th>Jenis</th>
                                <th class="text-center">Metode</th>
                                <th>Diverifikasi</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no=1; foreach ($list as $p):
                            $mc = [
                                'transfer' => 'bg-info text-dark',
                                'qris'     => 'bg-primary',
                                'cash'     => 'bg-secondary',
                            ];
                        ?>
                            <tr>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td>
                                    <small><?= tanggalIndo($p['tanggal_bayar']) ?></small>
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
                                    <?php if ($p['periode']): ?>
                                        <div class="small text-muted"><?= e($p['periode']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $mc[$p['metode']] ?? 'bg-secondary' ?>">
                                        <?= strtoupper($p['metode']) ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= e($p['nama_verifikator'] ?: '-') ?>
                                    </small>
                                </td>
                                <td class="text-right">
                                    <strong><?= rupiah($p['nominal_bayar']) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot class="bg-light">
                            <tr>
                                <td colspan="6" class="text-right font-weight-bold">TOTAL</td>
                                <td class="text-right">
                                    <strong class="text-primary" style="font-size:15px;">
                                        <?= rupiah($totalNominal) ?>
                                    </strong>
                                </td>
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