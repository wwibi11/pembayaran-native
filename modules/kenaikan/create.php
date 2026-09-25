<?php
// modules/kenaikan/create.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$errors = [];

// ============================================
// PREFILL dari URL ?santri=X
// ============================================
$preSantriId = (int) ($_GET['santri'] ?? 0);

// ============================================
// PROSES POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $santriId   = (int) ($_POST['santri_id'] ?? 0);
    $kelasBaru  = (int) ($_POST['kelas_id'] ?? 0);
    $tglEfektif = $_POST['tanggal_efektif'] ?? date('Y-m-d');
    $jenis      = $_POST['jenis_kenaikan'] ?? 'naik';
    $nilai      = trim($_POST['nilai_capaian'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    // Validasi
    if (!$santriId) $errors[] = 'Pilih santri.';
    if (!$kelasBaru) $errors[] = 'Pilih kelas tujuan.';
    if (!$tglEfektif) $errors[] = 'Tanggal efektif wajib diisi.';
    if (!in_array($jenis, ['naik','tinggal','pindah'])) $errors[] = 'Jenis kenaikan tidak valid.';

    // Cek santri
    $santri = null;
    if (!$errors) {
        $santri = fetchOne("SELECT s.*, k.nama_kelas, k.urutan AS urutan_sekarang
                            FROM santri s
                            LEFT JOIN kelas k ON k.id = s.kelas_id
                            WHERE s.id = ?", [$santriId]);
        if (!$santri) $errors[] = 'Santri tidak ditemukan.';
    }

    // Cek kelas baru
    $kelasBaruData = null;
    if (!$errors) {
        $kelasBaruData = fetchOne("SELECT * FROM kelas WHERE id = ?", [$kelasBaru]);
        if (!$kelasBaruData) $errors[] = 'Kelas tujuan tidak ditemukan.';

        // Cek: tidak boleh sama dengan kelas sekarang
        if ($kelasBaruData && $santri && $santri['kelas_id'] == $kelasBaru) {
            $errors[] = 'Kelas tujuan sama dengan kelas saat ini.';
        }

        // Cek: kalau "naik", urutan harus lebih besar
        if ($jenis === 'naik' && $kelasBaruData && $santri 
            && $santri['urutan_sekarang'] && $kelasBaruData['urutan'] <= $santri['urutan_sekarang']) {
            $errors[] = 'Kelas tujuan harus lebih tinggi untuk kenaikan.';
        }

        // Cek: kalau "tinggal", urutan harus lebih kecil atau sama
        if ($jenis === 'tinggal' && $kelasBaruData && $santri 
            && $santri['urutan_sekarang'] && $kelasBaruData['urutan'] >= $santri['urutan_sekarang']) {
            $errors[] = 'Kelas tujuan untuk "tinggal" harus lebih rendah.';
        }
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            $userId = currentUser()['id'];
            $today  = $tglEfektif;

            // 1. Tutup riwayat kelas lama
            execute("
                UPDATE riwayat_kelas
                SET tanggal_selesai = ?, status = ?
                WHERE santri_id = ? AND tanggal_selesai IS NULL
            ", [$today, $jenis, $santriId]);

            // 2. Buat riwayat kelas baru
            insert('riwayat_kelas', [
                'santri_id'        => $santriId,
                'kelas_id'         => $kelasBaru,
                'tanggal_mulai'    => $today,
                'status'           => 'aktif',
                'jenis_kenaikan'   => $jenis,
                'nilai_capaian'    => $nilai ?: null,
                'keterangan'       => $keterangan ?: null,
                'dipindahkan_oleh' => $userId,
            ]);

            // 3. Update cache kelas_id di santri
            update('santri', ['kelas_id' => $kelasBaru], 'id = ?', [$santriId]);

            db()->commit();

            setFlash('success', 
                'Santri "' . $santri['nama'] . '" berhasil ' . 
                ($jenis === 'naik' ? 'naik' : ($jenis === 'tinggal' ? 'tinggal' : 'pindah')) . 
                ' ke kelas "' . $kelasBaruData['nama_kelas'] . '".'
            );
            redirect('kenaikan/detail/' . $santriId);

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}

// ============================================
// DATA UNTUK FORM
// ============================================
$kelasList = fetchAll("SELECT id, nama_kelas, urutan FROM kelas WHERE is_active=1 ORDER BY urutan");

// Santri yang sedang aktif
$santriList = fetchAll("
    SELECT s.id, s.nis, s.nama, s.kelas_id, k.nama_kelas, k.urutan AS urutan_sekarang
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE s.status = 'aktif'
    ORDER BY s.nama
");
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/kenaikan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-user-plus text-success"></i> Naik Kelas Individual
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Pindahkan santri ke kelas baru berdasarkan kemampuan
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
                    <form method="POST" id="formKenaikan">
                        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                        <!-- Pilih Santri -->
                        <div class="form-group">
                            <label>Pilih Santri <span class="text-danger">*</span></label>
                            <select name="santri_id" id="selectSantri" class="form-control" required
                                    onchange="updateInfo()">
                                <option value="">- Pilih Santri -</option>
                                <?php foreach ($santriList as $s): ?>
                                    <option value="<?= $s['id'] ?>"
                                            data-nama="<?= e($s['nama']) ?>"
                                            data-nis="<?= e($s['nis']) ?>"
                                            data-kelas="<?= e($s['nama_kelas'] ?: '-') ?>"
                                            data-kelas-id="<?= (int) $s['kelas_id'] ?>"
                                            data-urutan="<?= (int) $s['urutan_sekarang'] ?>"
                                            <?= $preSantriId == $s['id'] ? 'selected' : '' ?>>
                                        <?= e($s['nama']) ?> (<?= e($s['nis']) ?>) — 
                                        <?= e($s['nama_kelas'] ?: 'Belum ada kelas') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Info santri -->
                        <div id="infoSantri" class="alert alert-info py-2 mb-3" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="infoNama"></strong>
                                    <span id="infoNis" class="small text-muted ml-2"></span>
                                </div>
                                <div>
                                    Kelas saat ini:
                                    <strong id="infoKelas" class="text-primary"></strong>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Jenis Kenaikan <span class="text-danger">*</span></label>
                                <select name="jenis_kenaikan" class="form-control" required
                                        onchange="filterKelasTujuan()" id="selectJenis">
                                    <option value="naik">⬆️ Naik Kelas</option>
                                    <option value="pindah">🔄 Pindah Kelas</option>
                                    <option value="tinggal">⬇️ Tinggal Kelas</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tanggal Efektif <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_efektif" class="form-control"
                                       value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Nilai Capaian</label>
                                <select name="nilai_capaian" class="form-control">
                                    <option value="">- Tidak ada -</option>
                                    <option value="A">A (Sangat Baik)</option>
                                    <option value="B">B (Baik)</option>
                                    <option value="C">C (Cukup)</option>
                                    <option value="D">D (Kurang)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Kelas Tujuan <span class="text-danger">*</span></label>
                            <select name="kelas_id" id="selectKelasTujuan" class="form-control" required>
                                <option value="">- Pilih Kelas -</option>
                                <?php foreach ($kelasList as $k): ?>
                                    <option value="<?= $k['id'] ?>" data-urutan="<?= $k['urutan'] ?>">
                                        <?= e($k['nama_kelas']) ?> (urutan <?= $k['urutan'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="hintKelas"></small>
                        </div>

                        <div class="form-group">
                            <label>Keterangan / Catatan</label>
                            <textarea name="keterangan" class="form-control" rows="3"
                                      placeholder="Contoh: Lancar tajwid, siap lanjut ke Jilid 2"></textarea>
                        </div>

                        <hr>
                        <button type="submit" class="btn btn-success"
                                data-confirm="Yakin pindahkan santri ini ke kelas baru?">
                            <i class="fas fa-arrow-up"></i> Proses Kenaikan
                        </button>
                        <a href="<?= BASE_URL ?>/kenaikan" class="btn btn-light">
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
                        <i class="fas fa-info-circle"></i> Panduan
                    </h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>Jenis Kenaikan:</strong></p>
                    <ul class="pl-3 mb-3">
                        <li><strong>⬆️ Naik Kelas</strong> — ke level lebih tinggi (Iqro 1 → Iqro 2)</li>
                        <li><strong>🔄 Pindah Kelas</strong> — pindah antar kelas di level setara</li>
                        <li><strong>⬇️ Tinggal Kelas</strong> — mundur ke level lebih rendah</li>
                    </ul>
                    <p class="mb-2"><strong>Efeknya:</strong></p>
                    <ul class="pl-3 mb-0">
                        <li>Riwayat kelas lama ditutup</li>
                        <li>Riwayat kelas baru dibuat</li>
                        <li>Kelas santri otomatis diupdate</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const kelasList = <?= json_encode(array_map(fn($k) => [
    'id' => $k['id'],
    'nama' => $k['nama_kelas'],
    'urutan' => (int) $k['urutan'],
], $kelasList)) ?>;

// ============================================
// UPDATE INFO SANTRI
// ============================================
function updateInfo() {
    const sel = document.getElementById('selectSantri');
    const opt = sel.options[sel.selectedIndex];
    
    if (opt.value) {
        document.getElementById('infoSantri').style.display = 'block';
        document.getElementById('infoNama').textContent = opt.dataset.nama;
        document.getElementById('infoNis').textContent = '(' + opt.dataset.nis + ')';
        document.getElementById('infoKelas').textContent = opt.dataset.kelas;
    } else {
        document.getElementById('infoSantri').style.display = 'none';
    }
    filterKelasTujuan();
}

// ============================================
// FILTER KELAS TUJUAN SESUAI JENIS
// ============================================
function filterKelasTujuan() {
    const santriSel = document.getElementById('selectSantri');
    const jenisSel = document.getElementById('selectJenis');
    const kelasSel = document.getElementById('selectKelasTujuan');
    const hint = document.getElementById('hintKelas');

    const opt = santriSel.options[santriSel.selectedIndex];
    const urutanSekarang = parseInt(opt.dataset.urutan) || 0;
    const kelasSekarangId = parseInt(opt.dataset.kelasId) || 0;
    const jenis = jenisSel.value;

    // Reset semua option
    Array.from(kelasSel.options).forEach(o => {
        o.disabled = false;
        o.style.display = '';
    });

    // Filter sesuai jenis
    let hintText = '';
    if (jenis === 'naik') {
        hintText = 'Kelas tujuan harus urutan lebih besar dari kelas saat ini';
        Array.from(kelasSel.options).forEach(o => {
            if (!o.value) return;
            const urutan = parseInt(o.dataset.urutan);
            if (urutan <= urutanSekarang) {
                o.disabled = true;
                o.style.opacity = '0.4';
            }
        });
    } else if (jenis === 'tinggal') {
        hintText = 'Kelas tujuan harus urutan lebih kecil dari kelas saat ini';
        Array.from(kelasSel.options).forEach(o => {
            if (!o.value) return;
            const urutan = parseInt(o.dataset.urutan);
            if (urutan >= urutanSekarang) {
                o.disabled = true;
                o.style.opacity = '0.4';
            }
        });
    } else {
        hintText = 'Pindah ke kelas lain (level setara atau berbeda)';
    }

    // Reset selection kalau option terpilih jadi disabled
    if (kelasSel.selectedOptions[0] && kelasSel.selectedOptions[0].disabled) {
        kelasSel.value = '';
    }

    hint.textContent = hintText;
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    updateInfo();
    filterKelasTujuan();
});
</script>