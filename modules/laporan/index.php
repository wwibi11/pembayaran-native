<?php
// modules/laporan/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'laporan';

// ============================================
// Filter Periode
// ============================================
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');

// Statistik utama
$totalPemasukan = (float) fetchColumn("
    SELECT COALESCE(SUM(nominal_bayar),0) FROM pembayaran
    WHERE status='diverifikasi'
      AND tanggal_bayar BETWEEN ? AND ?
", [$dari, $sampai]);

$totalTransaksi = (int) fetchColumn("
    SELECT COUNT(*) FROM pembayaran
    WHERE status='diverifikasi'
      AND tanggal_bayar BETWEEN ? AND ?
", [$dari, $sampai]);

$totalTagihanBelum = (float) fetchColumn("
    SELECT COALESCE(SUM(nominal),0) FROM tagihan
    WHERE status='belum_lunas'
");

$jumlahSantriAktif = (int) fetchColumn("SELECT COUNT(*) FROM santri WHERE status='aktif'");

// Pemasukan per jenis pembayaran
$perJenis = fetchAll("
    SELECT jp.nama, 
           COUNT(p.id) AS jml_transaksi,
           COALESCE(SUM(p.nominal_bayar),0) AS total
    FROM jenis_pembayaran jp
    LEFT JOIN tagihan t ON t.jenis_pembayaran_id = jp.id
    LEFT JOIN pembayaran p ON p.tagihan_id = t.id 
         AND p.status='diverifikasi'
         AND p.tanggal_bayar BETWEEN ? AND ?
    GROUP BY jp.id, jp.nama
    HAVING total > 0
    ORDER BY total DESC
", [$dari, $sampai]);

// Pemasukan per metode
$perMetode = fetchAll("
    SELECT metode,
           COUNT(*) AS jml,
           COALESCE(SUM(nominal_bayar),0) AS total
    FROM pembayaran
    WHERE status='diverifikasi'
      AND tanggal_bayar BETWEEN ? AND ?
    GROUP BY metode
    ORDER BY total DESC
", [$dari, $sampai]);

// Pemasukan per bulan (6 bulan terakhir)
$perBulan = fetchAll("
    SELECT DATE_FORMAT(tanggal_bayar, '%Y-%m') AS bulan,
           DATE_FORMAT(tanggal_bayar, '%b %Y') AS label,
           COALESCE(SUM(nominal_bayar),0) AS total,
           COUNT(*) AS jml
    FROM pembayaran
    WHERE status='diverifikasi'
      AND tanggal_bayar >= CURDATE() - INTERVAL 6 MONTH
    GROUP BY bulan, label
    ORDER BY bulan
");

// Top 5 santri dengan tunggakan terbesar
$topTunggakan = fetchAll("
    SELECT s.id, s.nis, s.nama, k.nama_kelas,
           COALESCE(SUM(t.nominal),0) AS total_tunggakan,
           COUNT(t.id) AS jml_tagihan
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN tagihan t ON t.santri_id = s.id AND t.status='belum_lunas'
    WHERE s.status='aktif'
    GROUP BY s.id, s.nis, s.nama, k.nama_kelas
    ORDER BY total_tunggakan DESC
    LIMIT 5
");

// Pembayaran terbaru di periode ini
$pembayaranTerbaru = fetchAll("
    SELECT p.*, s.nama AS nama_santri, s.nis, jp.nama AS jenis_nama
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN santri s ON s.id = t.santri_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    WHERE p.status='diverifikasi'
      AND p.tanggal_bayar BETWEEN ? AND ?
    ORDER BY p.tanggal_bayar DESC, p.id DESC
    LIMIT 10
", [$dari, $sampai]);
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-chart-line text-primary"></i> Laporan
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Ringkasan keuangan & aktivitas TPQ Madin
            </p>
        </div>
        <?php if (hasRole('admin') || hasRole('kepala')): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/laporan/pembayaran?dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="btn btn-primary btn-sm">
                <i class="fas fa-receipt"></i> Detail Pembayaran
            </a>
            <a href="<?= BASE_URL ?>/laporan/tunggakan" class="btn btn-danger btn-sm">
                <i class="fas fa-exclamation-triangle"></i> Tunggakan
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filter Periode -->
    <div class="card shadow mb-3">
        <div class="card-body py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <label class="mb-0 font-weight-bold text-muted" style="font-size:13px;">
                    <i class="fas fa-calendar"></i> Periode:
                </label>
                <input type="date" name="dari" class="form-control form-control-sm"
                       style="max-width:150px;" value="<?= e($dari) ?>">
                <span class="text-muted">s/d</span>
                <input type="date" name="sampai" class="form-control form-control-sm"
                       style="max-width:150px;" value="<?= e($sampai) ?>">

                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Tampilkan
                </button>

                <div class="ml-auto d-flex flex-wrap" style="gap:4px;">
                    <a href="?dari=<?= date('Y-m-01') ?>&sampai=<?= date('Y-m-t') ?>"
                       class="btn btn-sm btn-outline-secondary">Bulan Ini</a>
                    <a href="?dari=<?= date('Y-m-01', strtotime('-1 month')) ?>&sampai=<?= date('Y-m-t', strtotime('-1 month')) ?>"
                       class="btn btn-sm btn-outline-secondary">Bulan Lalu</a>
                    <a href="?dari=<?= date('Y-01-01') ?>&sampai=<?= date('Y-12-31') ?>"
                       class="btn btn-sm btn-outline-secondary">Tahun Ini</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistik Utama -->
    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Total Pemasukan
                    </div>
                    <div class="h5 mb-0 font-weight-bold text-success">
                        <?= rupiah($totalPemasukan) ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        <?= number_format($totalTransaksi) ?> transaksi
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
                    <div class="h5 mb-0 font-weight-bold text-danger">
                        <?= rupiah($totalTagihanBelum) ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        dari semua tagihan aktif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Santri Aktif
                    </div>
                    <div class="h5 mb-0 font-weight-bold">
                        <?= number_format($jumlahSantriAktif) ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        santri terdaftar
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                        Rata-rata Transaksi
                    </div>
                    <div class="h5 mb-0 font-weight-bold">
                        <?= $totalTransaksi > 0 ? rupiah($totalPemasukan / $totalTransaksi) : 'Rp 0' ?>
                    </div>
                    <div class="small text-muted" style="font-size:10px;">
                        per transaksi
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Chart Pemasukan -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-chart-line"></i> Pemasukan 6 Bulan Terakhir
                    </h6>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper" style="height:280px; position:relative;">
                        <canvas id="chartBulanan"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Per Metode -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-credit-card"></i> Per Metode
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($perMetode)): ?>
                        <div class="text-center py-4 text-muted">
                            <small>Belum ada data</small>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <tbody>
                            <?php
                            $mc = [
                                'transfer' => ['label' => '🏦 Transfer', 'color' => 'info'],
                                'qris'     => ['label' => '📱 QRIS',     'color' => 'primary'],
                                'cash'     => ['label' => '💵 Cash',     'color' => 'secondary'],
                            ];
                            foreach ($perMetode as $m):
                                $info = $mc[$m['metode']] ?? ['label' => $m['metode'], 'color' => 'secondary'];
                            ?>
                                <tr>
                                    <td><?= $info['label'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $info['color'] ?>">
                                            <?= $m['jml'] ?>x
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong><?= rupiah($m['total']) ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Per Jenis Pembayaran -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-money-bill-wave"></i> Per Jenis Pembayaran
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($perJenis)): ?>
                        <div class="text-center py-4 text-muted">
                            <small>Belum ada pemasukan di periode ini</small>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Jenis</th>
                                    <th class="text-center">Transaksi</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $totalAll = array_sum(array_column($perJenis, 'total'));
                            foreach ($perJenis as $j):
                                $persen = $totalAll > 0 ? round(($j['total'] / $totalAll) * 100, 1) : 0;
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= e($j['nama']) ?></strong>
                                        <div class="progress mt-1" style="height:4px;">
                                            <div class="progress-bar bg-primary" 
                                                 style="width: <?= $persen ?>%"></div>
                                        </div>
                                        <div class="small text-muted" style="font-size:10px;">
                                            <?= $persen ?>%
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark">
                                            <?= $j['jml_transaksi'] ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <strong><?= rupiah($j['total']) ?></strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top 5 Tunggakan -->
        <div class="col-lg-6 mb-3">
            <div class="card shadow">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Top 5 Tunggakan
                    </h6>
                    <a href="<?= BASE_URL ?>/laporan/tunggakan" class="small text-primary">
                        Lihat semua →
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($topTunggakan)): ?>
                        <div class="text-center py-4 text-success">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <p class="mb-0">Tidak ada tunggakan 🎉</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Santri</th>
                                    <th class="text-center">Tagihan</th>
                                    <th class="text-right">Tunggakan</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($topTunggakan as $t): ?>
                                <tr>
                                    <td>
                                        <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $t['id'] ?>"
                                           class="text-decoration-none font-weight-bold">
                                            <?= e($t['nama']) ?>
                                        </a>
                                        <div class="small text-muted">
                                            <?= e($t['nis']) ?>
                                            <?php if ($t['nama_kelas']): ?>
                                                · <?= e($t['nama_kelas']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?= $t['jml_tagihan'] ?></span>
                                    </td>
                                    <td class="text-right">
                                        <strong class="text-danger">
                                            <?= rupiah($t['total_tunggakan']) ?>
                                        </strong>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Pembayaran Terbaru -->
    <div class="card shadow mb-4">
        <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-receipt"></i> Pembayaran Terbaru di Periode Ini
            </h6>
            <a href="<?= BASE_URL ?>/laporan/pembayaran?dari=<?= $dari ?>&sampai=<?= $sampai ?>"
               class="small text-primary">
                Lihat semua →
            </a>
        </div>
        <div class="card-body p-0">
            <?php if (empty($pembayaranTerbaru)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2"></i>
                    <p class="mb-0">Belum ada pembayaran di periode ini</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Santri</th>
                                <th>Jenis</th>
                                <th class="text-center">Metode</th>
                                <th class="text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pembayaranTerbaru as $p):
                            $mc = [
                                'transfer' => 'bg-info text-dark',
                                'qris'     => 'bg-primary',
                                'cash'     => 'bg-secondary',
                            ];
                        ?>
                            <tr>
                                <td><small><?= tanggalIndo($p['tanggal_bayar']) ?></small></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $p['santri_id'] ?? 0 ?>"
                                       class="text-decoration-none">
                                        <?= e($p['nama_santri']) ?>
                                    </a>
                                    <div class="small text-muted"><?= e($p['nis']) ?></div>
                                </td>
                                <td><small><?= e($p['jenis_nama']) ?></small></td>
                                <td class="text-center">
                                    <span class="badge <?= $mc[$p['metode']] ?? 'bg-secondary' ?>">
                                        <?= strtoupper($p['metode']) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <strong><?= rupiah($p['nominal_bayar']) ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('chartBulanan');
    if (!ctx) return;

    new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($perBulan, 'label')) ?>,
            datasets: [{
                label: 'Pemasukan',
                data: <?= json_encode(array_map('floatval', array_column($perBulan, 'total'))) ?>,
                borderColor: '#2c6b9e',
                backgroundColor: 'rgba(44, 107, 158, 0.1)',
                fill: true,
                tension: 0.35,
                pointBackgroundColor: '#2c6b9e',
                pointRadius: 5,
                pointHoverRadius: 7,
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(c) {
                            return 'Rp ' + c.parsed.y.toLocaleString('id-ID');
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(v) {
                            if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
                            if (v >= 1000)    return 'Rp ' + (v/1000) + 'rb';
                            return 'Rp ' + v;
                        }
                    }
                }
            }
        }
    });
});
</script>

<style>
.table-hover tbody tr:hover { background: #f8fafc; }
</style>