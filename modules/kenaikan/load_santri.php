<?php
// modules/kenaikan/load_santri.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$kelasId = (int) ($_GET['kelas'] ?? 0);
if (!$kelasId) { exit; }

$santriList = fetchAll("
    SELECT id, nis, nama, jenis_kelamin 
    FROM santri 
    WHERE kelas_id = ? AND status='aktif'
    ORDER BY nama
", [$kelasId]);

if (empty($santriList)) {
    echo '<div class="text-center py-5 text-muted">
            <i class="fas fa-info-circle fa-2x mb-2"></i>
            <p>Tidak ada santri aktif di kelas ini</p>
          </div>';
    exit;
}
?>

<table class="table table-sm table-hover mb-0">
    <thead class="bg-light">
        <tr>
            <th style="width:40px;">
                <input type="checkbox" id="checkAllSantri">
            </th>
            <th>NIS</th>
            <th>Nama</th>
            <th class="text-center">JK</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($santriList as $s): ?>
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