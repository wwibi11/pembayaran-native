<?php
// views/header.php

// Ambil nama aplikasi dari settings
$app_name = function_exists('setting') ? setting('nama_madin', 'TPQ MADIN') : 'TPQ MADIN';
$pageTitle = isset($title) ? $title . ' - ' . $app_name : $app_name;
$logoPath  = function_exists('setting') ? setting('logo', 'assets/img/logo.png') : 'assets/img/logo.png';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="<?= BASE_URL ?>/<?= e($logoPath) ?>" rel="icon">
    <title><?= e($pageTitle) ?></title>

    <!-- Font Awesome -->
    <link href="<?= BASE_URL ?>/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="<?= BASE_URL ?>/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Ruang Admin Theme -->
    <link href="<?= BASE_URL ?>/assets/css/ruang-admin.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">

    <style>
        /* ============================================
           RESET & BASE
           ============================================ */
        * { box-sizing: border-box; }

        body, html {
            overflow-x: hidden !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #wrapper {
            display: flex !important;
            min-height: 100vh !important;
            width: 100% !important;
            overflow-x: hidden !important;
            max-width: 100% !important;
        }

        /* ==========================================
           OVERLAY SIDEBAR - MOBILE
           ========================================== */
        .sidebar-overlay {
            display: none !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important;
            right: 0 !important; bottom: 0 !important;
            background: rgba(0, 0, 0, 0.4) !important;
            z-index: 1039 !important;
            transition: all 0.3s ease !important;
        }
        .sidebar-overlay.show { display: block !important; }

        /* ==========================================
           SIDEBAR
           ========================================== */
        .sidebar {
            background: #ffffff !important;
            border-right: 1px solid #e8ecf1;
            box-shadow: 2px 0 8px rgba(0,0,0,0.04);
            width: 280px !important;
            min-width: 280px !important;
            max-width: 280px !important;
            padding-top: 0 !important;
            padding-bottom: 20px !important;
            display: flex !important;
            flex-direction: column !important;
            height: 100vh !important;
            position: fixed !important;
            top: 0 !important; left: 0 !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            z-index: 1040 !important;
            transition: transform 0.3s ease !important;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%) !important; }
            .sidebar.show { transform: translateX(0) !important; }
            .sidebar-overlay.show { display: block !important; }
        }

        @media (min-width: 769px) {
            .sidebar { transform: translateX(0) !important; }
            .sidebar-overlay { display: none !important; }
            #sidebarToggleTop { display: none !important; }
        }

        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-track { background: #f1f1f1; }
        .sidebar::-webkit-scrollbar-thumb {
            background: #c1c7cd; border-radius: 4px;
        }

        /* Sidebar brand */
        .sidebar .sidebar-brand {
            padding: 14px 16px;
            border-bottom: 1px solid #edf2f7;
            background: #ffffff !important;
            min-height: 70px;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-shrink: 0 !important;
            width: 100% !important;
        }

        .sidebar .sidebar-brand a {
            text-decoration: none;
            gap: 10px;
            display: flex;
            align-items: center;
        }

        .sidebar .sidebar-brand .sidebar-brand-text {
            color: #1a2634;
            font-weight: 700;
            font-size: 16px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }

        .sidebar .sidebar-brand .sidebar-brand-text small {
            display: block;
            font-weight: 400;
            font-size: 10px;
            color: #8a94a6;
        }

        .sidebar .sidebar-brand .sidebar-brand-icon img {
            width: 32px; height: 32px; object-fit: contain;
        }
        .sidebar .sidebar-brand .sidebar-brand-icon i {
            font-size: 28px; color: #2c6b9e;
        }

        /* Nav item */
        .sidebar .nav-item .nav-link {
            padding: 11px 16px !important;
            margin: 3px 12px !important;
            border-radius: 10px !important;
            font-size: 14px !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
            color: #4a5568 !important;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar .nav-item .nav-link i {
            width: 22px !important;
            text-align: center !important;
            flex-shrink: 0 !important;
            color: #8a94a6;
            font-size: 15px;
        }

        .sidebar .nav-item .nav-link:hover {
            background: #f0f4f8 !important;
            color: #2c6b9e !important;
        }
        .sidebar .nav-item .nav-link:hover i { color: #2c6b9e; }

        .sidebar .nav-item .nav-link.active {
            background: #e8f0fe !important;
            color: #2c6b9e !important;
            font-weight: 600;
        }
        .sidebar .nav-item .nav-link.active i { color: #2c6b9e; }

        .sidebar .sidebar-heading {
            padding: 10px 20px 4px !important;
            font-size: 10px !important;
            color: #8a94a6 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            font-weight: 600 !important;
        }

        .sidebar .sidebar-divider {
            margin: 4px 16px !important;
            border-color: #edf2f7 !important;
        }

        /* ==========================================
           CONTENT WRAPPER
           ========================================== */
        #content-wrapper {
            background: #f8f9fc;
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            min-height: 100vh !important;
            width: 100% !important;
            margin-left: 0 !important;
            overflow-x: hidden !important;
            max-width: 100% !important;
        }

        @media (min-width: 769px) {
            #content-wrapper {
                width: calc(100% - 280px) !important;
                margin-left: 280px !important;
                max-width: calc(100% - 280px) !important;
            }
        }

        #content {
            flex: 1 !important;
            padding: 0 16px !important;
            overflow-x: hidden !important;
            max-width: 100% !important;
        }

        @media (max-width: 576px) {
            #content { padding: 0 10px !important; }
        }

        .container-fluid {
            overflow-x: hidden !important;
            max-width: 100% !important;
            padding-left: 10px !important;
            padding-right: 10px !important;
        }

        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 5px !important;
                padding-right: 5px !important;
            }
        }

        /* ==========================================
           TOPBAR
           ========================================== */
        .bg-navbar {
            background: #ffffff !important;
            border-bottom: 1px solid #e8ecf1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 8px 16px !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1020 !important;
        }

        @media (max-width: 576px) {
            .bg-navbar { padding: 6px 10px !important; }
        }

        #sidebarToggleTop {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 40px !important;
            height: 40px !important;
            border: none !important;
            background: transparent !important;
            color: #4a5568 !important;
            font-size: 22px !important;
            cursor: pointer !important;
        }

        @media (min-width: 769px) {
            #sidebarToggleTop { display: none !important; }
        }

        /* ==========================================
           TABLE RESPONSIVE
           ========================================== */
        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch !important;
        }

        .table {
            font-size: 13px !important;
            min-width: 600px !important;
        }

        @media (max-width: 576px) {
            .table {
                font-size: 11px !important;
                min-width: 500px !important;
            }
            .table thead th,
            .table tbody td {
                padding: 6px 8px !important;
            }
        }

        /* ==========================================
           CARD
           ========================================== */
        .card {
            border-radius: 10px !important;
            border: 1px solid #eef2f7 !important;
        }
        .card-body { padding: 16px !important; }

        @media (max-width: 576px) {
            .card-body { padding: 12px !important; }
        }

        /* ==========================================
           STAT CARD
           ========================================== */
        .stat-card {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            padding: 12px 16px !important;
        }
        .stat-card .stat-icon {
            width: 40px !important;
            height: 40px !important;
            font-size: 16px !important;
            flex-shrink: 0 !important;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        .stat-card .stat-value { font-size: 20px !important; }

        @media (max-width: 576px) {
            .stat-card { padding: 10px 12px !important; gap: 10px !important; }
            .stat-card .stat-icon {
                width: 32px !important; height: 32px !important; font-size: 14px !important;
            }
            .stat-card .stat-value { font-size: 16px !important; }
            .stat-card .stat-label { font-size: 9px !important; }
        }

        /* ==========================================
           ROW & COLUMN
           ========================================== */
        .row {
            margin-left: -8px !important;
            margin-right: -8px !important;
        }
        .row > [class*="col-"] {
            padding-left: 8px !important;
            padding-right: 8px !important;
        }

        @media (max-width: 576px) {
            .row { margin-left: -4px !important; margin-right: -4px !important; }
            .row > [class*="col-"] {
                padding-left: 4px !important; padding-right: 4px !important;
            }
        }

        /* ==========================================
           BADGE & BUTTON
           ========================================== */
        .badge {
            font-size: 10px !important;
            padding: 3px 8px !important;
        }
        .btn-sm {
            font-size: 11px !important;
            padding: 4px 10px !important;
        }

        @media (max-width: 576px) {
            .btn-sm { font-size: 10px !important; padding: 3px 8px !important; }
            .badge  { font-size: 9px !important; padding: 2px 6px !important; }
        }

        /* ==========================================
           CHART
           ========================================== */
        .chart-wrapper {
            height: 200px !important;
            position: relative !important;
        }
        @media (max-width: 576px) {
            .chart-wrapper { height: 150px !important; }
        }

        /* ==========================================
           FOOTER
           ========================================== */
        .sticky-footer {
            background: #ffffff !important;
            border-top: 1px solid #e8ecf1;
            padding: 10px 0 !important;
            flex-shrink: 0 !important;
            margin-top: auto !important;
        }
        .sticky-footer .copyright {
            color: #8a94a6;
            font-size: 12px !important;
        }

        @media (max-width: 576px) {
            .sticky-footer .copyright { font-size: 10px !important; }
        }

        /* ==========================================
           DROPDOWN
           ========================================== */
        .dropdown-menu {
            z-index: 99999 !important;
            border-radius: 10px !important;
            border: none !important;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
            min-width: 180px !important;
        }
        @media (max-width: 576px) {
            .dropdown-menu {
                min-width: 160px !important;
                right: 0 !important;
                left: auto !important;
            }
        }

        /* ==========================================
           FORM ROW (untuk form santri)
           ========================================== */
        .form-row {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 4px;
            display: block;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2c6b9e;
            box-shadow: 0 0 0 3px rgba(44,107,158,0.1);
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <!-- ============================================
        OVERLAY SIDEBAR - UNTUK MOBILE
        ============================================ -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>