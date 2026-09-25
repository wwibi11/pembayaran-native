<?php
// modules/pembayaran/create.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$errors = [];
$old = [
    'tagihan_id'    => (int) ($_GET['tagihan'] ?? 0),
    'nominal_bayar' => '',
    'tanggal_bayar' => date('Y-m-d'),
    'metode'        => 'cash',
    'catatan_wali'  => '',
];

// Cari tagihan aktif kalau ada pre-select
$tagihanTerpilih = null;
if ($old['tagihan_id']) {
    $tagihanTerpilih = fetchOne("
        SELECT t.*, s.nama AS nama_santri, s.nis, k.nama_kelas,
               jp.nama AS jenis_nama
        FROM tagihan t
        JOIN santri s ON s.id = t.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        WHERE t.id = ? AND t.status != 'lunas'
    ", [$old['tagihan_id']]);

    if ($tagihanTerpilih) {
        $old['nominal_bayar'] = $tagihanTerpilih['nominal'];
    }
}

// List tagihan untuk dropdown (belum lunas)
$tagihanList = fetchAll("
    SELECT t.id, t.nominal, t.periode, t.status,
           s.nama AS nama_santri, s.nis, k.nama_kelas,
           jp.nama AS jenis_nama
    FROM tagihan t
    JOIN santri s ON s.id = t.santri_id
    LEFT JOIN kelas k ON k.id = s.kelas_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    WHERE t.status IN ('belum_lunas','menunggu_verifikasi')
    ORDER BY s.nama
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $old = [
        'tagihan_id'    => (int) ($_POST['tagihan_id'] ?? 0),
        'nominal_bayar' => (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_bayar'] ?? '0'),
        'tanggal_bayar' => $_POST['tanggal_bayar'] ?? date('Y-m-d'),
        'metode'        => $_POST['metode'] ?? 'cash',
        'catatan_wali'  => trim($_POST['catatan_wali'] ?? ''),
    ];

    if (!$old['tagihan_id']) $errors[] = 'Pilih tagihan.';
    if ($old['nominal_bayar'] <= 0) $errors[] = 'Nominal harus > 0.';
    if (!$old['tanggal_bayar']) $errors[] = 'Tanggal wajib diisi.';
    if (!in_array($old['metode'], ['cash','transfer','qris'])) $errors[] = 'Metode tidak valid.';

    // Cek tagihan
    if (!$errors) {
        $tagihan = fetchOne("SELECT * FROM tagihan WHERE id = ?", [$old['tagihan_id']]);
        if (!$tagihan) {
            $errors[] = 'Tagihan tidak ditemukan.';
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $userId = currentUser()['id'];

            // Insert pembayaran dengan status diverifikasi langsung
            $pembayaranId = insert('pembayaran', [
                'tagihan_id'    => $old['tagihan_id'],
                'uploaded_by'   => $userId,
                'nominal_bayar' => $old['nominal_bayar'],
                'tanggal_bayar' => $old['tanggal_bayar'],
                'metode'        => $old['metode'],
                'catatan_wali'  => $old['catatan_wali'] ?: 'Input manual oleh admin',
                'status'        => 'diverifikasi',
                'verified_by'   => $userId,
                'verified_at'   => date('Y-m-d H:i:s'),
            ]);

            // Update status tagihan
            recalculateTagihanStatus($old['tagihan_id']);

            db()->commit();

            setFlash('success', 
                "Pembayaran manual " . rupiah($old['nominal_bayar']) . 
                " berhasil dicatat & langsung diverifikasi."
            );
            redirect('pembayaran/detail/' . $pembayaranId);

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/pembayaran/verifikasi" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-cash-register text-success"></i> Input Pembayaran Manual
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Untuk pembayaran cash/offline. Langsung diverifikasi otomatis.
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
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                        <div class="form-group">
                            <label>Pilih Tagihan <span class="text-danger">*</span></label>
                            <select name="tagihan_id" class="form-control" required
                                    onchange="updateNominal(this)" id="selectTagihan">
                                <option value="">- Pilih Tagihan -</option>
                                <?php foreach ($tagihanList as $t): ?>
                                    <option value="<?= $t['id'] ?>"
                                            data-nominal="<?= $t['nominal'] ?>"
                                            data-santri="<?= e($t['nama_santri']) ?>"
                                            data-nis="<?= e($t['nis']) ?>"
                                            data-jenis="<?= e($t['jenis_nama']) ?>"
                                            data-periode="<?= e($t['periode']) ?>"
                                            <?= $old['tagihan_id'] == $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['nama_santri']) ?> (<?= e($t['nis']) ?>) —
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
                            <div class="form-group">
                                <label>Metode <span class="text-danger">*</span></label>
                                <select name="metode" class="form-control" required>
                                    <option value="cash" <?= $old['metode']==='cash'?'selected':'' ?>>
                                        💵 Cash / Tunai
                                    </option>
                                    <option value="transfer" <?= $old['metode']==='transfer'?'selected':'' ?>>
                                        🏦 Transfer Bank
                                    </option>
                                    <option value="qris" <?= $old['metode']==='qris'?'selected':'' ?>>
                                        📱 QRIS
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Catatan (opsional)</label>
                            <textarea name="catatan_wali" class="form-control" rows="2"
                                      placeholder="Contoh: Bayar cash di kantor, diterima oleh Ustadz Ahmad"><?= e($old['catatan_wali']) ?></textarea>
                        </div>

                        <div class="alert alert-info py-2">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                Pembayaran ini akan <strong>langsung diverifikasi</strong>. 
                                Gunakan untuk pembayaran cash/offline yang sudah diterima admin.
                            </small>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success"
                                data-confirm="Simpan & verifikasi pembayaran ini?">
                            <i class="fas fa-check"></i> Simpan & Verifikasi
                        </button>
                        <a href="<?= BASE_URL ?>/pembayaran/verifikasi" class="btn btn-light">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow mb-3 border-info">
                <div class="card-header py-2 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle"></i> Info
                    </h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>Kapan pakai form ini?</strong></p>
                    <ul class="pl-3 mb-0">
                        <li>Orang tua bayar <strong>cash</strong> langsung ke admin</li>
                        <li>Orang tua bayar via <strong>transfer bank</strong>, tapi tidak punya akun aplikasi</li>
                        <li>Orang tua bayar via <strong>QRIS</strong> dan langsung konfirmasi ke admin</li>
                        <li>Pembayaran kolektif yang dicatat manual</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function updateNominal(sel) {
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

document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('selectTagihan');
    if (sel && sel.value) updateNominal(sel);
});
</script>