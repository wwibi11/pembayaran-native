<?php
// modules/pembayaran/verifikasi.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

// Filter
$status = $_GET['status'] ?? 'menunggu';
if (!in_array($status, ['menunggu','diverifikasi','ditolak','semua'])) $status = 'menunggu';

$where  = "WHERE 1=1";
$params = [];

if ($status !== 'semua') {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

$list = fetchAll("
    SELECT p.*, 
           t.nominal AS nominal_tagihan, t.periode, t.jatuh_tempo, t.status AS tagihan_status,
           s.id AS santri_id, s.nama AS nama_santri, s.nis, k.nama_kelas,
           jp.nama AS jenis_nama,
           u.name AS nama_wali, u.email AS email_wali,
           v.name AS nama_verifikator
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    LEFT JOIN users u ON u.id = p.uploaded_by
    LEFT JOIN users v ON v.id = p.verified_by
    $where
    ORDER BY 
        CASE WHEN p.status='menunggu' THEN 0 ELSE 1 END,
        p.created_at DESC
", $params);

$stat = [
    'menunggu'    => (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE status='menunggu'"),
    'diverifikasi'=> (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE status='diverifikasi'"),
    'ditolak'     => (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE status='ditolak'"),
];
?>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-check-circle text-primary"></i> Verifikasi Pembayaran
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Validasi bukti bayar dari wali atau input manual
            </p>
        </div>
        <a href="<?= BASE_URL ?>/pembayaran/create" class="btn btn-success btn-sm mt-2 mt-md-0">
            <i class="fas fa-plus"></i> Input Pembayaran Manual
        </a>
    </div>

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

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $status==='menunggu'?'active':'' ?>" href="?status=menunggu">
                <i class="fas fa-clock"></i> Menunggu
                <span class="badge bg-warning text-dark"><?= $stat['menunggu'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status==='diverifikasi'?'active':'' ?>" href="?status=diverifikasi">
                <i class="fas fa-check"></i> Diverifikasi
                <span class="badge bg-success"><?= $stat['diverifikasi'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status==='ditolak'?'active':'' ?>" href="?status=ditolak">
                <i class="fas fa-times"></i> Ditolak
                <span class="badge bg-danger"><?= $stat['ditolak'] ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status==='semua'?'active':'' ?>" href="?status=semua">
                <i class="fas fa-list"></i> Semua
            </a>
        </li>
    </ul>

    <div class="card shadow mb-4">
        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>
                        <?php if ($status === 'menunggu'): ?>
                            Tidak ada pembayaran yang menunggu verifikasi. 👍
                        <?php else: ?>
                            Belum ada data pembayaran.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Santri</th>
                                <th>Tagihan</th>
                                <th class="text-right">Nominal Bayar</th>
                                <th>Tanggal</th>
                                <th class="text-center">Metode</th>
                                <th class="text-center">Status</th>
                                <th style="width:180px;" class="text-center">Aksi</th>
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
                                    <?php if ($p['nama_wali']): ?>
                                        <div class="small text-muted" style="font-size:10px;">
                                            <i class="fas fa-user"></i> <?= e($p['nama_wali']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= e($p['jenis_nama']) ?></div>
                                    <div class="small text-muted">
                                        <?= e($p['periode'] ?: '-') ?>
                                        · Rp <?= number_format($p['nominal_tagihan'], 0, ',', '.') ?>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <strong class="text-primary">
                                        <?= rupiah($p['nominal_bayar']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <small><?= tanggalIndo($p['tanggal_bayar']) ?></small>
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
                                    <?php if ($p['nama_verifikator']): ?>
                                        <div class="small text-muted" style="font-size:9px;">
                                            oleh <?= e($p['nama_verifikator']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:3px;">
                                        <a href="<?= BASE_URL ?>/pembayaran/detail/<?= (int) $p['id'] ?>"
                                           class="btn btn-sm btn-info" title="Detail & Bukti">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <?php if ($p['status'] === 'menunggu'): ?>
                                            <a href="<?= BASE_URL ?>/pembayaran/proses/<?= (int) $p['id'] ?>?aksi=approve"
                                               class="btn btn-sm btn-success"
                                               data-confirm="Verifikasi pembayaran <?= rupiah($p['nominal_bayar']) ?> dari <?= e($p['nama_santri']) ?>?"
                                               title="Setujui">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    data-toggle="modal" data-target="#modalTolak<?= $p['id'] ?>"
                                                    title="Tolak">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Modal Tolak -->
                                    <?php if ($p['status'] === 'menunggu'): ?>
                                    <div class="modal fade" id="modalTolak<?= $p['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <form method="POST" action="<?= BASE_URL ?>/pembayaran/proses/<?= (int) $p['id'] ?>">
                                                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                                <input type="hidden" name="aksi" value="reject">
                                                <div class="modal-content text-left">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title">Tolak Pembayaran</h5>
                                                        <button type="button" class="close text-white" data-dismiss="modal">
                                                            <span>&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-2">
                                                            <strong><?= e($p['nama_santri']) ?></strong> ·
                                                            <?= rupiah($p['nominal_bayar']) ?>
                                                        </p>
                                                        <div class="form-group mb-0">
                                                            <label>Alasan Penolakan <span class="text-danger">*</span></label>
                                                            <textarea name="catatan_admin" class="form-control" rows="3"
                                                                      placeholder="Contoh: Bukti tidak jelas, nominal tidak sesuai..."
                                                                      required></textarea>
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
                                    <?php endif; ?>
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
.nav-tabs .nav-link { color: #6b7280; font-size: 13px; }
.nav-tabs .nav-link.active { color: #2c6b9e; font-weight: 600; }
</style>