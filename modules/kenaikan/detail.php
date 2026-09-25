<?php
// modules/kenaikan/detail.php
require_once __DIR__ . '/../../config/functions.php';

$santriId = (int) ($id ?? 0);
$santri = fetchOne("SELECT s.*, k.nama_kelas FROM santri s
                    LEFT JOIN kelas k ON k.id = s.kelas_id
                    WHERE s.id = ?", [$santriId]);

if (!$santri) { setFlash('error', 'Santri tidak ditemukan.'); redirect('kenaikan'); }

// Cek akses wali
if (currentRole() === 'wali' && !isAnakDariWali($santriId, currentUser()['id'])) {
    http_response_code(403);
    exit('403 - Akses ditolak.');
}

// Riwayat kelas (timeline)
$riwayat = fetchAll("
    SELECT rk.*, k.nama_kelas, k.urutan,
           u.name AS dipindahkan_oleh
    FROM riwayat_kelas rk
    JOIN kelas k ON k.id = rk.kelas_id
    LEFT JOIN users u ON u.id = rk.dipindahkan_oleh
    WHERE rk.santri_id = ?
    ORDER BY rk.tanggal_mulai DESC
", [$santriId]);

// Statistik
$totalKelas = count($riwayat);
$kelasAktif = null;
foreach ($riwayat as $r) {
    if ($r['tanggal_selesai'] === null) {
        $kelasAktif = $r;
        break;
    }
}

// Rata-rata lama per kelas
$rataLama = 0;
$countLama = 0;
foreach ($riwayat as $r) {
    if ($r['tanggal_selesai']) {
        $hari = (strtotime($r['tanggal_selesai']) - strtotime($r['tanggal_mulai'])) / 86400;
        $rataLama += $hari;
        $countLama++;
    }
}
$rataLama = $countLama > 0 ? round($rataLama / $countLama) : 0;
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/kenaikan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-stream text-primary"></i>
                Perjalanan Belajar — <?= e($santri['nama']) ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                NIS: <?= e($santri['nis']) ?>
                · Kelas saat ini: <strong><?= e($santri['nama_kelas'] ?: 'Belum ada') ?></strong>
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/kenaikan/create?santri=<?= $santriId ?>"
               class="btn btn-sm btn-success">
                <i class="fas fa-plus"></i> Naik Kelas
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

    <!-- Statistik -->
    <div class="row mb-3">
        <div class="col-md-4 col-4 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Kelas Dilalui
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $totalKelas ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-4 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Kelas Saat Ini
                    </div>
                    <div class="h6 mb-0 font-weight-bold">
                        <?= e($santri['nama_kelas'] ?: '-') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-4 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Rata-rata Lama
                    </div>
                    <div class="h6 mb-0 font-weight-bold">
                        <?= $rataLama > 0 ? $rataLama . ' hari' : '-' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="card shadow mb-4">
        <div class="card-header py-2 bg-light">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-stream"></i> Timeline Perjalanan
            </h6>
        </div>
        <div class="card-body">

            <?php if (empty($riwayat)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-stream fa-3x mb-3"></i>
                    <p>Belum ada riwayat kelas</p>
                </div>
            <?php else: ?>

            <div class="timeline">
                <?php foreach ($riwayat as $i => $r):
                    $isActive = $r['tanggal_selesai'] === null;
                    $durasi = '';
                    if ($r['tanggal_selesai']) {
                        $hari = (strtotime($r['tanggal_selesai']) - strtotime($r['tanggal_mulai'])) / 86400;
                        if ($hari < 30) {
                            $durasi = round($hari) . ' hari';
                        } elseif ($hari < 365) {
                            $durasi = round($hari / 30) . ' bulan';
                        } else {
                            $durasi = round($hari / 365, 1) . ' tahun';
                        }
                    } else {
                        $hari = (time() - strtotime($r['tanggal_mulai'])) / 86400;
                        if ($hari < 30) {
                            $durasi = round($hari) . ' hari (aktif)';
                        } elseif ($hari < 365) {
                            $durasi = round($hari / 30) . ' bulan (aktif)';
                        } else {
                            $durasi = round($hari / 365, 1) . ' tahun (aktif)';
                        }
                    }

                    $jc = [
                        'naik'    => ['label' => 'Naik', 'color' => 'success', 'icon' => 'arrow-up'],
                        'tinggal' => ['label' => 'Tinggal', 'color' => 'warning', 'icon' => 'arrow-down'],
                        'pindah'  => ['label' => 'Pindah', 'color' => 'info', 'icon' => 'exchange-alt'],
                    ];
                    $j = $jc[$r['jenis_kenaikan']] ?? ['label' => '-', 'color' => 'secondary', 'icon' => 'circle'];
                ?>
                    <div class="timeline-item <?= $isActive ? 'active' : '' ?>">
                        <div class="timeline-marker bg-<?= $isActive ? 'success' : 'secondary' ?>">
                            <i class="fas fa-<?= $isActive ? 'check' : 'graduation-cap' ?>"></i>
                        </div>
                        <div class="timeline-content card mb-3">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start flex-wrap mb-2">
                                    <div>
                                        <h6 class="mb-1 font-weight-bold text-primary">
                                            <?= e($r['nama_kelas']) ?>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success ml-1">Sekarang</span>
                                            <?php endif; ?>
                                        </h6>
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar"></i>
                                            <?= tanggalIndo($r['tanggal_mulai']) ?>
                                            <?php if ($r['tanggal_selesai']): ?>
                                                → <?= tanggalIndo($r['tanggal_selesai']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="badge bg-<?= $j['color'] ?>">
                                            <i class="fas fa-<?= $j['icon'] ?>"></i>
                                            <?= $j['label'] ?>
                                        </span>
                                        <?php if ($r['nilai_capaian']): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-primary">
                                                    Nilai: <?= e($r['nilai_capaian']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row small text-muted mb-2">
                                    <div class="col-md-6">
                                        <i class="fas fa-clock"></i>
                                        Lama: <strong><?= $durasi ?></strong>
                                    </div>
                                    <div class="col-md-6 text-md-right">
                                        <?php if ($r['dipindahkan_oleh']): ?>
                                            <i class="fas fa-user"></i>
                                            oleh <?= e($r['dipindahkan_oleh']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($r['keterangan']): ?>
                                    <div class="small" style="background:#f8fafc; padding:8px 12px; border-radius:6px; border-left:3px solid #2c6b9e;">
                                        <i class="fas fa-comment text-muted"></i>
                                        <?= e($r['keterangan']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </div>
    </div>

</div>

<style>
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
    padding-bottom: 0;
}
.timeline-marker {
    position: absolute;
    left: -33px;
    top: 12px;
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
.timeline-item.active .timeline-content {
    border: 2px solid #16a34a;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.15);
}
.timeline-content {
    border: 1px solid #e5e7eb;
}
</style>