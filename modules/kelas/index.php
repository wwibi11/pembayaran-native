<?php
// modules/kelas/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'kelas';
$search = trim($_GET['q'] ?? '');
$tingkat = $_GET['tingkat'] ?? '';
$status = $_GET['status'] ?? 'aktif';   // aktif | nonaktif | semua

// ============================================
// Query dengan filter
// ============================================
$sql = "SELECT k.*,
        (SELECT COUNT(*) FROM santri s WHERE s.kelas_id = k.id AND s.status='aktif') AS jumlah_aktif,
        (SELECT COUNT(*) FROM santri s WHERE s.kelas_id = k.id) AS jumlah_total,
        (SELECT COUNT(*) FROM santri s WHERE s.kelas_id = k.id AND s.jenis_kelamin='L' AND s.status='aktif') AS jml_l,
        (SELECT COUNT(*) FROM santri s WHERE s.kelas_id = k.id AND s.jenis_kelamin='P' AND s.status='aktif') AS jml_p
        FROM kelas k
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (k.nama_kelas LIKE ? OR k.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($tingkat && in_array($tingkat, ['Dasar','Menengah','Lanjutan'])) {
    $sql .= " AND k.tingkat = ?";
    $params[] = $tingkat;
}

if ($status === 'aktif') {
    $sql .= " AND k.is_active = 1";
} elseif ($status === 'nonaktif') {
    $sql .= " AND k.is_active = 0";
}
// 'semua' → tidak ada filter

$sql .= " ORDER BY k.urutan ASC";

$list = fetchAll($sql, $params);

// ============================================
// Statistik ringkasan
// ============================================
$totalKelas      = (int) fetchOne("SELECT COUNT(*) c FROM kelas")['c'];
$totalKelasAktif = (int) fetchOne("SELECT COUNT(*) c FROM kelas WHERE is_active=1")['c'];
$totalSantri     = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE status='aktif'")['c'];
?>

<div class="container-fluid">

    <!-- ============================================
         HEADER
         ============================================ -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-layer-group text-primary"></i> Kelas / Level
            </h1>
            <p class="text-muted mb-0" style="font-size: 13px;">
                Kelola level/kompetensi TPQ (Iqro, Jilid, Al-Qur'an, dll)
            </p>
        </div>
        <div class="mt-2 mt-md-0">
            <?php if (hasRole('admin')): ?>
                <a href="<?= BASE_URL ?>/kelas/create" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Tambah Kelas
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================
         FLASH MESSAGE
         ============================================ -->
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

    <!-- ============================================
         QUICK STATS
         ============================================ -->
    <div class="row mb-3">
        <div class="col-md-4 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Kelas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalKelas) ?>
                            </div>
                            <div class="small text-muted" style="font-size:10px;">
                                <?= $totalKelasAktif ?> aktif
                            </div>
                        </div>
                        <i class="fas fa-layer-group fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Santri Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalSantri) ?>
                            </div>
                            <div class="small text-muted" style="font-size:10px;">
                                tersebar di <?= $totalKelasAktif ?> kelas
                            </div>
                        </div>
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-12 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Rata-rata per Kelas
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= $totalKelasAktif > 0 ? round($totalSantri / $totalKelasAktif, 1) : 0 ?>
                                <small class="text-muted">santri</small>
                            </div>
                            <div class="small text-muted" style="font-size:10px;">
                                di setiap kelas aktif
                            </div>
                        </div>
                        <i class="fas fa-chart-bar fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         LIST KELAS
         ============================================ -->
    <div class="card shadow mb-4">

        <!-- Filter bar -->
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <span class="mr-2 font-weight-bold text-primary" style="font-size:14px;">
                    <i class="fas fa-list"></i> Daftar Kelas
                </span>

                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:220px;" placeholder="Cari nama kelas..."
                       value="<?= e($search) ?>">

                <select name="tingkat" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="">Semua Tingkat</option>
                    <option value="Dasar"     <?= $tingkat==='Dasar'    ?'selected':'' ?>>Dasar</option>
                    <option value="Menengah"  <?= $tingkat==='Menengah' ?'selected':'' ?>>Menengah</option>
                    <option value="Lanjutan"  <?= $tingkat==='Lanjutan' ?'selected':'' ?>>Lanjutan</option>
                </select>

                <select name="status" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="aktif"    <?= $status==='aktif'    ?'selected':'' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status==='nonaktif' ?'selected':'' ?>>Nonaktif</option>
                    <option value="semua"    <?= $status==='semua'    ?'selected':'' ?>>Semua</option>
                </select>

                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>

                <?php if ($search || $tingkat || $status !== 'aktif'): ?>
                    <a href="<?= BASE_URL ?>/kelas" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabel -->
        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p class="mb-2">
                        <?php if ($search || $tingkat || $status !== 'aktif'): ?>
                            Tidak ada kelas yang cocok dengan filter.
                        <?php else: ?>
                            Belum ada data kelas.
                        <?php endif; ?>
                    </p>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/kelas/create" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Tambah Kelas Pertama
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:60px;" class="text-center">Urut</th>
                                <th>Nama Kelas</th>
                                <th style="width:110px;">Tingkat</th>
                                <th style="width:140px;" class="text-center">Santri Aktif</th>
                                <th>Deskripsi</th>
                                <th style="width:90px;" class="text-center">Status</th>
                                <th style="width:180px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $row):
                            $tc = [
                                'Dasar'    => 'bg-info text-dark',
                                'Menengah' => 'bg-warning text-dark',
                                'Lanjutan' => 'bg-success',
                            ];
                        ?>
                            <tr>
                                <!-- Urutan -->
                                <td class="text-center">
                                    <span class="badge bg-secondary" style="font-size:13px; padding:5px 10px;">
                                        <?= $row['urutan'] ?>
                                    </span>
                                </td>

                                <!-- Nama Kelas (link ke detail) -->
                                <td>
                                    <a href="<?= BASE_URL ?>/kelas/detail/<?= $row['id'] ?>"
                                       class="text-decoration-none font-weight-bold"
                                       style="color:#1a2634; font-size:15px;">
                                        <?= e($row['nama_kelas']) ?>
                                    </a>
                                    <div class="small text-muted" style="font-size:11px;">
                                        ID: #<?= $row['id'] ?>
                                    </div>
                                </td>

                                <!-- Tingkat -->
                                <td>
                                    <?php if ($row['tingkat']): ?>
                                        <span class="badge <?= $tc[$row['tingkat']] ?? 'bg-secondary' ?>">
                                            <?= e($row['tingkat']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Jumlah Santri -->
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/kelas/detail/<?= $row['id'] ?>"
                                       class="text-decoration-none d-inline-block">
                                        <span class="badge bg-primary" style="font-size:12px; padding:5px 10px;">
                                            <i class="fas fa-user-graduate" style="font-size:10px;"></i>
                                            <?= $row['jumlah_aktif'] ?>
                                        </span>
                                    </a>
                                    <?php if ($row['jumlah_aktif'] > 0): ?>
                                        <div class="small text-muted mt-1" style="font-size:10px;">
                                            <span title="Laki-laki">👦 <?= $row['jml_l'] ?></span>
                                            ·
                                            <span title="Perempuan">👧 <?= $row['jml_p'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <!-- Deskripsi -->
                                <td>
                                    <small class="text-muted" style="font-size:12px;">
                                        <?= e($row['deskripsi'] ?: '-') ?>
                                    </small>
                                </td>

                                <!-- Status -->
                                <td class="text-center">
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-ban"></i> Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:4px;">
                                        <!-- Lihat Santri -->
                                        <a href="<?= BASE_URL ?>/kelas/detail/<?= $row['id'] ?>"
                                           class="btn btn-sm btn-info"
                                           title="Lihat Santri di Kelas Ini">
                                            <i class="fas fa-users"></i>
                                        </a>

                                        <?php if (hasRole('admin')): ?>
                                            <!-- Edit -->
                                            <a href="<?= BASE_URL ?>/kelas/edit/<?= $row['id'] ?>"
                                               class="btn btn-sm btn-warning"
                                               title="Edit Kelas">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <!-- Hapus -->
                                            <a href="<?= BASE_URL ?>/kelas/delete/<?= $row['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               data-confirm="Hapus kelas '<?= e($row['nama_kelas']) ?>'? Santri yang terhubung akan di-set tanpa kelas."
                                               title="Hapus Kelas">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Footer -->
                <div class="card-footer bg-light py-2 d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Menampilkan <strong><?= count($list) ?></strong> kelas
                        <?php if ($status !== 'semua'): ?>
                            (<?= $status === 'aktif' ? 'aktif' : 'nonaktif' ?>)
                        <?php endif; ?>
                    </small>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/kelas/create" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Tambah
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<!-- ============================================
     CSS tambahan untuk vertikal align di tabel
     ============================================ -->
<style>
    .table.align-middle td {
        vertical-align: middle !important;
    }
    .table-hover tbody tr:hover {
        background: #f8fafc;
    }
</style>