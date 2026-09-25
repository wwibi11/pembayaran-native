<?php
// modules/pembayaran/detail.php
require_once __DIR__ . '/../../config/functions.php';

$id = (int) ($id ?? 0);
$p = fetchOne("
    SELECT p.*, 
           t.nominal AS nominal_tagihan, t.periode, t.jatuh_tempo,
           t.status AS tagihan_status, t.santri_id,
           s.nama AS nama_santri, s.nis,
           k.nama_kelas,
           jp.nama AS jenis_nama,
           u.name AS nama_wali, u.email AS email_wali, u.phone AS hp_wali,
           v.name AS nama_verifikator
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    LEFT JOIN users u ON u.id = p.uploaded_by
    LEFT JOIN users v ON v.id = p.verified_by
    WHERE p.id = ?
", [$id]);

if (!$p) { setFlash('error','Pembayaran tidak ditemukan.'); redirect('pembayaran'); }

// Cek akses wali
if (currentRole() === 'wali' && !isAnakDariWali((int) $p['santri_id'], currentUser()['id'])) {
    http_response_code(403);
    exit('403 - Akses ditolak.');
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/pembayaran/verifikasi" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-receipt text-primary"></i>
                Detail Pembayaran #<?= $p['id'] ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($p['nama_santri']) ?> · <?= e($p['jenis_nama']) ?>
            </p>
        </div>

        <?php if (hasRole('admin') && $p['status'] === 'menunggu'): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/pembayaran/proses/<?= (int) $p['id'] ?>?aksi=approve"
               class="btn btn-sm btn-success"
               data-confirm="Setujui pembayaran ini?">
                <i class="fas fa-check"></i> Setujui
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Info -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Informasi Pembayaran
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td width="35%" class="text-muted">Status</td>
                            <td>
                                <?php
                                $sc = [
                                    'diverifikasi' => 'bg-success',
                                    'menunggu'     => 'bg-warning text-dark',
                                    'ditolak'      => 'bg-danger',
                                ];
                                ?>
                                <span class="badge <?= $sc[$p['status']] ?? 'bg-secondary' ?>" style="font-size:12px;">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <tr><td class="text-muted">Santri</td>
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
                        </tr>
                        <tr><td class="text-muted">Tagihan</td>
                            <td>
                                <span class="badge bg-primary"><?= e($p['jenis_nama']) ?></span>
                                · <?= e($p['periode'] ?: '-') ?>
                                <div class="small text-muted">
                                    <a href="<?= BASE_URL ?>/tagihan/detail/<?= (int) $p['tagihan_id'] ?>">
                                        Lihat Tagihan #<?= $p['tagihan_id'] ?> →
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <tr><td class="text-muted">Nominal Tagihan</td>
                            <td><strong><?= rupiah($p['nominal_tagihan']) ?></strong></td>
                        </tr>
                        <tr><td class="text-muted">Nominal Bayar</td>
                            <td><strong class="text-primary" style="font-size:16px;">
                                <?= rupiah($p['nominal_bayar']) ?>
                            </strong></td>
                        </tr>
                        <tr><td class="text-muted">Tanggal Bayar</td>
                            <td><?= tanggalIndo($p['tanggal_bayar']) ?></td>
                        </tr>
                        <tr><td class="text-muted">Metode</td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= strtoupper($p['metode']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php if ($p['catatan_wali']): ?>
                        <tr><td class="text-muted">Catatan Wali</td>
                            <td><em><?= e($p['catatan_wali']) ?></em></td>
                        </tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Diupload</td>
                            <td>
                                <small>
                                    <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                                    <?php if ($p['nama_wali']): ?>
                                        oleh <strong><?= e($p['nama_wali']) ?></strong>
                                    <?php endif; ?>
                                </small>
                            </td>
                        </tr>
                        <?php if ($p['verified_at']): ?>
                        <tr><td class="text-muted">Diverifikasi</td>
                            <td>
                                <small>
                                    <?= date('d/m/Y H:i', strtotime($p['verified_at'])) ?>
                                    oleh <strong><?= e($p['nama_verifikator']) ?></strong>
                                </small>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($p['catatan_admin']): ?>
                        <tr><td class="text-muted">Catatan Admin</td>
                            <td>
                                <div class="alert alert-warning py-2 mb-0">
                                    <small><?= e($p['catatan_admin']) ?></small>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

            <!-- Aksi -->
            <?php if (hasRole('admin') && $p['status'] === 'menunggu'): ?>
            <div class="card shadow">
                <div class="card-body">
                    <a href="<?= BASE_URL ?>/pembayaran/proses/<?= (int) $p['id'] ?>?aksi=approve"
                       class="btn btn-success btn-block"
                       data-confirm="Setujui pembayaran ini?">
                        <i class="fas fa-check"></i> Setujui Pembayaran
                    </a>

                    <button type="button" class="btn btn-danger btn-block mt-2"
                            data-toggle="modal" data-target="#modalTolak">
                        <i class="fas fa-times"></i> Tolak Pembayaran
                    </button>

                    <!-- Modal tolak -->
                    <div class="modal fade" id="modalTolak" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="<?= BASE_URL ?>/pembayaran/proses/<?= (int) $p['id'] ?>">
                                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                <input type="hidden" name="aksi" value="reject">
                                <div class="modal-content text-left">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">Tolak Pembayaran</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group mb-0">
                                            <label>Alasan Penolakan <span class="text-danger">*</span></label>
                                            <textarea name="catatan_admin" class="form-control" rows="3"
                                                      placeholder="Contoh: Bukti tidak jelas" required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-times"></i> Tolak
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Bukti Bayar -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-image"></i> Bukti Pembayaran
                    </h6>
                </div>
                <div class="card-body text-center">
                    <?php if ($p['bukti_path'] && file_exists(__DIR__ . '/../../' . $p['bukti_path'])): ?>
                        <?php
                        $ext = strtolower(pathinfo($p['bukti_path'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg','jpeg','png']);
                        ?>
                        <?php if ($isImage): ?>
                            <a href="<?= BASE_URL . '/' . e($p['bukti_path']) ?>" target="_blank">
                                <img src="<?= BASE_URL . '/' . e($p['bukti_path']) ?>"
                                     style="max-width:100%; max-height:500px; border-radius:8px;
                                            box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                            </a>
                            <div class="mt-2">
                                <a href="<?= BASE_URL . '/' . e($p['bukti_path']) ?>"
                                   download class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i> Unduh
                                </a>
                                <a href="<?= BASE_URL . '/' . e($p['bukti_path']) ?>"
                                   target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-expand"></i> Buka Full
                                </a>
                            </div>
                        <?php else: ?>
                            <i class="fas fa-file-pdf fa-5x text-danger mb-3"></i>
                            <p>File PDF</p>
                            <a href="<?= BASE_URL . '/' . e($p['bukti_path']) ?>"
                               target="_blank" class="btn btn-primary">
                                <i class="fas fa-file-pdf"></i> Buka PDF
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-muted py-5">
                            <i class="fas fa-image fa-4x mb-3"></i>
                            <p>Bukti pembayaran tidak tersedia</p>
                            <small>Mungkin dihapus atau input manual cash</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>