<?php
// modules/kelas/anggota.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$kelas = fetchOne("SELECT * FROM kelas WHERE id = ?", [$id]);
if (!$kelas) {
    setFlash('error', 'Kelas tidak ditemukan.');
    redirect('kelas');
}

// ============================================
// PROSES SIMPAN
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $tambahIds = $_POST['tambah'] ?? [];
    $keluarIds = $_POST['keluar'] ?? [];

    $tambahIds = array_filter(array_map('intval', (array) $tambahIds));
    $keluarIds = array_filter(array_map('intval', (array) $keluarIds));

    if (empty($tambahIds) && empty($keluarIds)) {
        setFlash('error', 'Tidak ada perubahan.');
        redirect("kelas/anggota/$id");
    }

    db()->beginTransaction();
    try {
        $userId = currentUser()['id'];
        $today  = date('Y-m-d');

        // 1. TAMBAH
        foreach ($tambahIds as $santriId) {
            execute("
                UPDATE riwayat_kelas
                SET tanggal_selesai = ?, status = 'pindah'
                WHERE santri_id = ? AND tanggal_selesai IS NULL
            ", [$today, $santriId]);

            insert('riwayat_kelas', [
                'santri_id'        => $santriId,
                'kelas_id'         => $id,
                'tanggal_mulai'    => $today,
                'status'           => 'aktif',
                'jenis_kenaikan'   => 'pindah',
                'keterangan'       => 'Ditambahkan via kelola anggota',
                'dipindahkan_oleh' => $userId,
            ]);

            update('santri', ['kelas_id' => $id], 'id = ?', [$santriId]);
        }

        // 2. KELUARKAN
        foreach ($keluarIds as $santriId) {
            execute("
                UPDATE riwayat_kelas
                SET tanggal_selesai = ?, status = 'pindah'
                WHERE santri_id = ? AND kelas_id = ? AND tanggal_selesai IS NULL
            ", [$today, $santriId, $id]);

            update('santri', ['kelas_id' => null], 'id = ?', [$santriId]);
        }

        db()->commit();

        $msg = [];
        if ($tambahIds) $msg[] = count($tambahIds) . ' ditambahkan';
        if ($keluarIds) $msg[] = count($keluarIds) . ' dikeluarkan';

        setFlash('success', 'Berhasil: ' . implode(', ', $msg) . '.');
        redirect("kelas/detail/$id");

    } catch (Exception $e) {
        db()->rollBack();
        setFlash('error', 'Gagal: ' . $e->getMessage());
        redirect("kelas/anggota/$id");
    }
}

// ============================================
// DATA
// ============================================

// A. Anggota saat ini
$anggotaList = fetchAll("
    SELECT s.id, s.nis, s.nama, s.jenis_kelamin, s.foto,
           rk.tanggal_mulai
    FROM santri s
    LEFT JOIN riwayat_kelas rk ON rk.santri_id = s.id AND rk.tanggal_selesai IS NULL
    WHERE s.kelas_id = ? AND s.status = 'aktif'
    ORDER BY s.nama
", [$id]);

// B. Santri tersedia
$tersediaList = fetchAll("
    SELECT s.id, s.nis, s.nama, s.jenis_kelamin, s.foto,
           k.nama_kelas AS kelas_sekarang
    FROM santri s
    LEFT JOIN kelas k ON k.id = s.kelas_id
    WHERE s.status = 'aktif'
      AND (s.kelas_id IS NULL OR s.kelas_id != ?)
    ORDER BY 
      CASE WHEN s.kelas_id IS NULL THEN 0 ELSE 1 END,
      s.nama
", [$id]);
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/kelas/detail/<?= $id ?>" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-users-cog text-success"></i>
                Kelola Anggota: <?= e($kelas['nama_kelas']) ?>
            </h1>
            <p class="text-muted mb-0" style="font-size: 13px;">
                Centang santri → klik tombol pindah → <strong>preview perubahan</strong> muncul di bawah
            </p>
        </div>
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

    <form method="POST" id="formAnggota">
        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

        <!-- Container hidden inputs -->
        <div id="tambahContainer"></div>
        <div id="keluarContainer"></div>

        <div class="row">

            <!-- ============================================
                 KIRI: ANGGOTA SAAT INI
                 ============================================ -->
            <div class="col-md-6 mb-3">
                <div class="card shadow h-100">
                    <div class="card-header bg-primary text-white py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-users"></i>
                                Anggota Saat Ini
                                <span class="badge bg-light text-dark" id="countKiri">
                                    <?= count($anggotaList) ?>
                                </span>
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-light"
                                        onclick="checkAll('kiri', true)">
                                    <i class="fas fa-check-square"></i> Semua
                                </button>
                                <button type="button" class="btn btn-sm btn-light"
                                        onclick="checkAll('kiri', false)">
                                    <i class="fas fa-square"></i> Batal
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-2 bg-light border-bottom text-center">
                        <button type="button" class="btn btn-sm btn-warning" onclick="markKeluar()">
                            <i class="fas fa-arrow-right"></i> Tandai Akan Dikeluarkan
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="unmarkKeluar()">
                            <i class="fas fa-undo"></i> Batalkan Tanda
                        </button>
                    </div>

                    <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                        <div id="listKiri">
                            <?php if (empty($anggotaList)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p class="mb-0">Belum ada anggota</p>
                                </div>
                            <?php else: foreach ($anggotaList as $a): ?>
                                <label class="list-item d-flex align-items-center p-2 border-bottom"
                                       data-id="<?= $a['id'] ?>" data-side="kiri" data-nama="<?= e($a['nama']) ?>">
                                    <input type="checkbox" class="item-checkbox-kiri mr-2"
                                           value="<?= $a['id'] ?>">
                                    <div class="flex-grow-1">
                                        <div style="font-weight:600; font-size:13px;"><?= e($a['nama']) ?></div>
                                        <div class="small text-muted">NIS: <?= e($a['nis']) ?></div>
                                    </div>
                                    <span class="badge <?= $a['jenis_kelamin']==='L'?'bg-primary':'bg-danger' ?> mr-2">
                                        <?= $a['jenis_kelamin'] ?>
                                    </span>
                                    <span class="status-badge"></span>
                                </label>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 KANAN: SANTRI TERSEDIA
                 ============================================ -->
            <div class="col-md-6 mb-3">
                <div class="card shadow h-100">
                    <div class="card-header bg-success text-white py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-user-plus"></i>
                                Santri Tersedia
                                <span class="badge bg-light text-dark" id="countKanan">
                                    <?= count($tersediaList) ?>
                                </span>
                            </h6>
                            <div>
                                <button type="button" class="btn btn-sm btn-light"
                                        onclick="checkAll('kanan', true)">
                                    <i class="fas fa-check-square"></i> Semua
                                </button>
                                <button type="button" class="btn btn-sm btn-light"
                                        onclick="checkAll('kanan', false)">
                                    <i class="fas fa-square"></i> Batal
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-2 bg-light border-bottom text-center">
                        <button type="button" class="btn btn-sm btn-success" onclick="markTambah()">
                            <i class="fas fa-arrow-left"></i> Tandai Akan Ditambahkan
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="unmarkTambah()">
                            <i class="fas fa-undo"></i> Batalkan Tanda
                        </button>
                    </div>

                    <div class="card-body p-0" style="max-height: 480px; overflow-y: auto;">
                        <div id="listKanan">
                            <?php if (empty($tersediaList)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                                    <p class="mb-0">Semua santri sudah di kelas ini</p>
                                </div>
                            <?php else: foreach ($tersediaList as $t): ?>
                                <label class="list-item d-flex align-items-center p-2 border-bottom"
                                       data-id="<?= $t['id'] ?>" data-side="kanan" data-nama="<?= e($t['nama']) ?>">
                                    <input type="checkbox" class="item-checkbox-kanan mr-2"
                                           value="<?= $t['id'] ?>">
                                    <div class="flex-grow-1">
                                        <div style="font-weight:600; font-size:13px;"><?= e($t['nama']) ?></div>
                                        <div class="small text-muted">
                                            NIS: <?= e($t['nis']) ?>
                                            <?php if ($t['kelas_sekarang']): ?>
                                                · <span class="text-warning">
                                                    <i class="fas fa-exchange-alt" style="font-size:10px;"></i>
                                                    Dari: <?= e($t['kelas_sekarang']) ?>
                                                </span>
                                            <?php else: ?>
                                                · <span class="text-muted">Belum ada kelas</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <span class="badge <?= $t['jenis_kelamin']==='L'?'bg-primary':'bg-danger' ?> mr-2">
                                        <?= $t['jenis_kelamin'] ?>
                                    </span>
                                    <span class="status-badge"></span>
                                </label>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================
             PREVIEW PERUBAHAN
             ============================================ -->
        <div class="card shadow mb-3 border-warning" id="previewCard" style="display:none;">
            <div class="card-header bg-warning text-dark py-2">
                <h6 class="m-0 font-weight-bold">
                    <i class="fas fa-eye"></i>
                    Preview Perubahan (belum tersimpan)
                </h6>
            </div>
            <div class="card-body">

                <!-- AKAN DITAMBAHKAN -->
                <div id="previewTambah" style="display:none;">
                    <h6 class="text-success mb-2" style="font-size:13px;">
                        <i class="fas fa-plus-circle"></i>
                        Akan DITAMBAHKAN ke kelas ini
                        <span class="badge bg-success" id="badgeTambah">0</span>
                    </h6>
                    <div id="listPreviewTambah" class="mb-3"></div>
                </div>

                <!-- AKAN DIKELUARKAN -->
                <div id="previewKeluar" style="display:none;">
                    <h6 class="text-danger mb-2" style="font-size:13px;">
                        <i class="fas fa-minus-circle"></i>
                        Akan DIKELUARKAN dari kelas ini
                        <span class="badge bg-danger" id="badgeKeluar">0</span>
                    </h6>
                    <div id="listPreviewKeluar"></div>
                </div>

                <div id="previewEmpty" class="text-center text-muted py-3">
                    <i class="fas fa-info-circle"></i>
                    Belum ada perubahan. Centang santri lalu klik tombol "Tandai".
                </div>
            </div>
        </div>

        <!-- ============================================
             FOOTER AKSI
             ============================================ -->
        <div class="card shadow mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <span class="mr-3 text-muted">
                        <i class="fas fa-info-circle"></i>
                        Perubahan tercatat di riwayat kelas
                    </span>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="<?= BASE_URL ?>/kelas/detail/<?= $id ?>" class="btn btn-light">
                        <i class="fas fa-times"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-primary" id="btnSimpan" disabled>
                        <i class="fas fa-save"></i> Simpan Perubahan
                    </button>
                </div>
            </div>
        </div>
    </form>

</div>

<style>
.list-item {
    transition: background 0.15s;
    cursor: pointer;
}
.list-item:hover {
    background: #f8fafc;
}
.list-item.selected {
    background: #fef3c7;
}
/* Akan DITAMBAHKAN */
.list-item.will-add {
    background: #d1fae5 !important;
    border-left: 4px solid #16a34a;
}
.list-item.will-add .status-badge::before {
    content: '✓ AKAN DITAMBAH';
    background: #16a34a;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 700;
}
/* Akan DIKELUARKAN */
.list-item.will-remove {
    background: #fee2e2 !important;
    border-left: 4px solid #dc2626;
    opacity: 0.85;
}
.list-item.will-remove .status-badge::before {
    content: '✗ AKAN DIKELUAR';
    background: #dc2626;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 700;
}
.list-item.will-remove div[style*="font-weight:600"] {
    text-decoration: line-through;
    color: #991b1b;
}

.preview-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin: 3px;
}
.preview-item.add {
    background: #d1fae5;
    color: #166534;
    border: 1px solid #16a34a;
}
.preview-item.remove {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #dc2626;
}
</style>

<script>
// ============================================
// STATE
// ============================================
let tambahList = new Set();
let keluarList = new Set();

// ============================================
// CHECK ALL
// ============================================
function checkAll(side, val) {
    const cls = side === 'kiri' ? '.item-checkbox-kiri' : '.item-checkbox-kanan';
    document.querySelectorAll(cls).forEach(cb => {
        cb.checked = val;
        cb.closest('.list-item').classList.toggle('selected', val);
    });
}

document.addEventListener('change', function(e) {
    if (e.target.matches('.item-checkbox-kiri, .item-checkbox-kanan')) {
        e.target.closest('.list-item').classList.toggle('selected', e.target.checked);
    }
});

// ============================================
// MARK TAMBAH (Kanan → Kiri)
// ============================================
function markTambah() {
    const checked = document.querySelectorAll('.item-checkbox-kanan:checked');
    if (checked.length === 0) {
        alert('Pilih dulu santri yang akan ditambahkan.');
        return;
    }

    checked.forEach(cb => {
        const id   = parseInt(cb.value);
        const item = cb.closest('.list-item');

        // Set state
        tambahList.add(id);
        keluarList.delete(id);

        // Visual: tandai
        item.classList.add('will-add');
        item.classList.remove('will-remove');

        // Uncheck
        cb.checked = false;
        item.classList.remove('selected');
    });

    syncHidden();
    updatePreview();
}

// ============================================
// UNMARK TAMBAH
// ============================================
function unmarkTambah() {
    const checked = document.querySelectorAll('.item-checkbox-kanan:checked');
    if (checked.length === 0) return;

    checked.forEach(cb => {
        const id   = parseInt(cb.value);
        const item = cb.closest('.list-item');

        tambahList.delete(id);
        item.classList.remove('will-add');

        cb.checked = false;
        item.classList.remove('selected');
    });

    syncHidden();
    updatePreview();
}

// ============================================
// MARK KELUAR (Kiri → Kanan)
// ============================================
function markKeluar() {
    const checked = document.querySelectorAll('.item-checkbox-kiri:checked');
    if (checked.length === 0) {
        alert('Pilih dulu anggota yang akan dikeluarkan.');
        return;
    }

    checked.forEach(cb => {
        const id   = parseInt(cb.value);
        const item = cb.closest('.list-item');

        keluarList.add(id);
        tambahList.delete(id);

        item.classList.add('will-remove');
        item.classList.remove('will-add');

        cb.checked = false;
        item.classList.remove('selected');
    });

    syncHidden();
    updatePreview();
}

// ============================================
// UNMARK KELUAR
// ============================================
function unmarkKeluar() {
    const checked = document.querySelectorAll('.item-checkbox-kiri:checked');
    if (checked.length === 0) return;

    checked.forEach(cb => {
        const id   = parseInt(cb.value);
        const item = cb.closest('.list-item');

        keluarList.delete(id);
        item.classList.remove('will-remove');

        cb.checked = false;
        item.classList.remove('selected');
    });

    syncHidden();
    updatePreview();
}

// ============================================
// SYNC HIDDEN INPUT
// ============================================
function syncHidden() {
    const tc = document.getElementById('tambahContainer');
    const kc = document.getElementById('keluarContainer');

    tc.innerHTML = '';
    kc.innerHTML = '';

    tambahList.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'tambah[]';
        input.value = id;
        tc.appendChild(input);
    });

    keluarList.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'keluar[]';
        input.value = id;
        kc.appendChild(input);
    });
}

