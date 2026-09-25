<?php
// modules/laporan/export_pdf.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin') && !hasRole('kepala')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$type   = $_GET['type'] ?? 'pembayaran';
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');
$jenisId= (int) ($_GET['jenis'] ?? 0);
$kelasId= (int) ($_GET['kelas'] ?? 0);
$metode = $_GET['metode'] ?? '';

$namaMadin = setting('nama_madin', 'TPQ MADIN');
$alamat    = setting('alamat', '');

// Query berdasarkan type
if ($type === 'pembayaran') {
    $where = "WHERE p.status='diverifikasi' AND p.tanggal_bayar BETWEEN ? AND ?";
    $params = [$dari, $sampai];
    if ($jenisId) { $where .= " AND t.jenis_pembayaran_id = ?"; $params[] = $jenisId; }
    if ($kelasId) { $where .= " AND s.kelas_id = ?"; $params[] = $kelasId; }
    if ($metode)  { $where .= " AND p.metode = ?"; $params[] = $metode; }

    $list = fetchAll("
        SELECT p.tanggal_bayar, s.nis, s.nama AS nama_santri, k.nama_kelas,
               jp.nama AS jenis_nama, t.periode, p.metode, p.nominal_bayar,
               v.name AS verifikator
        FROM pembayaran p
        JOIN tagihan t ON t.id = p.tagihan_id
        JOIN santri s ON s.id = t.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        LEFT JOIN users v ON v.id = p.verified_by
        $where
        ORDER BY p.tanggal_bayar ASC
    ", $params);

    $judul = 'Laporan Pembayaran';
    $periodeText = tanggalIndo($dari) . ' s/d ' . tanggalIndo($sampai);

} else {
    $where = "WHERE t.status='belum_lunas' AND s.status='aktif'";
    $params = [];
    if ($kelasId) { $where .= " AND s.kelas_id = ?"; $params[] = $kelasId; }

    $list = fetchAll("
        SELECT s.nis, s.nama, k.nama_kelas,
               COUNT(t.id) AS jml_tagihan,
               SUM(t.nominal) AS total_tunggakan,
               MIN(t.jatuh_tempo) AS jatuh_tempo
        FROM santri s
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN tagihan t ON t.santri_id = s.id
        $where
        GROUP BY s.id, s.nis, s.nama, k.nama_kelas
        ORDER BY total_tunggakan DESC
    ", $params);

    $judul = 'Laporan Tunggakan';
    $periodeText = 'Per ' . tanggalIndo(date('Y-m-d'));
}

$total = 0;
foreach ($list as $row) {
    $total += (float) ($row['nominal_bayar'] ?? $row['total_tunggakan'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= e($judul) ?> - <?= e($namaMadin) ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            font-size: 12px;
            color: #1f2937;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            border-bottom: 3px double #2c6b9e;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #2c6b9e;
        }
        .header p {
            margin: 3px 0 0;
            font-size: 11px;
            color: #6b7280;
        }
        .info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 14px;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table th {
            background: #2c6b9e;
            color: #fff;
            padding: 7px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }
        table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table tbody tr:nth-child(even) {
            background: #f9fafb;
        }
        table tfoot td {
            background: #f3f4f6;
            font-weight: 700;
            padding: 8px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 600;
        }
        .badge-cash { background: #e5e7eb; color: #374151; }
        .badge-transfer { background: #dbeafe; color: #1e40af; }
        .badge-qris { background: #dbeafe; color: #1d4ed8; }
        .footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }
        .footer .ttd {
            text-align: center;
            width: 200px;
        }
        .footer .ttd .space {
            height: 60px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            table { font-size: 10px; }
        }
        .btn-print {
            position: fixed;
            top: 12px;
            right: 12px;
            padding: 10px 18px;
            background: #2c6b9e;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-print:hover { background: #1e40af; }
    </style>
</head>
<body>

<button class="btn-print no-print" onclick="window.print()">
    🖨️ Cetak / Simpan PDF
</button>

<div class="header">
    <h1><?= e($namaMadin) ?></h1>
    <p><?= e($alamat) ?></p>
    <h3 style="margin: 8px 0 0; font-size: 15px; color: #1f2937;">
        <?= e($judul) ?>
    </h3>
</div>

<div class="info">
    <div>
        <strong>Periode:</strong> <?= $periodeText ?>
    </div>
    <div>
        <strong>Dicetak:</strong> <?= tanggalIndo(date('Y-m-d')) ?> <?= date('H:i') ?>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:35px;">No</th>
            <?php if ($type === 'pembayaran'): ?>
                <th>Tanggal</th>
                <th>NIS</th>
                <th>Santri</th>
                <th>Kelas</th>
                <th>Jenis</th>
                <th>Periode</th>
                <th class="text-center">Metode</th>
                <th class="text-right">Nominal</th>
            <?php else: ?>
                <th>NIS</th>
                <th>Nama Santri</th>
                <th>Kelas</th>
                <th class="text-center">Jml Tagihan</th>
                <th>Jatuh Tempo</th>
                <th class="text-right">Tunggakan</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($list)): ?>
        <tr>
            <td colspan="10" class="text-center" style="padding:30px; color:#9ca3af;">
                Tidak ada data
            </td>
        </tr>
    <?php else: $no=1; foreach ($list as $row): ?>
        <tr>
            <td><?= $no++ ?></td>
            <?php if ($type === 'pembayaran'): ?>
                <td><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
                <td><?= e($row['nis']) ?></td>
                <td><?= e($row['nama_santri']) ?></td>
                <td><?= e($row['nama_kelas'] ?: '-') ?></td>
                <td><?= e($row['jenis_nama']) ?></td>
                <td><?= e($row['periode'] ?: '-') ?></td>
                <td class="text-center">
                    <span class="badge badge-<?= $row['metode'] ?>">
                        <?= strtoupper($row['metode']) ?>
                    </span>
                </td>
                <td class="text-right"><?= number_format($row['nominal_bayar'], 0, ',', '.') ?></td>
            <?php else: ?>
                <td><?= e($row['nis']) ?></td>
                <td><?= e($row['nama']) ?></td>
                <td><?= e($row['nama_kelas'] ?: '-') ?></td>
                <td class="text-center"><?= $row['jml_tagihan'] ?></td>
                <td><?= $row['jatuh_tempo'] ? date('d/m/Y', strtotime($row['jatuh_tempo'])) : '-' ?></td>
                <td class="text-right"><?= number_format($row['total_tunggakan'], 0, ',', '.') ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
    <?php if (!empty($list)): ?>
    <tfoot>
        <tr>
            <td colspan="<?= $type === 'pembayaran' ? 8 : 6 ?>" class="text-right">TOTAL</td>
            <td class="text-right">Rp <?= number_format($total, 0, ',', '.') ?></td>
        </tr>
    </tfoot>
    <?php endif; ?>
</table>

<div class="footer">
    <div></div>
    <div class="ttd">
        <div><?= e(setting('nama_madin', 'TPQ MADIN')) ?></div>
        <div style="margin-top:4px;">Kepala Madin</div>
        <div class="space"></div>
        <div style="border-top:1px solid #1f2937; padding-top:4px;">
            <?php if ($namaKepala = setting('nama_kepala')): ?>
                <strong><?= e($namaKepala) ?></strong>
                <?php if ($nipKepala = setting('nip_kepala')): ?>
                    <br><small>NIP: <?= e($nipKepala) ?></small>
                <?php endif; ?>
            <?php else: ?>
                (....................................)
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>