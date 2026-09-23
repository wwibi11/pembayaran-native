<?php
// modules/reports/index.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin/staff
if (!isAdmin() && !isStaff()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// Ambil data untuk laporan
$total_items = fetchColumn("SELECT COUNT(*) FROM items");
$total_categories = fetchColumn("SELECT COUNT(*) FROM categories");
$total_borrowers = fetchColumn("SELECT COUNT(*) FROM borrowers WHERE is_active = 1");
$total_loans = fetchColumn("SELECT COUNT(*) FROM loans");
$total_loans_active = fetchColumn("SELECT COUNT(*) FROM loans WHERE status IN ('dipinjam', 'terlambat')");
$total_loans_returned = fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'dikembalikan'");
$total_loans_overdue = fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'terlambat'");
$total_returns = fetchColumn("SELECT COUNT(*) FROM returns");

// Statistik per kategori
$category_stats = fetchAll(
    "SELECT c.name, COUNT(i.id) as total_items, SUM(i.quantity) as total_stock
     FROM categories c
     LEFT JOIN items i ON c.id = i.category_id
     GROUP BY c.id
     ORDER BY total_items DESC"
);

// 10 Barang paling sering dipinjam
$top_items = fetchAll(
    "SELECT i.id, i.code, i.name, COUNT(ld.id) as total_loans
     FROM items i
     LEFT JOIN loan_details ld ON i.id = ld.item_id
     GROUP BY i.id
     ORDER BY total_loans DESC
     LIMIT 10"
);

// Peminjaman per bulan (6 bulan terakhir)
$monthly_loans = fetchAll(
    "SELECT 
        DATE_FORMAT(loan_date, '%b %Y') as bulan,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as returned,
        SUM(CASE WHEN status IN ('dipinjam', 'terlambat') THEN 1 ELSE 0 END) as active
     FROM loans
     WHERE loan_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY YEAR(loan_date), MONTH(loan_date)
     ORDER BY loan_date ASC"
);

// Flash messages
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

<style>
/* ============================================
   STYLE UNTUK HALAMAN REPORTS
   ============================================ */
.card-report {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    height: 100%;
}

.card-report:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.card-report .card-body {
    padding: 20px;
}

.card-report .card-header {
    background: #fff;
    border-bottom: 1px solid #eef2f7;
    padding: 14px 20px;
    border-radius: 12px 12px 0 0 !important;
}

.alert-custom {
    border-radius: 10px;
    border: none;
    padding: 12px 18px;
    font-size: 13px;
    border-left: 4px solid transparent;
}

.alert-custom.alert-success {
    background: #dcfce7 !important;
    color: #166534 !important;
    border-left-color: #22c55e !important;
}

.alert-custom.alert-danger {
    background: #fee2e2 !important;
    color: #991b1b !important;
    border-left-color: #dc2626 !important;
}

/* Stat Card */
.stat-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 18px 20px;
    border: 1px solid #eef2f7;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 16px;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}

.stat-card .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #fff;
    flex-shrink: 0;
}