// ============================================
// UPDATE PREVIEW
// ============================================
function updatePreview() {
    const card = document.getElementById('previewCard');
    const pTambah = document.getElementById('previewTambah');
    const pKeluar = document.getElementById('previewKeluar');
    const pEmpty  = document.getElementById('previewEmpty');
    const listT   = document.getElementById('listPreviewTambah');
    const listK   = document.getElementById('listPreviewKeluar');
    const btn     = document.getElementById('btnSimpan');

    const total = tambahList.size + keluarList.size;

    // Update tombol
    btn.disabled = total === 0;

    // Update footer badge
    if (total === 0) {
        card.style.display = 'none';
        return;
    }

    card.style.display = 'block';
    pEmpty.style.display = 'none';

    // ================================
    // Preview: AKAN DITAMBAHKAN
    // ================================
    if (tambahList.size > 0) {
        pTambah.style.display = 'block';
        document.getElementById('badgeTambah').textContent = tambahList.size;

        listT.innerHTML = '';
        tambahList.forEach(id => {
            const item = document.querySelector(`.item-checkbox-kanan[value="${id}"]`)?.closest('.list-item');
            const nama = item?.dataset.nama || ('ID ' + id);
            listT.innerHTML += `<span class="preview-item add">➕ ${nama}</span>`;
        });
    } else {
        pTambah.style.display = 'none';
    }

    // ================================
    // Preview: AKAN DIKELUARKAN
    // ================================
    if (keluarList.size > 0) {
        pKeluar.style.display = 'block';
        document.getElementById('badgeKeluar').textContent = keluarList.size;

        listK.innerHTML = '';
        keluarList.forEach(id => {
            const item = document.querySelector(`.item-checkbox-kiri[value="${id}"]`)?.closest('.list-item');
            const nama = item?.dataset.nama || ('ID ' + id);
            listK.innerHTML += `<span class="preview-item remove">➖ ${nama}</span>`;
        });
    } else {
        pKeluar.style.display = 'none';
    }
}

// ============================================
// KONFIRMASI SEBELUM SUBMIT
// ============================================
document.getElementById('formAnggota').addEventListener('submit', function(e) {
    const total = tambahList.size + keluarList.size;
    if (total === 0) {
        e.preventDefault();
        return;
    }

    let msg = 'Yakin simpan perubahan?\n\n';
    if (tambahList.size) msg += `✅ Tambah ${tambahList.size} santri ke "${<?= json_encode($kelas['nama_kelas']) ?>}"\n`;
    if (keluarList.size) msg += `❌ Keluarkan ${keluarList.size} santri dari "${<?= json_encode($kelas['nama_kelas']) ?>}"\n`;
    msg += '\nSemua perubahan dicatat di riwayat kelas.';

    if (!confirm(msg)) e.preventDefault();
});
</script>