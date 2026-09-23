<?php
// views/topbar.php

$user_role   = $_SESSION['user']['role'] ?? 'wali';
$user_name   = $_SESSION['user']['name'] ?? 'User';
$user_email  = $_SESSION['user']['email'] ?? '';

$role_labels = [
    'admin'  => 'Administrator',
    'kepala' => 'Kepala Madin',
    'wali'   => 'Wali Santri',
];

$role_badge_color = [
    'admin'  => '#2c6b9e',
    'kepala' => '#b45309',
    'wali'   => '#15803d',
];

$app_name = function_exists('setting') ? setting('nama_madin', 'TPQ MADIN') : 'TPQ MADIN';

$badgeColor = $role_badge_color[$user_role] ?? '#4a5568';
?>

<style>
/* ============================================
   USER DROPDOWN — CSS ONLY (anti-konflik)
   ============================================ */
.user-dd {
    position: relative;
}

.user-dd-trigger {
    display: flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 8px;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
    text-decoration: none !important;
}
.user-dd-trigger:hover {
    background: #f0f4f8;
}

.user-dd-menu {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    min-width: 240px;
    padding: 6px 0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    z-index: 99999;
    pointer-events: none;
}

/* Show saat hover ATAU focus-within */
.user-dd:hover .user-dd-menu,
.user-dd:focus-within .user-dd-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    pointer-events: auto;
}

.user-dd-item {
    display: flex;
    align-items: center;
    padding: 9px 18px;
    color: #4a5568;
    font-size: 13px;
    text-decoration: none !important;
    transition: background 0.15s;
    cursor: pointer;
}
.user-dd-item:hover {
    background: #f0f4f8;
    color: #2c6b9e;
}

.user-dd-item.danger {
    color: #dc2626;
    font-weight: 500;
}
.user-dd-item.danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

.user-dd-header {
    padding: 12px 18px 10px;
}
.user-dd-header .name {
    font-weight: 600;
    font-size: 13px;
    color: #1a2634;
}
.user-dd-header .email {
    color: #8a94a6;
    font-size: 11px;
    margin-top: 2px;
    word-break: break-all;
}
.user-dd-header .badge-role {
    display: inline-block;
    color: #fff;
    font-size: 9px;
    padding: 3px 8px;
    border-radius: 10px;
    margin-top: 8px;
}

.user-dd-divider {
    height: 1px;
    background: #edf2f7;
    margin: 4px 0;
}

/* Hapus caret Bootstrap supaya tidak dobel */
.user-dd-trigger::after {
    display: none !important;
}

/* Mobile: tetap bisa dibuka via tap (focus-within) */
@media (max-width: 768px) {
    .user-dd-menu {
        right: 0;
        left: auto;
        min-width: 220px;
    }
}
</style>

