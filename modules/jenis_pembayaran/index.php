\<?php
// modules/jenis_pembayaran/index.php
require_once __DIR__ . '/../../config/functions.php';

$current_module = 'jenis_pembayaran';

$search  = trim($_GET['q'] ?? '');
$periode = $_GET['periode'] ?? '';
$status  = $_GET['status'] ?? 'aktif';

$sql = "SELECT 
            jp.*,
            (SELECT COUNT(*) FROM tagihan t WHERE t.jenis_pembayaran_id = jp.id) AS jumlah_tagihan,
            (SELECT COALESCE(SUM(t.nominal),0) FROM tagihan t 
             WHERE t.jenis_pembayaran_id = jp.id AND t.status='lunas') AS total_lunas,
            (SELECT COALESCE(SUM(t.nominal),0) FROM tagihan t 
             WHERE t.jenis_pembayaran_id = jp.id AND t.status='belum_lunas') AS total_belum
        FROM jenis_pembayaran jp
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (jp.nama LIKE ? OR jp.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($periode && in_array($periode, ['bulanan','tahunan','sekali'])) {
    $sql .= " AND jp.periode = ?";
    $params[] = $periode;
}
if ($status === 'aktif') {
    $sql .= " AND jp.is_active = 1";
} elseif ($status === 'nonaktif') {
    $sql .= " AND jp.is_active = 0";
}
$sql .= " ORDER BY jp.id ASC";

$list = fetchAll($sql, $params);

$stat = [
    'total'   => (int) fetchOne("SELECT COUNT(*) c FROM jenis_pembayaran")['c'],
    'aktif'   => (int) fetchOne("SELECT COUNT(*) c FROM jenis_pembayaran WHERE is_active=1")['c'],
    'bulanan' => (int) fetchOne("SELECT COUNT(*) c FROM jenis_pembayaran WHERE periode='bulanan'")['c'],
];
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-money-bill-wave text-primary"></i> Jenis Pembayaran
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Kelola jenis pembayaran TPQ (SPP, Pendaftaran, Seragam, dll)
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
        <a href="<?= BASE_URL ?>/jenis_pembayaran/create"
           class="btn btn-primary btn-sm mt-2 mt-md-0">
            <i class="fas fa-plus"></i> Tambah Jenis
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
        <div class="col-md-4 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Jenis
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['total'] ?></div>
                        </div>
                        <i class="fas fa-list fa-2x text-gray-300"></i>
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
                                Aktif
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['aktif'] ?></div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
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
                                Bulanan
                            </div>
                            <div class="h5 mb-0 font-weight-bold"><?= $stat['bulanan'] ?></div>
                        </div>
                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:240px;" placeholder="Cari nama jenis..."
                       value="<?= e($search) ?>">

                <select name="periode" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="">Semua Periode</option>
                    <option value="bulanan" <?= $periode==='bulanan'?'selected':'' ?>>Bulanan</option>
                    <option value="tahunan" <?= $periode==='tahunan'?'selected':'' ?>>Tahunan</option>
                    <option value="sekali"  <?= $periode==='sekali' ?'selected':'' ?>>Sekali Bayar</option>
                </select>

                <select name="status" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="aktif"    <?= $status==='aktif'   ?'selected':'' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status==='nonaktif'?'selected':'' ?>>Nonaktif</option>
                    <option value="semua"    <?= $status==='semua'   ?'selected':'' ?>>Semua</option>
                </select>

                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>

                <?php if ($search || $periode || $status !== 'aktif'): ?>
                    <a href="<?= BASE_URL ?>/jenis_pembayaran" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>
                        <?php if ($search || $periode || $status !== 'aktif'): ?>
                            Tidak ada jenis pembayaran yang cocok dengan filter.
                        <?php else: ?>
                            Belum ada data jenis pembayaran.
                        <?php endif; ?>
                    </p>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/jenis_pembayaran/create" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Tambah Jenis Pertama
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Nama</th>
                                <th style="width:130px;">Nominal Default</th>
                                <th style="width:110px;">Periode</th>
                                <th style="width:90px;" class="text-center">Dipakai</th>
                                <th style="width:130px;" class="text-right">Total Lunas</th>
                                <th style="width:130px;" class="text-right">Total Belum</th>
                                <th style="width:100px;" class="text-center">Otomatis</th>
                                <th style="width:80px;" class="text-center">Status</th>
                                <?php if (hasRole('admin')): ?>
                                <th style="width:120px;" class="text-center">Aksi</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $no = 1; foreach ($list as $row):
                            $pc = [
                                'bulanan' => 'bg-info text-dark',
                                'tahunan' => 'bg-warning text-dark',
                                'sekali'  => 'bg-secondary',
                            ];
                        ?>
                            <tr>
                                <td class="text-muted"><?= $no++ ?></td>
                                <td>
                                    <strong style="font-size:14px;"><?= e($row['nama']) ?></strong>
                                    <?php if ($row['deskripsi']): ?>
                                        <div class="small text-muted" style="font-size:11px;">
                                            <?= e($row['deskripsi']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="text-primary">
                                        <?= rupiah($row['nominal_default']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?= $pc[$row['periode']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($row['periode']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="<?= BASE_URL ?>/tagihan?jenis=<?= (int) $row['id'] ?>"
                                       class="text-decoration-none">
                                        <span class="badge bg-light text-dark">
                                            <i class="fas fa-file-invoice" style="font-size:10px;"></i>
                                            <?= number_format($row['jumlah_tagihan']) ?>
                                        </span>
                                    </a>
                                </td>
                                <td class="text-right">
                                    <span class="text-success" style="font-size:12px;">
                                        <?= rupiah($row['total_lunas']) ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <span class="text-danger" style="font-size:12px;">
                                        <?= rupiah($row['total_belum']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['auto_generate_saat_daftar']): ?>
                                        <span class="badge bg-success" title="Auto-generate saat santri baru daftar">
                                            <i class="fas fa-magic"></i> Auto
                                        </span>
                                        <?php if ($row['bisa_prorata']): ?>
                                            <span class="badge bg-info text-dark" title="Bisa dihitung prorata">
                                                <i class="fas fa-percentage"></i>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (hasRole('admin')): ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:4px;">
                                        <a href="<?= BASE_URL ?>/jenis_pembayaran/edit/<?= (int) $row['id'] ?>"
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>/jenis_pembayaran/delete/<?= (int) $row['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           data-confirm="Hapus jenis '<?= e($row['nama']) ?>'?"
                                           title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($list) ?></strong> jenis pembayaran
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