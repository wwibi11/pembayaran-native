<?php
// modules/kenaikan/massal.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$errors = null;

$kelasList = fetchAll("SELECT id, nama_kelas, urutan FROM kelas WHERE is_active=1 ORDER BY urutan");

// Pre-filter kelas dari URL
$preKelas = (int) ($_GET['kelas'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $kelasAsal  = (int) ($_POST['kelas_asal'] ?? 0);
    $kelasTujuan= (int) ($_POST['kelas_tujuan'] ?? 0);
    $tglEfektif = $_POST['tanggal_efektif'] ?? date('Y-m-d');
    $jenis      = $_POST['jenis_kenaikan'] ?? 'naik';
    $nilai      = trim($_POST['nilai_capaian'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');
    $santriIds  = array_filter(array_map('intval', (array) ($_POST['santri_ids'] ?? [])));

    // Validasi
    if (!$kelasAsal)     $errors[] = 'Pilih kelas asal.';
    if (!$kelasTujuan)   $errors[] = 'Pilih kelas tujuan.';
    if ($kelasAsal === $kelasTujuan) $errors[] = 'Kelas tujuan harus beda dari kelas asal.';
    if (empty($santriIds)) $errors[] = 'Pilih minimal 1 santri.';
    if (!$tglEfektif)    $errors[] = 'Tanggal efektif wajib diisi.';

    if (!$errors) {
        db()->beginTransaction();
        try {
            $userId = currentUser()['id'];
            $created = 0;

            foreach ($santriIds as $sid) {
                // Cek santri valid & di kelas asal
                $s = fetchOne("SELECT id, nama FROM santri WHERE id = ? AND kelas_id = ?", [$sid, $kelasAsal]);
                if (!$s) continue;

                // Tutup riwayat lama
                execute("
                    UPDATE riwayat_kelas
                    SET tanggal_selesai = ?, status = ?
                    WHERE santri_id = ? AND tanggal_selesai IS NULL
                ", [$tglEfektif, $jenis, $sid]);

                // Buat riwayat baru
                insert('riwayat_kelas', [
                    'santri_id'        => $sid,
                    'kelas_id'         => $kelasTujuan,
                    'tanggal_mulai'    => $tglEfektif,
                    'status'           => 'aktif',
                    'jenis_kenaikan'   => $jenis,
                    'nilai_capaian'    => $nilai ?: null,
                    'keterangan'       => $keterangan ?: ('Naik kelas massal dari ' . $kelasAsal),
                    'dipindahkan_oleh' => $userId,
                ]);

                // Update cache
                update('santri', ['kelas_id' => $kelasTujuan], 'id = ?', [$sid]);

                $created++;
            }

            db()->commit();
            setFlash('success', "$created santri berhasil dipindahkan ke kelas baru.");
            redirect('kenaikan');

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}

// Ambil santri per kelas (untuk preview)
$santriPerKelas = [];
if ($preKelas > 0) {
    $santriPerKelas = fetchAll("
        SELECT id, nis, nama, jenis_kelamin 
        FROM santri 
        WHERE kelas_id = ? AND status='aktif'
        ORDER BY nama
    ", [$preKelas]);
}
?>

<div class="container-fluid">

    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/kenaikan" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-users text-primary"></i> Naik Kelas Massal
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Pindahkan banyak santri sekaligus ke kelas baru
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

    <form method="POST" id="formMassal">
        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

        <div class="row">
            <div class="col-lg-7">

                <!-- Konfigurasi -->
                <div class="card shadow mb-3">
                    <div class="card-header py-2 bg-light">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-cog"></i> Konfigurasi
                        </h6>
                    </div>
                    <div class="card-body">

                        <div class="form-row">
                            <div class="form-group">
                                <label>Kelas Asal <span class="text-danger">*</span></label>
                                <select name="kelas_asal" id="selectKelasAsal" class="form-control" required
                                        onchange="loadSantri(this.value)">
                                    <option value="">- Pilih Kelas -</option>
                                    <?php foreach ($kelasList as $k): ?>
                                        <option value="<?= $k['id'] ?>" <?= $preKelas==$k['id']?'selected':'' ?>>
                                            <?= e($k['nama_kelas']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kelas Tujuan <span class="text-danger">*</span></label>
                                <select name="kelas_tujuan" class="form-control" required>
                                    <option value="">- Pilih Kelas -</option>
                                    <?php foreach ($kelasList as $k): ?>
                                        <option value="<?= $k['id'] ?>"><?= e($k['nama_kelas']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Jenis <span class="text-danger">*</span></label>
                                <select name="jenis_kenaikan" class="form-control" required>
                                    <option value="naik">⬆️ Naik Kelas</option>
                                    <option value="pindah">🔄 Pindah Kelas</option>
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
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="2"
                                      placeholder="Contoh: Kenaikan akhir Jilid 1 batch 2025"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Santri yang akan dipindah -->
                <div class="card shadow mb-3">
                    <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-users"></i> Pilih Santri
                        </h6>
                        <div>
                            <button type="button" class="btn btn-sm btn-light" onclick="checkAll(true)">
                                <i class="fas fa-check-square"></i> Semua
                            </button>
                            <button type="button" class="btn btn-sm btn-light" onclick="checkAll(false)">
                                <i class="fas fa-square"></i> Batal
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="listSantri">
                            <?php if ($preKelas && !empty($santriPerKelas)): ?>
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th style="width:40px;">
                                                <input type="checkbox" id="checkAllSantri" onchange="checkAll(this.checked)">
                                            </th>
                                            <th>NIS</th>
                                            <th>Nama</th>
                                            <th class="text-center">JK</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($santriPerKelas as $s): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="santri_ids[]"
                                                       value="<?= $s['id'] ?>" class="santri-checkbox">
                                            </td>
                                            <td><small><?= e($s['nis']) ?></small></td>
                                            <td><strong><?= e($s['nama']) ?></strong></td>
                                            <td class="text-center">
                                                <span class="badge <?= $s['jenis_kelamin']==='L'?'bg-primary':'bg-danger' ?>">
                                                    <?= $s['jenis_kelamin'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-users fa-3x mb-2"></i>
                                    <p>Pilih kelas asal untuk melihat daftar santri</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="col-lg-5">
                <div class="card shadow mb-3 sticky-top" style="top:80px;">
                    <div class="card-header py-2 bg-info text-white">
                        <h6 class="m-0 font-weight-bold">
                            <i class="fas fa-eye"></i> Ringkasan
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="h2 mb-0 font-weight-bold text-primary" id="countSelected">0</div>
                            <div class="small text-muted">santri akan dipindah</div>
                        </div>

                        <table class="table table-sm mb-0">
                            <tr>
                                <td class="text-muted">Dari Kelas</td>
                                <td class="text-right" id="prevAsal">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Ke Kelas</td>
                                <td class="text-right" id="prevTujuan">-</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tanggal</td>
                                <td class="text-right" id="prevTgl">-</td>
                            </tr>
                        </table>

                        <div class="alert alert-warning py-2 mt-3 mb-0">
                            <small>
                                <i class="fas fa-exclamation-triangle"></i>
                                Perubahan tidak bisa dibatalkan lewat UI. Pastikan data benar.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="card shadow">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary btn-block" id="btnSubmit" disabled
                                data-confirm="Yakin proses kenaikan massal?">
                            <i class="fas fa-arrow-up"></i> Proses Massal
                        </button>
                        <a href="<?= BASE_URL ?>/kenaikan" class="btn btn-light btn-block mt-2">
                            <i class="fas fa-times"></i> Batal
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// ============================================
// LOAD SANTRI BY KELAS (AJAX ke page reload partial)
// ============================================
function loadSantri(kelasId) {
    const container = document.getElementById('listSantri');
    if (!kelasId) {
        container.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fas fa-users fa-3x mb-2"></i>
                <p>Pilih kelas asal untuk melihat daftar santri</p>
            </div>`;
        return;
    }

    container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Memuat...</div>';

    fetch('<?= BASE_URL ?>/kenaikan/load_santri?kelas=' + kelasId)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html;
            bindCheckboxes();
        })
        .catch(err => {
            container.innerHTML = '<div class="text-center py-4 text-danger">Gagal memuat data</div>';
        });
}

// ============================================
// CHECK ALL
// ============================================
function checkAll(val) {
    document.querySelectorAll('.santri-checkbox').forEach(cb => {
        cb.checked = val;
    });
    updateCount();
}

function bindCheckboxes() {
    document.querySelectorAll('.santri-checkbox').forEach(cb => {
        cb.addEventListener('change', updateCount);
    });
    const ca = document.getElementById('checkAllSantri');
    if (ca) ca.addEventListener('change', function() { checkAll(this.checked); });
}

// ============================================
// UPDATE COUNT & FOOTER
// ============================================
function updateCount() {
    const n = document.querySelectorAll('.santri-checkbox:checked').length;
    document.getElementById('countSelected').textContent = n;
    document.getElementById('btnSubmit').disabled = n === 0;
}

// ============================================
// UPDATE PREVIEW
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const asal = document.getElementById('selectKelasAsal');
    const tujuan = document.querySelector('select[name="kelas_tujuan"]');
    const tgl = document.querySelector('input[name="tanggal_efektif"]');

    function updatePreview() {
        const asalTxt = asal.selectedIndex > 0 ? asal.options[asal.selectedIndex].text : '-';
        const tujuanTxt = tujuan.selectedIndex > 0 ? tujuan.options[tujuan.selectedIndex].text : '-';
        document.getElementById('prevAsal').textContent = asalTxt.trim();
        document.getElementById('prevTujuan').textContent = tujuanTxt.trim();
        document.getElementById('prevTgl').textContent = tgl.value || '-';
    }

    asal.addEventListener('change', updatePreview);
    tujuan.addEventListener('change', updatePreview);
    tgl.addEventListener('change', updatePreview);

    // Bind awal
    bindCheckboxes();
    updateCount();
    updatePreview();

    // Kalau ada pre-kelas → auto update preview
    if (asal.value) updatePreview();
});
</script>