<div id="content-wrapper" class="d-flex flex-column">
    <div id="content">

        <!-- ============================================
             TOPBAR
             ============================================ -->
        <nav class="navbar navbar-expand navbar-light bg-navbar topbar static-top"
             style="z-index: 999; position: sticky; top: 0;">

            <!-- HAMBURGER MENU (mobile) -->
            <button id="sidebarToggleTop" class="btn btn-link rounded-circle"
                    style="color: #4a5568; font-size: 22px; width: 40px; height: 40px;
                           display: flex !important; align-items: center; justify-content: center;
                           border: none; background: transparent; padding: 0; cursor: pointer;"
                    title="Toggle Sidebar"
                    aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>

            <!-- BRAND TITLE (mobile only) -->
            <span class="navbar-brand d-md-none"
                  style="color: #1a2634; font-weight: 600; font-size: 14px; padding: 0; margin: 0 10px;">
                <i class="fas fa-mosque" style="color: #2c6b9e;"></i>
                <?= e($app_name) ?>
            </span>

            <!-- BREADCRUMB (desktop only) -->
            <div class="d-none d-md-block">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb" style="background: transparent; padding: 0; margin: 0;">
                        <li class="breadcrumb-item">
                            <a href="<?= BASE_URL ?>/dashboard"
                               style="color: #8a94a6; text-decoration: none;">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </li>
                        <?php if (isset($current_module) && $current_module != 'dashboard'): ?>
                            <li class="breadcrumb-item active" style="color: #1a2634; font-weight: 500;">
                                <?= e(ucwords(str_replace('_', ' ', $current_module))) ?>
                            </li>
                            <?php if (isset($current_action) && !in_array($current_action, ['index', 'dashboard'])): ?>
                                <li class="breadcrumb-item active" style="color: #1a2634;">
                                    <?= e(ucwords(str_replace('_', ' ', $current_action))) ?>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>

            <!-- RIGHT MENU -->
            <ul class="navbar-nav ml-auto" style="align-items: center;">

                <!-- QUICK ACTION: Admin → badge verifikasi -->
                <?php if ($user_role === 'admin'):
                    $jmlPending = 0;
                    if (function_exists('fetchOne')) {
                        $jmlPending = (int) (fetchOne("
                            SELECT COUNT(*) c FROM pembayaran WHERE status='menunggu'
                        ")['c'] ?? 0);
                    }
                ?>
                    <li class="nav-item d-none d-md-block" style="margin-right: 8px;">
                        <a href="<?= BASE_URL ?>/pembayaran/verifikasi"
                           class="nav-link position-relative"
                           style="padding: 6px 10px; border-radius: 8px; color: #4a5568;"
                           title="Verifikasi Pembayaran">
                            <i class="fas fa-bell" style="font-size: 18px;"></i>
                            <?php if ($jmlPending > 0): ?>
                                <span class="badge badge-danger"
                                      style="position: absolute; top: 0; right: 2px;
                                             font-size: 9px; padding: 2px 5px;">
                                    <?= $jmlPending ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <!-- ============================================
                     USER DROPDOWN (CSS-ONLY)
                     ============================================ -->
                <li class="nav-item">
                    <div class="user-dd" tabindex="0">

                        <!-- Trigger -->
                        <div class="user-dd-trigger">
                            <div class="img-profile rounded-circle"
                                 style="width: 36px; height: 36px; background: <?= e($badgeColor) ?>;
                                        display: flex; align-items: center; justify-content: center;
                                        color: #fff; font-weight: 700; font-size: 14px;">
                                <?= e(strtoupper(substr($user_name, 0, 1))) ?>
                            </div>

                            <span class="ml-2 d-none d-lg-inline"
                                  style="color: #1a2634; font-weight: 500; font-size: 13px;">
                                <?= e($user_name) ?>
                                <small style="display: block; font-weight: 400;
                                              color: #8a94a6; font-size: 10px;">
                                    <?= e($role_labels[$user_role] ?? ucfirst($user_role)) ?>
                                </small>
                            </span>

                            <i class="fas fa-chevron-down ml-2"
                               style="font-size: 10px; color: #8a94a6;"></i>
                        </div>

                        <!-- Menu -->
                        <div class="user-dd-menu">
                            <div class="user-dd-header">
                                <div class="name"><?= e($user_name) ?></div>
                                <div class="email"><?= e($user_email) ?></div>
                                <span class="badge-role"
                                      style="background: <?= e($badgeColor) ?>;">
                                    <?= e($role_labels[$user_role] ?? ucfirst($user_role)) ?>
                                </span>
                            </div>

                            <div class="user-dd-divider"></div>

                            <a class="user-dd-item" href="<?= BASE_URL ?>/profil">
                                <i class="fas fa-user-circle fa-fw mr-2"
                                   style="color: #8a94a6; width: 18px;"></i>
                                Profil Saya
                            </a>

                            <a class="user-dd-item" href="<?= BASE_URL ?>/profil/ganti_password">
                                <i class="fas fa-key fa-fw mr-2"
                                   style="color: #8a94a6; width: 18px;"></i>
                                Ganti Password
                            </a>

                            <div class="user-dd-divider"></div>

                            <a class="user-dd-item danger"
                               href="<?= BASE_URL ?>/auth/logout.php"
                               onclick="return confirm('Yakin ingin logout?');">
                                <i class="fas fa-sign-out-alt fa-fw mr-2"
                                   style="width: 18px;"></i>
                                Logout
                            </a>
                        </div>

                    </div>
                </li>

            </ul>
        </nav>