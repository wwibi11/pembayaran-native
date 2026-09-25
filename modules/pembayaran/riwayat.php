<?php
// modules/pembayaran/riwayat.php
require_once __DIR__ . '/../../config/functions.php';

$isWali  = hasRole('wali');
$isAdmin = hasRole('admin');
$isKepala= hasRole('kepala');

$santriId = (int) ($_GET['santri'] ?? 0);
$filterStatus = $_GET['status'] ?? 'semua';

$where  = "WHERE 1=1";
$params = [];

// Wali: hanya lihat anak-anaknya
if ($isWali) {
    $anakIds = array_column(getAnakByWali(currentUser()['id']), 'id');
    if (empty($anakIds)) {
        $where .= " AND 1=0";
    } else {
        $ph = implode(',', array_fill(0, count($anakIds), '?'));
        $where .= " AND t.santri_id IN ($ph)";
        $params = array_merge($params, $anakIds);
    }
}

// Filter santri (kalau ada)
if ($santriId > 0) {
    $where .= " AND t.santri_id = ?";
    $params[] = $santriId;
}

// Filter status
if ($filterStatus !== 'semua' && in_array($filterStatus, ['menunggu','diverifikasi','ditolak'])) {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
}

$list = fetchAll("
    SELECT p.*, 
           t.nominal AS nominal_tagihan, t.periode, t.santri_id,
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
    LIMIT 100
", $params);

// Info santri
$infoSantri = null;
if ($santriId > 0) {
    $infoSantri = fetchOne("SELECT nama, nis FROM santri WHERE id = ?", [$santriId]);
}
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-history text-primary"></i> Riwayat Pembayaran
                <?php if ($infoSantri): ?>
                    <small class="text-muted">— <?= e($infoSantri['nama']) ?></small>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?php if ($isWali): ?>
                    Riwayat pembayaran anak Anda
                <?php else: ?>
                    Semua riwayat pembayaran
                <?php endif; ?>
            </p>
        </div>
        <?php if ($isWali): ?>
            <a href="<?= BASE_URL ?>/pembayaran/upload" class="btn btn-primary btn-sm mt-2 mt-md-0">
                <i class="fas fa-upload"></i> Upload Bukti Baru
            </a>
        <?php endif; ?>
    </div>

    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-2">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <?php if ($santriId): ?>
                    <input type="hidden" name="santri" value="<?= $santriId ?>">
                <?php endif; ?>

                <select name="status" class="form-control form-control-sm" style="max-width:180px;">
                    <option value="semua"        <?= $filterStatus==='semua'?'selected':'' ?>>Semua Status</option>
                    <option value="diverifikasi" <?= $filterStatus==='diverifikasi'?'selected':'' ?>>Diverifikasi</option>
                    <option value="menunggu"     <?= $filterStatus==='menunggu'?'selected':'' ?>>Menunggu</option>
                    <option value="ditolak"      <?= $filterStatus==='ditolak'?'selected':'' ?>>Ditolak</option>
                </select>

                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <?php if ($filterStatus !== 'semua' || $santriId): ?>
                    <a href="<?= BASE_URL ?>/pembayaran/riwayat" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada riwayat pembayaran</p>
                    <?php if ($isWali): ?>
                        <a href="<?= BASE_URL ?>/pembayaran/upload" class="btn btn-primary btn-sm">
                            <i class="fas fa-upload"></i> Upload Bukti Pertama
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
                                <th>Jenis</th>
                                <th class="text-right">Nominal</th>
                                <th class="text-center">Metode</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aksi</th>
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
                                    <?php if (!$infoSantri): ?>
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
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= e($p['jenis_nama']) ?></span>
                                    <?php if ($p['periode']): ?>
                                        <div class="small text-muted"><?= e($p['periode']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <strong class="text-primary"><?= rupiah($p['nominal_bayar']) ?></strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $mc[$p['metode']] ?? 'bg-secondary' ?>">
                                        <?= strtoupper($p['metode']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $sc[$p['status']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/pembayaran/detail/<?= (int) $p['id'] ?>"
                                       class="btn btn-sm btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        <strong><?= count($list) ?></strong> pembayaran
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