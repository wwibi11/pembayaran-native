<?php
// modules/orang_tua/detail.php
require_once __DIR__ . '/../../config/functions.php';

$id = (int) ($id ?? 0);
if ($id <= 0) {
    setFlash('error', 'ID orang tua tidak valid.');
    redirect('orang_tua');
}

// ============================================
// AMBIL DATA ORANG TUA
// ============================================
$row = fetchOne("
    SELECT 
        ot.*,
        u.id         AS user_id_akun,
        u.email      AS email_akun,
        u.name       AS nama_akun,
        u.is_active  AS akun_aktif
    FROM orang_tua ot
    LEFT JOIN users u ON u.id = ot.user_id
    WHERE ot.id = ?
    LIMIT 1
", [$id]);

if (!$row) {
    setFlash('error', 'Data orang tua dengan ID ' . $id . ' tidak ditemukan.');
    redirect('orang_tua');
}

// ============================================
// HANDLE POST
// ============================================
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);
    $aksi = $_POST['aksi'] ?? '';

    // -------- Aksi: LINK SANTRI EXISTING --------
    if ($aksi === 'link_existing') {
        $santriIds = $_POST['santri_ids'] ?? [];
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
        $santriIds = array_filter(array_map('intval', (array) $santriIds));

        if (empty($santriIds)) {
            $errors[] = 'Pilih minimal satu santri.';
        } else {
            db()->beginTransaction();
            try {
                $ok = 0;
                foreach ($santriIds as $sid) {
                    // Cek santri ada & belum terhubung ke ortu ini
                    $ada = fetchOne("SELECT id FROM santri WHERE id = ?", [$sid]);
                    $link = fetchOne("SELECT id FROM wali_santri WHERE orang_tua_id = ? AND santri_id = ?", [$id, $sid]);

                    if ($ada && !$link) {
                        insert('wali_santri', [
                            'orang_tua_id' => $id,
                            'santri_id'    => $sid,
                            'is_primary'   => $isPrimary,
                        ]);
                        $ok++;
                    }
                }
                db()->commit();
                setFlash('success', "$ok santri berhasil dihubungkan.");
                redirect("orang_tua/detail/$id");
            } catch (Exception $e) {
                db()->rollBack();
                $errors[] = 'Gagal: ' . $e->getMessage();
            }
        }
    }

    // -------- Aksi: UNLINK SANTRI --------
    if ($aksi === 'unlink') {
        $santriId = (int) ($_POST['santri_id'] ?? 0);
        if ($santriId > 0) {
            execute("DELETE FROM wali_santri WHERE orang_tua_id = ? AND santri_id = ?", [$id, $santriId]);
            setFlash('success', 'Link ke santri berhasil dilepas.');
        }
        redirect("orang_tua/detail/$id");
    }

    // -------- Aksi: TOGGLE PRIMARY --------
    if ($aksi === 'toggle_primary') {
        $santriId = (int) ($_POST['santri_id'] ?? 0);
        if ($santriId > 0) {
            // Toggle status is_primary untuk link ini
            $link = fetchOne("SELECT is_primary FROM wali_santri WHERE orang_tua_id = ? AND santri_id = ?", [$id, $santriId]);
            if ($link) {
                execute("UPDATE wali_santri SET is_primary = ? WHERE orang_tua_id = ? AND santri_id = ?",
                        [$link['is_primary'] ? 0 : 1, $id, $santriId]);
                setFlash('success', 'Status wali utama berhasil diubah.');
            }
        }
        redirect("orang_tua/detail/$id");
    }
}

