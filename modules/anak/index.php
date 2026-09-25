<?php
// modules/anak/index.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('wali')) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus wali santri.');
}

$current_module = 'anak';
$userId = currentUser()['id'];

// ============================================
// AMBIL DATA ANAK
// ============================================
$anakList = fetchAll("
    SELECT 
        s.id, s.nis, s.nama, s.nama_panggilan, s.jenis_kelamin, 
        s.tanggal_lahir, s.foto, s.status, s.kelas_id,
        k.nama_kelas,
        ot.tipe AS hubungan_tipe,
        ws.is_primary,
        (SELECT COUNT(*) FROM tagihan t 
         WHERE t.santri_id = s.id AND t.status IN ('belum_lunas','menunggu_verifikasi')) AS jml_tagihan_aktif,
        (SELECT COUNT(*) FROM tagihan t 
         WHERE t.santri_id = s.id AND t.status='lunas') AS jml_tagihan_lunas,
        (SELECT COALESCE(SUM(t.nominal),0) FROM tagihan t 
         WHERE t.santri_id = s.id AND t.status='belum_lunas') AS total_tunggakan,
        (SELECT COALESCE(SUM(p.nominal_bayar),0) FROM pembayaran p
         JOIN tagihan t ON t.id = p.tagihan_id
         WHERE t.santri_id = s.id AND p.status='diverifikasi') AS total_dibayar
    FROM orang_tua ot
    JOIN wali_santri ws ON ws.orang_tua_id = ot.id
    JOIN santri s ON s.id = ws.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE ot.user_id = ?
    ORDER BY s.status = 'aktif' DESC, s.nama ASC
", [$userId]);

// Statistik total
$totalAnak   = count($anakList);
$totalAnakAktif = count(array_filter($anakList, fn($a) => $a['status'] === 'aktif'));
$totalTunggakan = array_sum(array_column($anakList, 'total_tunggakan'));
$totalDibayar   = array_sum(array_column($anakList, 'total_dibayar'));
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-child text-success"></i> Anak Saya
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Daftar anak Anda yang terdaftar di TPQ Madin
            </p>
        </div>
        <a href="<?= BASE_URL ?>/pembayaran/upload" class="btn btn-primary btn-sm mt-2 mt-md-0">
            <i class="fas fa-upload"></i> Upload Bukti Bayar
        </a>
    </div>

    <!-- Flash -->
    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <?php if ($totalAnak === 0): ?>
        <!-- Empty State -->
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                <h5>Belum ada anak yang terhubung</h5>
                <p class="text-muted mb-0">
                    Hubungi admin TPQ untuk menghubungkan akun Anda dengan data santri.
                </p>
            </div>
        </div>
    <?php else: ?>

        <!-- Statistik -->
        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Anak
                        </div>
                        <div class="h5 mb-0 font-weight-bold"><?= $totalAnak ?></div>
                        <div class="small text-muted" style="font-size:10px;">
                            <?= $totalAnakAktif ?> aktif
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Total Tunggakan
                        </div>
                        <div class="h6 mb-0 font-weight-bold text-danger">
                            <?= rupiah($totalTunggakan) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Total Dibayar
                        </div>
                        <div class="h6 mb-0 font-weight-bold text-success">
                            <?= rupiah($totalDibayar) ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Kelas Aktif
                        </div>
                        <div class="h5 mb-0 font-weight-bold">
                            <?= count(array_unique(array_filter(
                                array_column($anakList, 'nama_kelas')
                            ))) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kartu Anak -->
        <div class="row">
        <?php foreach ($anakList as $anak):
            // Hitung umur
            $umur = '';
            if ($anak['tanggal_lahir']) {
                $umur = (new DateTime($anak['tanggal_lahir']))->diff(new DateTime())->y . ' th';
            }

            // Status badge
            $statusColors = [
                'aktif'  => 'bg-success',
                'lulus'  => 'bg-primary',
                'keluar' => 'bg-secondary',
                'cuti'   => 'bg-warning text-dark',
            ];
            $statusColor = $statusColors[$anak['status']] ?? 'bg-secondary';

            // Hubungan
            $hubungan = ucfirst($anak['hubungan_tipe'] ?? 'wali');
        ?>
            <div class="col-lg-6 mb-3">
                <div class="card shadow h-100 <?= $anak['status'] !== 'aktif' ? 'border-secondary' : '' ?>">
                    
                    <!-- Header Card -->
                    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <?php if ($anak['foto'] && file_exists(__DIR__ . '/../../' . $anak['foto'])): ?>
                                <img src="<?= BASE_URL . '/' . e($anak['foto']) ?>"
                                     style="width:36px;height:36px;object-fit:cover;border-radius:50%;"
                                     class="mr-2">
                            <?php else: ?>
                                <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center"
                                     style="width:36px;height:36px;
                                            background:<?= $anak['jenis_kelamin']==='L' ? '#2c6b9e' : '#ec4899' ?>;
                                            color:#fff;font-weight:700;font-size:14px;">
                                    <?= e(strtoupper(substr($anak['nama'], 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong><?= e($anak['nama']) ?></strong>
                                <span class="badge <?= $statusColor ?> ml-1" style="font-size:9px;">
                                    <?= ucfirst($anak['status']) ?>
                                </span>
                                <?php if ($anak['is_primary']): ?>
                                    <i class="fas fa-star text-warning ml-1" 
                                       title="Wali Utama" style="font-size:11px;"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="badge bg-info text-dark">
                            <?= e($anak['nama_kelas'] ?: 'Belum ada kelas') ?>
                        </span>
                    </div>

                    <!-- Body Card -->
                    <div class="card-body p-3">

                        <!-- Info Singkat -->
                        <div class="row mb-3" style="font-size:12px;">
                            <div class="col-6">
                                <div class="text-muted small">NIS</div>
                                <div><strong><?= e($anak['nis']) ?></strong></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small">Umur</div>
                                <div><strong><?= $umur ?: '-' ?></strong></div>
                            </div>
                        </div>

                        <hr class="my-2">

                        <!-- Statistik Tagihan -->
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <div class="small text-muted">Tagihan Aktif</div>
                                <div class="h5 mb-0 font-weight-bold 
                                            <?= $anak['jml_tagihan_aktif'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $anak['jml_tagihan_aktif'] ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Tunggakan</div>
                                <div class="h6 mb-0 font-weight-bold text-danger" style="font-size:14px;">
                                    <?= rupiah($anak['total_tunggakan']) ?>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="small text-muted">Total Dibayar</div>
                                <div class="h6 mb-0 font-weight-bold text-success" style="font-size:14px;">
                                    <?= rupiah($anak['total_dibayar']) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Progress: Lunas vs Total -->
                        <?php
                        $totalTagihan = $anak['jml_tagihan_lunas'] + $anak['jml_tagihan_aktif'];
                        $persen = $totalTagihan > 0 ? round(($anak['jml_tagihan_lunas'] / $totalTagihan) * 100) : 0;
                        ?>
                        <?php if ($totalTagihan > 0): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>Progress Pembayaran</span>
                                    <span><?= $anak['jml_tagihan_lunas'] ?>/<?= $totalTagihan ?> lunas</span>
                                </div>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-success" 
                                         style="width: <?= $persen ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- Footer: Aksi -->
                    <div class="card-footer bg-white py-2">
                        <div class="d-flex flex-wrap" style="gap:4px;">
                            <a href="<?= BASE_URL ?>/anak/detail/<?= (int) $anak['id'] ?>"
                               class="btn btn-sm btn-info flex-fill">
                                <i class="fas fa-eye"></i> Detail
                            </a>
                            <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $anak['id'] ?>"
                               class="btn btn-sm btn-primary flex-fill">
                                <i class="fas fa-file-invoice"></i> Tagihan
                            </a>
                            <?php if ($anak['jml_tagihan_aktif'] > 0): ?>
                                <a href="<?= BASE_URL ?>/pembayaran/upload?anak=<?= (int) $anak['id'] ?>"
                                   class="btn btn-sm btn-success flex-fill">
                                    <i class="fas fa-upload"></i> Bayar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<style>
.card-footer .btn {
    font-size: 12px;
    padding: 6px 8px;
}
.card-footer .btn i {
    font-size: 11px;
}
</style>