.stat-card .stat-icon.primary { background: #2563eb; }
.stat-card .stat-icon.success { background: #22c55e; }
.stat-card .stat-icon.warning { background: #f59e0b; }
.stat-card .stat-icon.danger { background: #dc2626; }
.stat-card .stat-icon.info { background: #06b6d4; }
.stat-card .stat-icon.purple { background: #8b5cf6; }

.stat-card .stat-content { flex: 1; }
.stat-card .stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
    margin-bottom: 2px;
}
.stat-card .stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}
.stat-card .stat-sub {
    font-size: 12px;
    color: #94a3b8;
    margin-top: 2px;
}

/* Export Buttons */
.btn-export {
    border-radius: 8px;
    padding: 10px 24px;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.3s ease;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-export-pdf {
    background: #dc2626;
    color: #fff;
}

.btn-export-pdf:hover {
    background: #b91c1c;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(220, 38, 38, 0.3);
}

.btn-export-excel {
    background: #16a34a;
    color: #fff;
}

.btn-export-excel:hover {
    background: #15803d;
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
}

/* Table Reports */
.table-reports {
    font-size: 13px;
    margin-bottom: 0;
}

.table-reports thead th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px;
    border-bottom: 2px solid #eef2f7;
}

.table-reports tbody td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.table-reports tbody tr:hover {
    background: #f8fafc;
}

.badge-code {
    background: #1e293b !important;
    color: #ffffff !important;
    font-size: 11px;
    padding: 3px 10px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-block;
}

.chart-wrapper {
    height: 220px;
    position: relative;
}

@media (max-width: 768px) {
    .stat-card .stat-value {
        font-size: 20px;
    }
    .stat-card .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    .btn-export {
        padding: 8px 16px;
        font-size: 12px;
    }
}
</style>

<div class="container-fluid px-4">
    <!-- ============================================
    HEADER
    ============================================ -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-file-alt text-primary me-2"></i>Laporan & Export
            </h4>
            <p class="text-muted small mt-1">Lihat statistik dan export laporan dalam berbagai format</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="index.php?url=reports/export_pdf" class="btn-export btn-export-pdf btn-sm" target="_blank">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
            <a href="index.php?url=reports/export_excel" class="btn-export btn-export-excel btn-sm" target="_blank">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>

    <!-- ============================================
    ALERT
    ============================================ -->
    <?php if ($success): ?>
    <div class="alert alert-custom alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ============================================
    STATISTIK UTAMA
    ============================================ -->
    <div class="row g-3 mb-4">
        <div class="col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-boxes"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Barang</div>
                    <div class="stat-value"><?= number_format($total_items) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-tags"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Kategori</div>
                    <div class="stat-value"><?= number_format($total_categories) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon info"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Peminjam</div>
                    <div class="stat-value"><?= number_format($total_borrowers) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-hand-holding"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Total Peminjaman</div>
                    <div class="stat-value"><?= number_format($total_loans) ?></div>
                    <div class="stat-sub"><?= number_format($total_loans_active) ?> aktif</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Selesai</div>
                    <div class="stat-value"><?= number_format($total_loans_returned) ?></div>
                    <div class="stat-sub"><?= number_format($total_returns) ?> pengembalian</div>
                </div>
            </div>
        </div>
        <div class="col-xl-2 col-lg-3 col-md-4 col-6">
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <div class="stat-label">Terlambat</div>
                    <div class="stat-value"><?= number_format($total_loans_overdue) ?></div>
                    <div class="stat-sub">Perlu perhatian</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
    GRAFIK PEMINJAMAN BULANAN
    ============================================ -->
    <div class="card card-report mb-4">
        <div class="card-header">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-chart-line text-primary me-2"></i>Tren Peminjaman 6 Bulan Terakhir
            </h6>
        </div>
        <div class="card-body">
            <div class="chart-wrapper">
                <canvas id="chartLoans"></canvas>
            </div>
        </div>
    </div>

    <!-- ============================================
    DUA KOLOM: KATEGORI & TOP ITEMS
    ============================================ -->
    <div class="row g-4 mb-4">
        <!-- Statistik Kategori -->
        <div class="col-lg-6">
            <div class="card card-report">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-tags text-primary me-2"></i>Statistik per Kategori
                    </h6>
                    <span class="badge bg-secondary"><?= count($category_stats) ?> kategori</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-reports">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th class="text-center">Jumlah Item</th>
                                    <th class="text-center">Total Stok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($category_stats)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Tidak ada data</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($category_stats as $cat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat['name']) ?></td>
                                    <td class="text-center"><?= $cat['total_items'] ?></td>
                                    <td class="text-center"><?= $cat['total_stock'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 10 Barang Paling Sering Dipinjam -->
        <div class="col-lg-6">
            <div class="card card-report">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-trophy text-warning me-2"></i>Top 10 Barang Paling Sering Dipinjam
                    </h6>
                    <span class="badge bg-secondary"><?= count($top_items) ?> barang</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-reports">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Kode</th>
                                    <th>Nama Barang</th>
                                    <th class="text-center">Total Pinjam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_items)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Tidak ada data</td>
                                </tr>
                                <?php else: ?>
                                <?php $rank = 1; ?>
                                <?php foreach ($top_items as $item): ?>
                                <tr>
                                    <td>
                                        <?php if ($rank <= 3): ?>
                                        <span class="badge bg-warning text-dark"><?= $rank ?></span>
                                        <?php else: ?>
                                        <span class="text-muted"><?= $rank ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge-code"><?= htmlspecialchars($item['code']) ?></span></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary"><?= $item['total_loans'] ?></span></td>
                                </tr>
                                <?php $rank++; ?>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
    RINGKASAN PEMINJAMAN
    ============================================ -->
    <div class="card card-report mb-4">
        <div class="card-header">
            <h6 class="mb-0 fw-bold">
                <i class="fas fa-list text-primary me-2"></i>Ringkasan Peminjaman
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-reports">
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th class="text-center">Jumlah</th>
                            <th class="text-center">Persentase</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total_all_loans = max($total_loans, 1);
                        $status_data = [
                            ['dipinjam', 'Dipinjam', $total_loans_active - $total_loans_overdue, 'Sedang dipinjam'],
                            ['terlambat', 'Terlambat', $total_loans_overdue, 'Melewati batas waktu'],
                            ['dikembalikan', 'Dikembalikan', $total_loans_returned, 'Sudah dikembalikan']
                        ];
                        ?>
                        <?php foreach ($status_data as $data): ?>
                        <tr>
                            <td><?= getStatusBadge($data[0]) ?></td>
                            <td class="text-center"><?= $data[2] ?></td>
                            <td class="text-center">
                                <?php if ($total_all_loans > 0): ?>
                                <div class="progress" style="height:6px;">
                                    <div class="progress-bar bg-<?= $data[0] == 'dikembalikan' ? 'success' : ($data[0] == 'terlambat' ? 'danger' : 'warning') ?>" 
                                         style="width: <?= round(($data[2] / $total_all_loans) * 100) ?>%;"></div>
                                </div>
                                <small class="text-muted"><?= round(($data[2] / $total_all_loans) * 100) ?>%</small>
                                <?php else: ?>
                                0%
                                <?php endif; ?>
                            </td>
                            <td><?= $data[3] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ============================================
    TOMBOL EXPORT DI BAWAH
    ============================================ -->
    <div class="card card-report">
        <div class="card-body text-center py-4">
            <h6 class="mb-3 fw-bold text-dark">Export Laporan</h6>
            <p class="text-muted small mb-3">Download laporan dalam format PDF atau Excel</p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="index.php?url=reports/export_pdf" class="btn-export btn-export-pdf" target="_blank">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="index.php?url=reports/export_excel" class="btn-export btn-export-excel" target="_blank">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
CHART.JS
============================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // CHART: TREN PEMINJAMAN
    // ============================================
    const ctx = document.getElementById('chartLoans').getContext('2d');

    const monthlyData = <?= json_encode($monthly_loans) ?>;
    const labels = monthlyData.map(item => item.bulan);
    const totalData = monthlyData.map(item => item.total);
    const returnedData = monthlyData.map(item => item.returned);
    const activeData = monthlyData.map(item => item.active);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Total Peminjaman',
                data: totalData,
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#2563eb',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4
            }, {
                label: 'Selesai',
                data: returnedData,
                borderColor: '#22c55e',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#22c55e',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4
            }, {
                label: 'Aktif',
                data: activeData,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                fill: true,
                tension: 0.3,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        font: { size: 11 },
                        color: '#475569',
                        padding: 16
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { size: 10 },
                        color: '#94a3b8'
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 10 },
                        color: '#94a3b8'
                    }
                }
            }
        }
    });
});
</script>