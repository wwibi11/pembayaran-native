<?php
// modules/tagihan/generate.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$errors = null;

// ============================================
// AMBIL DATA UNTUK FORM
// ============================================
$jenisPeriodik = fetchAll("
    SELECT * FROM jenis_pembayaran 
    WHERE is_active=1 AND periode IN ('bulanan','tahunan')
    ORDER BY nama
");

$jenisSekali = fetchAll("
    SELECT * FROM jenis_pembayaran 
    WHERE is_active=1 AND periode = 'sekali'
    ORDER BY nama
");

$kelasList = fetchAll("SELECT id, nama_kelas FROM kelas WHERE is_active=1 ORDER BY urutan");

$santriAktif = fetchAll("
    SELECT s.id, s.nis, s.nama, s.kelas_id, k.nama_kelas
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE s.status = 'aktif'
    ORDER BY k.urutan, s.nama
");

// Kalau ada ?santri=ID, pre-select
$preSelectSantri = (int) ($_GET['santri'] ?? 0);

// ============================================
// PROSES POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $jenisId     = (int) ($_POST['jenis_pembayaran_id'] ?? 0);
    $jatuhTempo  = $_POST['jatuh_tempo'] ?? '';
    $targetKelas = $_POST['target_kelas'] ?? [];
    $targetSantriIds = array_filter(array_map('intval', (array) ($_POST['target_santri'] ?? [])));
    $periodeInput = trim($_POST['periode'] ?? '');
    $nominal      = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');

    $jenis = fetchOne("SELECT * FROM jenis_pembayaran WHERE id = ?", [$jenisId]);
    if (!$jenis) $errors[] = 'Jenis pembayaran tidak valid.';

    // Kalau periode = sekali, periode di-override
    $periodeFinal = $periodeInput;
    if ($jenis && $jenis['periode'] === 'sekali') {
        // Format: DAFTAR-YYYY-MM-DD (atau SERAGAM-YYYY-MM-DD)
        $kode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $jenis['nama']), 0, 8));
        $periodeFinal = $kode . '-' . date('Ymd');
    }

    if ($nominal <= 0 && $jenis) {
        $nominal = (float) $jenis['nominal_default'];
    }

    if (!$errors && $nominal <= 0) $errors[] = 'Nominal harus > 0.';

    // Tentukan target santri
    $targetSantri = [];

    if (!empty($targetSantriIds)) {
        // Mode per anak (wajib untuk sekali bayar)
        $ph = implode(',', array_fill(0, count($targetSantriIds), '?'));
        $targetSantri = fetchAll("
            SELECT id, kelas_id FROM santri 
            WHERE id IN ($ph) AND status='aktif'
        ", $targetSantriIds);
    } elseif ($jenis && $jenis['periode'] === 'sekali') {
        $errors[] = 'Untuk jenis sekali bayar (Pendaftaran/Seragam), wajib pilih santri spesifik.';
    } else {
        // Mode per kelas
        $sqlSantri = "SELECT id, kelas_id FROM santri WHERE status='aktif'";
        $ps = [];

        if (!empty($targetKelas) && !in_array('all', $targetKelas)) {
            $targetKelas = array_filter(array_map('intval', $targetKelas));
            if ($targetKelas) {
                $ph = implode(',', array_fill(0, count($targetKelas), '?'));
                $sqlSantri .= " AND kelas_id IN ($ph)";
                $ps = array_merge($ps, $targetKelas);
            }
        }
        $targetSantri = fetchAll($sqlSantri, $ps);
    }

    if (!$errors && empty($targetSantri)) {
        $errors[] = 'Tidak ada santri target.';
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $created = 0;
            $skipped = 0;
            $userId  = currentUser()['id'];

            foreach ($targetSantri as $s) {
                // Cek duplikat
                $dup = fetchOne("
                    SELECT id FROM tagihan
                    WHERE santri_id = ? AND jenis_pembayaran_id = ? AND periode = ?
                ", [$s['id'], $jenisId, $periodeFinal]);

                if ($dup) {
                    $skipped++;
                    continue;
                }

                insert('tagihan', [
                    'santri_id'           => $s['id'],
                    'jenis_pembayaran_id' => $jenisId,
                    'kelas_id'            => $s['kelas_id'],
                    'periode'             => $periodeFinal,
                    'nominal'             => $nominal,
                    'jatuh_tempo'         => $jatuhTempo ?: null,
                    'status'              => 'belum_lunas',
                    'created_by'          => $userId,
                ]);
                $created++;
            }

            db()->commit();

            $msg = "Berhasil generate: <strong>$created</strong> tagihan baru.";
            if ($skipped > 0) $msg .= " ($skipped dilewati karena duplikat)";

            setFlash('success', strip_tags($msg));
            redirect('tagihan');

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/tagihan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-magic text-primary"></i> Generate Tagihan
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Buat tagihan massal atau per anak
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

    <form method="POST" id="formGenerate">
        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

        <div class="row">
            <!-- ============================================
                 KOLOM KIRI: KONFIGURASI
                 ============================================ -->
            <div class="col-lg-7">

                <!-- Jenis Pembayaran -->
                <div class="card shadow mb-3">
                    <div class="card-header py-2 bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-cog"></i> Konfigurasi Tagihan
                        </h6>
                    </div>
                    <div class="card-body">

                        <div class="form-group">
                            <label>Jenis Pembayaran <span class="text-danger">*</span></label>
                            <select name="jenis_pembayaran_id" id="jenisSelect"
                                    class="form-control" required onchange="onJenisChange()">
                                <option value="">- Pilih Jenis -</option>

                                <?php if ($jenisPeriodik): ?>
                                <optgroup label="🔄 Pembayaran Periodik (Bulanan/Tahunan)">
                                    <?php foreach ($jenisPeriodik as $j): ?>
                                        <option value="<?= $j['id'] ?>"
                                                data-nominal="<?= $j['nominal_default'] ?>"
                                                data-periode="<?= $j['periode'] ?>"
                                                data-nama="<?= e($j['nama']) ?>">
                                            <?= e($j['nama']) ?> — <?= rupiah($j['nominal_default']) ?> / <?= $j['periode'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>

                                <?php if ($jenisSekali): ?>
                                <optgroup label="⚡ Sekali Bayar (wajib per anak)">
                                    <?php foreach ($jenisSekali as $j): ?>
                                        <option value="<?= $j['id'] ?>"
                                                data-nominal="<?= $j['nominal_default'] ?>"
                                                data-periode="sekali"
                                                data-nama="<?= e($j['nama']) ?>">
                                            <?= e($j['nama']) ?> — <?= rupiah($j['nominal_default']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endif; ?>
                            </select>

                            <!-- Info khusus sekali bayar -->
                            <div id="infoSekali" class="alert alert-info py-2 mt-2 mb-0" style="display:none;">
                                <small>
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Jenis sekali bayar.</strong>
                                    Tagihan ini biasanya sudah dibuat <em>otomatis</em> saat santri baru daftar.
                                    Generate manual berguna untuk <strong>santri lama</strong> yang belum punya tagihan.
                                    <br>
                                    <i class="fas fa-arrow-right"></i> <strong>Wajib pilih santri spesifik</strong> di bawah.
                                </small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Nominal <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="text" name="nominal" id="inputNominal"
                                           class="form-control" data-rupiah
                                           placeholder="Otomatis dari jenis">
                                </div>
                            </div>

                            <!-- PERIODE: hanya untuk bulanan/tahunan -->
                            <div class="form-group" id="wrapPeriode">
                                <label>Periode <span class="text-danger">*</span></label>
                                <input type="text" name="periode" id="inputPeriode"
                                       class="form-control"
                                       placeholder="2025-10 atau 2025"
                                       value="<?= date('Y-m') ?>">
                                <small class="text-muted">
                                    Format: <code>2025-10</code> (bulanan) / <code>2025</code> (tahunan)
                                </small>
                            </div>

                            <!-- PERIODE AUTO: untuk sekali bayar -->
                            <div class="form-group" id="wrapPeriodeAuto" style="display:none;">
                                <label>Periode</label>
                                <input type="text" class="form-control" id="periodeAutoText"
                                       value="Otomatis dari tanggal" disabled>
                                <small class="text-muted">Sistem isi otomatis</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Jatuh Tempo</label>
                            <input type="date" name="jatuh_tempo" class="form-control"
                                   value="<?= date('Y-m-t') ?>">
                        </div>
                    </div>
                </div>

                <!-- Target Santri -->
                <div class="card shadow mb-3">
                    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-users"></i> Target Santri
                        </h6>
                        <span id="badgeMode" class="badge bg-secondary">Pilih jenis dulu</span>
                    </div>
                    <div class="card-body">

                        <!-- Mode per kelas (untuk periodik) -->
                        <div id="modeKelas">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                       name="target_kelas[]" value="all" id="targetAll"
                                       checked onchange="toggleKelas(this)">
                                <label class="form-check-label font-weight-bold" for="targetAll">
                                    Semua Kelas (semua santri aktif)
                                </label>
                            </div>

                            <div id="kelasCheckboxes" style="display:none;">
                                <p class="small text-muted mb-2">Pilih kelas:</p>
                                <div class="row">
                                    <?php foreach ($kelasList as $k): ?>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input kelas-checkbox"
                                                       type="checkbox" name="target_kelas[]"
                                                       value="<?= $k['id'] ?>"
                                                       id="kelas_<?= $k['id'] ?>">
                                                <label class="form-check-label" for="kelas_<?= $k['id'] ?>">
                                                    <?= e($k['nama_kelas']) ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Mode per anak -->
                        <div id="modeAnak" style="display:none;">
                            <div class="alert alert-warning py-2 mb-2">
                                <small>
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Jenis sekali bayar</strong> harus generate per anak.
                                </small>
                            </div>

                            <input type="text" id="searchSantri"
                                   class="form-control form-control-sm mb-2"
                                   placeholder="🔍 Cari nama / NIS...">

                            <div class="border rounded" style="max-height:280px; overflow-y:auto;">
                                <table class="table table-sm table-hover mb-0" id="tabelSantri">
                                    <thead class="bg-light" style="position:sticky;top:0;z-index:1;">
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" id="checkAllSantri">
                                            </th>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th>Kelas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($santriAktif as $s): ?>
                                        <tr data-nama="<?= e(strtolower($s['nama'])) ?>"
                                            data-nis="<?= e(strtolower($s['nis'])) ?>">
                                            <td>
                                                <input type="checkbox" name="target_santri[]"
                                                       value="<?= (int) $s['id'] ?>"
                                                       class="santri-checkbox"
                                                       <?= $preSelectSantri == $s['id'] ? 'checked' : '' ?>>
                                            </td>
                                            <td><small class="text-muted"><?= e($s['nis']) ?></small></td>
                                            <td><strong><?= e($s['nama']) ?></strong></td>
                                            <td><small><?= e($s['nama_kelas'] ?: '-') ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-2 small text-muted">
                                <span id="countSelected">0</span> santri dipilih
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ============================================
                 KOLOM KANAN: PREVIEW + AKSI
                 ============================================ -->
            <div class="col-lg-5">
                <div class="card shadow mb-3 sticky-top" style="top:80px;">
                    <div class="card-header py-2 bg-info text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-eye"></i> Preview
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="h3 mb-0 font-weight-bold text-primary" id="previewCount">0</div>
                            <div class="small text-muted" id="previewLabel">santri akan ditagih</div>
                        </div>

                        <hr>

                        <table class="table table-sm mb-0" style="font-size:12px;">
                            <tr>
                                <td class="text-muted">Jenis</td>
                                <td class="text-right" id="previewJenis">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nominal</td>
                                <td class="text-right" id="previewNominal">Rp 0</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Periode</td>
                                <td class="text-right" id="previewPeriode">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Jatuh Tempo</td>
                                <td class="text-right" id="previewJatuhTempo">-</td>
                            </tr>
                            <tr style="border-top:2px solid #dee2e6;">
                                <td class="font-weight-bold">Total Nilai</td>
                                <td class="text-right font-weight-bold text-primary" id="previewTotal">Rp 0</td>
                            </tr>
                        </table>

                        <div class="alert alert-warning py-2 mt-3 mb-0">
                            <small>
                                <i class="fas fa-exclamation-triangle"></i>
                                Tagihan duplikat (santri + jenis + periode sama) akan otomatis <strong>dilewati</strong>.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary btn-block"
                                data-confirm="Generate tagihan untuk target terpilih?">
                            <i class="fas fa-magic"></i> Generate Sekarang
                        </button>
                        <a href="<?= BASE_URL ?>/tagihan" class="btn btn-light btn-block mt-2">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// Data santri untuk preview
const santriData = <?= json_encode(array_map(fn($s) => [
    'id' => $s['id'],
    'kelas_id' => $s['kelas_id']
], $santriAktif)) ?>;

let currentPeriode = 'bulanan';

// ============================================
// ON CHANGE JENIS PEMBAYARAN
// ============================================
function onJenisChange() {
    const sel = document.getElementById('jenisSelect');
    const opt = sel.options[sel.selectedIndex];

    if (!opt || !opt.value) {
        document.getElementById('badgeMode').textContent = 'Pilih jenis dulu';
        document.getElementById('badgeMode').className = 'badge bg-secondary';
        return;
    }

    const periode = opt.dataset.periode;
    const nominal = opt.dataset.nominal;
    currentPeriode = periode;

    // Update badge mode
    const badge = document.getElementById('badgeMode');

    if (periode === 'sekali') {
        // Mode PER ANAK
        document.getElementById('modeKelas').style.display = 'none';
        document.getElementById('modeAnak').style.display  = 'block';
        document.getElementById('infoSekali').style.display = 'block';

        document.getElementById('wrapPeriode').style.display     = 'none';
        document.getElementById('wrapPeriodeAuto').style.display = 'block';

        badge.textContent = '⚡ Per Anak';
        badge.className = 'badge bg-warning text-dark';

        // Uncheck target kelas biar tidak dikirim
        document.querySelectorAll('.kelas-checkbox, #targetAll').forEach(c => c.checked = false);
    } else {
        // Mode PER KELAS
        document.getElementById('modeKelas').style.display = 'block';
        document.getElementById('modeAnak').style.display  = 'none';
        document.getElementById('infoSekali').style.display = 'none';

        document.getElementById('wrapPeriode').style.display     = 'block';
        document.getElementById('wrapPeriodeAuto').style.display = 'none';

        badge.textContent = periode === 'bulanan' ? '🔄 Bulanan' : '🔄 Tahunan';
        badge.className = 'badge bg-primary';

        // Reset target kelas default
        document.getElementById('targetAll').checked = true;
        document.getElementById('kelasCheckboxes').style.display = 'none';
    }

    // Auto isi nominal
    if (nominal && !document.getElementById('inputNominal').value) {
        const num = parseFloat(nominal);
        document.getElementById('inputNominal').value = num.toLocaleString('id-ID');
    }

    // Set default periode
    if (periode === 'bulanan') {
        document.getElementById('inputPeriode').value = new Date().toISOString().slice(0, 7);
    } else if (periode === 'tahunan') {
        document.getElementById('inputPeriode').value = new Date().getFullYear();
    }

    updatePreview();
}

// ============================================
// TOGGLE KELAS
// ============================================
function toggleKelas(cb) {
    const container = document.getElementById('kelasCheckboxes');
    const checkboxes = document.querySelectorAll('.kelas-checkbox');

    if (cb.checked) {
        container.style.display = 'none';
        checkboxes.forEach(c => c.checked = false);
    } else {
        container.style.display = 'block';
    }
    updatePreview();
}

// ============================================
// UPDATE PREVIEW
// ============================================
function updatePreview() {
    const sel = document.getElementById('jenisSelect');
    const opt = sel.options[sel.selectedIndex];

    if (!opt || !opt.value) {
        document.getElementById('previewCount').textContent = '0';
        document.getElementById('previewJenis').textContent = '-';
        document.getElementById('previewNominal').textContent = 'Rp 0';
        document.getElementById('previewTotal').textContent = 'Rp 0';
        return;
    }

    const periode = opt.dataset.periode;
    let count = 0;

    if (periode === 'sekali') {
        // Hitung santri terpilih
        count = document.querySelectorAll('.santri-checkbox:checked').length;
        document.getElementById('previewLabel').textContent = 'santri dipilih';
        document.getElementById('countSelected').textContent = count;
    } else {
        // Hitung berdasarkan kelas
        const targetAll = document.getElementById('targetAll').checked;
        if (targetAll) {
            count = santriData.length;
        } else {
            const selectedKelas = Array.from(document.querySelectorAll('.kelas-checkbox:checked'))
                                       .map(c => parseInt(c.value));
            count = santriData.filter(s => selectedKelas.includes(parseInt(s.kelas_id))).length;
        }
        document.getElementById('previewLabel').textContent = 'santri akan ditagih';
    }

    document.getElementById('previewCount').textContent = count;

    // Jenis
    const jenisTxt = opt.dataset.nama || '-';
    document.getElementById('previewJenis').textContent = jenisTxt;

    // Nominal
    let nominalStr = document.getElementById('inputNominal').value || '0';
    let nominal = parseFloat(nominalStr.replace(/\./g, '').replace(',', '.')) || 0;
    document.getElementById('previewNominal').textContent = 'Rp ' + nominal.toLocaleString('id-ID');

    // Periode
    let periodeTxt = '-';
    if (periode === 'sekali') {
        periodeTxt = 'Otomatis';
    } else {
        periodeTxt = document.getElementById('inputPeriode').value || '-';
    }
    document.getElementById('previewPeriode').textContent = periodeTxt;

    // Jatuh tempo
    document.getElementById('previewJatuhTempo').textContent = 
        document.querySelector('input[name="jatuh_tempo"]').value || '-';

    // Total
    document.getElementById('previewTotal').textContent = 
        'Rp ' + (count * nominal).toLocaleString('id-ID');
}

// ============================================
// LISTENERS
// ============================================
document.addEventListener('DOMContentLoaded', function() {

    ['inputNominal', 'inputPeriode'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreview);
    });

    document.querySelectorAll('.kelas-checkbox').forEach(cb => {
        cb.addEventListener('change', updatePreview);
    });

    document.querySelectorAll('.santri-checkbox').forEach(cb => {
        cb.addEventListener('change', function() {
            const checked = document.querySelectorAll('.santri-checkbox:checked').length;
            document.getElementById('countSelected').textContent = checked;
            updatePreview();
        });
    });

    document.querySelector('input[name="jatuh_tempo"]').addEventListener('change', updatePreview);

    // Search santri
    const searchSantri = document.getElementById('searchSantri');
    if (searchSantri) {
        searchSantri.addEventListener('input', function() {
            const q = this.value.toLowerCase().trim();
            document.querySelectorAll('#tabelSantri tbody tr').forEach(tr => {
                const nama = tr.dataset.nama || '';
                const nis  = tr.dataset.nis  || '';
                tr.style.display = (nama.includes(q) || nis.includes(q)) ? '' : 'none';
            });
        });
    }

    // Check all santri
    const checkAll = document.getElementById('checkAllSantri');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            document.querySelectorAll('#tabelSantri tbody tr').forEach(tr => {
                if (tr.style.display !== 'none') {
                    const cb = tr.querySelector('.santri-checkbox');
                    if (cb) cb.checked = this.checked;
                }
            });
            const checked = document.querySelectorAll('.santri-checkbox:checked').length;
            document.getElementById('countSelected').textContent = checked;
            updatePreview();
        });
    }

    // Kalau ada pre-select dari URL
    if (<?= $preSelectSantri ? 'true' : 'false' ?>) {
        // Force mode anak dengan pilih jenis sekali bayar dulu
        setTimeout(() => {
            const checked = document.querySelectorAll('.santri-checkbox:checked').length;
            document.getElementById('countSelected').textContent = checked;
            updatePreview();
        }, 100);
    }

    updatePreview();
});
</script>

<style>
.sticky-top { z-index: 100; }
</style>