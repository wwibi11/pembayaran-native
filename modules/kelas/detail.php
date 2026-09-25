<?php
// modules/kelas/detail.php
require_once __DIR__ . '/../../config/functions.php';

$id = (int) ($id ?? 0);
$kelas = fetchOne("SELECT * FROM kelas WHERE id = ?", [$id]);

if (!$kelas) {
    setFlash('error', 'Kelas tidak ditemukan.');
    redirect('kelas');
}

// Filter santri
$search = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'aktif';   // aktif | lulus | keluar | semua

// Query santri di kelas ini
$sql = "SELECT s.id, s.nis, s.nama, s.jenis_kelamin, s.tanggal_lahir, s.status,
               s.no_hp, s.foto,
               rk.tanggal_mulai AS masuk_kelas,
               rk.nilai_capaian
        FROM santri s
        LEFT JOIN riwayat_kelas rk ON rk.santri_id = s.id AND rk.tanggal_selesai IS NULL
        WHERE s.kelas_id = ?";
$params = [$id];

if ($status !== 'semua') {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}

if ($search) {
    $sql .= " AND (s.nama LIKE ? OR s.nis LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY s.nama ASC";

$santriList = fetchAll($sql, $params);

// Statistik kelas
$totalAktif  = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE kelas_id = ? AND status='aktif'", [$id])['c'];
$totalLulus  = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE kelas_id = ? AND status='lulus'", [$id])['c'];
$totalKeluar = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE kelas_id = ? AND status='keluar'", [$id])['c'];
$totalAll    = $totalAktif + $totalLulus + $totalKeluar;

// Jenis kelamin
$jmlL = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE kelas_id = ? AND jenis_kelamin='L' AND status='aktif'", [$id])['c'];
$jmlP = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE kelas_id = ? AND jenis_kelamin='P' AND status='aktif'", [$id])['c'];

// Rata-rata lama di kelas (untuk santri aktif)
$rataLama = fetchOne("
    SELECT AVG(DATEDIFF(CURDATE(), rk.tanggal_mulai)) AS rata
    FROM riwayat_kelas rk
    JOIN santri s ON s.id = rk.santri_id
    WHERE rk.kelas_id = ? AND rk.tanggal_selesai IS NULL AND s.status='aktif'
", [$id])['rata'] ?? 0;
?>

<div class="container-fluid">

    <!-- ============================================
         HEADER
         ============================================ -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/kelas" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-layer-group text-primary"></i>
                <?= e($kelas['nama_kelas']) ?>
                <?php if ($kelas['tingkat']): ?>
                    <span class="badge bg-info text-dark" style="font-size:12px;">
                        <?= e($kelas['tingkat']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!$kelas['is_active']): ?>
                    <span class="badge bg-secondary" style="font-size:12px;">Nonaktif</span>
                <?php endif; ?>
            </h1>
            <p class="text-muted mb-0" style="font-size: 13px;">
                <?= e($kelas['deskripsi'] ?: 'Level urutan ke-' . $kelas['urutan']) ?>
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
            <div class="mt-2 mt-md-0">
                <a href="<?= BASE_URL ?>/kelas/edit/<?= $kelas['id'] ?>"
                class="btn btn-sm btn-warning">
                    <i class="fas fa-edit"></i> Edit Kelas
                </a>
                <a href="<?= BASE_URL ?>/kelas/anggota/<?= $kelas['id'] ?>"
                class="btn btn-sm btn-success">
                    <i class="fas fa-user-plus"></i> Kelola Anggota
                </a>
                <a href="<?= BASE_URL ?>/kenaikan?kelas=<?= $kelas['id'] ?>"
                class="btn btn-sm btn-primary">
                    <i class="fas fa-arrow-up"></i> Naik Kelas Massal
                </a>
            </div>
            <?php endif; ?>
    </div>

    <!-- ============================================
         STAT CARDS
         ============================================ -->
    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Santri Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalAktif) ?>
                            </div>
                        </div>
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Laki / Perempuan
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= $jmlL ?> <span class="text-muted">/</span> <?= $jmlP ?>
                            </div>
                        </div>
                        <i class="fas fa-venus-mars fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Rata-rata Lama
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= $rataLama > 0 ? round($rataLama) . ' hari' : '-' ?>
                            </div>
                        </div>
                        <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Total Historis
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?= number_format($totalAll) ?>
                            </div>
                            <div class="small text-muted" style="font-size:10px;">
                                <?= $totalLulus ?> lulus · <?= $totalKeluar ?> keluar
                            </div>
                        </div>
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================
         FILTER + LIST SANTRI
         ============================================ -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <span class="mr-2 font-weight-bold text-primary" style="font-size:14px;">
                    <i class="fas fa-users"></i> Daftar Santri
                </span>

                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:220px;" placeholder="Cari nama / NIS..."
                       value="<?= e($search) ?>">

                <select name="status" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="aktif"  <?= $status==='aktif' ?'selected':'' ?>>Aktif</option>
                    <option value="lulus"  <?= $status==='lulus' ?'selected':'' ?>>Lulus</option>
                    <option value="keluar" <?= $status==='keluar'?'selected':'' ?>>Keluar</option>
                    <option value="semua"  <?= $status==='semua' ?'selected':'' ?>>Semua</option>
                </select>

                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>

                <?php if ($search || $status !== 'aktif'): ?>
                    <a href="<?= BASE_URL ?>/kelas/detail/<?= $kelas['id'] ?>"
                       class="btn btn-sm btn-secondary">
                        Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($santriList)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                    <p class="mb-0">
                        <?php if ($search || $status !== 'aktif'): ?>
                            Tidak ada santri yang cocok dengan filter.
                        <?php else: ?>
                            Belum ada santri di kelas ini.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:70px;">Foto</th>
                                <th>NIS</th>
                                <th>Nama Santri</th>
                                <th style="width:80px;" class="text-center">JK</th>
                                <th>Umur</th>
                                <th>Lama di Kelas</th>
                                <th class="text-center">Nilai</th>
                                <th class="text-center">Status</th>
                                <th style="width:80px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; foreach ($santriList as $s):
                            // Hitung umur
                            $umur = '';
                            if ($s['tanggal_lahir']) {
                                $tgl = new DateTime($s['tanggal_lahir']);
                                $now = new DateTime();
                                $umur = $now->diff($tgl)->y . ' th';
                            }

                            // Lama di kelas
                            $lama = '';
                            if ($s['masuk_kelas']) {
                                $hari = (int) ((time() - strtotime($s['masuk_kelas'])) / 86400);
                                if ($hari < 30) {
                                    $lama = $hari . ' hari';
                                } elseif ($hari < 365) {
                                    $lama = round($hari / 30) . ' bulan';
                                } else {
                                    $lama = round($hari / 365, 1) . ' tahun';
                                }
                            }

                            // Status badge
                            $sc = [
                                'aktif'  => 'bg-success',
                                'lulus'  => 'bg-primary',
                                'keluar' => 'bg-secondary',
                                'cuti'   => 'bg-warning text-dark',
                            ];
                        ?>
                            <tr>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td>
                                    <?php $fotoPath = $s['foto'] ? BASE_URL . '/' . $s['foto'] : null; ?>
                                    <?php if ($fotoPath && file_exists(__DIR__ . '/../../' . $s['foto'])): ?>
                                        <img src="<?= e($fotoPath) ?>" alt="Foto"
                                             style="width:40px; height:40px; object-fit:cover; border-radius:50%;">
                                    <?php else: ?>
                                        <div style="width:40px; height:40px; border-radius:50%;
                                                    background:#e8f0fe; color:#2c6b9e;
                                                    display:flex; align-items:center; justify-content:center;
                                                    font-weight:700; font-size:14px;">
                                            <?= e(strtoupper(substr($s['nama'], 0, 1))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-muted" style="font-size:12px;"><?= e($s['nis']) ?></span></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/santri/detail/<?= $s['id'] ?>"
                                       class="text-decoration-none font-weight-bold">
                                        <?= e($s['nama']) ?>
                                    </a>
                                    <?php if ($s['no_hp']): ?>
                                        <div class="small text-muted">
                                            <i class="fas fa-phone" style="font-size:10px;"></i>
                                            <?= e($s['no_hp']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['jenis_kelamin'] === 'L'): ?>
                                        <span class="badge bg-primary">L</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#ec4899;">P</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= $umur ?: '-' ?></small></td>
                                <td>
                                    <?php if ($lama): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-clock" style="font-size:10px;"></i>
                                            <?= $lama ?>
                                        </small>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($s['nilai_capaian']): ?>
                                        <?php
                                        $nc = [
                                            'A' => 'bg-success',
                                            'B' => 'bg-info text-dark',
                                            'C' => 'bg-warning text-dark',
                                            'D' => 'bg-danger',
                                        ];
                                        ?>
                                        <span class="badge <?= $nc[strtoupper($s['nilai_capaian'])] ?? 'bg-secondary' ?>">
                                            <?= e($s['nilai_capaian']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $sc[$s['status']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/santri/detail/<?= $s['id'] ?>"
                                       class="btn btn-sm btn-info" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($santriList) ?></strong> santri
                        <?= $status !== 'semua' ? '(' . $status . ')' : '' ?>
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>