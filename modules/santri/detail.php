<?php
// modules/santri/detail.php
require_once __DIR__ . '/../../config/functions.php';

$id = (int) ($id ?? 0);
$santri = fetchOne("SELECT s.*, k.nama_kelas FROM santri s
                    LEFT JOIN kelas k ON k.id = s.kelas_id
                    WHERE s.id = ?", [$id]);
if (!$santri) { setFlash('error','Santri tidak ditemukan.'); redirect('santri'); }

// Wali/orang tua
$waliList = fetchAll("
    SELECT ot.*, ws.is_primary
    FROM wali_santri ws
    JOIN orang_tua ot ON ot.id = ws.orang_tua_id
    WHERE ws.santri_id = ?
    ORDER BY ws.is_primary DESC, ot.tipe
", [$id]);

// Riwayat kelas
$riwayat = fetchAll("
    SELECT rk.*, k.nama_kelas
    FROM riwayat_kelas rk
    JOIN kelas k ON k.id = rk.kelas_id
    WHERE rk.santri_id = ?
    ORDER BY rk.tanggal_mulai DESC
", [$id]);

// Tagihan
$tagihan = fetchAll("
    SELECT t.*, jp.nama AS jenis
    FROM tagihan t
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    WHERE t.santri_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
", [$id]);
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3 flex-wrap">
        <a href="<?= BASE_URL ?>/santri" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-graduate text-primary"></i>
                <?= e($santri['nama']) ?>
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                NIS: <?= e($santri['nis']) ?> ·
                Kelas: <?= e($santri['nama_kelas'] ?? 'Belum ada') ?>
            </p>
        </div>
        <?php if (hasRole('admin')): ?>
        <div class="mt-2 mt-md-0">
            <a href="<?= BASE_URL ?>/santri/edit/<?= $santri['id'] ?>"
               class="btn btn-sm btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?= BASE_URL ?>/kenaikan/create/<?= $santri['id'] ?>"
               class="btn btn-sm btn-primary">
                <i class="fas fa-arrow-up"></i> Naik Kelas
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">

        <!-- Kiri: Biodata -->
        <div class="col-lg-5 mb-3">

            <!-- Profil Card -->
            <div class="card shadow mb-3">
                <div class="card-body text-center">
                    <?php if ($santri['foto'] && file_exists(__DIR__ . '/../../' . $santri['foto'])): ?>
                        <img src="<?= BASE_URL . '/' . e($santri['foto']) ?>"
                             style="width:100px;height:100px;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <div style="width:100px;height:100px;border-radius:50%;
                                    background:#e8f0fe;color:#2c6b9e;margin:0 auto;
                                    display:flex;align-items:center;justify-content:center;
                                    font-weight:700;font-size:36px;">
                            <?= e(strtoupper(substr($santri['nama'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>
                    <h5 class="mt-3 mb-1"><?= e($santri['nama']) ?></h5>
                    <p class="text-muted mb-2"><?= e($santri['nama_panggilan'] ?: '-') ?></p>
                    <?php
                    $sc = ['aktif'=>'bg-success','lulus'=>'bg-primary','keluar'=>'bg-secondary','cuti'=>'bg-warning text-dark'];
                    ?>
                    <span class="badge <?= $sc[$santri['status']] ?? 'bg-secondary' ?>">
                        <?= ucfirst($santri['status']) ?>
                    </span>
                </div>
            </div>

            <!-- Biodata -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-id-card"></i> Biodata
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" width="40%">NIS</td><td><?= e($santri['nis']) ?></td></tr>
                        <tr><td class="text-muted">NISN</td><td><?= e($santri['nisn'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">NIK</td><td><?= e($santri['nik'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">No. KK</td><td><?= e($santri['no_kk'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">No. Akta</td><td><?= e($santri['no_akta'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">JK</td><td><?= $santri['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?></td></tr>
                        <tr><td class="text-muted">TTL</td>
                            <td><?= e($santri['tempat_lahir'] ?: '-') ?>, <?= tanggalIndo($santri['tanggal_lahir']) ?></td></tr>
                        <tr><td class="text-muted">Anak ke-</td><td><?= e($santri['anak_ke'] ?: '-') ?> dari <?= e($santri['jumlah_saudara'] ?: '-') ?> saudara</td></tr>
                        <tr><td class="text-muted">Gol. Darah</td><td><?= e($santri['golongan_darah']) ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Kontak & Alamat -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-map-marker-alt"></i> Kontak & Alamat
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" width="40%">No. HP</td><td><?= e($santri['no_hp'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Alamat</td><td><?= e($santri['alamat'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">RT/RW</td><td><?= e($santri['rt_rw'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Kelurahan</td><td><?= e($santri['kelurahan'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Kecamatan</td><td><?= e($santri['kecamatan'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Kabupaten</td><td><?= e($santri['kabupaten'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Provinsi</td><td><?= e($santri['provinsi'] ?: '-') ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- Sekolah Formal -->
            <div class="card shadow">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-school"></i> Sekolah Formal
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr><td class="text-muted" width="40%">Sekolah</td><td><?= e($santri['sekolah_formal'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Kelas</td><td><?= e($santri['kelas_formal'] ?: '-') ?></td></tr>
                        <tr><td class="text-muted">Masuk TPQ</td><td><?= tanggalIndo($santri['tanggal_masuk']) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Kanan: Wali, Riwayat, Tagihan -->
        <div class="col-lg-7 mb-3">

            <!-- Orang Tua / Wali -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-users"></i> Orang Tua / Wali (<?= count($waliList) ?>)
                    </h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($waliList)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle"></i> Belum ada data orang tua terhubung
                        </div>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Tipe</th>
                                    <th>Nama</th>
                                    <th>No. HP</th>
                                    <th>Pekerjaan</th>
                                    <th class="text-center">Utama</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($waliList as $w): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= $w['tipe']==='ayah'?'bg-primary':($w['tipe']==='ibu'?'bg-danger':'bg-secondary') ?>">
                                            <?= ucfirst($w['tipe']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/orang_tua/detail/<?= $w['id'] ?>"
                                           class="text-decoration-none font-weight-bold">
                                            <?= e($w['nama_lengkap']) ?>
                                        </a>
                                    </td>
                                    <td><?= e($w['no_hp'] ?: '-') ?></td>
                                    <td><small><?= e($w['pekerjaan'] ?: '-') ?></small></td>
                                    <td class="text-center">
                                        <?php if ($w['is_primary']): ?>
                                            <i class="fas fa-star text-warning"></i>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Riwayat Kelas -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-book-reader"></i> Riwayat Belajar
                    </h6>
                    <?php if (hasRole('admin')): ?>
                        <a href="<?= BASE_URL ?>/kenaikan/create/<?= $santri['id'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Naik Kelas
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($riwayat)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle"></i> Belum ada riwayat kelas
                        </div>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Kelas</th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                    <th>Nilai</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($riwayat as $r):
                                $sc2 = ['aktif'=>'bg-success','naik'=>'bg-primary','tinggal'=>'bg-warning text-dark',
                                        'pindah'=>'bg-info text-dark','lulus'=>'bg-success','keluar'=>'bg-secondary'];
                            ?>
                                <tr>
                                    <td><strong><?= e($r['nama_kelas']) ?></strong></td>
                                    <td><small><?= tanggalIndo($r['tanggal_mulai']) ?></small></td>
                                    <td><small><?= $r['tanggal_selesai'] ? tanggalIndo($r['tanggal_selesai']) : '<span class="text-success">Sekarang</span>' ?></small></td>
                                    <td>
                                        <?php if ($r['nilai_capaian']): ?>
                                            <span class="badge bg-primary"><?= e($r['nilai_capaian']) ?></span>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $sc2[$r['status']] ?? 'bg-secondary' ?>">
                                            <?= ucfirst($r['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tagihan -->
            <div class="card shadow">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-invoice-dollar"></i> Tagihan Terbaru
                    </h6>
                    <a href="<?= BASE_URL ?>/tagihan?santri=<?= $santri['id'] ?>"
                       class="small text-primary">Lihat semua →</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($tagihan)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle"></i> Belum ada tagihan
                        </div>
                    <?php else: ?>
                        <table class="table table-sm mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Jenis</th>
                                    <th>Periode</th>
                                    <th class="text-right">Nominal</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($tagihan as $t):
                                $tc = ['lunas'=>'bg-success','menunggu_verifikasi'=>'bg-warning text-dark',
                                       'belum_lunas'=>'bg-danger','ditolak'=>'bg-secondary'];
                            ?>
                                <tr>
                                    <td><?= e($t['jenis']) ?></td>
                                    <td><small><?= e($t['periode'] ?: '-') ?></small></td>
                                    <td class="text-right"><strong><?= rupiah($t['nominal']) ?></strong></td>
                                    <td class="text-center">
                                        <span class="badge <?= $tc[$t['status']] ?? 'bg-secondary' ?>">
                                            <?= ucfirst(str_replace('_',' ',$t['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>