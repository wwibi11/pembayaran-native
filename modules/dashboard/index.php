<?php
// modules/dashboard/index.php

require_once __DIR__ . '/../../config/functions.php';

$user     = currentUser();
$role     = $user['role'];
$userId   = $user['id'];
$namaUser = $user['name'];

// ============================================
// HELPER: fetchColumn fallback
// ============================================
if (!function_exists('fetchColumn')) {
    function fetchColumn(string $sql, array $params = []) {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}

// ============================================
// HELPER: badge status tagihan
// ============================================
if (!function_exists('badgeTagihan')) {
    function badgeTagihan(string $status): string {
        return match($status) {
            'lunas'               => '<span class="badge bg-success">Lunas</span>',
            'menunggu_verifikasi' => '<span class="badge bg-warning text-dark">Menunggu</span>',
            'belum_lunas'         => '<span class="badge bg-danger">Belum Lunas</span>',
            'ditolak'             => '<span class="badge bg-secondary">Ditolak</span>',
            default               => '<span class="badge bg-secondary">' . e($status) . '</span>',
        };
    }
}

// ============================================
// ADMIN & KEPALA — Statistik Global
// ============================================
if (in_array($role, ['admin', 'kepala'])) {

    // --- Statistik utama ---
    $totalSantri   = (int) fetchColumn("SELECT COUNT(*) FROM santri WHERE status='aktif'");
    $totalKelas    = (int) fetchColumn("SELECT COUNT(*) FROM kelas WHERE is_active=1");
    $totalOrtu     = (int) fetchColumn("SELECT COUNT(*) FROM orang_tua");
    $totalWaliAkun = (int) fetchColumn("SELECT COUNT(*) FROM users WHERE role='wali' AND is_active=1");

    $tagihanLunas   = (int) fetchColumn("SELECT COUNT(*) FROM tagihan WHERE status='lunas'");
    $tagihanBelum   = (int) fetchColumn("SELECT COUNT(*) FROM tagihan WHERE status='belum_lunas'");
    $tagihanPending = (int) fetchColumn("SELECT COUNT(*) FROM tagihan WHERE status='menunggu_verifikasi'");

    // --- Pemasukan bulan ini & bulan lalu ---
    $pemasukanBulanIni = (float) fetchColumn("
        SELECT COALESCE(SUM(nominal_bayar),0) FROM pembayaran
        WHERE status='diverifikasi'
          AND MONTH(tanggal_bayar) = MONTH(CURDATE())
          AND YEAR(tanggal_bayar)  = YEAR(CURDATE())
    ");

    $pemasukanBulanLalu = (float) fetchColumn("
        SELECT COALESCE(SUM(nominal_bayar),0) FROM pembayaran
        WHERE status='diverifikasi'
          AND MONTH(tanggal_bayar) = MONTH(CURDATE() - INTERVAL 1 MONTH)
          AND YEAR(tanggal_bayar)  = YEAR(CURDATE() - INTERVAL 1 MONTH)
    ");

    $persenPemasukan = $pemasukanBulanLalu > 0
        ? round((($pemasukanBulanIni - $pemasukanBulanLalu) / $pemasukanBulanLalu) * 100, 1)
        : 0;

    // --- Chart 1: Santri per kelas ---
    $chartSantriPerKelas = fetchAll("
        SELECT k.nama_kelas, COUNT(s.id) AS total
        FROM kelas k
        LEFT JOIN santri s ON s.kelas_id = k.id AND s.status='aktif'
        WHERE k.is_active=1
        GROUP BY k.id, k.nama_kelas, k.urutan
        ORDER BY k.urutan
    ");

    // --- Chart 2: Status tagihan ---
    $chartStatusTagihan = fetchAll("
        SELECT status, COUNT(*) AS total FROM tagihan GROUP BY status
    ");

    // --- Chart 3: Pemasukan 6 bulan terakhir ---
    $chartPemasukan6Bulan = fetchAll("
        SELECT DATE_FORMAT(tanggal_bayar, '%Y-%m') AS bulan,
               DATE_FORMAT(tanggal_bayar, '%b %Y') AS label,
               SUM(nominal_bayar) AS total
        FROM pembayaran
        WHERE status='diverifikasi'
          AND tanggal_bayar >= CURDATE() - INTERVAL 6 MONTH
        GROUP BY bulan, label
        ORDER BY bulan
    ");

    // --- Recent: Pembayaran terbaru ---
    $recentPembayaran = fetchAll("
        SELECT p.id, p.nominal_bayar, p.tanggal_bayar, p.status,
               s.nama AS nama_santri, s.nis,
               jp.nama AS jenis
        FROM pembayaran p
        JOIN tagihan t ON t.id = p.tagihan_id
        JOIN santri s ON s.id = t.santri_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");

    // --- Tagihan tertunggak (5 paling urgent) ---
    $tagihanTertunggak = fetchAll("
        SELECT t.id, t.nominal, t.periode, t.jatuh_tempo, t.status,
               s.nama AS nama_santri, s.nis,
               k.nama_kelas,
               jp.nama AS jenis
        FROM tagihan t
        JOIN santri s ON s.id = t.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        WHERE t.status IN ('belum_lunas', 'menunggu_verifikasi')
        ORDER BY t.jatuh_tempo ASC
        LIMIT 5
    ");
}

// ============================================
// WALI — Statistik Anak Sendiri
// ============================================
if ($role === 'wali') {

    $anakList  = getAnakByWali($userId);
    $totalAnak = count($anakList);
    $anakIds   = array_column($anakList, 'id');

    $tagihanBelumAnak   = 0;
    $tagihanLunasAnak   = 0;
    $totalTunggakan     = 0;
    $totalBayarTahunIni = 0;
    $tagihanPerAnak     = [];

    if ($totalAnak > 0) {
        $ph = implode(',', array_fill(0, $totalAnak, '?'));

        $tagihanBelumAnak = (int) fetchColumn("
            SELECT COUNT(*) FROM tagihan
            WHERE santri_id IN ($ph)
              AND status IN ('belum_lunas', 'menunggu_verifikasi')
        ", $anakIds);

        $tagihanLunasAnak = (int) fetchColumn("
            SELECT COUNT(*) FROM tagihan
            WHERE santri_id IN ($ph) AND status='lunas'
        ", $anakIds);

        $totalTunggakan = (float) fetchColumn("
            SELECT COALESCE(SUM(nominal),0) FROM tagihan
            WHERE santri_id IN ($ph) AND status='belum_lunas'
        ", $anakIds);

        $totalBayarTahunIni = (float) fetchColumn("
            SELECT COALESCE(SUM(p.nominal_bayar),0)
            FROM pembayaran p
            JOIN tagihan t ON t.id = p.tagihan_id
            WHERE t.santri_id IN ($ph)
              AND p.status='diverifikasi'
              AND YEAR(p.tanggal_bayar) = YEAR(CURDATE())
        ", $anakIds);

        // Tagihan aktif per anak
        foreach ($anakList as $anak) {
            $tagihanPerAnak[$anak['id']] = fetchAll("
                SELECT t.id, t.nominal, t.periode, t.jatuh_tempo, t.status,
                       jp.nama AS jenis
                FROM tagihan t
                JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
                WHERE t.santri_id = ?
                  AND t.status IN ('belum_lunas', 'menunggu_verifikasi')
                ORDER BY t.jatuh_tempo ASC
                LIMIT 3
            ", [$anak['id']]);
        }
    }
}
?>

<div class="container-fluid">

    <!-- ============================================
         HEADER SAPAAN
         ============================================ -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
        <div>
            <h1 class="h3 mb-1 text-gray-800">
                Assalamu'alaikum, <?= e($namaUser) ?> 👋
            </h1>
            <p class="text-muted mb-0" style="font-size: 13px;">
                <?php if ($role === 'admin'): ?>
                    Berikut ringkasan sistem pembayaran TPQ Madin hari ini.
                <?php elseif ($role === 'kepala'): ?>
                    Pantau seluruh aktivitas pembayaran &amp; santri TPQ Madin.
                <?php else: ?>
                    Pantau tagihan &amp; pembayaran anak Anda.
                <?php endif; ?>
            </p>
        </div>
        <div class="text-muted" style="font-size: 12px;">
            <i class="fas fa-calendar-alt"></i>
            <?= tanggalIndo(date('Y-m-d')) ?>
        </div>
    </div>

    <?php if ($role === 'admin' || $role === 'kepala'): ?>
    <!-- ==================================================
         DASHBOARD: ADMIN & KEPALA
         ================================================== -->

    <!-- STAT CARDS UTAMA -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Santri Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalSantri) ?>
                            </div>
                            <div class="small text-gray-500">
                                <?= number_format($totalKelas) ?> kelas/level
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Pemasukan Bulan Ini
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= rupiah($pemasukanBulanIni) ?>
                            </div>
                            <div class="small <?= $persenPemasukan >= 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="fas fa-arrow-<?= $persenPemasukan >= 0 ? 'up' : 'down' ?>"></i>
                                <?= abs($persenPemasukan) ?>% vs bulan lalu
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Menunggu Verifikasi
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($tagihanPending) ?>
                            </div>
                            <div class="small text-gray-500">
                                Tagihan butuh validasi
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Belum Lunas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($tagihanBelum) ?>
                            </div>
                            <div class="small text-gray-500">
                                <?= number_format($tagihanLunas) ?> sudah lunas
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QUICK INFO -->
    <div class="row mb-4">
        <div class="col-md-3 col-6 mb-2">
            <div class="card shadow h-100 py-2">
                <div class="card-body text-center">
                    <div class="h5 mb-0 font-weight-bold text-primary">
                        <?= number_format($totalKelas) ?>
                    </div>
                    <div class="small text-gray-500">
                        <i class="fas fa-layer-group"></i> Kelas
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card shadow h-100 py-2">
                <div class="card-body text-center">
                    <div class="h5 mb-0 font-weight-bold text-info">
                        <?= number_format($totalOrtu) ?>
                    </div>
                    <div class="small text-gray-500">
                        <i class="fas fa-users"></i> Orang Tua/Wali
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        <?= number_format($totalWaliAkun) ?> punya akun
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card shadow h-100 py-2">
                <div class="card-body text-center">
                    <div class="h5 mb-0 font-weight-bold text-success">
                        <?= number_format($tagihanLunas) ?>
                    </div>
                    <div class="small text-gray-500">
                        <i class="fas fa-check-circle"></i> Tagihan Lunas
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card shadow h-100 py-2">
                <div class="card-body text-center">
                    <div class="h5 mb-0 font-weight-bold text-warning">
                        <?= number_format($tagihanPending + $tagihanBelum) ?>
                    </div>
                    <div class="small text-gray-500">
                        <i class="fas fa-hourglass-half"></i> Perlu Aksi
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CHARTS -->
    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Pemasukan 6 Bulan Terakhir
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper" style="height:260px;">
                        <canvas id="chartPemasukan"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-pie"></i> Status Tagihan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper" style="height:260px;">
                        <canvas id="chartStatusTagihan"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users"></i> Santri per Kelas
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper" style="height:260px;">
                        <canvas id="chartSantriKelas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABEL: RECENT & TERTUNGGAK -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-receipt"></i> Pembayaran Terbaru
                    </h6>
                    <a href="<?= BASE_URL ?>/pembayaran" class="small text-primary">Lihat semua →</a>
                </div>
                <div class="card-body p-0" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Santri</th>
                                <th>Jenis</th>
                                <th class="text-right">Nominal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPembayaran as $row): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= e($row['nama_santri']) ?></div>
                                    <div class="small text-muted"><?= e($row['nis']) ?></div>
                                </td>
                                <td><?= e($row['jenis']) ?></td>
                                <td class="text-right"><?= rupiah($row['nominal_bayar']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'diverifikasi'): ?>
                                        <span class="badge bg-success">✓</span>
                                    <?php elseif ($row['status'] === 'menunggu'): ?>
                                        <span class="badge bg-warning text-dark">⏳</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recentPembayaran)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        Belum ada pembayaran
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Tagihan Tertunggak
                    </h6>
                    <a href="<?= BASE_URL ?>/tagihan" class="small text-primary">Lihat semua →</a>
                </div>
                <div class="card-body p-0" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Santri</th>
                                <th>Tagihan</th>
                                <th class="text-right">Nominal</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tagihanTertunggak as $row): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= e($row['nama_santri']) ?></div>
                                    <div class="small text-muted">
                                        <?= e($row['nama_kelas'] ?? '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= e($row['jenis']) ?></div>
                                    <div class="small text-muted">
                                        <?= e($row['periode'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="text-right"><?= rupiah($row['nominal']) ?></td>
                                <td><?= badgeTagihan($row['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($tagihanTertunggak)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle text-success"></i>
                                        Semua tagihan lancar
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <?php if ($role === 'wali'): ?>
    <!-- ==================================================
         DASHBOARD: WALI SANTRI
         ================================================== -->

    <!-- STAT CARDS -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Anak Terdaftar
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalAnak) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-child fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Tagihan Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($tagihanBelumAnak) ?>
                            </div>
                            <div class="small text-gray-500">
                                Belum dibayar / menunggu
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Tunggakan
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= rupiah($totalTunggakan) ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Sudah Dibayar <?= date('Y') ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= rupiah($totalBayarTahunIni) ?>
                            </div>
                            <div class="small text-gray-500">
                                <?= number_format($tagihanLunasAnak) ?> tagihan lunas
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DAFTAR ANAK -->
    <?php if ($totalAnak === 0): ?>
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                <h5>Belum ada anak yang terhubung</h5>
                <p class="text-muted">Hubungi admin TPQ untuk menghubungkan akun Anda dengan data santri.</p>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($anakList as $anak): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center flex-wrap">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle mr-3"
                         style="width: 48px; height: 48px; background: #e8f0fe;
                                display: flex; align-items: center; justify-content: center;
                                color: #2c6b9e; font-weight: 700; font-size: 18px;">
                        <?= e(strtoupper(substr($anak['nama'], 0, 1))) ?>
                    </div>
                    <div>
                        <h6 class="mb-0 font-weight-bold text-primary">
                            <?= e($anak['nama']) ?>
                        </h6>
                        <div class="small text-muted">
                            NIS: <?= e($anak['nis']) ?> ·
                            Kelas: <?= e($anak['nama_kelas'] ?? 'Belum ada kelas') ?>
                        </div>
                    </div>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="<?= BASE_URL ?>/tagihan?anak=<?= $anak['id'] ?>"
                       class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-file-invoice"></i> Lihat Tagihan
                    </a>
                    <a href="<?= BASE_URL ?>/pembayaran/upload?anak=<?= $anak['id'] ?>"
                       class="btn btn-sm btn-primary">
                        <i class="fas fa-upload"></i> Bayar
                    </a>
                </div>
            </div>

            <div class="card-body p-0">
                <?php $tagihanAnak = $tagihanPerAnak[$anak['id']] ?? []; ?>
                <?php if (empty($tagihanAnak)): ?>
                    <div class="text-center py-4 text-success">
                        <i class="fas fa-check-circle"></i>
                        Tidak ada tagihan tertunggak. Alhamdulillah! 🎉
                    </div>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Jenis</th>
                                <th>Periode</th>
                                <th>Jatuh Tempo</th>
                                <th class="text-right">Nominal</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tagihanAnak as $t):
                                $terlambat = $t['jatuh_tempo'] && strtotime($t['jatuh_tempo']) < time()
                                             && $t['status'] === 'belum_lunas';
                            ?>
                            <tr>
                                <td><?= e($t['jenis']) ?></td>
                                <td><?= e($t['periode'] ?? '-') ?></td>
                                <td>
                                    <?= tanggalIndo($t['jatuh_tempo']) ?>
                                    <?php if ($terlambat): ?>
                                        <span class="badge bg-danger ml-1">Lewat</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right font-weight-bold">
                                    <?= rupiah($t['nominal']) ?>
                                </td>
                                <td><?= badgeTagihan($t['status']) ?></td>
                                <td class="text-right">
                                    <?php if ($t['status'] === 'belum_lunas'): ?>
                                        <a href="<?= BASE_URL ?>/pembayaran/upload?tagihan=<?= $t['id'] ?>"
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-upload"></i> Bayar
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <?php endif; ?>

</div>

<?php if ($role === 'admin' || $role === 'kepala'): ?>
<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {

    // 1. Pemasukan 6 Bulan
    const ctxPemasukan = document.getElementById('chartPemasukan');
    if (ctxPemasukan) {
        new Chart(ctxPemasukan.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($chartPemasukan6Bulan, 'label')) ?>,
                datasets: [{
                    label: 'Pemasukan',
                    data: <?= json_encode(array_map('floatval', array_column($chartPemasukan6Bulan, 'total'))) ?>,
                    borderColor: '#2c6b9e',
                    backgroundColor: 'rgba(44, 107, 158, 0.1)',
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#2c6b9e',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return 'Rp ' + ctx.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                if (v >= 1000000) return 'Rp ' + (v/1000000) + 'jt';
                                if (v >= 1000)    return 'Rp ' + (v/1000)    + 'rb';
                                return 'Rp ' + v;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Status Tagihan
    const ctxStatus = document.getElementById('chartStatusTagihan');
    if (ctxStatus) {
        const rawStatus = <?= json_encode($chartStatusTagihan) ?>;
        const labelMap = {
            'lunas':               'Lunas',
            'menunggu_verifikasi': 'Menunggu',
            'belum_lunas':         'Belum Lunas',
            'ditolak':             'Ditolak'
        };
        const colorMap = {
            'lunas':               '#16a34a',
            'menunggu_verifikasi': '#f59e0b',
            'belum_lunas':         '#dc2626',
            'ditolak':             '#6b7280'
        };

        new Chart(ctxStatus.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: rawStatus.map(r => labelMap[r.status] || r.status),
                datasets: [{
                    data: rawStatus.map(r => parseInt(r.total)),
                    backgroundColor: rawStatus.map(r => colorMap[r.status] || '#999'),
                    borderColor: '#fff',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, font: { size: 11 } }
                    }
                }
            }
        });
    }

    // 3. Santri per Kelas
    const ctxKelas = document.getElementById('chartSantriKelas');
    if (ctxKelas) {
        const dataKelas = <?= json_encode($chartSantriPerKelas) ?>;
        new Chart(ctxKelas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: dataKelas.map(r => r.nama_kelas),
                datasets: [{
                    label: 'Jumlah Santri',
                    data: dataKelas.map(r => parseInt(r.total)),
                    backgroundColor: '#2c6b9e',
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }

});
</script>
<?php endif; ?>