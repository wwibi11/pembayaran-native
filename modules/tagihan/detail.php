<?php
// modules/tagihan/detail.php
require_once __DIR__ . '/../../config/functions.php';

$id = (int) ($id ?? 0);
$tagihan = fetchOne("
    SELECT t.*, 
           s.nama AS nama_santri, s.nis, s.kelas_id,
           k.nama_kelas,
           jp.nama AS jenis_nama, jp.periode AS jenis_periode,
           u.name AS created_by_name
    FROM tagihan t
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.id = ?
", [$id]);

if (!$tagihan) {
    setFlash('error', 'Tagihan tidak ditemukan.');
    redirect('tagihan');
}

// Cek akses wali
if (currentRole() === 'wali') {
    if (!isAnakDariWali($tagihan['santri_id'], currentUser()['id'])) {
        http_response_code(403);
        exit('403 - Akses ditolak.');
    }
}

$pembayaranList = fetchAll("
    SELECT p.*, u.name AS verified_by_name
    FROM pembayaran p
    LEFT JOIN users u ON u.id = p.verified_by
    WHERE p.tagihan_id = ?
    ORDER BY p.created_at DESC
", [$id]);

$totalDibayar = 0;
foreach ($pembayaranList as $p) {
    if ($p['status'] === 'diverifikasi') {
        $totalDibayar += (float) $p['nominal_bayar'];
    }
}

$sisa = (float) $tagihan['nominal'] - $totalDibayar;
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/tagihan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-file-invoice text-primary"></i>
                Detail Tagihan #<?= $tagihan['id'] ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($tagihan['jenis_nama']) ?> · <?= e($tagihan['periode'] ?: '-') ?>
            </p>
        </div>

        <?php if (hasRole('admin') && $tagihan['status'] === 'belum_lunas'): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/tagihan/edit/<?= (int) $tagihan['id'] ?>"
               class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?= BASE_URL ?>/tagihan/delete/<?= (int) $tagihan['id'] ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Hapus tagihan ini?">
                <i class="fas fa-trash"></i> Hapus
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

    <div class="row">

        <!-- Info Tagihan -->
        <div class="col-lg-7 mb-3">
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Informasi Tagihan
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td width="35%" class="text-muted">Santri</td>
                            <td>
                                <a href="<?= BASE_URL ?>/santri/detail/<?= (int) $tagihan['santri_id'] ?>"
                                   class="text-decoration-none font-weight-bold">
                                    <?= e($tagihan['nama_santri']) ?>
                                </a>
                                <div class="small text-muted">
                                    NIS: <?= e($tagihan['nis']) ?>
                                    <?php if ($tagihan['nama_kelas']): ?>
                                        · <?= e($tagihan['nama_kelas']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Jenis Pembayaran</td>
                            <td><span class="badge bg-primary"><?= e($tagihan['jenis_nama']) ?></span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Periode</td>
                            <td><?= e($tagihan['periode'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Jatuh Tempo</td>
                            <td><?= $tagihan['jatuh_tempo'] ? tanggalIndo($tagihan['jatuh_tempo']) : '-' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nominal</td>
                            <td><strong class="text-primary" style="font-size:16px;">
                                <?= rupiah($tagihan['nominal']) ?>
                            </strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Total Dibayar</td>
                            <td><strong class="text-success"><?= rupiah($totalDibayar) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Sisa</td>
                            <td>
                                <?php if ($sisa > 0): ?>
                                    <strong class="text-danger"><?= rupiah($sisa) ?></strong>
                                <?php else: ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check"></i> Lunas
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <?php
                                $sc = [
                                    'lunas'               => 'bg-success',
                                    'menunggu_verifikasi' => 'bg-warning text-dark',
                                    'belum_lunas'         => 'bg-danger',
                                    'ditolak'             => 'bg-secondary',
                                ];
                                ?>
                                <span class="badge <?= $sc[$tagihan['status']] ?? 'bg-secondary' ?>">
                                    <?= ucfirst(str_replace('_',' ',$tagihan['status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>
                                <small><?= tanggalIndo(substr($tagihan['created_at'], 0, 10)) ?>
                                    oleh <?= e($tagihan['created_by_name'] ?: 'System') ?>
                                </small>
                            </td>
                        </tr>
                    </table>

                    <?php if (currentRole() === 'wali' && $tagihan['status'] === 'belum_lunas'): ?>
                        <hr>
                        <a href="<?= BASE_URL ?>/pembayaran/upload/<?= (int) $tagihan['id'] ?>"
                           class="btn btn-primary btn-block">
                            <i class="fas fa-upload"></i> Upload Bukti Bayar
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Riwayat Pembayaran -->
        <div class="col-lg-5 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> Riwayat Pembayaran (<?= count($pembayaranList) ?>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($pembayaranList)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p class="mb-0">Belum ada pembayaran</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                        <?php foreach ($pembayaranList as $p):
                            $pc = [
                                'diverifikasi' => 'text-success',
                                'menunggu'     => 'text-warning',
                                'ditolak'      => 'text-danger',
                            ];
                        ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong><?= rupiah($p['nominal_bayar']) ?></strong>
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
                                </div>
                                <?php if ($p['bukti_path'] && file_exists(__DIR__ . '/../../' . $p['bukti_path'])): ?>
                                    <a href="<?= BASE_URL . '/' . e($p['bukti_path']) ?>" target="_blank"
                                       class="btn btn-sm btn-outline-info mt-2">
                                        <i class="fas fa-image"></i> Lihat Bukti
                                    </a>
                                <?php endif; ?>
                                <?php if ($p['catatan_admin']): ?>
                                    <div class="small text-danger mt-1">
                                        <i class="fas fa-comment"></i> <?= e($p['catatan_admin']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>