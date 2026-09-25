<?php
// modules/anak/detail.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('wali')) {
    http_response_code(403);
    exit('Akses ditolak. Halaman ini khusus wali santri.');
}

$santriId = (int) ($id ?? 0);
if ($santriId <= 0) {
    setFlash('error', 'ID santri tidak valid.');
    redirect('anak');
}

// Cek apakah santri ini benar anak dari wali yang login
if (!isAnakDariWali($santriId, currentUser()['id'])) {
    http_response_code(403);
    exit('403 - Ini bukan anak Anda.');
}

// Ambil data santri
$santri = fetchOne("
    SELECT s.*, k.nama_kelas, k.urutan
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE s.id = ?
", [$santriId]);

if (!$santri) {
    setFlash('error', 'Santri tidak ditemukan.');
    redirect('anak');
}

// ============================================
// DATA TAGIHAN
// ============================================
$tagihanAktif = fetchAll("
    SELECT t.*, jp.nama AS jenis_nama
    FROM tagihan t
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    WHERE t.santri_id = ?
      AND t.status IN ('belum_lunas','menunggu_verifikasi','ditolak')
    ORDER BY t.jatuh_tempo ASC, t.created_at DESC
", [$santriId]);

$tagihanLunas = fetchAll("
    SELECT t.*, jp.nama AS jenis_nama
    FROM tagihan t
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    WHERE t.santri_id = ? AND t.status='lunas'
    ORDER BY t.created_at DESC
    LIMIT 5
", [$santriId]);

// Statistik
$sumTagihan = fetchOne("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status='lunas' THEN 1 ELSE 0 END) AS lunas,
        SUM(CASE WHEN status='belum_lunas' THEN 1 ELSE 0 END) AS belum,
        SUM(CASE WHEN status='menunggu_verifikasi' THEN 1 ELSE 0 END) AS menunggu,
        COALESCE(SUM(CASE WHEN status='belum_lunas' THEN nominal END), 0) AS total_tunggakan,
        COALESCE(SUM(CASE WHEN status='lunas' THEN nominal END), 0) AS total_lunas
    FROM tagihan WHERE santri_id = ?
", [$santriId]);

// ============================================
// RIWAYAT KELAS (Perjalanan Belajar)
// ============================================
$riwayat = fetchAll("
    SELECT rk.*, k.nama_kelas, k.urutan
    FROM riwayat_kelas rk
    JOIN kelas k ON k.id = rk.kelas_id
    WHERE rk.santri_id = ?
    ORDER BY rk.tanggal_mulai DESC
", [$santriId]);

// ============================================
// RIWAYAT PEMBAYARAN
// ============================================
$pembayaranList = fetchAll("
    SELECT p.*, t.id AS tagihan_id, jp.nama AS jenis_nama,
           v.name AS nama_verifikator
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    LEFT JOIN users v ON v.id = p.verified_by
    WHERE t.santri_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
", [$santriId]);

// Umur
$umur = '';
if ($santri['tanggal_lahir']) {
    $umur = (new DateTime($santri['tanggal_lahir']))->diff(new DateTime())->y;
}
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/anak" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-child text-success"></i>
                <?= e($santri['nama']) ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                NIS: <?= e($santri['nis']) ?>
                · Kelas: <?= e($santri['nama_kelas'] ?: 'Belum ada') ?>
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/tagihan/santri/<?= $santriId ?>"
               class="btn btn-sm btn-primary">
                <i class="fas fa-file-invoice"></i> Semua Tagihan
            </a>
            <?php if (!empty($tagihanAktif)): ?>
                <a href="<?= BASE_URL ?>/pembayaran/upload?anak=<?= $santriId ?>"
                   class="btn btn-sm btn-success">
                    <i class="fas fa-upload"></i> Bayar
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <div class="row">

        <!-- ============================================
             KIRI: Profil & Biodata
             ============================================ -->
        <div class="col-lg-4 mb-3">

            <!-- Kartu Profil -->
            <div class="card shadow mb-3">
                <div class="card-body text-center">
                    <?php if ($santri['foto'] && file_exists(__DIR__ . '/../../' . $santri['foto'])): ?>
                        <img src="<?= BASE_URL . '/' . e($santri['foto']) ?>"
                             style="width:100px;height:100px;object-fit:cover;
                                    border-radius:50%;border:4px solid #e8f0fe;">
                    <?php else: ?>
                        <div class="rounded-circle mx-auto"
                             style="width:100px;height:100px;
                                    background:<?= $santri['jenis_kelamin']==='L' ? '#2c6b9e' : '#ec4899' ?>;
                                    display:flex;align-items:center;justify-content:center;
                                    color:#fff;font-weight:700;font-size:40px;
                                    border:4px solid #f3f4f6;">
                            <?= e(strtoupper(substr($santri['nama'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="mt-3 mb-1"><?= e($santri['nama']) ?></h5>
                    <p class="text-muted mb-2"><?= e($santri['nama_panggilan'] ?: '-') ?></p>

                    <?php
                    $sc = [
                        'aktif'  => 'bg-success',
                        'lulus'  => 'bg-primary',
                        'keluar' => 'bg-secondary',
                        'cuti'   => 'bg-warning text-dark',
                    ];
                    ?>
                    <span class="badge <?= $sc[$santri['status']] ?? 'bg-secondary' ?>"
                          style="font-size:12px;padding:5px 14px;">
                        <?= ucfirst($santri['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Biodata -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-id-card"></i> Biodata
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td width="40%" class="text-muted">NIS</td>
                            <td><strong><?= e($santri['nis']) ?></strong></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Jenis Kelamin</td>
                            <td><?= $santri['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">TTL</td>
                            <td>
                                <?= e($santri['tempat_lahir'] ?: '-') ?>
                                <?php if ($santri['tanggal_lahir']): ?>
                                    , <?= tanggalIndo($santri['tanggal_lahir']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Umur</td>
                            <td><?= $umur ?> tahun</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Kelas</td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= e($santri['nama_kelas'] ?: 'Belum ada') ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tgl Masuk</td>
                            <td><?= tanggalIndo($santri['tanggal_masuk']) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Statistik Tagihan -->
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> Ringkasan Tagihan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-2">
                        <div class="col-4">
                            <div class="small text-muted">Total</div>
                            <div class="font-weight-bold"><?= $sumTagihan['total'] ?? 0 ?></div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Lunas</div>
                            <div class="font-weight-bold text-success">
                                <?= $sumTagihan['lunas'] ?? 0 ?>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted">Belum</div>
                            <div class="font-weight-bold text-danger">
                                <?= ($sumTagihan['belum'] ?? 0) + ($sumTagihan['menunggu'] ?? 0) ?>
                            </div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">Tunggakan</td>
                            <td class="text-right font-weight-bold text-danger">
                                <?= rupiah($sumTagihan['total_tunggakan'] ?? 0) ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Sudah Dibayar</td>
                            <td class="text-right font-weight-bold text-success">
                                <?= rupiah($sumTagihan['total_lunas'] ?? 0) ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================
             KANAN: Tagihan & Riwayat
             ============================================ -->
        <div class="col-lg-8 mb-3">

            <!-- TAGIHAN AKTIF -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-circle"></i> Tagihan Aktif
                        <span class="badge bg-danger"><?= count($tagihanAktif) ?></span>
                    </h6>
                    <?php if (!empty($tagihanAktif)): ?>
                        <a href="<?= BASE_URL ?>/pembayaran/upload?anak=<?= $santriId ?>"
                           class="btn btn-sm btn-success">
                            <i class="fas fa-upload"></i> Bayar
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($tagihanAktif)): ?>
                        <div class="text-center py-4 text-success">
                            <i class="fas fa-check-circle fa-3x mb-2"></i>
                            <p class="mb-0 font-weight-bold">Semua tagihan lunas! 🎉</p>
                            <small class="text-muted">Terima kasih atas pembayarannya</small>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Periode</th>
                                        <th>Jatuh Tempo</th>
                                        <th class="text-right">Nominal</th>
                                        <th class="text-center">Status</th>
                                        <th style="width:80px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tagihanAktif as $t):
                                    $tc = [
                                        'belum_lunas'         => 'bg-danger',
                                        'menunggu_verifikasi' => 'bg-warning text-dark',
                                        'ditolak'             => 'bg-secondary',
                                    ];
                                    $terlambat = $t['jatuh_tempo'] 
                                                 && strtotime($t['jatuh_tempo']) < time()
                                                 && $t['status'] === 'belum_lunas';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($t['jenis_nama']) ?></strong>
                                        </td>
                                        <td>
                                            <small><?= e($t['periode'] ?: '-') ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?= $t['jatuh_tempo'] ? tanggalIndo($t['jatuh_tempo']) : '-' ?>
                                            </small>
                                            <?php if ($terlambat): ?>
                                                <span class="badge bg-danger ml-1" style="font-size:9px;">
                                                    Lewat
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <strong><?= rupiah($t['nominal']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $tc[$t['status']] ?? 'bg-secondary' ?>">
                                                <?= ucfirst(str_replace('_',' ',$t['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($t['status'] === 'belum_lunas'): ?>
                                                <a href="<?= BASE_URL ?>/pembayaran/upload/<?= (int) $t['id'] ?>"
                                                   class="btn btn-sm btn-primary" title="Bayar">
                                                    <i class="fas fa-upload"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="<?= BASE_URL ?>/tagihan/detail/<?= (int) $t['id'] ?>"
                                                   class="btn btn-sm btn-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIWAYAT PEMBAYARAN -->
            <?php if (!empty($pembayaranList)): ?>
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-history"></i> Riwayat Pembayaran Terbaru
                    </h6>
                    <a href="<?= BASE_URL ?>/pembayaran/riwayat?santri=<?= $santriId ?>"
                       class="small text-primary">
                        Lihat semua →
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                    <?php foreach ($pembayaranList as $p): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center flex-wrap mb-1" style="gap:6px;">
                                        <strong><?= rupiah($p['nominal_bayar']) ?></strong>
                                        <span class="badge bg-light text-dark">
                                            <?= e($p['jenis_nama']) ?>
                                        </span>
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
                                    <?php if ($p['catatan_admin']): ?>
                                        <div class="small text-danger mt-1">
                                            <i class="fas fa-comment"></i>
                                            <?= e($p['catatan_admin']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= BASE_URL ?>/pembayaran/detail/<?= (int) $p['id'] ?>"
                                   class="btn btn-sm btn-outline-info ml-2">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- PERJALANAN BELAJAR (Riwayat Kelas) -->
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-book-reader"></i> Perjalanan Belajar
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($riwayat)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-info-circle"></i>
                            <p class="mb-0">Belum ada riwayat kelas</p>
                        </div>
                    <?php else: ?>

                    <div class="timeline">
                        <?php foreach ($riwayat as $r):
                            $isActive = $r['tanggal_selesai'] === null;
                            $durasi = '';

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

                            $jc = [
                                'naik'    => ['label' => 'Naik',    'color' => 'success', 'icon' => 'arrow-up'],
                                'tinggal' => ['label' => 'Tinggal', 'color' => 'warning', 'icon' => 'arrow-down'],
                                'pindah'  => ['label' => 'Pindah',  'color' => 'info',    'icon' => 'exchange-alt'],
                            ];
                            $j = $jc[$r['jenis_kenaikan']] ?? ['label' => '-', 'color' => 'secondary', 'icon' => 'circle'];
                        ?>
                            <div class="timeline-item <?= $isActive ? 'active' : '' ?>">
                                <div class="timeline-marker bg-<?= $isActive ? 'success' : 'secondary' ?>">
                                    <i class="fas fa-<?= $isActive ? 'check' : 'graduation-cap' ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap mb-1">
                                        <div>
                                            <strong class="text-primary"><?= e($r['nama_kelas']) ?></strong>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success ml-1" style="font-size:9px;">
                                                    Sekarang
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($r['nilai_capaian']): ?>
                                            <span class="badge bg-primary">
                                                Nilai: <?= e($r['nilai_capaian']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fas fa-calendar"></i>
                                        <?= tanggalIndo($r['tanggal_mulai']) ?>
                                        <?php if ($r['tanggal_selesai']): ?>
                                            → <?= tanggalIndo($r['tanggal_selesai']) ?>
                                        <?php endif; ?>
                                        · <?= $durasi ?>
                                    </div>
                                    <?php if ($r['keterangan']): ?>
                                        <div class="small text-muted mt-1" style="font-style:italic;">
                                            "<?= e($r['keterangan']) ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }

/* Timeline */
.timeline {
    position: relative;
    padding-left: 36px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 12px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
.timeline-item {
    position: relative;
    padding-bottom: 16px;
}
.timeline-item:last-child { padding-bottom: 0; }
.timeline-marker {
    position: absolute;
    left: -30px;
    top: 2px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    border: 3px solid #fff;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    z-index: 1;
}
.timeline-content {
    background: #f8fafc;
    padding: 10px 14px;
    border-radius: 8px;
    border-left: 3px solid #cbd5e1;
}
.timeline-item.active .timeline-content {
    background: #f0fdf4;
    border-left-color: #16a34a;
}
</style>