// ============================================
// DAFTAR ANAK YANG SUDAH TERHUBUNG
// ============================================
$anakList = fetchAll("
    SELECT 
        s.id, s.nis, s.nama, s.status, s.jenis_kelamin, s.kelas_id,
        k.nama_kelas,
        ws.is_primary
    FROM wali_santri ws
    JOIN santri s ON s.id = ws.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE ws.orang_tua_id = ?
    ORDER BY s.nama ASC
", [$id]);

// ============================================
// DAFTAR SANTRI YANG BELUM TERHUBUNG
// (untuk pilihan di modal)
// ============================================
$santriTersedia = fetchAll("
    SELECT s.id, s.nis, s.nama, s.jenis_kelamin, s.status,
           k.nama_kelas
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE s.id NOT IN (
        SELECT santri_id FROM wali_santri WHERE orang_tua_id = ?
    )
    AND s.status IN ('aktif','cuti')
    ORDER BY s.nama ASC
", [$id]);

// ============================================
// TOTAL TUNGGAKAN
// ============================================
$totalTunggakan = 0;
if (!empty($anakList)) {
    $anakIds = array_column($anakList, 'id');
    $ph = implode(',', array_fill(0, count($anakIds), '?'));
    $totalTunggakan = (float) fetchColumn("
        SELECT COALESCE(SUM(nominal),0) FROM tagihan
        WHERE santri_id IN ($ph) AND status = 'belum_lunas'
    ", $anakIds);
}
?>

<div class="container-fluid">

    <!-- ============================================
         HEADER + TOMBOL AKSI
         ============================================ -->
    <div class="d-flex align-items-start mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/orang_tua" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-circle text-primary"></i> Detail Orang Tua
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($row['nama_lengkap']) ?> — <?= ucfirst($row['tipe']) ?>
            </p>
        </div>

        <?php if (hasRole('admin')): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/orang_tua/edit/<?= (int) $row['id'] ?>"
               class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?= BASE_URL ?>/orang_tua/akun/<?= (int) $row['id'] ?>"
               class="btn btn-sm btn-<?= $row['user_id_akun'] ? 'primary' : 'success' ?>">
                <i class="fas fa-user-cog"></i>
                <?= $row['user_id_akun'] ? 'Kelola Akun' : 'Buat Akun' ?>
            </a>
            <a href="<?= BASE_URL ?>/orang_tua/delete/<?= (int) $row['id'] ?>"
               class="btn btn-sm btn-danger"
               data-confirm="Yakin hapus data '<?= e($row['nama_lengkap']) ?>'?">
                <i class="fas fa-trash"></i> Hapus
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
    <?php if ($err = getFlash('error')): ?>
        <div class="alert alert-danger alert-auto-close">
            <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">

        <!-- ============================================
             KIRI: BIODATA
             ============================================ -->
        <div class="col-lg-5 mb-3">

            <div class="card shadow mb-3">
                <div class="card-body text-center">
                    <div class="rounded-circle mx-auto mb-2"
                         style="width:80px;height:80px;
                                background:<?= $row['tipe']==='ayah' ? '#2c6b9e' : ($row['tipe']==='ibu' ? '#ec4899' : '#6b7280') ?>;
                                display:flex;align-items:center;justify-content:center;
                                color:#fff;font-weight:700;font-size:32px;">
                        <?= e(strtoupper(substr($row['nama_lengkap'], 0, 1))) ?>
                    </div>
                    <h5 class="mb-1"><?= e($row['nama_lengkap']) ?></h5>
                    <span class="badge <?= $row['tipe']==='ayah' ? 'bg-primary' : ($row['tipe']==='ibu' ? 'bg-danger' : 'bg-secondary') ?>">
                        <?= ucfirst($row['tipe']) ?>
                    </span>

                    <div class="mt-3">
                        <?php if ($row['user_id_akun']): ?>
                            <span class="badge <?= $row['akun_aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                <i class="fas fa-<?= $row['akun_aktif'] ? 'check-circle' : 'ban' ?>"></i>
                                Akun <?= $row['akun_aktif'] ? 'Aktif' : 'Nonaktif' ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted">
                                <i class="fas fa-user-slash"></i> Belum punya akun
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-id-card"></i> Biodata
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td width="40%" class="text-muted">Nama</td>
                            <td><strong><?= e($row['nama_lengkap']) ?></strong></td></tr>
                        <tr><td class="text-muted">Tipe</td>
                            <td><?= ucfirst($row['tipe']) ?></td></tr>
                        <tr><td class="text-muted">Jenis Kelamin</td>
                            <td><?= $row['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td></tr>
                        <tr><td class="text-muted">NIK</td>
                            <td><?= e($row['nik'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">TTL</td>
                            <td><?= e($row['tempat_lahir'] ?: '-') ?>,
                                <?= $row['tanggal_lahir'] ? tanggalIndo($row['tanggal_lahir']) : '-' ?></td></tr>
                        <tr><td class="text-muted">Pendidikan</td>
                            <td><?= e($row['pendidikan_terakhir'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Pekerjaan</td>
                            <td><?= e($row['pekerjaan'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Penghasilan</td>
                            <td><?= e($row['penghasilan'] ?: '-') ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-address-book"></i> Kontak & Alamat
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td width="40%" class="text-muted">No. HP</td>
                            <td>
                                <?php if ($row['no_hp']): ?>
                                    <a href="tel:<?= e($row['no_hp']) ?>" class="text-decoration-none">
                                        <i class="fas fa-phone text-success"></i> <?= e($row['no_hp']) ?>
                                    </a>
                                <?php else: ?>-<?php endif; ?>
                            </td></tr>
                        <tr><td class="text-muted">Email</td>
                            <td><?= e($row['email'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Alamat</td>
                            <td><?= e($row['alamat'] ?: '-') ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================
             KANAN: ANAK & AKUN
             ============================================ -->
        <div class="col-lg-7 mb-3">

            <!-- Info Akun -->
            <?php if ($row['user_id_akun']): ?>
            <div class="card shadow mb-3 border-primary">
                <div class="card-header py-2 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-user-check"></i> Akun Login Wali
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-2">
                        <tr><td width="30%" class="text-muted">Email</td>
                            <td><strong><?= e($row['email_akun']) ?></strong></td></tr>
                        <tr><td class="text-muted">Nama Akun</td>
                            <td><?= e($row['nama_akun']) ?></td></tr>
                        <tr><td class="text-muted">Status</td>
                            <td>
                                <?php if ($row['akun_aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td></tr>
                    </table>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/orang_tua/akun/<?= (int) $row['id'] ?>"
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-cog"></i> Kelola Akun
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ringkasan -->
            <div class="row mb-3">
                <div class="col-6">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Anak</div>
                            <div class="h5 mb-0 font-weight-bold"><?= count($anakList) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Tunggakan</div>
                            <div class="h6 mb-0 font-weight-bold"><?= rupiah($totalTunggakan) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 DAFTAR ANAK TERHUBUNG
                 ============================================ -->
            <div class="card shadow">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center flex-wrap">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-child"></i> Anak Terhubung (<?= count($anakList) ?>)
                    </h6>
                    <?php if (hasRole('admin')): ?>
                        <button type="button" class="btn btn-sm btn-primary"
                                data-toggle="modal" data-target="#modalTambahAnak">
                            <i class="fas fa-plus"></i> Tambah Anak
                        </button>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0">
                    <?php if (empty($anakList)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle fa-2x mb-2"></i>
                            <p class="mb-2">Belum ada anak yang terhubung</p>
                            <?php if (hasRole('admin')): ?>
                                <button type="button" class="btn btn-sm btn-primary"
                                        data-toggle="modal" data-target="#modalTambahAnak">
                                    <i class="fas fa-plus"></i> Tambah Anak Pertama
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>NIS</th>
                                        <th>Nama</th>
                                        <th>Kelas</th>
                                        <th class="text-center">JK</th>
                                        <th class="text-center">Utama</th>
                                        <th class="text-center">Status</th>
                                        <th style="width:110px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($anakList as $a):
                                    $sc = ['aktif'=>'bg-success','lulus'=>'bg-primary',
                                           'keluar'=>'bg-secondary','cuti'=>'bg-warning text-dark'];
                                ?>
                                    <tr>
                                        <td><small class="text-muted"><?= e($a['nis']) ?></small></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/santri/detail/<?= (int) $a['id'] ?>"
                                               class="text-decoration-none font-weight-bold">
                                                <?= e($a['nama']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?php if ($a['nama_kelas']): ?>
                                                <span class="badge bg-info text-dark"><?= e($a['nama_kelas']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $a['jenis_kelamin']==='L'?'bg-primary':'bg-danger' ?>">
                                                <?= $a['jenis_kelamin'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if (hasRole('admin')): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                                    <input type="hidden" name="aksi" value="toggle_primary">
                                                    <input type="hidden" name="santri_id" value="<?= (int) $a['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-link p-0"
                                                            title="<?= $a['is_primary'] ? 'Batalkan wali utama' : 'Jadikan wali utama' ?>">
                                                        <i class="fas fa-star <?= $a['is_primary'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <?php if ($a['is_primary']): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php else: ?>-<?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $sc[$a['status']] ?? 'bg-secondary' ?>">
                                                <?= ucfirst($a['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center" style="gap:4px;">
                                                <a href="<?= BASE_URL ?>/santri/detail/<?= (int) $a['id'] ?>"
                                                   class="btn btn-sm btn-info" title="Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if (hasRole('admin')): ?>
                                                    <form method="POST" style="display:inline;"
                                                          data-confirm="Lepas link dengan '<?= e($a['nama']) ?>'?">
                                                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                                        <input type="hidden" name="aksi" value="unlink">
                                                        <input type="hidden" name="santri_id" value="<?= (int) $a['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                                title="Lepas Link">
                                                            <i class="fas fa-unlink"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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
    </div>
</div>

<!-- ============================================
     MODAL: TAMBAH ANAK (2 TAB)
     ============================================ -->
<?php if (hasRole('admin')): ?>
<div class="modal fade" id="modalTambahAnak" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-child"></i> Tambah Anak Terhubung
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body">

                <!-- TAB NAVIGATION -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="tab-existing-btn"
                           data-toggle="tab" href="#tab-existing" role="tab">
                            <i class="fas fa-search"></i> Pilih Santri yang Ada
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="tab-baru-btn"
                           data-toggle="tab" href="#tab-baru" role="tab">
                            <i class="fas fa-user-plus"></i> Input Santri Baru
                        </a>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- ============================================
                         TAB 1: PILIH SANTRI EXISTING
                         ============================================ -->
                    <div class="tab-pane fade show active" id="tab-existing" role="tabpanel">
                        <?php if (empty($santriTersedia)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-info-circle fa-2x mb-2"></i>
                                <p class="mb-0">Semua santri aktif sudah terhubung dengan orang tua ini.</p>
                                <p class="small">Gunakan tab <strong>"Input Santri Baru"</strong> untuk menambah santri.</p>
                            </div>
                        <?php else: ?>
                            <form method="POST" id="formLinkExisting">
                                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">
                                <input type="hidden" name="aksi" value="link_existing">

                                <div class="mb-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <input type="text" id="searchSantri"
                                               class="form-control form-control-sm"
                                               placeholder="🔍 Cari nama / NIS..."
                                               style="max-width:240px;">
                                    </div>
                                    <small class="text-muted">
                                        <span id="countTersedia"><?= count($santriTersedia) ?></span> santri tersedia
                                    </small>
                                </div>

                                <div class="border rounded" style="max-height:320px; overflow-y:auto;">
                                    <table class="table table-sm table-hover mb-0" id="tabelSantri">
                                        <thead class="bg-light" style="position:sticky;top:0;z-index:1;">
                                            <tr>
                                                <th style="width:40px;">
                                                    <input type="checkbox" id="checkAllSantri">
                                                </th>
                                                <th>NIS</th>
                                                <th>Nama</th>
                                                <th>Kelas</th>
                                                <th class="text-center">JK</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($santriTersedia as $s): ?>
                                            <tr data-nama="<?= e(strtolower($s['nama'])) ?>"
                                                data-nis="<?= e(strtolower($s['nis'])) ?>">
                                                <td>
                                                    <input type="checkbox" name="santri_ids[]"
                                                           value="<?= (int) $s['id'] ?>">
                                                </td>
                                                <td><small class="text-muted"><?= e($s['nis']) ?></small></td>
                                                <td><strong><?= e($s['nama']) ?></strong></td>
                                                <td>
                                                    <?php if ($s['nama_kelas']): ?>
                                                        <span class="badge bg-info text-dark"><?= e($s['nama_kelas']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge <?= $s['jenis_kelamin']==='L'?'bg-primary':'bg-danger' ?>">
                                                        <?= $s['jenis_kelamin'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="form-check mt-3">
                                    <input type="checkbox" class="form-check-input" id="isPrimaryCheck"
                                           name="is_primary" value="1">
                                    <label class="form-check-label small" for="isPrimaryCheck">
                                        Jadikan <strong>wali utama</strong> (kontak darurat)
                                    </label>
                                </div>

                                <hr>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted align-self-center">
                                        <span id="countSelected">0</span> santri dipilih
                                    </small>
                                    <div>
                                        <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-link"></i> Hubungkan
                                        </button>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- ============================================
                         TAB 2: INPUT SANTRI BARU
                         ============================================ -->
                    <div class="tab-pane fade" id="tab-baru" role="tabpanel">
                        <div class="alert alert-info py-2 mb-3">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                Anda akan diarahkan ke form <strong>Tambah Santri</strong>.
                                Data orang tua <strong><?= e($row['nama_lengkap']) ?></strong>
                                akan otomatis terisi di form tersebut.
                            </small>
                        </div>

                        <p>Klik tombol di bawah untuk membuka form tambah santri baru. Setelah santri disimpan,
                           akan otomatis terhubung dengan <strong><?= e($row['nama_lengkap']) ?></strong>
                           sebagai <strong><?= ucfirst($row['tipe']) ?></strong>.</p>

                        <hr>
                        <div class="text-right">
                            <button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Batal</button>
                            <a href="<?= BASE_URL ?>/santri/create?ortu_id=<?= (int) $row['id'] ?>&tipe=<?= e($row['tipe']) ?>"
                               class="btn btn-success btn-sm">
                                <i class="fas fa-user-plus"></i> Buka Form Tambah Santri
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ============================================
     SCRIPT
     ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // SEARCH FILTER di tab "Pilih Santri"
    // ============================================
    const searchInput = document.getElementById('searchSantri');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            let count = 0;

            document.querySelectorAll('#tabelSantri tbody tr').forEach(tr => {
                const nama = tr.dataset.nama || '';
                const nis  = tr.dataset.nis  || '';
                const match = nama.includes(q) || nis.includes(q);
                tr.style.display = match ? '' : 'none';
                if (match) count++;
            });

            document.getElementById('countTersedia').textContent = count;
        });
    }

    // ============================================
    // CHECK ALL
    // ============================================
    const checkAll = document.getElementById('checkAllSantri');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const visible = [];
            document.querySelectorAll('#tabelSantri tbody tr').forEach(tr => {
                if (tr.style.display !== 'none') {
                    const cb = tr.querySelector('input[type=checkbox]');
                    if (cb) {
                        cb.checked = this.checked;
                        visible.push(cb);
                    }
                }
            });
            updateCountSelected();
        });
    }

    // ============================================
    // UPDATE JUMLAH TERPILIH
    // ============================================
    function updateCountSelected() {
        const n = document.querySelectorAll('#tabelSantri input[type=checkbox]:checked').length;
        const el = document.getElementById('countSelected');
        if (el) el.textContent = n;
    }

    document.addEventListener('change', function(e) {
        if (e.target.matches('#tabelSantri input[type=checkbox]')) {
            updateCountSelected();
        }
    });

    // ============================================
    // AUTO-OPEN TAB "INPUT BARU" kalau dari URL
    // ============================================
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'baru') {
        const btn = document.getElementById('tab-baru-btn');
        if (btn) btn.click();
    }

});
</script>

<style>
.table.align-middle td { vertical-align: middle !important; }
.table-hover tbody tr:hover { background: #f8fafc; }
.modal .nav-tabs .nav-link { color: #4a5568; }
.modal .nav-tabs .nav-link.active {
    color: #2c6b9e;
    font-weight: 700;
    border-bottom: 2px solid #2c6b9e;
}
</style>