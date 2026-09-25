<?php
// modules/riwayat_kelas/index.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('wali')) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus wali santri.');
}

$current_module = 'riwayat_kelas';
$userId = currentUser()['id'];

// ============================================
// AMBIL ANAK-ANAK WALI
// ============================================
$anakList = fetchAll("
    SELECT 
        s.id, s.nis, s.nama, s.nama_panggilan, s.jenis_kelamin,
        s.foto, s.status, s.tanggal_masuk,
        k.nama_kelas AS kelas_sekarang
    FROM orang_tua ot
    JOIN wali_santri ws ON ws.orang_tua_id = ot.id
    JOIN santri s ON s.id = ws.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE ot.user_id = ?
    ORDER BY s.status = 'aktif' DESC, s.nama ASC
", [$userId]);

// Filter anak tertentu (kalau ada ?santri=X)
$santriId = (int) ($_GET['santri'] ?? 0);

if ($santriId > 0) {
    // Cek akses
    if (!isAnakDariWali($santriId, $userId)) {
        http_response_code(403);
        exit('403 - Ini bukan anak Anda.');
    }
    $anakList = array_filter($anakList, fn($a) => $a['id'] == $santriId);
}

// ============================================
// AMBIL RIWAYAT KELAS PER ANAK
// ============================================
$riwayatPerAnak = [];
$statPerAnak = [];

foreach ($anakList as $anak) {
    $riwayatPerAnak[$anak['id']] = fetchAll("
        SELECT rk.*, k.nama_kelas, k.urutan, k.tingkat
        FROM riwayat_kelas rk
        JOIN kelas k ON k.id = rk.kelas_id
        WHERE rk.santri_id = ?
        ORDER BY rk.tanggal_mulai DESC
    ", [$anak['id']]);

    // Statistik per anak
    $jmlKelas = count($riwayatPerAnak[$anak['id']]);
    $totalHari = 0;
    $kelasAktif = null;

    foreach ($riwayatPerAnak[$anak['id']] as $r) {
        if ($r['tanggal_selesai']) {
            $totalHari += (strtotime($r['tanggal_selesai']) - strtotime($r['tanggal_mulai'])) / 86400;
        } else {
            $totalHari += (time() - strtotime($r['tanggal_mulai'])) / 86400;
            $kelasAktif = $r;
        }
    }

    $statPerAnak[$anak['id']] = [
        'jml_kelas'    => $jmlKelas,
        'total_hari'   => round($totalHari),
        'kelas_aktif'  => $kelasAktif,
    ];
}
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-book-reader text-primary"></i> Perjalanan Belajar
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Riwayat kenaikan kelas & perkembangan anak-anak Anda
            </p>
        </div>
        <a href="<?= BASE_URL ?>/anak" class="btn btn-outline-primary btn-sm mt-2 mt-md-0">
            <i class="fas fa-child"></i> Daftar Anak
        </a>
    </div>

    <!-- Flash -->
    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($anakList)): ?>
        <!-- Empty State -->
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                <h5>Belum ada anak yang terhubung</h5>
                <p class="text-muted mb-0">
                    Hubungi admin TPQ untuk menghubungkan akun Anda dengan data anak.
                </p>
            </div>
        </div>
    <?php else: ?>

        <!-- Filter Anak (kalau lebih dari 1) -->
        <?php if (count($anakList) > 1): ?>
        <div class="card shadow mb-3">
            <div class="card-body py-2">
                <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                    <span class="text-muted small">
                        <i class="fas fa-filter"></i> Filter:
                    </span>
                    <a href="<?= BASE_URL ?>/riwayat_kelas"
                       class="btn btn-sm <?= $santriId == 0 ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <i class="fas fa-users"></i> Semua Anak
                    </a>
                    <?php foreach ($anakList as $anak):
                        $anakAsli = $santriId == 0 ? $anak : $anak;
                    ?>
                    <?php endforeach; ?>
                    <?php
                    // Ambil semua anak dari wali (tanpa filter) untuk button
                    $allAnak = fetchAll("
                        SELECT s.id, s.nama
                        FROM orang_tua ot
                        JOIN wali_santri ws ON ws.orang_tua_id = ot.id
                        JOIN santri s ON s.id = ws.santri_id
                        WHERE ot.user_id = ?
                        ORDER BY s.nama
                    ", [$userId]);
                    foreach ($allAnak as $aa):
                    ?>
                        <a href="<?= BASE_URL ?>/riwayat_kelas?santri=<?= (int) $aa['id'] ?>"
                           class="btn btn-sm <?= $santriId == $aa['id'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                            <i class="fas fa-child"></i> <?= e($aa['nama']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Timeline per Anak -->
        <?php foreach ($anakList as $anak):
            $riwayat = $riwayatPerAnak[$anak['id']];
            $stat = $statPerAnak[$anak['id']];

            // Umur
            $umur = '';
            if ($anak['tanggal_lahir'] ?? null) {
                $umur = (new DateTime($anak['tanggal_lahir']))->diff(new DateTime())->y;
            }

            // Format total hari
            if ($stat['total_hari'] < 30) {
                $durasiTotal = $stat['total_hari'] . ' hari';
            } elseif ($stat['total_hari'] < 365) {
                $durasiTotal = round($stat['total_hari'] / 30) . ' bulan';
            } else {
                $durasiTotal = round($stat['total_hari'] / 365, 1) . ' tahun';
            }
        ?>

        <div class="card shadow mb-4">
            <!-- Header Anak -->
            <div class="card-header bg-primary text-white py-3">
                <div class="d-flex align-items-center flex-wrap">
                    <div class="rounded-circle mr-3 flex-shrink-0"
                         style="width:52px;height:52px;
                                background:<?= $anak['jenis_kelamin']==='L' ? 'rgba(255,255,255,0.25)' : 'rgba(236,72,153,0.4)' ?>;
                                display:flex;align-items:center;justify-content:center;
                                color:#fff;font-weight:700;font-size:20px;
                                border:2px solid rgba(255,255,255,0.3);">
                        <?= e(strtoupper(substr($anak['nama'], 0, 1))) ?>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 font-weight-bold"><?= e($anak['nama']) ?></h5>
                        <div class="small" style="opacity:0.9;">
                            NIS: <?= e($anak['nis']) ?>
                            · Kelas: <?= e($anak['kelas_sekarang'] ?: 'Belum ada') ?>
                            <?php if ($umur): ?> · <?= $umur ?> tahun<?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <?php
                        $sc = ['aktif'=>'light','lulus'=>'warning','keluar'=>'secondary','cuti'=>'info'];
                        $scText = ['aktif'=>'text-success','lulus'=>'text-warning','keluar'=>'text-secondary','cuti'=>'text-info'];
                        ?>
                        <span class="badge badge-<?= $sc[$anak['status']] ?? 'secondary' ?> <?= $scText[$anak['status']] ?? '' ?>"
                              style="padding:6px 14px;font-size:11px;">
                            <?= ucfirst($anak['status']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Statistik Anak -->
            <div class="card-body border-bottom">
                <div class="row text-center">
                    <div class="col-4 border-right">
                        <div class="small text-muted">Total Kelas</div>
                        <div class="h4 mb-0 font-weight-bold text-primary">
                            <?= $stat['jml_kelas'] ?>
                        </div>
                    </div>
                    <div class="col-4 border-right">
                        <div class="small text-muted">Total Waktu Belajar</div>
                        <div class="h5 mb-0 font-weight-bold text-info">
                            <?= $durasiTotal ?>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Kelas Sekarang</div>
                        <div class="h6 mb-0 font-weight-bold text-success">
                            <?= e($stat['kelas_aktif']['nama_kelas'] ?? $anak['kelas_sekarang'] ?: '-') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card-body">
                <?php if (empty($riwayat)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <p class="mb-0">Belum ada riwayat kelas</p>
                        <small>Riwayat akan muncul setelah anak mulai belajar atau naik kelas</small>
                    </div>
                <?php else: ?>

                <div class="timeline">
                    <?php foreach ($riwayat as $i => $r):
                        $isActive = $r['tanggal_selesai'] === null;

                        // Durasi
                        if ($r['tanggal_selesai']) {
                            $hari = (strtotime($r['tanggal_selesai']) - strtotime($r['tanggal_mulai'])) / 86400;
                        } else {
                            $hari = (time() - strtotime($r['tanggal_mulai'])) / 86400;
                        }

                        if ($hari < 30) {
                            $durasi = round($hari) . ' hari';
                        } elseif ($hari < 365) {
                            $durasi = round($hari / 30) . ' bulan';
                        } else {
                            $durasi = round($hari / 365, 1) . ' tahun';
                        }

                        // Jenis kenaikan
                        $jc = [
                            'naik'    => ['label' => 'Naik Kelas', 'color' => 'success', 'icon' => 'arrow-up'],
                            'tinggal' => ['label' => 'Tinggal Kelas', 'color' => 'warning', 'icon' => 'arrow-down'],
                            'pindah'  => ['label' => 'Pindah Kelas', 'color' => 'info', 'icon' => 'exchange-alt'],
                        ];
                        $j = $jc[$r['jenis_kenaikan']] ?? null;

                        // Nilai warna
                        $nc = [
                            'A' => 'success',
                            'B' => 'info',
                            'C' => 'warning',
                            'D' => 'danger',
                        ];
                    ?>
                        <div class="timeline-item <?= $isActive ? 'active' : '' ?>">
                            <div class="timeline-marker <?= $isActive ? 'bg-success' : 'bg-secondary' ?>">
                                <?php if ($isActive): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <i class="fas fa-graduation-cap"></i>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                                    <div>
                                        <h6 class="mb-1 font-weight-bold text-primary">
                                            <?= e($r['nama_kelas']) ?>
                                            <?php if ($r['tingkat']): ?>
                                                <span class="badge bg-light text-dark ml-1" style="font-size:9px;">
                                                    <?= e($r['tingkat']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success ml-1" style="font-size:9px;">
                                                    <i class="fas fa-circle" style="font-size:5px;"></i> Sekarang
                                                </span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar"></i>
                                            <?= tanggalIndo($r['tanggal_mulai']) ?>
                                            <?php if ($r['tanggal_selesai']): ?>
                                                → <?= tanggalIndo($r['tanggal_selesai']) ?>
                                            <?php endif; ?>
                                            <span class="mx-1">·</span>
                                            <i class="fas fa-clock"></i> <?= $durasi ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <?php if ($j): ?>
                                            <span class="badge bg-<?= $j['color'] ?>">
                                                <i class="fas fa-<?= $j['icon'] ?>"></i> <?= $j['label'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($r['nilai_capaian']): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-<?= $nc[strtoupper($r['nilai_capaian'])] ?? 'secondary' ?>">
                                                    Nilai: <?= e($r['nilai_capaian']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($r['keterangan']): ?>
                                    <div class="small p-2 mt-2 rounded" style="background:#f8fafc;border-left:3px solid #cbd5e1;">
                                        <i class="fas fa-comment text-muted"></i>
                                        <?= e($r['keterangan']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>

            <!-- Footer: Aksi -->
            <div class="card-footer bg-light py-2">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Kenaikan kelas berdasarkan kemampuan santri, bukan periode waktu
                    </small>
                    <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $anak['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-invoice"></i> Lihat Tagihan
                    </a>
                </div>
            </div>
        </div>

        <?php endforeach; ?>

    <?php endif; ?>

</div>

<style>
.table-hover tbody tr:hover { background: #f8fafc; }

/* Timeline */
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-marker {
    position: absolute;
    left: -33px;
    top: 2px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    border: 3px solid #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    z-index: 1;
}
.timeline-content {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    transition: all 0.2s;
}
.timeline-content:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.timeline-item.active .timeline-content {
    border: 2px solid #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.15);
}

@media (max-width: 576px) {
    .timeline { padding-left: 32px; }
    .timeline-marker {
        left: -27px;
        width: 24px;
        height: 24px;
        font-size: 10px;
    }
    .timeline::before { left: 11px; }
}
</style>