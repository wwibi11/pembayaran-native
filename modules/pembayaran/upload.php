<?php
// modules/pembayaran/upload.php
require_once __DIR__ . '/../../config/functions.php';

$isAdmin = hasRole('admin');
$isWali  = hasRole('wali');

if (!$isAdmin && !$isWali) { http_response_code(403); exit('Akses ditolak.'); }

$errors = [];

// Tagihan yang mau dibayar
$tagihanId = (int) ($id ?? $_GET['tagihan'] ?? 0);
$tagihan = null;

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
}

// Cek akses wali
if ($isWali && $tagihan) {
    if (!isAnakDariWali((int) $tagihan['santri_id'], currentUser()['id'])) {
        http_response_code(403);
        exit('403 - Ini bukan anak Anda.');
    }
}

// Ambil daftar tagihan belum lunas milik wali
$tagihanList = [];
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
} elseif ($isAdmin) {
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

$old = [
    'tagihan_id'    => $tagihanId,
    'nominal_bayar' => $tagihan ? $tagihan['nominal'] : '',
    'tanggal_bayar' => date('Y-m-d'),
    'metode'        => 'transfer',
    'catatan_wali'  => '',
];

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
            // Admin dengan cash → langsung diverifikasi
            // Wali → menunggu verifikasi
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

            // Recalculate status tagihan
            recalculateTagihanStatus($old['tagihan_id']);

            db()->commit();

            if ($isAdminCash) {
                setFlash('success', 'Pembayaran cash berhasil dicatat & diverifikasi.');
                redirect('pembayaran/detail/' . $pembayaranId);
            } else {
                setFlash('success', 'Bukti pembayaran berhasil diupload. Menunggu verifikasi admin.');
                redirect('pembayaran/detail/' . $pembayaranId);
            }

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    // Reset info tagihan
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
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/pembayaran/riwayat" class="btn btn-sm btn-light mr-2">
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
                                            data-periode="<?= e($t['periode']) ?>"
                                            <?= $old['tagihan_id'] == $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['nama_santri']) ?> —
                                        <?= e($t['jenis_nama']) ?>
                                        <?= $t['periode'] ? ' (' . e($t['periode']) . ')' : '' ?>
                                        — Rp <?= number_format($t['nominal'], 0, ',', '.') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div id="infoTagihan" class="alert alert-info py-2 mb-3" style="display:none;">
                            <small>
                                <strong id="infoSantri"></strong>
                                <span id="infoJenis" class="ml-2"></span>
                                <span id="infoPeriode" class="ml-2 text-muted"></span>
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

                        <div class="form-group">
                            <label>Metode Pembayaran <span class="text-danger">*</span></label>
                            <div class="row">
                                <?php
                                $metodes = [
                                    'transfer' => ['label' => 'Transfer Bank', 'icon' => '🏦'],
                                    'qris'     => ['label' => 'QRIS',          'icon' => '📱'],
                                    'cash'     => ['label' => 'Cash / Tunai',  'icon' => '💵'],
                                ];
                                foreach ($metodes as $key => $m):
                                ?>
                                    <div class="col-4">
                                        <label class="d-block p-2 border rounded text-center"
                                               style="cursor:pointer;"
                                               id="labelMetode<?= $key ?>">
                                            <input type="radio" name="metode" value="<?= $key ?>"
                                                   <?= $old['metode']===$key?'checked':'' ?>
                                                   onchange="pilihMetode(this)"
                                                   style="margin-right:4px;">
                                            <span style="font-size:20px;"><?= $m['icon'] ?></span>
                                            <div class="small"><?= $m['label'] ?></div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group" id="wrapBukti">
                            <label>Bukti Pembayaran <span class="text-danger">*</span></label>
                            <input type="file" name="bukti" class="form-control-file"
                                   accept="image/jpeg,image/png,application/pdf"
                                   data-preview="previewBukti" id="inputBukti">
                            <small class="text-muted d-block">
                                Format: JPG, PNG, atau PDF. Maks 5 MB.
                                <br>Upload screenshot bukti transfer / QRIS.
                            </small>
                            <div class="mt-2">
                                <img id="previewBukti" style="display:none; max-width:200px; border-radius:8px;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="catatan_wali" class="form-control" rows="2"
                                      placeholder="Contoh: Transfer dari rekening BCA a.n. Budi"><?= e($old['catatan_wali']) ?></textarea>
                        </div>

                        <div class="alert alert-warning py-2">
                            <small>
                                <i class="fas fa-exclamation-triangle"></i>
                                Pastikan nominal & bukti sesuai. Pembayaran akan diverifikasi admin 
                                dalam 1x24 jam.
                            </small>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-primary"
                                data-confirm="Upload bukti pembayaran ini?">
                            <i class="fas fa-upload"></i> Upload Bukti
                        </button>
                        <a href="<?= BASE_URL ?>/pembayaran/riwayat" class="btn btn-light">
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
                        <i class="fas fa-info-circle"></i> Panduan
                    </h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>1. Pilih tagihan yang mau dibayar</strong></p>
                    <p class="mb-2"><strong>2. Transfer sesuai nominal</strong></p>
                    <ul class="pl-3 mb-3">
                        <li>🏦 Transfer Bank: <strong>BCA 1234567890</strong> a.n. TPQ Madin</li>
                        <li>📱 QRIS: Scan di kantor TPQ</li>
                        <li>💵 Cash: Bayar langsung ke admin</li>
                    </ul>
                    <p class="mb-2"><strong>3. Upload bukti</strong></p>
                    <ul class="pl-3 mb-3">
                        <li>Screenshot m-banking / struk ATM</li>
                        <li>Foto bukti QRIS</li>
                    </ul>
                    <p class="mb-0"><strong>4. Tunggu verifikasi</strong></p>
                    <p class="mb-0 text-muted">Admin akan verifikasi dalam 1x24 jam.</p>
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
    const periode = opt.dataset.periode;

    if (nominal) {
        const num = parseFloat(nominal);
        document.getElementById('inputNominal').value = num.toLocaleString('id-ID');
    }

    if (santri) {
        document.getElementById('infoTagihan').style.display = 'block';
        document.getElementById('infoSantri').textContent = santri;
        document.getElementById('infoJenis').textContent = '— ' + jenis;
        document.getElementById('infoPeriode').textContent = periode ? '· ' + periode : '';
    } else {
        document.getElementById('infoTagihan').style.display = 'none';
    }
}

function pilihMetode(el) {
    // Warna border
    document.querySelectorAll('[id^="labelMetode"]').forEach(l => {
        l.classList.remove('border-primary', 'bg-light');
    });
    el.closest('label').classList.add('border-primary', 'bg-light');

    // Kalau admin pilih cash, bukti optional
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