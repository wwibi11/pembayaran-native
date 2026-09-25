<?php
// modules/users/detail.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
if (!$user) { setFlash('error', 'User tidak ditemukan.'); redirect('users'); }

// Data tambahan berdasarkan role
$dataOrtu = null;
$anakList = [];
$statUpload = 0;
$statVerifikasi = 0;
$pembayaranRecent = [];

if ($user['role'] === 'wali') {
    $dataOrtu = fetchOne("SELECT * FROM orang_tua WHERE user_id = ?", [$id]);
    if ($dataOrtu) {
        $anakList = fetchAll("
            SELECT s.id, s.nis, s.nama, s.status, k.nama_kelas, ws.is_primary
            FROM wali_santri ws
            JOIN santri s ON s.id = ws.santri_id
            LEFT JOIN kelas k ON k.id = s.kelas_id
            WHERE ws.orang_tua_id = ?
            ORDER BY s.nama
        ", [$dataOrtu['id']]);
    }

    $statUpload = (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE uploaded_by = ?", [$id]);
    $pembayaranRecent = fetchAll("
        SELECT p.*, s.nama AS nama_santri, jp.nama AS jenis_nama
        FROM pembayaran p
        JOIN tagihan t ON t.id = p.tagihan_id
        JOIN santri s ON s.id = t.santri_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        WHERE p.uploaded_by = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ", [$id]);
}

if ($user['role'] === 'admin') {
    $statVerifikasi = (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE verified_by = ?", [$id]);
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/users" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-circle text-primary"></i> Detail User
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($user['name']) ?>
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/users/edit/<?= $id ?>" class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?= BASE_URL ?>/users/reset_password/<?= $id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-key"></i> Reset Password
            </a>
            <?php if ($user['id'] != currentUser()['id']): ?>
                <a href="<?= BASE_URL ?>/users/delete/<?= $id ?>"
                   class="btn btn-sm btn-danger"
                   data-confirm="Yakin hapus user '<?= e($user['name']) ?>'?">
                    <i class="fas fa-trash"></i> Hapus
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Kiri: Profil -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow">
                <div class="card-body text-center">
                    <div class="rounded-circle mx-auto mb-3"
                         style="width:100px;height:100px;
                                background:<?= $user['role']==='admin' ? '#2c6b9e' : ($user['role']==='kepala' ? '#b45309' : '#15803d') ?>;
                                display:flex;align-items:center;justify-content:center;
                                color:#fff;font-weight:700;font-size:38px;">
                        <?= e(strtoupper(substr($user['name'], 0, 1))) ?>
                    </div>
                    <h5 class="mb-1"><?= e($user['name']) ?></h5>
                    <p class="text-muted mb-3" style="word-break:break-all; font-size:13px;">
                        <?= e($user['email']) ?>
                    </p>

                    <span class="badge bg-<?= 
                        $user['role']==='admin' ? 'danger' : 
                        ($user['role']==='kepala' ? 'warning text-dark' : 'success') ?>"
                          style="font-size:12px; padding:5px 12px;">
                        <?= ucfirst($user['role']) ?>
                    </span>

                    <div class="mt-3">
                        <?php if ($user['is_active']): ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check-circle"></i> Aktif
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fas fa-ban"></i> Nonaktif
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow mt-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Info Akun
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">ID</td>
                            <td>#<?= $user['id'] ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">No. HP</td>
                            <td>
                                <?php if ($user['phone']): ?>
                                    <a href="tel:<?= e($user['phone']) ?>">
                                        <?= e($user['phone']) ?>
                                    </a>
                                <?php else: ?>-<?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td><small><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Update</td>
                            <td><small><?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?></small></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kanan: Data terkait -->
        <div class="col-lg-8 mb-3">

            <!-- Wali: data orang tua & anak -->
            <?php if ($user['role'] === 'wali'): ?>

                <!-- Statistik -->
                <div class="row mb-3">
                    <div class="col-md-4 col-4 mb-2">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Anak Terhubung
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?= count($anakList) ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-4 mb-2">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Upload
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?= $statUpload ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-4 mb-2">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Punya Data Ortu
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?= $dataOrtu ? 'Ya' : 'Tidak' ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data orang tua -->
                <?php if (!$dataOrtu): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Akun belum terhubung ke data orang tua.</strong>
                        Buka <a href="<?= BASE_URL ?>/orang_tua" class="alert-link">Data Orang Tua</a> 
                        → klik <strong>Kelola Akun</strong> pada orang tua yang sesuai, lalu hubungkan.
                    </div>
                <?php else: ?>
                    <div class="card shadow mb-3">
                        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-id-card"></i> Data Orang Tua Terhubung
                            </h6>
                            <a href="<?= BASE_URL ?>/orang_tua/detail/<?= $dataOrtu['id'] ?>"
                               class="small text-primary">
                                Lihat Detail →
                            </a>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td width="30%" class="text-muted">Nama</td>
                                    <td><strong><?= e($dataOrtu['nama_lengkap']) ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Tipe</td>
                                    <td><span class="badge bg-primary"><?= ucfirst($dataOrtu['tipe']) ?></span></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">NIK</td>
                                    <td><?= e($dataOrtu['nik'] ?: '-') ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">No. HP</td>
                                    <td><?= e($dataOrtu['no_hp'] ?: '-') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Daftar anak -->
                    <div class="card shadow mb-3">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-child"></i> Anak Terhubung (<?= count($anakList) ?>)
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($anakList)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle"></i> Belum ada anak terhubung
                                </div>
                            <?php else: ?>
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Kelas</th>
                                            <th class="text-center">Utama</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($anakList as $a): ?>
                                        <tr>
                                            <td><small><?= e($a['nis']) ?></small></td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/santri/detail/<?= (int) $a['id'] ?>"
                                                   class="text-decoration-none font-weight-bold">
                                                    <?= e($a['nama']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?= e($a['nama_kelas'] ?: '-') ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($a['is_primary']): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php else: ?>-<?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= 
                                                    $a['status']==='aktif' ? 'success' : 
                                                    ($a['status']==='lulus' ? 'primary' : 'secondary') ?>">
                                                    <?= ucfirst($a['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Riwayat upload -->
                    <div class="card shadow">
                        <div class="card-header py-2 bg-light">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history"></i> Upload Bukti Terbaru
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($pembayaranRecent)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle"></i> Belum ada upload
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                <?php foreach ($pembayaranRecent as $p): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <div>
                                                <strong><?= rupiah($p['nominal_bayar']) ?></strong>
                                                <span class="badge bg-light text-dark">
                                                    <?= e($p['jenis_nama']) ?>
                                                </span>
                                                <span class="badge bg-<?= 
                                                    $p['status']==='diverifikasi' ? 'success' : 
                                                    ($p['status']==='menunggu' ? 'warning text-dark' : 'danger') ?>">
                                                    <?= ucfirst($p['status']) ?>
                                                </span>
                                            </div>
                                            <div class="small text-muted">
                                                <?= e($p['nama_santri']) ?> · 
                                                <?= tanggalIndo($p['tanggal_bayar']) ?>
                                            </div>
                                        </div>
                                        <a href="<?= BASE_URL ?>/pembayaran/detail/<?= $p['id'] ?>"
                                           class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php elseif ($user['role'] === 'admin'): ?>

                <!-- Admin: statistik -->
                <div class="card shadow">
                    <div class="card-header py-2 bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-chart-bar"></i> Statistik Admin
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h3 mb-0 font-weight-bold text-primary"><?= $statVerifikasi ?></div>
                                <div class="small text-muted">Pembayaran Diverifikasi</div>
                            </div>
                            <div class="col-6">
                                <div class="h3 mb-0 font-weight-bold text-info">
                                    <?= (int) fetchColumn("SELECT COUNT(*) FROM santri") ?>
                                </div>
                                <div class="small text-muted">Total Santri</div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>

                <!-- Kepala: info -->
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-eye fa-3x text-warning mb-3"></i>
                        <h5>Kepala Madin</h5>
                        <p class="text-muted mb-0">Memiliki akses read-only ke semua data.</p>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </div>
</div>