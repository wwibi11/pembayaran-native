<?php
// modules/santri/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'santri';

// ============================================
// Filter
// ============================================
$search  = trim($_GET['q'] ?? '');
$kelasId = (int) ($_GET['kelas'] ?? 0);
$status  = $_GET['status'] ?? 'aktif';

// ============================================
// Query
// ============================================
$sql = "SELECT s.*, k.nama_kelas,
        (SELECT COUNT(*) FROM wali_santri ws WHERE ws.santri_id = s.id) AS jumlah_wali
        FROM santri s
        LEFT JOIN kelas k ON k.id = s.kelas_id
        WHERE 1=1";
$params = [];

if ($status !== 'semua') {
    $sql .= " AND s.status = ?";
    $params[] = $status;
}
if ($kelasId > 0) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $kelasId;
}
if ($search) {
    $sql .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY s.nama ASC";

$list = fetchAll($sql, $params);
$kelasList = fetchAll("SELECT id, nama_kelas FROM kelas WHERE is_active=1 ORDER BY urutan");

// Statistik
$stat = [
    'total'    => (int) fetchOne("SELECT COUNT(*) c FROM santri")['c'],
    'aktif'    => (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE status='aktif'")['c'],
    'lulus'    => (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE status='lulus'")['c'],
    'keluar'   => (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE status='keluar'")['c'],
];
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-graduate text-primary"></i> Data Santri
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Kelola data santri TPQ Madin
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
        <a href="<?= BASE_URL ?>/santri/create" class="btn btn-primary btn-sm mt-2 mt-md-0">
            <i class="fas fa-plus"></i> Tambah Santri
        </a>
        <?php endif; ?>
    </div>

    <!-- Flash -->
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

    <!-- Quick Stats -->
    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['total'] ?></div>
                        </div>
                        <i class="fas fa-users fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Aktif</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['aktif'] ?></div>
                        </div>
                        <i class="fas fa-user-check fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Lulus</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['lulus'] ?></div>
                        </div>
                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card border-left-secondary shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Keluar</div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['keluar'] ?></div>
                        </div>
                        <i class="fas fa-user-slash fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter + List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:220px;" placeholder="Cari nama / NIS / NIK..."
                       value="<?= e($search) ?>">

                <select name="kelas" class="form-control form-control-sm" style="max-width:180px;">
                    <option value="">Semua Kelas</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>" <?= $kelasId==$k['id']?'selected':'' ?>>
                            <?= e($k['nama_kelas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="form-control form-control-sm" style="max-width:140px;">
                    <option value="aktif"  <?= $status==='aktif' ?'selected':'' ?>>Aktif</option>
                    <option value="lulus"  <?= $status==='lulus' ?'selected':'' ?>>Lulus</option>
                    <option value="keluar" <?= $status==='keluar'?'selected':'' ?>>Keluar</option>
                    <option value="cuti"   <?= $status==='cuti'  ?'selected':'' ?>>Cuti</option>
                    <option value="semua"  <?= $status==='semua' ?'selected':'' ?>>Semua</option>
                </select>

                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>

                <?php if ($search || $kelasId || $status !== 'aktif'): ?>
                    <a href="<?= BASE_URL ?>/santri" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-user-slash fa-3x mb-3"></i>
                    <p>Belum ada data santri.</p>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/santri/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Tambah Santri Pertama
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th style="width:60px;">Foto</th>
                                <th>NIS</th>
                                <th>Nama Santri</th>
                                <th style="width:70px;" class="text-center">JK</th>
                                <th>Kelas</th>
                                <th>Umur</th>
                                <th class="text-center">Wali</th>
                                <th class="text-center">Status</th>
                                <th style="width:140px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; foreach ($list as $s):
                            $umur = '';
                            if ($s['tanggal_lahir']) {
                                $umur = (new DateTime($s['tanggal_lahir']))->diff(new DateTime())->y . ' th';
                            }
                            $sc = ['aktif'=>'bg-success','lulus'=>'bg-primary','keluar'=>'bg-secondary','cuti'=>'bg-warning text-dark'];
                        ?>
                            <tr>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td>
                                    <?php if ($s['foto'] && file_exists(__DIR__ . '/../../' . $s['foto'])): ?>
                                        <img src="<?= BASE_URL . '/' . e($s['foto']) ?>"
                                             style="width:40px;height:40px;object-fit:cover;border-radius:50%;">
                                    <?php else: ?>
                                        <div style="width:40px;height:40px;border-radius:50%;
                                                    background:#e8f0fe;color:#2c6b9e;
                                                    display:flex;align-items:center;justify-content:center;
                                                    font-weight:700;font-size:14px;">
                                            <?= e(strtoupper(substr($s['nama'], 0, 1))) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= e($s['nis']) ?></small></td>
                                <td>
                                    <a href="<?= BASE_URL ?>/santri/detail/<?= $s['id'] ?>"
                                       class="text-decoration-none font-weight-bold">
                                        <?= e($s['nama']) ?>
                                    </a>
                                    <?php if ($s['nama_panggilan']): ?>
                                        <div class="small text-muted">(<?= e($s['nama_panggilan']) ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $s['jenis_kelamin']==='L'?'bg-primary':'bg-pink' ?>"
                                          style="<?= $s['jenis_kelamin']==='P'?'background:#ec4899;':'' ?>">
                                        <?= $s['jenis_kelamin'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($s['nama_kelas']): ?>
                                        <a href="<?= BASE_URL ?>/kelas/detail/<?= $s['kelas_id'] ?>"
                                           class="text-decoration-none">
                                            <span class="badge bg-info text-dark">
                                                <?= e($s['nama_kelas']) ?>
                                            </span>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Belum ada</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= $umur ?: '-' ?></small></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-users" style="font-size:10px;"></i>
                                        <?= $s['jumlah_wali'] ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $sc[$s['status']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($s['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:4px;">
                                        <a href="<?= BASE_URL ?>/santri/detail/<?= $s['id'] ?>"
                                           class="btn btn-sm btn-info" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasRole('admin')): ?>
                                            <a href="<?= BASE_URL ?>/santri/edit/<?= $s['id'] ?>"
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>/santri/delete/<?= $s['id'] ?>"
                                               class="btn btn-sm btn-danger"
                                               data-confirm="Hapus santri '<?= e($s['nama']) ?>'?"
                                               title="Hapus">
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
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($list) ?></strong> santri
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }
</style>