<?php
// modules/pembayaran/upload.php
require_once __DIR__ . '/../../config/functions.php';

$isAdmin = hasRole('admin');
$isWali  = hasRole('wali');

if (!$isAdmin && !$isWali) { http_response_code(403); exit('Akses ditolak.'); }

$errors = [];

// Tagihan spesifik (dari URL: /pembayaran/upload/5 atau ?tagihan=5)
$tagihanId = (int) ($id ?? $_GET['tagihan'] ?? 0);
$tagihan   = null;

// ============================================
// MODE: FORM UPLOAD
// - Wali: hanya kalau ada tagihanId spesifik
// - Admin: selalu form (bisa tanpa tagihanId, dropdown)
// ============================================
$showForm = false;

if ($tagihanId > 0) {
    $tagihan = fetchOne("
        SELECT t.*, s.nama AS nama_santri, s.nis, s.id AS santri_id,
               k.nama_kelas, jp.nama AS jenis_nama
        FROM tagihan t
        JOIN santri s ON s.id = t.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        WHERE t.id = ?
    ", [$tagihanId]);

    if (!$tagihan) {
        setFlash('error', 'Tagihan tidak ditemukan.');
        redirect('pembayaran/upload');
    }

    // Cek akses wali
    if ($isWali && !isAnakDariWali((int) $tagihan['santri_id'], currentUser()['id'])) {
        http_response_code(403);
        exit('403 - Ini bukan anak Anda.');
    }

    if ($tagihan['status'] === 'lunas') {
        setFlash('error', 'Tagihan ini sudah lunas.');
        redirect('pembayaran/upload');
    }

    $showForm = true;
} elseif ($isAdmin) {
    // Admin tanpa ID → form dengan dropdown
    $showForm = true;
}

// ============================================
// PROSES POST (upload)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $old = [
        'tagihan_id'    => (int) ($_POST['tagihan_id'] ?? 0),
        'nominal_bayar' => (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_bayar'] ?? '0'),
        'tanggal_bayar' => $_POST['tanggal_bayar'] ?? date('Y-m-d'),
        'metode'        => $_POST['metode'] ?? 'transfer',
        'catatan_wali'  => trim($_POST['catatan_wali'] ?? ''),
    ];

    if (!$old['tagihan_id']) $errors[] = 'Pilih tagihan.';
    if ($old['nominal_bayar'] <= 0) $errors[] = 'Nominal harus > 0.';
    if (!$old['tanggal_bayar']) $errors[] = 'Tanggal wajib diisi.';
    if (!in_array($old['metode'], ['transfer','qris','cash'])) $errors[] = 'Metode tidak valid.';

    // Cek tagihan
    $tagihanCek = null;
    if (!$errors) {
        $tagihanCek = fetchOne("SELECT * FROM tagihan WHERE id = ?", [$old['tagihan_id']]);
        if (!$tagihanCek) {
            $errors[] = 'Tagihan tidak ditemukan.';
        } elseif ($tagihanCek['status'] === 'lunas') {
            $errors[] = 'Tagihan sudah lunas.';
        } elseif ($isWali && !isAnakDariWali((int) $tagihanCek['santri_id'], currentUser()['id'])) {
            $errors[] = 'Ini bukan anak Anda.';
        }
    }

    // Upload file wajib (kecuali admin cash)
    $isAdminCash = $isAdmin && $old['metode'] === 'cash';
    $buktiPath = null;

    if (!$errors && !$isAdminCash) {
        if (empty($_FILES['bukti']['name'])) {
            $errors[] = 'Bukti pembayaran wajib diupload.';
        } else {
            $buktiPath = uploadFile($_FILES['bukti'], 'bukti_bayar',
                                    ['jpg','jpeg','png','pdf']);
            if (!$buktiPath) {
                $errors[] = 'File tidak valid. Format: JPG, PNG, PDF (maks 5 MB).';
            }
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $userId = currentUser()['id'];
            $status = $isAdminCash ? 'diverifikasi' : 'menunggu';

            $pembayaranId = insert('pembayaran', [
                'tagihan_id'    => $old['tagihan_id'],
                'uploaded_by'   => $userId,
                'nominal_bayar' => $old['nominal_bayar'],
                'tanggal_bayar' => $old['tanggal_bayar'],
                'metode'        => $old['metode'],
                'bukti_path'    => $buktiPath,
                'catatan_wali'  => $old['catatan_wali'] ?: null,
                'status'        => $status,
                'verified_by'   => $isAdminCash ? $userId : null,
                'verified_at'   => $isAdminCash ? date('Y-m-d H:i:s') : null,
            ]);

            recalculateTagihanStatus($old['tagihan_id']);
            db()->commit();

            if ($isAdminCash) {
                setFlash('success', 'Pembayaran cash berhasil dicatat & diverifikasi.');
            } else {
                setFlash('success', 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.');
            }
            redirect('pembayaran/detail/' . $pembayaranId);

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    // Kembali ke form dengan error
    $showForm = true;
    if ($old['tagihan_id']) {
        $tagihan = fetchOne("
            SELECT t.*, s.nama AS nama_santri, s.nis, s.id AS santri_id,
                   k.nama_kelas, jp.nama AS jenis_nama
            FROM tagihan t
            JOIN santri s ON s.id = t.santri_id
            LEFT JOIN kelas k ON k.id = s.kelas_id
            JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
            WHERE t.id = ?
        ", [$old['tagihan_id']]);
    }
}

// ============================================
// DATA UNTUK FORM DROPDOWN
// ============================================
$tagihanList = [];
$old = $old ?? [
    'tagihan_id'    => $tagihanId,
    'nominal_bayar' => $tagihan ? $tagihan['nominal'] : '',
    'tanggal_bayar' => date('Y-m-d'),
    'metode'        => 'transfer',
    'catatan_wali'  => '',
];

if ($showForm && !$tagihan) {
    // Ambil daftar tagihan untuk dropdown
    if ($isWali) {
        $tagihanList = fetchAll("
            SELECT t.id, t.nominal, t.periode, t.jatuh_tempo, t.status,
                   s.nama AS nama_santri, s.nis, k.nama_kelas,
                   jp.nama AS jenis_nama
            FROM tagihan t
            JOIN santri s ON s.id = t.santri_id
            JOIN wali_santri ws ON ws.santri_id = s.id
            JOIN orang_tua ot ON ot.id = ws.orang_tua_id
            LEFT JOIN kelas k ON k.id = s.kelas_id
            JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
            WHERE ot.user_id = ?
              AND t.status IN ('belum_lunas', 'ditolak')
            ORDER BY s.nama, t.created_at DESC
        ", [currentUser()['id']]);
    } else {
        $tagihanList = fetchAll("
            SELECT t.id, t.nominal, t.periode, t.jatuh_tempo, t.status,
                   s.nama AS nama_santri, s.nis, k.nama_kelas,
                   jp.nama AS jenis_nama
            FROM tagihan t
            JOIN santri s ON s.id = t.santri_id
            LEFT JOIN kelas k ON k.id = s.kelas_id
            JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
            WHERE t.status IN ('belum_lunas', 'ditolak')
            ORDER BY t.created_at DESC
            LIMIT 100
        ");
    }
}

// ============================================
// DATA UNTUK LANDING (wali)
// ============================================
$anakList = [];
$tagihanPerAnak = [];
$statWali = ['anak' => 0, 'tagihan_aktif' => 0, 'total_tunggakan' => 0];

if ($isWali && !$showForm) {
    $userId = currentUser()['id'];

    $anakList = fetchAll("
        SELECT s.id, s.nis, s.nama, s.nama_panggilan, s.jenis_kelamin, 
               s.foto, s.status, k.nama_kelas, ot.tipe AS hubungan_tipe
        FROM orang_tua ot
        JOIN wali_santri ws ON ws.orang_tua_id = ot.id
        JOIN santri s ON s.id = ws.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        WHERE ot.user_id = ?
        ORDER BY s.status = 'aktif' DESC, s.nama ASC
    ", [$userId]);

    // Ambil tagihan aktif per anak
    foreach ($anakList as $anak) {
        $tagihanPerAnak[$anak['id']] = fetchAll("
            SELECT t.id, t.nominal, t.periode, t.jatuh_tempo, t.status,
                   jp.nama AS jenis_nama
            FROM tagihan t
            JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
            WHERE t.santri_id = ?
              AND t.status IN ('belum_lunas','ditolak')
            ORDER BY t.jatuh_tempo ASC
        ", [$anak['id']]);

        $statWali['tagihan_aktif'] += count($tagihanPerAnak[$anak['id']]);
        $statWali['total_tunggakan'] += array_sum(array_column($tagihanPerAnak[$anak['id']], 'nominal'));
    }

    $statWali['anak'] = count($anakList);
}

// ============================================
// DATA DARI SETTINGS
// ============================================
$namaMadin    = setting('nama_madin', 'TPQ MADIN');
$alamatMadin  = setting('alamat', '');
$teleponMadin = setting('telepon', '');
$infoRekening = setting('info_rekening', '');
$infoQris     = setting('info_qris', '');
$infoCash     = setting('info_cash', '');
$deadlineVerif= setting('deadline_verifikasi', '24');
?>

<?php if (!$showForm): ?>
<!-- ============================================ -->
<!-- MODE 1: LANDING UNTUK WALI                   -->
<!-- ============================================ -->
<div class="container-fluid">

    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/dashboard" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-upload text-primary"></i> Upload Bukti Bayar
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Pilih tagihan anak Anda yang ingin dibayar
            </p>
        </div>
    </div>

    <?php if ($err = getFlash('error')): ?>
        <div class="alert alert-danger alert-auto-close">
            <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
        </div>
    <?php endif; ?>
    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($anakList)): ?>
        <!-- Wali tidak punya anak terhubung -->
        <div class="card shadow">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                <h5>Belum ada anak yang terhubung</h5>
                <p class="text-muted mb-3">
                    Akun Anda belum terhubung dengan data santri manapun.
                </p>
                <p class="text-muted mb-0">
                    Hubungi admin TPQ untuk menghubungkan akun Anda dengan data anak.
                </p>
            </div>
        </div>
    <?php else: ?>

        <!-- Statistik -->
        <div class="row mb-3">
            <div class="col-md-4 col-4 mb-2">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Anak Terdaftar
                        </div>
                        <div class="h5 mb-0 font-weight-bold"><?= $statWali['anak'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-4 mb-2">
                <div class="card border-left-danger shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Tagihan Aktif
                        </div>
                        <div class="h5 mb-0 font-weight-bold"><?= $statWali['tagihan_aktif'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-4 mb-2">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Total Tunggakan
                        </div>
                        <div class="h6 mb-0 font-weight-bold text-danger">
                            <?= rupiah($statWali['total_tunggakan']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($statWali['tagihan_aktif'] == 0): ?>
            <!-- Semua lunas -->
            <div class="card shadow mb-3 border-success">
                <div class="card-body text-center py-4">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h5 class="text-success">Semua Tagihan Lunas! 🎉</h5>
                    <p class="text-muted mb-0">
                        Alhamdulillah, tidak ada tagihan aktif untuk anak-anak Anda saat ini.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Kartu per Anak -->
        <h6 class="text-muted mb-2">
            <i class="fas fa-child"></i> Pilih Anak untuk Lihat Tagihan
        </h6>
        <div class="row">
        <?php foreach ($anakList as $anak):
            $umur = '';
            if ($anak['tanggal_lahir'] ?? null) {
                $umur = (new DateTime($anak['tanggal_lahir']))->diff(new DateTime())->y;
            }
            $tagihans = $tagihanPerAnak[$anak['id']] ?? [];
            $jmlAktif = count($tagihans);
            $totalAnakTunggakan = array_sum(array_column($tagihans, 'nominal'));
        ?>
            <div class="col-lg-6 mb-3">
                <div class="card shadow h-100 <?= $jmlAktif > 0 ? 'border-warning' : 'border-success' ?>">
                    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle mr-2 d-flex align-items-center justify-content-center"
                                 style="width:38px;height:38px;
                                        background:<?= $anak['jenis_kelamin']==='L' ? '#2c6b9e' : '#ec4899' ?>;
                                        color:#fff;font-weight:700;font-size:15px;">
                                <?= e(strtoupper(substr($anak['nama'], 0, 1))) ?>
                            </div>
                            <div>
                                <strong><?= e($anak['nama']) ?></strong>
                                <div class="small text-muted">
                                    NIS: <?= e($anak['nis']) ?>
                                    · <?= e($anak['nama_kelas'] ?: 'Belum ada kelas') ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($jmlAktif > 0): ?>
                            <span class="badge bg-danger">
                                <?= $jmlAktif ?> tagihan
                            </span>
                        <?php else: ?>
                            <span class="badge bg-success">
                                <i class="fas fa-check"></i> Lunas
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="card-body p-0">
                        <?php if (empty($tagihans)): ?>
                            <div class="text-center py-4 text-success">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p class="mb-0 small">
                                    Tidak ada tagihan aktif
                                </p>
                            </div>
                        <?php else: ?>
                            <table class="table table-sm table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Jenis</th>
                                        <th>Periode</th>
                                        <th>Jatuh Tempo</th>
                                        <th class="text-right">Nominal</th>
                                        <th style="width:90px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($tagihans as $t):
                                    $terlambat = $t['jatuh_tempo']
                                                 && strtotime($t['jatuh_tempo']) < time()
                                                 && $t['status'] === 'belum_lunas';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($t['jenis_nama']) ?></strong>
                                        </td>
                                        <td><small><?= e($t['periode'] ?: '-') ?></small></td>
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
                                        <td class="text-right">
                                            <a href="<?= BASE_URL ?>/pembayaran/upload/<?= (int) $t['id'] ?>"
                                               class="btn btn-sm btn-success">
                                                <i class="fas fa-upload"></i> Bayar
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>

                            <div class="px-3 py-2 bg-light border-top small text-muted">
                                <div class="d-flex justify-content-between">
                                    <span>Total tagihan aktif:</span>
                                    <strong class="text-danger">
                                        <?= rupiah($totalAnakTunggakan) ?>
                                    </strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Footer: Quick action -->
                    <div class="card-footer bg-white py-2">
                        <div class="d-flex" style="gap:4px;">
                            <a href="<?= BASE_URL ?>/tagihan/santri/<?= (int) $anak['id'] ?>"
                               class="btn btn-sm btn-outline-primary flex-fill">
                                <i class="fas fa-list"></i> Semua Tagihan
                            </a>
                            <a href="<?= BASE_URL ?>/anak/detail/<?= (int) $anak['id'] ?>"
                               class="btn btn-sm btn-outline-info flex-fill">
                                <i class="fas fa-user"></i> Detail Anak
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- Panduan -->
        <div class="card shadow mb-4 border-info">
            <div class="card-header py-2 bg-info text-white">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-info-circle"></i> Panduan Pembayaran
                </h6>
            </div>
            <div class="card-body small">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-2"><strong>1. Pilih tagihan yang mau dibayar</strong></p>
                        <p class="mb-2"><strong>2. Transfer sesuai nominal</strong></p>
                        <ul class="pl-3 mb-3">
                            <?php if ($infoRekening): ?>
                                <li>🏦 <strong>Bank:</strong> <?= e($infoRekening) ?></li>
                            <?php endif; ?>
                            <?php if ($infoQris): ?>
                                <li>📱 <strong>QRIS:</strong> <?= e($infoQris) ?></li>
                            <?php endif; ?>
                            <?php if ($infoCash): ?>
                                <li>💵 <strong>Cash:</strong> <?= e($infoCash) ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-2"><strong>3. Upload bukti</strong></p>
                        <ul class="pl-3 mb-3">
                            <li>Screenshot m-banking / struk ATM</li>
                            <li>Foto bukti QRIS</li>
                        </ul>
                        <p class="mb-0"><strong>4. Tunggu verifikasi</strong></p>
                        <p class="mb-0 text-muted">
                            Admin akan verifikasi dalam
                            <strong><?= e($deadlineVerif) ?> jam</strong>.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php else: ?>

<!-- ============================================ -->
<!-- MODE 2: FORM UPLOAD                          -->
<!-- ============================================ -->
<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/pembayaran/upload" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-upload text-primary"></i> Upload Bukti Bayar
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Upload bukti transfer/QRIS untuk diverifikasi admin
            </p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow mb-3">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="formUpload">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                        <?php if ($tagihan): ?>
                            <!-- FORM dengan tagihan spesifik (readonly) -->
                            <input type="hidden" name="tagihan_id" value="<?= (int) $tagihan['id'] ?>">

                            <div class="alert alert-info py-2 mb-3">
                                <strong><?= e($tagihan['nama_santri']) ?></strong>
                                — <?= e($tagihan['jenis_nama']) ?>
                                <?= $tagihan['periode'] ? '(' . e($tagihan['periode']) . ')' : '' ?>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>NIS</label>
                                    <input type="text" class="form-control" 
                                           value="<?= e($tagihan['nis']) ?>" readonly>
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Kelas</label>
                                    <input type="text" class="form-control" 
                                           value="<?= e($tagihan['nama_kelas'] ?: '-') ?>" readonly>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nominal Bayar <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="text" name="nominal_bayar"
                                               class="form-control" data-rupiah
                                               value="<?= number_format((float) $tagihan['nominal'], 0, ',', '.') ?>"
                                               required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Tanggal Bayar <span class="text-danger">*</span></label>
                                    <input type="date" name="tanggal_bayar" class="form-control"
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>

                        <?php else: ?>
                            <!-- FORM dengan dropdown (admin) -->
                            <div class="form-group">
                                <label>Pilih Tagihan <span class="text-danger">*</span></label>
                                <select name="tagihan_id" class="form-control" required
                                        onchange="updateInfo(this)" id="selectTagihan">
                                    <option value="">- Pilih Tagihan -</option>
                                    <?php foreach ($tagihanList as $t): ?>
                                        <option value="<?= $t['id'] ?>"
                                                data-nominal="<?= $t['nominal'] ?>"
                                                data-santri="<?= e($t['nama_santri']) ?>"
                                                data-jenis="<?= e($t['jenis_nama']) ?>"
                                                data-periode="<?= e($t['periode']) ?>">
                                            <?= e($t['nama_santri']) ?> —
                                            <?= e($t['jenis_nama']) ?>
                                            — Rp <?= number_format($t['nominal'], 0, ',', '.') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div id="infoTagihan" class="alert alert-info py-2 mb-3" style="display:none;">
                                <small>
                                    <strong id="infoSantri"></strong>
                                    <span id="infoJenis" class="ml-2"></span>
                                </small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Nominal Bayar <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">Rp</span>
                                        </div>
                                        <input type="text" name="nominal_bayar"
                                               class="form-control" data-rupiah
                                               id="inputNominal"
                                               value="<?= e($old['nominal_bayar']) ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Tanggal Bayar <span class="text-danger">*</span></label>
                                    <input type="date" name="tanggal_bayar" class="form-control"
                                           value="<?= e($old['tanggal_bayar']) ?>" required>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Metode -->
                        <div class="form-group">
                            <label>Metode Pembayaran <span class="text-danger">*</span></label>
                            <div class="row">
                                <?php
                                $metodes = [
                                    'transfer' => ['label' => 'Transfer Bank', 'icon' => '🏦'],
                                    'qris'     => ['label' => 'QRIS',          'icon' => '📱'],
                                    'cash'     => ['label' => 'Cash / Tunai',  'icon' => '💵'],
                                ];
                                $oldMetode = $old['metode'] ?? 'transfer';
                                foreach ($metodes as $key => $m):
                                ?>
                                    <div class="col-4">
                                        <label class="d-block p-2 border rounded text-center"
                                               style="cursor:pointer;"
                                               id="labelMetode<?= $key ?>">
                                            <input type="radio" name="metode" value="<?= $key ?>"
                                                   <?= $oldMetode===$key?'checked':'' ?>
                                                   onchange="pilihMetode(this)"
                                                   style="margin-right:4px;">
                                            <span style="font-size:20px;"><?= $m['icon'] ?></span>
                                            <div class="small"><?= $m['label'] ?></div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Bukti -->
                        <div class="form-group" id="wrapBukti">
                            <label>Bukti Pembayaran <span class="text-danger">*</span></label>
                            <input type="file" name="bukti" class="form-control-file"
                                   accept="image/jpeg,image/png,application/pdf"
                                   data-preview="previewBukti" id="inputBukti">
                            <small class="text-muted d-block">
                                Format: JPG, PNG, atau PDF. Maks 5 MB.
                            </small>
                            <div class="mt-2">
                                <img id="previewBukti" style="display:none; max-width:200px; border-radius:8px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="catatan_wali" class="form-control" rows="2"
                                      placeholder="Contoh: Transfer dari rekening BCA a.n. Budi"><?= e($old['catatan_wali'] ?? '') ?></textarea>
                        </div>

                        <div class="alert alert-warning py-2">
                            <small>
                                <i class="fas fa-exclamation-triangle"></i>
                                Pastikan nominal & bukti sesuai. Pembayaran akan diverifikasi admin 
                                dalam <strong><?= e($deadlineVerif) ?> jam</strong>.
                            </small>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary"
                                data-confirm="Upload bukti pembayaran ini?">
                            <i class="fas fa-upload"></i> Upload Bukti
                        </button>
                        <a href="<?= BASE_URL ?>/pembayaran/upload" class="btn btn-light">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow mb-3 border-info">
                <div class="card-header py-2 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle"></i> Panduan Pembayaran
                    </h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>Transfer sesuai nominal:</strong></p>
                    <ul class="pl-3 mb-3">
                        <?php if ($infoRekening): ?>
                            <li>🏦 <strong>Bank:</strong><br>
                                <span class="text-primary"><?= e($infoRekening) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($infoQris): ?>
                            <li>📱 <strong>QRIS:</strong><br>
                                <span class="text-primary"><?= e($infoQris) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if ($infoCash): ?>
                            <li>💵 <strong>Cash:</strong><br>
                                <span class="text-primary"><?= e($infoCash) ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if (!$infoRekening && !$infoQris && !$infoCash): ?>
                            <li class="text-muted"><em>Hubungi admin untuk info pembayaran</em></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="card shadow">
                <div class="card-body small">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-mosque text-primary mr-2" style="font-size:20px;"></i>
                        <div>
                            <strong><?= e($namaMadin) ?></strong>
                            <?php if ($alamatMadin): ?>
                                <div class="text-muted" style="font-size:11px;">
                                    <?= e($alamatMadin) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($teleponMadin): ?>
                        <div class="text-muted" style="font-size:12px;">
                            <i class="fas fa-phone"></i> 
                            <a href="tel:<?= e($teleponMadin) ?>" class="text-decoration-none">
                                <?= e($teleponMadin) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function updateInfo(sel) {
    const opt = sel.options[sel.selectedIndex];
    const nominal = opt.dataset.nominal;
    const santri  = opt.dataset.santri;
    const jenis   = opt.dataset.jenis;

    if (nominal) {
        const num = parseFloat(nominal);
        document.getElementById('inputNominal').value = num.toLocaleString('id-ID');
    }
    if (santri) {
        document.getElementById('infoTagihan').style.display = 'block';
        document.getElementById('infoSantri').textContent = santri;
        document.getElementById('infoJenis').textContent = '— ' + jenis;
    } else {
        document.getElementById('infoTagihan').style.display = 'none';
    }
}

function pilihMetode(el) {
    document.querySelectorAll('[id^="labelMetode"]').forEach(l => {
        l.classList.remove('border-primary', 'bg-light');
    });
    el.closest('label').classList.add('border-primary', 'bg-light');

    const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
    const wrapBukti = document.getElementById('wrapBukti');
    const inputBukti = document.getElementById('inputBukti');

    if (isAdmin && el.value === 'cash') {
        wrapBukti.style.opacity = '0.5';
        inputBukti.required = false;
    } else {
        wrapBukti.style.opacity = '1';
        inputBukti.required = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('selectTagihan');
    if (sel && sel.value) updateInfo(sel);

    const checked = document.querySelector('input[name="metode"]:checked');
    if (checked) pilihMetode(checked);
});
</script>

<?php endif; ?>