<?php
// modules/loans/create.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin/staff
if (!isAdmin() && !isStaff()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// Ambil data peminjam
$borrowers = fetchAll("SELECT id, name, code, institution FROM borrowers WHERE is_active = 1 ORDER BY name");

// Ambil data barang untuk dipinjam
$items = fetchAll("SELECT id, code, name, quantity, `condition`, `status` FROM items WHERE status = 'tersedia' AND quantity > 0 ORDER BY name");

// Keranjang
$session_id = session_id();
$cart_items = fetchAll(
    "SELECT t.*, i.code, i.name, i.photo, i.quantity as stock,
            c.name as category_name
     FROM temp_loans t
     LEFT JOIN items i ON t.item_id = i.id
     LEFT JOIN categories c ON i.category_id = c.id
     WHERE t.session_id = ?",
    [$session_id]
);

$cart_total = array_sum(array_column($cart_items, 'quantity'));

// Flash messages
$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Proses tambah ke keranjang
if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_GET['item_id'])) {
    $item_id = (int)$_GET['item_id'];
    $quantity = (int)($_GET['quantity'] ?? 1);
    
    $item = fetchOne("SELECT quantity, name FROM items WHERE id = ?", [$item_id]);
    if ($item && $item['quantity'] >= $quantity) {
        $existing = fetchOne(
            "SELECT id, quantity FROM temp_loans WHERE session_id = ? AND item_id = ?",
            [$session_id, $item_id]
        );
        
        if ($existing) {
            $new_qty = $existing['quantity'] + $quantity;
            if ($new_qty <= $item['quantity']) {
                update('temp_loans', ['quantity' => $new_qty], 'id = ?', [$existing['id']]);
                $_SESSION['success'] = 'Jumlah barang diperbarui!';
            } else {
                $_SESSION['error'] = 'Stok tidak mencukupi! Tersisa: ' . $item['quantity'];
            }
        } else {
            insert('temp_loans', [
                'session_id' => $session_id,
                'item_id' => $item_id,
                'quantity' => $quantity,
                'condition_before' => 'baik'
            ]);
            $_SESSION['success'] = 'Barang ditambahkan ke keranjang!';
        }
    } else {
        $_SESSION['error'] = 'Stok tidak mencukupi!';
    }
    redirect('index.php?url=loans/create');
}

// Hapus dari keranjang
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['temp_id'])) {
    delete('temp_loans', 'id = ? AND session_id = ?', [$_GET['temp_id'], $session_id]);
    $_SESSION['success'] = 'Barang dihapus dari keranjang!';
    redirect('index.php?url=loans/create');
}

// Kosongkan keranjang
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    delete('temp_loans', 'session_id = ?', [$session_id]);
    $_SESSION['success'] = 'Keranjang dikosongkan!';
    redirect('index.php?url=loans/create');
}

// Proses peminjaman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process'])) {
    $borrower_id = (int)$_POST['borrower_id'];
    $loan_date = $_POST['loan_date'] ?? date('Y-m-d');
    $expected_return_date = $_POST['expected_return_date'] ?? date('Y-m-d', strtotime('+7 days'));
    $notes = trim($_POST['notes'] ?? '');
    
    if ($borrower_id <= 0) {
        $_SESSION['error'] = 'Pilih peminjam terlebih dahulu!';
        redirect('index.php?url=loans/create');
    }
    
    $cart = fetchAll("SELECT * FROM temp_loans WHERE session_id = ?", [$session_id]);
    if (empty($cart)) {
        $_SESSION['error'] = 'Keranjang kosong!';
        redirect('index.php?url=loans/create');
    }
    
    // ============================================
    // PROSES TRANSAKSI
    // ============================================
    $error_message = null;
    
    try {
        // Mulai transaksi
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        
        $loan_code = generateLoanCode();
        $total_items = array_sum(array_column($cart, 'quantity'));
        
        // Insert ke loans
        $loan_id = insert('loans', [
            'code' => $loan_code,
            'borrower_id' => $borrower_id,
            'loan_date' => $loan_date,
            'expected_return_date' => $expected_return_date,
            'total_items' => $total_items,
            'status' => 'dipinjam',
            'notes' => $notes,
            'created_by' => currentUserId()
        ]);
        
        if (!$loan_id) {
            throw new Exception('Gagal menyimpan data peminjaman!');
        }
        
        // Proses setiap item di keranjang
        foreach ($cart as $item) {
            // Insert ke loan_details
            $detail_id = insert('loan_details', [
                'loan_id' => $loan_id,
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'condition_before' => $item['condition_before'],
                'status' => 'dipinjam'
            ]);
            
            if (!$detail_id) {
                throw new Exception('Gagal menyimpan detail peminjaman!');
            }
            
            // Update stok barang
            $current_stock = fetchColumn("SELECT quantity FROM items WHERE id = ?", [$item['item_id']]);
            $new_stock = $current_stock - $item['quantity'];
            
            if ($new_stock < 0) {
                throw new Exception('Stok tidak mencukupi!');
            }
            
            updateData('items', ['quantity' => $new_stock], 'id', $item['item_id']);
            
            // Update status barang jika stok habis
            if ($new_stock <= 0) {
                updateData('items', ['status' => 'dipinjam'], 'id', $item['item_id']);
            }
        }
        
        // Kosongkan keranjang
        delete('temp_loans', 'session_id = ?', [$session_id]);
        
        // Commit transaksi
        $pdo->commit();
        
        $_SESSION['success'] = 'Peminjaman berhasil! Kode: ' . $loan_code;
        redirect('index.php?url=loans/detail&id=' . $loan_id);
        
    } catch (Exception $e) {
        // Rollback jika ada error
        try {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Exception $e2) {
            // Ignore rollback error
        }
        
        $error_message = $e->getMessage();
        $_SESSION['error'] = 'Error: ' . $error_message;
        redirect('index.php?url=loans/create');
    }
}

