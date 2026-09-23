<?php
// ============================================
// AUTH GUARD - Proteksi Halaman
// ============================================

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CEK LOGIN
// ============================================
if (!isset($_SESSION['user'])) {
    $_SESSION['error'] = 'Silakan login terlebih dahulu!';
    header("Location: login.php");
    exit;
}

// ============================================
// CEK STATUS AKTIF
// ============================================
// Load database jika belum
if (!function_exists('fetchOne')) {
    require_once __DIR__ . '/../config/database.php';
}

$user = fetchOne(
    "SELECT is_active FROM users WHERE id = ?",
    [$_SESSION['user']['id']]
);

if (!$user || $user['is_active'] != 1) {
    session_destroy();
    $_SESSION['error'] = 'Akun Anda tidak aktif. Hubungi administrator!';
    header("Location: login.php");
    exit;
}

// ============================================
// FUNGSI-FUNGSI ROLE
// ============================================

/**
 * Check if user has allowed role
 */
function checkRole($allowedRoles = []) {
    $userRole = $_SESSION['user']['role'] ?? '';
    
    if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles)) {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Akses Ditolak</title>
            <link href="../vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
            <link href="../vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
            <style>
                body {
                    background: #f8f9fc;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                }
                .error-container {
                    text-align: center;
                    padding: 40px;
                    max-width: 500px;
                }
                .error-container .error-icon {
                    font-size: 80px;
                    color: #dc2626;
                    margin-bottom: 20px;
                }
                .error-container .error-code {
                    font-size: 72px;
                    font-weight: 700;
                    color: #1a2634;
                    line-height: 1;
                }
                .error-container .error-title {
                    font-size: 24px;
                    font-weight: 600;
                    color: #1a2634;
                    margin: 10px 0;
                }
                .error-container .error-description {
                    font-size: 16px;
                    color: #6b7280;
                    margin-bottom: 30px;
                }
                .error-container .btn-home {
                    background: #2c6b9e;
                    color: white;
                    padding: 12px 30px;
                    border-radius: 8px;
                    text-decoration: none;
                    transition: all 0.3s ease;
                    display: inline-block;
                }
                .error-container .btn-home:hover {
                    background: #1f507a;
                    color: white;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="error-code">403</div>
                <div class="error-title">Akses Ditolak</div>
                <div class="error-description">
                    Anda tidak memiliki izin untuk mengakses halaman ini.<br>
                    <small style="color: #8a94a6;">Role Anda: ' . $userRole . '</small>
                </div>
                <a href="../index.php?url=dashboard" class="btn-home">
                    <i class="fas fa-home"></i> Kembali ke Dashboard
                </a>
            </div>
        </body>
        </html>';
        exit;
    }
}

/**
 * Check if user is admin (includes super_admin)
 */
function isAdmin() {
    return isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['admin', 'super_admin']);
}

/**
 * Check if user is super admin
 */
function isSuperAdmin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'super_admin';
}

/**
 * Check if user is staff
 */
function isStaff() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'staff';
}

/**
 * Get current user data
 */
function currentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID
 */
function currentUserId() {
    return $_SESSION['user']['id'] ?? 0;
}

/**
 * Get current user role
 */
function currentUserRole() {
    return $_SESSION['user']['role'] ?? 'staff';
}

// ============================================
// AKHIR FILE
// ============================================