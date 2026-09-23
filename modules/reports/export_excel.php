<?php
// modules/reports/export_excel.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin/staff
if (!isAdmin() && !isStaff()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// ============================================
// LOAD PHPSPREADSHEET VIA COMPOSER
// ============================================
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Ambil data
$items = fetchAll("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.created_at DESC");
$loans = fetchAll(
    "SELECT l.*, b.name as borrower_name, b.institution 
     FROM loans l 
     LEFT JOIN borrowers b ON l.borrower_id = b.id 
     ORDER BY l.created_at DESC"
);

$spreadsheet = new Spreadsheet();

// ============================================
// SHEET 1: DATA BARANG
// ============================================
$sheet1 = $spreadsheet->getActiveSheet();
$sheet1->setTitle('Data Barang');

// Header
$headers1 = ['Kode', 'Nama Barang', 'Kategori', 'Brand', 'Stok', 'Min Stok', 'Kondisi', 'Status', 'Lokasi'];
$col = 'A';
foreach ($headers1 as $header) {
    $sheet1->setCellValue($col . '1', $header);
    $sheet1->getColumnDimension($col)->setWidth(15);
    $col++;
}

// Style header
$sheet1->getStyle('A1:I1')->getFont()->setBold(true);
$sheet1->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2E8F0');

// Data
$row = 2;
foreach ($items as $item) {
    $sheet1->setCellValue('A' . $row, $item['code']);
    $sheet1->setCellValue('B' . $row, $item['name']);
    $sheet1->setCellValue('C' . $row, $item['category_name'] ?? '-');
    $sheet1->setCellValue('D' . $row, $item['brand'] ?? '-');
    $sheet1->setCellValue('E' . $row, $item['quantity']);
    $sheet1->setCellValue('F' . $row, $item['min_quantity']);
    $sheet1->setCellValue('G' . $row, ucfirst($item['condition']));
    $sheet1->setCellValue('H' . $row, ucfirst($item['status']));
    $sheet1->setCellValue('I' . $row, $item['location'] ?? '-');
    $row++;
}

// ============================================
// SHEET 2: DATA PEMINJAMAN
// ============================================
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('Data Peminjaman');

// Header
$headers2 = ['Kode', 'Peminjam', 'Instansi', 'Tanggal Pinjam', 'Tanggal Kembali', 'Status', 'Total Item'];
$col = 'A';
foreach ($headers2 as $header) {
    $sheet2->setCellValue($col . '1', $header);
    $sheet2->getColumnDimension($col)->setWidth(18);
    $col++;
}

// Style header
$sheet2->getStyle('A1:G1')->getFont()->setBold(true);
$sheet2->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E2E8F0');

// Data
$row = 2;
foreach ($loans as $loan) {
    $sheet2->setCellValue('A' . $row, $loan['code']);
    $sheet2->setCellValue('B' . $row, $loan['borrower_name'] ?? '-');
    $sheet2->setCellValue('C' . $row, $loan['institution'] ?? '-');
    $sheet2->setCellValue('D' . $row, formatDate($loan['loan_date']));
    $sheet2->setCellValue('E' . $row, formatDate($loan['expected_return_date']));
    $sheet2->setCellValue('F' . $row, ucfirst($loan['status']));
    $sheet2->setCellValue('G' . $row, $loan['total_items']);
    $row++;
}

// ============================================
// SHEET 3: STATISTIK
// ============================================
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('Statistik');

// Header
$sheet3->setCellValue('A1', 'STATISTIK INVENTARIS');
$sheet3->mergeCells('A1:B1');
$sheet3->getStyle('A1:B1')->getFont()->setBold(true)->setSize(14);

$stats = [
    ['Total Barang', count($items)],
    ['Total Peminjaman', count($loans)],
    ['Total Kategori', fetchColumn("SELECT COUNT(*) FROM categories")],
    ['Total Peminjam', fetchColumn("SELECT COUNT(*) FROM borrowers WHERE is_active = 1")],
    ['Barang Tersedia', fetchColumn("SELECT COUNT(*) FROM items WHERE status = 'tersedia'")],
    ['Barang Dipinjam', fetchColumn("SELECT COUNT(*) FROM items WHERE status = 'dipinjam'")],
    ['Barang Rusak', fetchColumn("SELECT COUNT(*) FROM items WHERE `condition` = 'rusak'")],
    ['Peminjaman Aktif', fetchColumn("SELECT COUNT(*) FROM loans WHERE status IN ('dipinjam', 'terlambat')")],
    ['Peminjaman Selesai', fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'dikembalikan'")],
    ['Terlambat', fetchColumn("SELECT COUNT(*) FROM loans WHERE status = 'terlambat'")],
];

$row = 3;
foreach ($stats as $stat) {
    $sheet3->setCellValue('A' . $row, $stat[0]);
    $sheet3->setCellValue('B' . $row, $stat[1]);
    $sheet3->getStyle('A' . $row)->getFont()->setBold(true);
    $row++;
}

// ============================================
// OUTPUT FILE
// ============================================
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="laporan_inventaris_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;