ob_end_flush();
?>

<!-- ============================================
STYLE DAN HTML (SAMA SEPERTI SEBELUMNYA)
============================================ -->
<style>
.card-form { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.card-form .card-body { padding: 20px; }
.card-form .card-header { background: #fff; border-bottom: 1px solid #eef2f7; padding: 16px 20px; border-radius: 12px 12px 0 0 !important; }

.form-label-custom { font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px; display: block; }
.form-label-custom .required { color: #dc2626; }
.form-control-custom, .form-select-custom {
    border-radius: 8px; border: 1px solid #e2e8f0; padding: 8px 12px; font-size: 13px;
    background: #fafbfc; transition: all 0.2s; width: 100%;
}
.form-control-custom:focus, .form-select-custom:focus {
    border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: #fff; outline: none;
}

.btn-custom { border-radius: 8px; padding: 8px 20px; font-size: 13px; font-weight: 500; }
.btn-custom-primary { background: #2563eb; color: #fff; border: none; }
.btn-custom-primary:hover { background: #1d4ed8; color: #fff; }
.btn-custom-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.btn-custom-secondary:hover { background: #e2e8f0; color: #1e293b; }
.btn-custom-danger { background: #dc2626; color: #fff; border: none; }
.btn-custom-danger:hover { background: #b91c1c; color: #fff; }

.alert-custom { border-radius: 10px; border: none; padding: 12px 18px; font-size: 13px; border-left: 4px solid transparent; }
.alert-custom.alert-success { background: #dcfce7 !important; color: #166534 !important; border-left-color: #22c55e !important; }
.alert-custom.alert-danger { background: #fee2e2 !important; color: #991b1b !important; border-left-color: #dc2626 !important; }

.table-cart { font-size: 13px; }
.table-cart thead th { background: #f8fafc; color: #475569; font-size: 11px; text-transform: uppercase; padding: 10px 14px; border-bottom: 2px solid #eef2f7; }
.table-cart tbody td { padding: 10px 14px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.thumbnail-sm { width: 40px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid #eef2f7; background: #f8fafc; }
.thumbnail-placeholder-sm { width: 40px; height: 40px; border-radius: 6px; border: 1px solid #eef2f7; background: #f8fafc; display: flex; align-items: center; justify-content: center; color: #94a3b8; }
</style>

<div class="container-fluid px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-plus-circle text-success me-2"></i>Tambah Peminjaman
            </h4>
            <p class="text-muted small mt-1">Pilih barang dan peminjam untuk peminjaman baru</p>
        </div>
        <a href="index.php?url=loans" class="btn btn-custom btn-custom-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-custom alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Kolom Kiri: Daftar Barang -->
        <div class="col-lg-7">
            <div class="card card-form">
                <div class="card-header">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-boxes text-primary me-2"></i>Daftar Barang Tersedia</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-cart">
                            <thead>
                                <tr><th>Kode</th><th>Nama</th><th>Stok</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                <tr><td colspan="4" class="text-center text-muted">Tidak ada barang tersedia</td></tr>
                                <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><span class="badge-code" style="font-size:11px;"><?= htmlspecialchars($item['code']) ?></span></td>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>
                                        <a href="index.php?url=loans/create&action=add&item_id=<?= $item['id'] ?>&quantity=1" 
                                           class="btn btn-sm btn-primary">+1</a>
                                        <a href="index.php?url=loans/create&action=add&item_id=<?= $item['id'] ?>&quantity=2" 
                                           class="btn btn-sm btn-secondary">+2</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Keranjang & Form -->
        <div class="col-lg-5">
            <!-- Keranjang -->
            <div class="card card-form mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-shopping-cart text-warning me-2"></i>Keranjang</h6>
                    <span class="badge bg-primary"><?= $cart_total ?> item</span>
                </div>
                <div class="card-body">
                    <?php if (empty($cart_items)): ?>
                    <p class="text-muted text-center py-3">Keranjang kosong</p>
                    <?php else: ?>
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-cart">
                            <thead>
                                <tr><th>Barang</th><th>Jml</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><?= $item['quantity'] ?></td>
                                    <td>
                                        <a href="index.php?url=loans/create&action=remove&temp_id=<?= $item['id'] ?>" 
                                           class="btn btn-sm btn-danger" onclick="return confirm('Hapus?')">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a href="index.php?url=loans/create&action=clear" class="btn btn-sm btn-danger" onclick="return confirm('Kosongkan keranjang?')">
                            <i class="fas fa-trash"></i> Kosongkan
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Peminjaman -->
            <?php if (!empty($cart_items)): ?>
            <div class="card card-form">
                <div class="card-header">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-check-circle text-success me-2"></i>Konfirmasi Peminjaman</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="process" value="1">
                        <div class="mb-3">
                            <label class="form-label-custom">Peminjam <span class="required">*</span></label>
                            <select name="borrower_id" class="form-select-custom" required>
                                <option value="">-- Pilih Peminjam --</option>
                                <?php foreach ($borrowers as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?> (<?= htmlspecialchars($b['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Tanggal Pinjam</label>
                                    <input type="date" name="loan_date" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label-custom">Tanggal Kembali</label>
                                    <input type="date" name="expected_return_date" class="form-control-custom" value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Catatan</label>
                            <textarea name="notes" class="form-control-custom" rows="2" placeholder="Catatan peminjaman..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-custom btn-custom-primary w-100">
                            <i class="fas fa-check me-1"></i> Proses Peminjaman
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>