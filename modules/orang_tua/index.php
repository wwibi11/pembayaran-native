<?php
// modules/orang_tua/index.php
require_once __DIR__ . '/../../config/functions.php';

$search = trim($_GET['q'] ?? '');
$tipe   = $_GET['tipe'] ?? '';

$sql = "SELECT ot.*,
        (SELECT COUNT(*) FROM wali_santri ws WHERE ws.orang_tua_id = ot.id) AS jumlah_anak,
        u.id AS user_id_akun,
        u.email AS email_akun,
        u.is_active AS akun_aktif
        FROM orang_tua ot
        LEFT JOIN users u ON u.id = ot.user_id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (ot.nama_lengkap LIKE ? OR ot.nik LIKE ? OR ot.no_hp LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($tipe && in_array($tipe, ['ayah','ibu','wali'])) {
    $sql .= " AND ot.tipe = ?";
    $params[] = $tipe;
}
$sql .= " ORDER BY ot.nama_lengkap ASC";

$list = fetchAll($sql, $params);

$stat = [
    'total'      => (int) fetchOne("SELECT COUNT(*) c FROM orang_tua")['c'],
    'punya_akun' => (int) fetchOne("SELECT COUNT(*) c FROM orang_tua WHERE user_id IS NOT NULL")['c'],
];
?>

<div class="container-fluid">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-users text-primary"></i> Data Orang Tua / Wali
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Kelola data ayah, ibu, dan wali santri
            </p>
        </div>
        <a href="<?= BASE_URL ?>/orang_tua/create" class="btn btn-primary btn-sm mt-2 mt-md-0">
            <i class="fas fa-plus"></i> Tambah Orang Tua
        </a>
    </div>

    <!-- FLASH -->
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

    <!-- STATISTIK -->
    <div class="row mb-3">
        <div class="col-md-6 col-6 mb-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                        Total Orang Tua
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stat['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-6 mb-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                        Punya Akun Login
                    </div>
                    <div class="h5 mb-0 font-weight-bold"><?= $stat['punya_akun'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- LIST -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <form method="GET" class="d-flex flex-wrap" style="gap:8px;">
                <input type="text" name="q" class="form-control form-control-sm"
                       style="max-width:240px;" placeholder="Cari nama / NIK / HP..."
                       value="<?= e($search) ?>">
                <select name="tipe" class="form-control form-control-sm" style="max-width:150px;">
                    <option value="">Semua Tipe</option>
                    <option value="ayah" <?= $tipe==='ayah'?'selected':'' ?>>Ayah</option>
                    <option value="ibu"  <?= $tipe==='ibu' ?'selected':'' ?>>Ibu</option>
                    <option value="wali" <?= $tipe==='wali'?'selected':'' ?>>Wali</option>
                </select>
                <button class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Cari</button>
                <?php if ($search || $tipe): ?>
                    <a href="<?= BASE_URL ?>/orang_tua" class="btn btn-sm btn-secondary">Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body p-0">
            <?php if (empty($list)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>Belum ada data orang tua</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th>Nama</th>
                                <th style="width:70px;">Tipe</th>
                                <th>NIK</th>
                                <th>No. HP</th>
                                <th style="width:60px;" class="text-center">Anak</th>
                                <th style="width:160px;">Akun</th>
                                <th style="width:200px;" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $row):
                            $tc = ['ayah'=>'bg-primary','ibu'=>'bg-danger','wali'=>'bg-secondary'];
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['nama_lengkap']) ?></strong>
                                    <?php if ($row['pekerjaan']): ?>
                                        <div class="small text-muted"><?= e($row['pekerjaan']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $tc[$row['tipe']] ?? 'bg-secondary' ?>">
                                        <?= ucfirst($row['tipe']) ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?= e($row['nik'] ?: '-') ?></small></td>
                                <td>
                                    <?php if ($row['no_hp']): ?>
                                        <a href="tel:<?= e($row['no_hp']) ?>" class="text-decoration-none">
                                            <i class="fas fa-phone text-success"></i>
                                            <?= e($row['no_hp']) ?>
                                        </a>
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info text-dark"><?= $row['jumlah_anak'] ?></span>
                                </td>
                                <td>
                                    <?php if ($row['user_id_akun']): ?>
                                        <span class="badge <?= $row['akun_aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                            <i class="fas fa-<?= $row['akun_aktif'] ? 'check-circle' : 'ban' ?>"></i>
                                            <?= $row['akun_aktif'] ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                        <div class="small text-muted" style="font-size:10px; word-break:break-all;">
                                            <?= e($row['email_akun']) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted">Belum ada</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ============================================
                                     KOLOM AKSI — EDIT, HAPUS, DETAIL, AKUN
                                     ============================================ -->
                                <td class="text-center">
                                    <div class="d-flex justify-content-center" style="gap:4px;">

                                        <!-- DETAIL -->
                                        <a href="<?= BASE_URL ?>/orang_tua/detail/<?= (int) $row['id'] ?>"
                                           class="btn btn-sm btn-info"
                                           title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <!-- EDIT -->
                                        <a href="<?= BASE_URL ?>/orang_tua/edit/<?= (int) $row['id'] ?>"
                                           class="btn btn-sm btn-warning"
                                           title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <!-- AKUN -->
                                        <a href="<?= BASE_URL ?>/orang_tua/akun/<?= (int) $row['id'] ?>"
                                           class="btn btn-sm btn-<?= $row['user_id_akun'] ? 'primary' : 'success' ?>"
                                           title="<?= $row['user_id_akun'] ? 'Kelola Akun' : 'Buat Akun' ?>">
                                            <i class="fas fa-user-cog"></i>
                                        </a>

                                        <!-- HAPUS -->
                                        <a href="<?= BASE_URL ?>/orang_tua/delete/<?= (int) $row['id'] ?>"
                                           class="btn btn-sm btn-danger"
                                           data-confirm="Yakin hapus data '<?= e($row['nama_lengkap']) ?>'?"
                                           title="Hapus Data">
                                            <i class="fas fa-trash"></i>
                                        </a>

                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-light py-2">
                    <small class="text-muted">
                        Menampilkan <strong><?= count($list) ?></strong> data
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