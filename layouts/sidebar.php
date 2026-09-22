<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Route;
use App\Model\MenuModel;

$Menus = new MenuModel();
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

// Tüm menü verisini hiyerarşik ve kullanıcı sırasına göre oluştur
$menu_data = $Menus->getHierarchicalMenuForRole($currentUserId);

// Aktif menü tespiti
$currentPath = $_GET['p'] ?? '';
$currentMenu = $Menus->getMenuByLink($currentPath);
$activeMenuIds = $Menus->getActiveMenuIds($currentMenu);

// Favori menüler
$favoriteMenuIds = $Menus->getFavoriteMenuIds($currentUserId);
$favoriteMenus = $Menus->getFavoriteMenus($currentUserId);

?>

<div class="vertical-menu">

    <style>
        :root {
            --sidebar-bg: #ffffff;
            --sidebar-border: #f1f1f4;
            --sidebar-item-hover: #f4f4f5;
            --sidebar-item-active: #f4f4f5;
            --sidebar-foreground: #3f3f46;
            --sidebar-muted: #71717a;
            --sidebar-accent: #18181b;
            --sidebar-font: var(--bs-font-sans-serif, inherit);
        }

        [data-bs-theme="dark"] {
            --sidebar-bg: #191e22;
            --sidebar-border: #22292f;
            --sidebar-item-hover: #242b31;
            --sidebar-item-active: #242b31;
            --sidebar-foreground: #adb5bd;
            --sidebar-muted: #74788d;
            --sidebar-accent: #1c84ee;
        }

        body[data-sidebar="dark"] {
            --sidebar-bg: #191e22;
            --sidebar-border: #303840;
            --sidebar-item-hover: #30373f;
            --sidebar-item-active: #30373f;
            --sidebar-foreground: #f8fafc;
            --sidebar-muted: #cbd5e1;
            --sidebar-accent: #6d5dfc;
        }

        .vertical-menu {
            display: flex !important;
            flex-direction: column !important;
            height: 100vh !important;
            overflow: hidden !important;
            background-color: var(--sidebar-bg) !important;
            background-image: linear-gradient(rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.05)) !important;
            border-right: 1px solid var(--sidebar-border) !important;
            box-shadow: none !important;
            font-family: inherit !important;
            top: 0 !important;
        }

                .navbar-brand-box {
                    display: none !important;
                }

                #page-topbar {
                    left: 250px !important;
                    background-color: var(--sidebar-bg) !important;
                    background-image: linear-gradient(rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.05)) !important;
                    border-bottom: 1px solid var(--sidebar-border) !important;
                    box-shadow: none !important;
                }

                body[data-topbar="dark"] #page-topbar {
                    background-color: #191e22 !important;
                    border-bottom-color: #22292f !important;
                }

                body[data-sidebar="dark"] .vertical-menu,
                body[data-sidebar="dark"] .sidebar-sticky-top {
                    background-color: #191e22 !important;
                    border-right-color: #22292f !important;
                }

                body[data-sidebar="dark"] #sidebar-menu a,
                body[data-sidebar="dark"] #side-menu .menu-title,
                body[data-sidebar="dark"] .brand-name {
                    color: #f8fafc !important;
                }

                body[data-sidebar="dark"] .brand-sub,
                body[data-sidebar="dark"] #sidebar-menu ul.sub-menu li a {
                    color: #cbd5e1 !important;
                }

                body[data-sidebar="dark"] #sidebar-menu a i,
                body[data-sidebar="dark"] #sidebar-menu a svg,
                body[data-sidebar="dark"] .sidebar-search-container .search-icon {
                    color: #cbd5e1 !important;
                    stroke: #cbd5e1 !important;
                }

                body[data-sidebar="dark"] .sidebar-search {
                    background-color: rgba(255, 255, 255, 0.12) !important;
                    border-color: rgba(255, 255, 255, 0.18) !important;
                    color: #ffffff !important;
                }

                body[data-sidebar="dark"] .sidebar-search:focus {
                    background-color: rgba(255, 255, 255, 0.18) !important;
                    border-color: rgba(255, 255, 255, 0.35) !important;
                    color: #ffffff !important;
                    box-shadow: none !important;
                }

                body[data-sidebar="dark"] .sidebar-search::placeholder {
                    color: rgba(255, 255, 255, 0.5) !important;
                }

                body[data-sidebar="dark"] #sidebar-menu ul li a:hover,
                body[data-sidebar="dark"] #sidebar-menu ul li a.active,
                body[data-sidebar="dark"] #sidebar-menu ul li.mm-active > a {
                    background-color: rgba(255, 255, 255, 0.1) !important;
                    color: #ffffff !important;
                }

                body[data-topbar="dark"] #page-topbar .header-item,
                body[data-topbar="dark"] #page-topbar .topbar-page-title,
                body[data-topbar="dark"] #page-topbar #topbar-page-title {
                    color: #f8fafc !important;
                }

                body[data-topbar="dark"] #page-topbar .topbar-page-desc,
                body[data-topbar="dark"] #page-topbar #topbar-page-desc {
                    color: #cbd5e1 !important;
                }

                body[data-sidebar-size="sm"] #page-topbar {
                    left: 60px !important;
                    width: calc(100% - 60px) !important;
                }

                body[data-sidebar-size="sm"] .vertical-menu {
                    width: 60px !important;
                }

                body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a {
                    padding: 12px 0 !important;
                    text-align: center !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                }

                body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a i,
                body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a svg,
                body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a [data-feather] {
                    margin: 0 auto !important;
                    display: block !important;
                }

                body[data-sidebar-size="sm"] .star-btn,
                body[data-sidebar-size="sm"] .vertical-menu .star-btn,
                body[data-sidebar-size="sm"] #side-menu .star-btn,
                body[data-sidebar-size="sm"] #sidebar-menu .star-btn {
                    display: none !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                }

                @media (max-width: 992px) {
                    #page-topbar {
                        left: 0 !important;
                    }
                    .navbar-brand-box {
                        display: flex !important;
                    }
                    .sidebar-brand-box {
                        display: none !important;
                    }
                }

                #sidebar-menu {
                    padding: 8px;
                    min-height: 100%;
                }

                /* Sticky Sidebar Header */
                .sidebar-sticky-top {
                    position: relative;
                    z-index: 100;
                    flex-shrink: 0;
                    background-color: var(--sidebar-bg);
                    background-image: linear-gradient(rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.05)) !important;
                    padding: 12px 8px 12px 8px;
                    margin: 0;
                    border-bottom: 1px solid transparent;
                    transition: all 0.2s ease;
                }

                .sidebar-menu-scroll {
                    flex: 1 1 auto;
                    min-height: 0;
                    height: 100%;
                    overflow-y: auto;
                }

                body[data-sidebar="red"] .sidebar-sticky-top { background-color: #f46a6a !important; }
                body[data-sidebar="purple"] .sidebar-sticky-top { background-color: #5156be !important; }
                body[data-sidebar="slate"] .sidebar-sticky-top { background-color: #252526 !important; }
                body[data-sidebar="emerald"] .sidebar-sticky-top { background-color: #10b981 !important; }
                body[data-sidebar="orange"] .sidebar-sticky-top { background-color: #f97316 !important; }
                body[data-sidebar="rose"] .sidebar-sticky-top { background-color: #ec003f !important; }
                body[data-sidebar="ersan"] .sidebar-sticky-top { background-color: #e2bd61 !important; }
                body[data-sidebar="teal"] .sidebar-sticky-top { background-color: #0d9488 !important; }
                body[data-sidebar="cyan"] .sidebar-sticky-top { background-color: #06b6d4 !important; }
                body[data-sidebar="default"] .sidebar-sticky-top { background-color: #1c84ee !important; }
                body[data-sidebar="brand"] .sidebar-sticky-top { background-color: var(--bs-primary) !important; }
                body[data-sidebar="dark"] .sidebar-sticky-top { background-color: #191e22 !important; }
                body[data-sidebar="light"] .sidebar-sticky-top { background-color: #ffffff !important; }

                html[data-theme-mode="slate"][data-bs-theme="dark"] body .vertical-menu,
                html[data-theme-mode="slate"][data-bs-theme="dark"] body .sidebar-sticky-top,
                html[data-theme-mode="slate"][data-bs-theme="dark"] body #page-topbar {
                    background-color: #222830 !important;
                    background-image: none !important;
                    border-color: #2b333e !important;
                }

                /* Sidebar Search & Settings */
                .sidebar-search-container {
                    padding: 0 8px;
                    position: relative;
                }

                .sidebar-search {
                    background-color: var(--sidebar-item-hover) !important;
                    border: 1px solid var(--sidebar-border) !important;
                    color: var(--sidebar-foreground) !important;
                    border-radius: 8px !important;
                    padding-left: 36px !important;
                    height: 38px;
                    font-size: 13px;
                    transition: all 0.2s ease;
                    width: 100%;
                }

                .sidebar-search:focus {
                    border-color: var(--sidebar-accent) !important;
                    background-color: var(--sidebar-item-hover) !important;
                    color: var(--sidebar-foreground) !important;
                    box-shadow: none !important;
                    outline: none !important;
                }

                .sidebar-search-container .search-icon {
                    position: absolute !important;
                    left: 12px !important;
                    top: 50% !important;
                    width: 14px;
                    height: 14px;
                    color: var(--sidebar-muted);
                    pointer-events: none;
                    transform: translateY(-50%);
                }

                .btn-sidebar-settings {
                    width: 38px;
                    height: 38px;
                    border-radius: 8px !important;
                    background-color: var(--sidebar-item-hover) !important;
                    border: 1px solid var(--sidebar-border) !important;
                    color: var(--sidebar-muted) !important;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 0 !important;
                    flex-shrink: 0;
                    transition: all 0.2s ease;
                }

                .btn-sidebar-settings:hover,
                .btn-sidebar-settings:focus,
                .btn-sidebar-settings[aria-expanded="true"] {
                    background-color: var(--sidebar-item-active) !important;
                    color: var(--sidebar-accent) !important;
                    border-color: var(--sidebar-accent) !important;
                }

                body[data-sidebar="dark"] .btn-sidebar-settings,
                body[data-sidebar="red"] .btn-sidebar-settings,
                body[data-sidebar="purple"] .btn-sidebar-settings,
                body[data-sidebar="slate"] .btn-sidebar-settings,
                body[data-sidebar="emerald"] .btn-sidebar-settings,
                body[data-sidebar="orange"] .btn-sidebar-settings,
                body[data-sidebar="rose"] .btn-sidebar-settings,
                body[data-sidebar="ersan"] .btn-sidebar-settings,
                body[data-sidebar="teal"] .btn-sidebar-settings,
                body[data-sidebar="cyan"] .btn-sidebar-settings,
                body[data-sidebar="default"] .btn-sidebar-settings,
                body[data-sidebar="brand"] .btn-sidebar-settings {
                    background-color: rgba(255, 255, 255, 0.12) !important;
                    border-color: rgba(255, 255, 255, 0.15) !important;
                    color: rgba(255, 255, 255, 0.7) !important;
                }

                body[data-sidebar="dark"] .btn-sidebar-settings:hover,
                body[data-sidebar="dark"] .btn-sidebar-settings[aria-expanded="true"] {
                    background-color: rgba(255, 255, 255, 0.2) !important;
                    color: #ffffff !important;
                }

                .menu-settings-dropdown .dropdown-menu {
                    background: var(--bs-card-bg, #ffffff);
                    border: 1px solid var(--sidebar-border, #e9ecef);
                    border-radius: 8px;
                    padding: 6px;
                    z-index: 1050;
                }

                [data-bs-theme="dark"] .menu-settings-dropdown .dropdown-menu {
                    background: #1c2228 !important;
                    border-color: #283038 !important;
                }

                .menu-settings-dropdown .dropdown-item {
                    border-radius: 6px;
                    font-size: 13px;
                    padding: 6px 10px;
                    color: var(--sidebar-foreground);
                }

                .menu-settings-dropdown .dropdown-item:hover {
                    background-color: rgba(239, 68, 68, 0.1);
                    color: #ef4444;
                }

                body[data-sidebar="red"] .sidebar-search,
                body[data-sidebar="purple"] .sidebar-search,
                body[data-sidebar="slate"] .sidebar-search,
                body[data-sidebar="emerald"] .sidebar-search,
                body[data-sidebar="orange"] .sidebar-search,
                body[data-sidebar="rose"] .sidebar-search,
                body[data-sidebar="ersan"] .sidebar-search,
                body[data-sidebar="teal"] .sidebar-search,
                body[data-sidebar="cyan"] .sidebar-search,
                body[data-sidebar="default"] .sidebar-search,
                body[data-sidebar="brand"] .sidebar-search,
                body[data-sidebar="dark"] .sidebar-search {
                    background-color: rgba(255, 255, 255, 0.12) !important;
                    border-color: rgba(255, 255, 255, 0.15) !important;
                    color: #ffffff !important;
                }

                body[data-sidebar="red"] .sidebar-search:focus,
                body[data-sidebar="purple"] .sidebar-search:focus,
                body[data-sidebar="slate"] .sidebar-search:focus,
                body[data-sidebar="emerald"] .sidebar-search:focus,
                body[data-sidebar="orange"] .sidebar-search:focus,
                body[data-sidebar="rose"] .sidebar-search:focus,
                body[data-sidebar="ersan"] .sidebar-search:focus,
                body[data-sidebar="teal"] .sidebar-search:focus,
                body[data-sidebar="cyan"] .sidebar-search:focus,
                body[data-sidebar="default"] .sidebar-search:focus,
                body[data-sidebar="brand"] .sidebar-search:focus,
                body[data-sidebar="dark"] .sidebar-search:focus {
                    background-color: rgba(255, 255, 255, 0.18) !important;
                    border-color: rgba(255, 255, 255, 0.35) !important;
                    color: #ffffff !important;
                    box-shadow: none !important;
                }

                body[data-sidebar="red"] .sidebar-search::placeholder,
                body[data-sidebar="purple"] .sidebar-search::placeholder,
                body[data-sidebar="slate"] .sidebar-search::placeholder,
                body[data-sidebar="emerald"] .sidebar-search::placeholder,
                body[data-sidebar="orange"] .sidebar-search::placeholder,
                body[data-sidebar="rose"] .sidebar-search::placeholder,
                body[data-sidebar="ersan"] .sidebar-search::placeholder,
                body[data-sidebar="teal"] .sidebar-search::placeholder,
                body[data-sidebar="cyan"] .sidebar-search::placeholder,
                body[data-sidebar="default"] .sidebar-search::placeholder,
                body[data-sidebar="brand"] .sidebar-search::placeholder,
                body[data-sidebar="dark"] .sidebar-search::placeholder {
                    color: rgba(255, 255, 255, 0.5) !important;
                }

                body[data-sidebar-size="sm"] .sidebar-sticky-top {
                    position: static;
                    padding: 12px 8px;
                }

                /* Sidebar Brand/Header */
                .sidebar-brand-box {
                    padding: 0 12px 12px 12px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    position: relative;
                }

                .brand-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .brand-logo {
                    width: 32px;
                    height: 32px;
                    background-color: var(--bs-primary) !important;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff !important;
                    flex-shrink: 0;
                }

                .brand-logo i {
                    width: 18px;
                    height: 18px;
                }

                .brand-info {
                    display: flex;
                    flex-direction: column;
                    line-height: 1.25;
                }

                .brand-name {
                    font-weight: 600;
                    font-size: 14px;
                    color: var(--sidebar-foreground);
                }

                .brand-sub {
                    font-size: 11px;
                    color: var(--sidebar-muted);
                }

                body[data-sidebar="dark"] .brand-name,
                body[data-sidebar="brand"] .brand-name,
                body[data-sidebar="default"] .brand-name,
                body[data-sidebar="cyan"] .brand-name,
                body[data-sidebar="teal"] .brand-name,
                body[data-sidebar="ersan"] .brand-name,
                body[data-sidebar="rose"] .brand-name,
                body[data-sidebar="orange"] .brand-name,
                body[data-sidebar="emerald"] .brand-name,
                body[data-sidebar="slate"] .brand-name,
                body[data-sidebar="purple"] .brand-name,
                body[data-sidebar="red"] .brand-name {
                    color: #ffffff !important;
                }

                body[data-sidebar="dark"] .brand-sub,
                body[data-sidebar="brand"] .brand-sub,
                body[data-sidebar="default"] .brand-sub,
                body[data-sidebar="cyan"] .brand-sub,
                body[data-sidebar="teal"] .brand-sub,
                body[data-sidebar="ersan"] .brand-sub,
                body[data-sidebar="rose"] .brand-sub,
                body[data-sidebar="orange"] .brand-sub,
                body[data-sidebar="emerald"] .brand-sub,
                body[data-sidebar="slate"] .brand-sub,
                body[data-sidebar="purple"] .brand-sub,
                body[data-sidebar="red"] .brand-sub {
                    color: rgba(255, 255, 255, 0.6) !important;
                }

                /* Menu Items & Groups Styling */
                #side-menu {
                    padding: 0;
                    margin: 0;
                }

                .menu-section {
                    list-style: none;
                    margin-bottom: 4px;
                }

                #sidebar-menu .menu-title,
                #side-menu .menu-title,
                .vertical-menu .menu-title {
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: none;
                    color: var(--sidebar-muted);
                    padding: 10px 12px 6px 12px !important;
                    letter-spacing: 0.01em;
                    pointer-events: auto !important;
                    cursor: grab !important;
                    user-select: none !important;
                    -webkit-user-select: none !important;
                    touch-action: none;
                    border-radius: 6px;
                    transition: background 0.15s ease, color 0.15s ease;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                }

                #sidebar-menu .menu-title:hover,
                #side-menu .menu-title:hover {
                    background-color: var(--sidebar-item-hover) !important;
                    color: var(--sidebar-foreground) !important;
                }

                #sidebar-menu .menu-title:active,
                #side-menu .menu-title:active {
                    cursor: grabbing !important;
                }

                #side-menu .menu-title > * {
                    pointer-events: none !important;
                }

                .group-drag-handle {
                    width: 14px !important;
                    height: 14px !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    transition: opacity 0.2s ease, visibility 0.2s ease, color 0.2s ease !important;
                    color: var(--sidebar-muted) !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    flex-shrink: 0 !important;
                }

                #side-menu .menu-title:hover .group-drag-handle,
                #side-menu .menu-section:hover .group-drag-handle {
                    opacity: 0.8 !important;
                    visibility: visible !important;
                    color: var(--sidebar-accent) !important;
                }

                .menu-items-list {
                    padding: 0;
                    margin: 0;
                }

                /* Drag & Drop Visual Classes */
                .sortable-ghost-group {
                    opacity: 0.45;
                    background: rgba(28, 132, 238, 0.08) !important;
                    border: 1px dashed var(--sidebar-accent) !important;
                    border-radius: 8px;
                }

                .sortable-fallback-group {
                    background: var(--sidebar-bg, #ffffff) !important;
                    border: 1px solid var(--sidebar-border, #e2e8f0) !important;
                    border-radius: 8px !important;
                    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.25) !important;
                    opacity: 0.95 !important;
                    overflow: hidden !important;
                    pointer-events: none !important;
                }

                .sortable-ghost-item {
                    opacity: 0.4;
                    background: rgba(28, 132, 238, 0.15) !important;
                    border-radius: 8px;
                }

                .sortable-ghost-subitem {
                    opacity: 0.4;
                    background: rgba(28, 132, 238, 0.15) !important;
                    border-radius: 8px;
                }

                .sortable-chosen-item,
                .sortable-chosen-group,
                .sortable-chosen-subitem {
                    cursor: grabbing !important;
                }

                .menu-item-draggable,
                .submenu-item-draggable {
                    cursor: grab;
                }

                .menu-item-draggable:active,
                .submenu-item-draggable:active {
                    cursor: grabbing;
                }

                #sidebar-menu ul li ul.sub-menu li a:hover {
                    padding-left: 1.2rem !important;
                }

                #sidebar-menu ul li ul.sub-menu li a {
                    position: relative;
                    transition: background-color 0.2s ease, color 0.2s ease, padding 0.2s ease;
                    white-space: nowrap !important;
                    padding-left: 1rem !important;
                }

                #side-menu li {
                    position: relative;
                }

                #sidebar-menu #side-menu li a,
                #sidebar-menu ul li a {
                    padding: 8px 12px !important;
                    border-radius: 8px !important;
                    font-size: 14px;
                    color: var(--sidebar-foreground) !important;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    transition: background-color 0.2s ease, color 0.2s ease, padding 0.2s ease;
                    margin: 2px 12px 2px 4px;
                    position: relative;
                }

                #sidebar-menu #side-menu li a:hover,
                #sidebar-menu ul li a:hover {
                    background-color: var(--sidebar-item-hover) !important;
                    border-radius: 8px !important;
                }

                #sidebar-menu #side-menu li.mm-active > a,
                #sidebar-menu #side-menu li a.active,
                #sidebar-menu ul li.mm-active > a,
                #sidebar-menu ul li a.active {
                    background-color: var(--sidebar-item-active) !important;
                    font-weight: 500;
                    border-radius: 8px !important;
                }

                #side-menu li a i {
                    width: 16px;
                    height: 16px;
                    font-size: 16px;
                    color: var(--sidebar-muted);
                    transition: color 0.2s ease;
                    margin: 0 !important;
                }

                #side-menu li a:hover i,
                #side-menu li.mm-active > a i,
                #side-menu li a.active i {
                    color: var(--sidebar-foreground);
                }

                /* Sub-menu Indentation & MetisMenu State */
                .sub-menu {
                    padding: 0 0 0 12px !important;
                    margin: 0 0 0 28px !important;
                    list-style: none;
                    border-left: 1px solid var(--sidebar-border) !important;
                    position: relative;
                }

                .sub-menu.mm-collapse:not(.mm-show) {
                    display: none;
                }

                .sub-menu.mm-collapsing {
                    position: relative;
                    height: 0;
                    overflow: hidden;
                    transition: height 0.3s ease;
                }

                .sub-menu.mm-collapse.mm-show {
                    display: block;
                }

                #sidebar-menu .sub-menu li a,
                #sidebar-menu ul li ul.sub-menu li a {
                    font-size: 13px !important;
                    padding-left: 1rem !important;
                    color: var(--sidebar-foreground) !important;
                    border-radius: 8px !important;
                    margin: 2px 16px 2px 6px;
                    white-space: nowrap !important;
                }

                #sidebar-menu .sub-menu li a:hover,
                #sidebar-menu .sub-menu li.mm-active > a,
                #sidebar-menu .sub-menu li a.active,
                #sidebar-menu ul li ul.sub-menu li a:hover,
                #sidebar-menu ul li ul.sub-menu li.mm-active > a,
                #sidebar-menu ul li ul.sub-menu li a.active {
                    padding-left: 1.2rem !important;
                    border-radius: 8px !important;
                }

                /* Arrow styling */
                .has-arrow:after {
                    content: "" !important;
                    display: block !important;
                    width: 6px !important;
                    height: 6px !important;
                    border-width: 0 0 1.5px 1.5px !important;
                    border-style: solid !important;
                    border-color: var(--sidebar-muted) !important;
                    position: absolute;
                    right: 20px !important;
                    top: 50% !important;
                    transform: translateY(-60%) rotate(-135deg) !important;
                    transition: transform 0.2s ease !important;
                    pointer-events: none;
                }

                .mm-active > .has-arrow:after {
                    transform: translateY(-30%) rotate(-45deg) !important;
                }

                [data-feather] {
                    width: 16px;
                    height: 16px;
                }

                /* Star styling */
                .star-btn {
                    position: absolute;
                    right: 16px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: var(--sidebar-muted);
                    opacity: 0;
                    transition: all 0.2s ease;
                    width: 24px;
                    height: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                    z-index: 5;
                }

                .has-arrow + .star-btn {
                    right: 44px !important;
                }

                #side-menu li:hover > .star-btn {
                    opacity: 1 !important;
                }

                #side-menu li:has(> .sub-menu:hover) > .star-btn {
                    opacity: 0 !important;
                    pointer-events: none !important;
                }

                .star-btn.active {
                    opacity: 1 !important;
                    color: #f1b44c !important;
                }

                .star-btn:hover {
                    background-color: rgba(241, 180, 76, 0.1);
                    color: #f1b44c !important;
                }

                #side-menu li a {
                    padding-right: 72px !important;
                }
                
                .sub-menu li a {
                    padding-right: 52px !important;
                }

                .simplebar-track.simplebar-vertical {
                    background-color: transparent;
                    width: 6px;
                }
                .simplebar-scrollbar:before {
                    background: var(--sidebar-border);
                    opacity: 0.5;
                }

                body[data-sidebar-size="sm"] .vertical-menu {
                    width: 60px !important;
                }
                
                body[data-sidebar-size="sm"] .brand-info,
                body[data-sidebar-size="sm"] .brand-sub,
                body[data-sidebar-size="sm"] .menu-name,
                body[data-sidebar-size="sm"] .menu-title,
                body[data-sidebar-size="sm"] .sidebar-search-container {
                    display: none !important;
                }

                body[data-sidebar-size="sm"] .sidebar-brand-box {
                    padding: 12px;
                    justify-content: center;
                }
            </style>

            <div class="sidebar-sticky-top">
                <div class="sidebar-brand-box">
                    <div class="brand-wrapper">
                        <div class="brand-logo">
                            <i data-feather="box"></i>
                        </div>
                        <div class="brand-info">
                            <span class="brand-name">Ersan ELK</span>
                            <span class="brand-sub">Yönetim Paneli</span>
                        </div>
                    </div>
                </div>

                <div class="sidebar-search-container">
                    <div class="d-flex align-items-center gap-1">
                        <div class="position-relative flex-grow-1">
                            <input type="text" class="form-control sidebar-search" id="menu-search-input"
                                placeholder="Menüde ara...">
                            <i data-feather="search" class="search-icon"></i>
                        </div>
                        <div class="dropdown menu-settings-dropdown">
                            <button class="btn btn-sidebar-settings dropdown-toggle" type="button" id="sidebarMenuSettingsBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Menü Ayarları">
                                <i data-feather="settings" style="width: 15px; height: 15px;"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-1" aria-labelledby="sidebarMenuSettingsBtn">
                                <li>
                                    <h6 class="dropdown-header text-muted py-1" style="font-size: 11px; text-transform: uppercase;">Menü Ayarları</h6>
                                </li>
                                <li>
                                    <button class="dropdown-item d-flex align-items-center gap-2 py-2" type="button" id="btn-reset-menu-order">
                                        <i data-feather="rotate-ccw" style="width: 14px; height: 14px; color: #ef4444;"></i>
                                        <span>Varsayılan Menü Sırası</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div data-simplebar class="sidebar-menu-scroll" id="sidebar-menu-scroll">
                <script>
                    // Sayfa render edilirken yüklenmeden önce anında scroll konumunu oku ve uygula
                    (function() {
                        try {
                            var savedPos = localStorage.getItem('sidebar_scroll_top');
                            if (savedPos !== null) {
                                var el = document.getElementById('sidebar-menu-scroll');
                                if (el) {
                                    el.scrollTop = parseInt(savedPos, 10) || 0;
                                }
                            }
                        } catch(e) {}
                    })();
                </script>
                <!--- Sidemenu -->
                <div id="sidebar-menu">

                    <!-- Left Menu Start -->
                    <div id="side-menu" class="side-menu-container">

                <?php foreach ($menu_data as $group_name => $menus): ?>

                    <div class="menu-section" data-group-name="<?php echo htmlspecialchars($group_name); ?>">
                        <div class="menu-title d-flex align-items-center justify-content-between" data-key="t-menu" title="Sıralamak için sürükleyin">
                            <span class="group-title-text"><?php echo htmlspecialchars($group_name); ?></span>
                            <svg class="group-drag-handle" viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
                                <circle cx="9" cy="5" r="1.5"></circle>
                                <circle cx="9" cy="12" r="1.5"></circle>
                                <circle cx="9" cy="19" r="1.5"></circle>
                                <circle cx="15" cy="5" r="1.5"></circle>
                                <circle cx="15" cy="12" r="1.5"></circle>
                                <circle cx="15" cy="19" r="1.5"></circle>
                            </svg>
                        </div>

                        <ul class="metismenu menu-items-list list-unstyled" data-group-name="<?php echo htmlspecialchars($group_name); ?>">
                            <?php foreach ($menus as $menu): ?>
                                <?php
                                if (isset($menu->is_menu) && $menu->is_menu == 0)
                                    continue;

                                $has_children = !empty($menu->children);

                                $visibleChildren = [];
                                if ($has_children) {
                                    foreach ($menu->children as $sub_menu) {
                                        if (isset($sub_menu->is_menu) && $sub_menu->is_menu == 0) continue;
                                        if (!empty($sub_menu->menu_link) && !$Menus->userCanAccessMenuLink($currentUserId, $sub_menu->menu_link)) continue;
                                        $visibleChildren[] = $sub_menu;
                                    }
                                    $has_children = !empty($visibleChildren);
                                }

                                if (!$has_children && !empty($menu->menu_link) && !$Menus->userCanAccessMenuLink($currentUserId, $menu->menu_link)) continue;

                                $is_active = in_array((int) $menu->id, $activeMenuIds);
                                $active_class = $is_active ? 'mm-active' : '';
                                $has_arrow_class = $has_children ? 'has-arrow' : '';
                                $link = $has_children ? 'javascript: void(0);' : Route::Link($menu->menu_link);
                                
                                $isFavorited = in_array((int) $menu->id, $favoriteMenuIds);
                                ?>
                                <li class="<?php echo $active_class; ?> menu-item-draggable" data-menu-id="<?php echo $menu->id; ?>">
                                    <a href="<?php echo $link; ?>"
                                        class="<?php echo $has_arrow_class; ?> waves-effect <?php echo $is_active ? 'active' : ''; ?>">
                                        <?php if (!empty($menu->menu_icon)): ?>
                                            <i data-feather="<?php echo htmlspecialchars($menu->menu_icon); ?>"></i>
                                        <?php endif; ?>
                                        <span class="menu-name"><?php echo htmlspecialchars($menu->menu_name); ?></span>
                                    </a>
                                    <div class="star-btn <?php echo $isFavorited ? 'active' : ''; ?>" 
                                         data-id="<?php echo $menu->id; ?>" 
                                         title="<?php echo $isFavorited ? 'Favorilerden Kaldır' : 'Favorilere Ekle'; ?>">
                                        <i class="<?php echo $isFavorited ? 'fas' : 'far'; ?> fa-star" style="font-size: 11px;"></i>
                                    </div>

                                    <?php if ($has_children): ?>
                                        <ul class="sub-menu mm-collapse <?php echo $is_active ? 'mm-show' : ''; ?>" aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>" data-parent-id="<?php echo $menu->id; ?>">
                                            <?php
                                            foreach ($visibleChildren as $sub_menu):
                                                $is_sub_active = in_array((int) $sub_menu->id, $activeMenuIds);
                                                $isSubFavorited = in_array((int) $sub_menu->id, $favoriteMenuIds);
                                                ?>
                                                <li class="<?php echo $is_sub_active ? 'mm-active' : ''; ?> submenu-item-draggable" data-menu-id="<?php echo $sub_menu->id; ?>">
                                                    <a class="waves-effect <?php echo $is_sub_active ? 'active' : ''; ?>"
                                                        href="<?php echo Route::Link($sub_menu->menu_link); ?>" data-key="t-user-grid">
                                                        <span class="menu-name"><?php echo htmlspecialchars($sub_menu->menu_name); ?></span>
                                                    </a>
                                                    <div class="star-btn <?php echo $isSubFavorited ? 'active' : ''; ?>" 
                                                         data-id="<?php echo $sub_menu->id; ?>" 
                                                         title="<?php echo $isSubFavorited ? 'Favorilerden Kaldır' : 'Favorilere Ekle'; ?>">
                                                        <i class="<?php echo $isSubFavorited ? 'fas' : 'far'; ?> fa-star" style="font-size: 11px;"></i>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                <?php endforeach; ?>

            </div>

        </div>
        <!-- Sidebar -->
    </div>
</div>

<script>
    // Anında render: DOMContentLoaded beklemeden sidebar ikonlarını hemen oluştur
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // Sidebar Scroll Konumu Yönetimi (LocalStorage)
    (function () {
        function getSidebarScrollElement() {
            return document.querySelector('.vertical-menu .simplebar-content-wrapper') 
                || document.getElementById('sidebar-menu-scroll')
                || document.querySelector('.sidebar-menu-scroll') 
                || document.querySelector('.vertical-menu');
        }

        function restoreSidebarScroll() {
            try {
                var savedPos = localStorage.getItem('sidebar_scroll_top');
                if (savedPos === null) {
                    var lock = document.getElementById('sidebar-scroll-lock');
                    if (lock) lock.remove();
                    return false;
                }
                var topVal = parseInt(savedPos, 10) || 0;
                var el = getSidebarScrollElement();
                if (el) {
                    el.scrollTop = topVal;
                    var lock = document.getElementById('sidebar-scroll-lock');
                    if (lock) lock.remove();
                    return true;
                }
            } catch (e) {}
            return false;
        }

        function saveSidebarScroll() {
            try {
                var el = getSidebarScrollElement();
                if (el && typeof el.scrollTop !== 'undefined') {
                    localStorage.setItem('sidebar_scroll_top', el.scrollTop);
                }
            } catch (e) {}
        }

        // Anında ilk geri yükleme
        restoreSidebarScroll();

        // Global capturing scroll dinleyicisi (SimpleBar wrapper oluşturulur oluşturulmaz scroll hareketlerini yakalar)
        var scrollTimer = null;
        document.addEventListener('scroll', function (e) {
            if (e.target && (e.target.classList?.contains('simplebar-content-wrapper') || e.target.id === 'sidebar-menu-scroll' || e.target.classList?.contains('sidebar-menu-scroll'))) {
                if (scrollTimer) clearTimeout(scrollTimer);
                scrollTimer = setTimeout(saveSidebarScroll, 30);
            }
        }, true);

        document.addEventListener('DOMContentLoaded', function () {
            restoreSidebarScroll();
            setTimeout(restoreSidebarScroll, 30);
            setTimeout(restoreSidebarScroll, 100);

            // Menü linklerine tıklandığında anında kaydet
            document.querySelectorAll('#side-menu a, .vertical-menu a').forEach(function (link) {
                link.addEventListener('click', saveSidebarScroll, { passive: true });
            });
        });

        // Sayfadan ayrılırken kaydet
        window.addEventListener('beforeunload', saveSidebarScroll);
        window.addEventListener('pagehide', saveSidebarScroll);
    })();

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // MetisMenu başlat
        if (typeof $ !== 'undefined' && $.fn.metisMenu) {
            $('#side-menu .metismenu').metisMenu();
        }

        // Aktif Menüyü Görünür Alana Otomatik Scroll Etme (yalnızca kaydedilmiş scroll yoksa)
        function scrollToActiveSidebarMenu() {
            try {
                if (localStorage.getItem('sidebar_scroll_top') !== null) {
                    return; // Kullanıcının kayıtlı scroll konumu varsa onu koru
                }
            } catch (e) {}

            const activeEl = document.querySelector('#side-menu li.mm-active a.active, #side-menu a.active, #side-menu li.mm-active');
            const scrollWrapper = document.querySelector('.vertical-menu .simplebar-content-wrapper') || document.querySelector('.vertical-menu');
            if (!activeEl || !scrollWrapper) return;

            const stickyHeader = document.querySelector('.sidebar-sticky-top');
            const stickyHeight = stickyHeader ? stickyHeader.offsetHeight : 120;
            const activeRect = activeEl.getBoundingClientRect();
            const wrapperRect = scrollWrapper.getBoundingClientRect();

            const relativeTop = activeRect.top - wrapperRect.top;
            if (relativeTop < stickyHeight || relativeTop > (wrapperRect.height - 80)) {
                const targetScroll = scrollWrapper.scrollTop + relativeTop - stickyHeight - 20;
                scrollWrapper.scrollTo({
                    top: Math.max(0, targetScroll),
                    behavior: 'smooth'
                });
            }
        }

        setTimeout(scrollToActiveSidebarMenu, 150);
        setTimeout(scrollToActiveSidebarMenu, 400);

        const searchInput = document.getElementById('menu-search-input');
        const sideMenu = document.getElementById('side-menu');

        // 1. Menüde Arama Filtreleme
        if (searchInput && sideMenu) {
            searchInput.addEventListener('input', function () {
                const filter = this.value.toLowerCase().trim();
                const sections = sideMenu.querySelectorAll('.menu-section');

                if (filter === '') {
                    sideMenu.querySelectorAll('li').forEach(li => li.style.display = '');
                    sections.forEach(s => s.style.display = '');
                    return;
                }

                sections.forEach(section => {
                    let sectionHasVisible = false;
                    const topItems = section.querySelectorAll('.menu-items-list > li[data-menu-id]');

                    topItems.forEach(topLi => {
                        let topLiHasVisible = false;
                        const topAnchor = topLi.querySelector(':scope > a');
                        const topText = topAnchor ? topAnchor.textContent.toLowerCase() : '';

                        const subItems = topLi.querySelectorAll('.sub-menu > li[data-menu-id]');
                        let subHasMatch = false;

                        subItems.forEach(subLi => {
                            const subAnchor = subLi.querySelector('a');
                            const subText = subAnchor ? subAnchor.textContent.toLowerCase() : '';
                            if (subText.includes(filter)) {
                                subLi.style.display = '';
                                subHasMatch = true;
                            } else {
                                subLi.style.display = 'none';
                            }
                        });

                        if (topText.includes(filter) || subHasMatch) {
                            topLi.style.display = '';
                            topLiHasVisible = true;
                            if (subHasMatch) {
                                topLi.classList.add('mm-active');
                                const subMenu = topLi.querySelector('ul.sub-menu');
                                if (subMenu) {
                                    subMenu.classList.add('mm-show');
                                    subMenu.style.display = 'block';
                                }
                            }
                        } else {
                            topLi.style.display = 'none';
                        }

                        if (topLiHasVisible) {
                            sectionHasVisible = true;
                        }
                    });

                    section.style.display = sectionHasVisible ? '' : 'none';
                });
            });
        }

        // 2. SortableJS ile Sürükle-Bırak Menü Sıralaması
        function initMenuSortables() {
            if (typeof Sortable === 'undefined' || !sideMenu) return;

            const scrollContainer = document.querySelector('.vertical-menu .simplebar-content-wrapper') || true;

            // A. Grupların Kendi Arasında Sıralanması
            new Sortable(sideMenu, {
                animation: 200,
                handle: '.menu-title',
                draggable: '.menu-section',
                ghostClass: 'sortable-ghost-group',
                chosenClass: 'sortable-chosen-group',
                dragClass: 'sortable-drag-group',
                scroll: scrollContainer,
                scrollSensitivity: 50,
                scrollSpeed: 12,
                bubbleScroll: true,
                swapThreshold: 0.65,
                invertSwap: true,
                onEnd: function() {
                    saveMenuOrderToServer();
                }
            });

            // B. Üst Menülerin Kendi Grubu İçinde Sıralanması
            document.querySelectorAll('.menu-items-list').forEach(function(listEl) {
                const groupName = listEl.getAttribute('data-group-name') || 'default';
                new Sortable(listEl, {
                    group: 'group-menus-' + groupName,
                    animation: 180,
                    draggable: '.menu-item-draggable',
                    ghostClass: 'sortable-ghost-item',
                    chosenClass: 'sortable-chosen-item',
                    filter: '.star-btn, .sub-menu',
                    preventOnFilter: false,
                    scroll: scrollContainer,
                    scrollSensitivity: 50,
                    scrollSpeed: 12,
                    bubbleScroll: true,
                    swapThreshold: 0.65,
                    invertSwap: true,
                    onEnd: function() {
                        saveMenuOrderToServer();
                    }
                });
            });

            // C. Alt Menülerin Kendi Üst Menüsü İçinde Sıralanması
            document.querySelectorAll('ul.sub-menu').forEach(function(subListEl) {
                const parentId = subListEl.getAttribute('data-parent-id') || 'sub';
                new Sortable(subListEl, {
                    group: 'parent-submenus-' + parentId,
                    animation: 180,
                    draggable: '.submenu-item-draggable',
                    ghostClass: 'sortable-ghost-subitem',
                    chosenClass: 'sortable-chosen-subitem',
                    filter: '.star-btn',
                    preventOnFilter: false,
                    scroll: scrollContainer,
                    scrollSensitivity: 50,
                    scrollSpeed: 12,
                    bubbleScroll: true,
                    swapThreshold: 0.65,
                    invertSwap: true,
                    onEnd: function() {
                        saveMenuOrderToServer();
                    }
                });
            });
        }

        // Menü Sırasını Sunucuya Kaydetme Fonksiyonu
        function saveMenuOrderToServer() {
            if (searchInput && searchInput.value.trim() !== '') {
                return; // Arama esnasında filtrelenmiş eksik liste kaydedilmez
            }

            const groups = [];
            const menus = {};
            const submenus = {};

            document.querySelectorAll('#side-menu .menu-section').forEach(function(section) {
                const groupName = section.getAttribute('data-group-name');
                if (!groupName) return;
                groups.push(groupName);

                const groupMenuIds = [];
                const menuList = section.querySelector('.menu-items-list');
                if (menuList) {
                    menuList.querySelectorAll(':scope > li[data-menu-id]').forEach(function(li) {
                        const mId = parseInt(li.getAttribute('data-menu-id'), 10);
                        if (mId > 0) {
                            groupMenuIds.push(mId);

                            const subList = li.querySelector(':scope > ul.sub-menu');
                            if (subList) {
                                const subIds = [];
                                subList.querySelectorAll(':scope > li[data-menu-id]').forEach(function(subLi) {
                                    const sId = parseInt(subLi.getAttribute('data-menu-id'), 10);
                                    if (sId > 0) {
                                        subIds.push(sId);
                                    }
                                });
                                submenus[mId] = subIds;
                            }
                        }
                    });
                }
                menus[groupName] = groupMenuIds;
            });

            fetch('api/menu-order.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ groups, menus, submenus })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') {
                        showToast('Menü sırası kaydedildi');
                    }
                } else {
                    if (typeof showToast === 'function') {
                        showToast(data.message || 'Sıralama kaydedilemedi', true);
                    }
                }
            })
            .catch(err => {
                console.error('Menu save error:', err);
            });
        }

        // 3. Varsayılan Menü Sırasına Sıfırlama Butonu
        const btnReset = document.getElementById('btn-reset-menu-order');
        if (btnReset) {
            btnReset.addEventListener('click', function(e) {
                e.preventDefault();

                const executeReset = () => {
                    fetch('api/menu-order.php?action=reset', {
                        method: 'POST'
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            if (typeof showToast === 'function') {
                                showToast('Menü sırası varsayılana sıfırlandı. Sayfa yenileniyor...');
                            }
                            setTimeout(() => {
                                window.location.reload();
                            }, 600);
                        } else {
                            if (typeof showToast === 'function') {
                                showToast(data.message || 'Sıfırlama başarısız oldu', true);
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Reset error:', err);
                        if (typeof showToast === 'function') {
                            showToast('Sunucu ile iletişim kurulamadı', true);
                        }
                    });
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Varsayılan Sıraya Dönülsün mü?',
                        text: 'Özelleştirdiğiniz menü sırası sıfırlanacak ve sistemin varsayılan menü düzenine geri dönülecektir.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Evet, Sıfırla',
                        cancelButtonText: 'Vazgeç',
                        confirmButtonColor: '#1c84ee',
                        cancelButtonColor: '#74788d'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executeReset();
                        }
                    });
                } else {
                    if (confirm('Menü sıranız varsayılan haline döndürülecektir. Onaylıyor musunuz?')) {
                        executeReset();
                    }
                }
            });
        }

        // Sortable başlat
        initMenuSortables();
    });
</script>

<!-- Custom Context Menu Markup -->
<div id="sidebar-context-menu" class="sidebar-context-menu" style="display: none;">
    <div class="context-menu-item" id="ctx-fav-toggle">
        <i data-feather="star" class="ctx-icon ctx-star-icon"></i>
        <span id="ctx-fav-text">Sık Kullanılanlara Ekle</span>
    </div>
    <div class="context-menu-divider"></div>
    <div class="context-menu-item" id="ctx-open-tab">
        <i data-feather="external-link" class="ctx-icon"></i>
        <span>Yeni Sekmede Aç</span>
    </div>
</div>

<div id="fav-toast-notification" class="fav-toast-notification">
    <i data-feather="check-circle" style="width:16px;height:16px;"></i>
    <span id="fav-toast-text">Sık kullanılanlara eklendi</span>
</div>

<style>
.sidebar-context-menu {
    position: fixed;
    z-index: 99999;
    background: var(--bs-card-bg, #ffffff);
    border: 1px solid var(--sidebar-border, #e9ecef);
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 4px 10px -2px rgba(0, 0, 0, 0.1);
    padding: 6px;
    min-width: 190px;
    backdrop-filter: blur(12px);
    animation: ctxFadeIn 0.12s ease-out;
}

[data-bs-theme="dark"] .sidebar-context-menu {
    background: #1c2228 !important;
    border-color: #283038 !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
}

@keyframes ctxFadeIn {
    from { opacity: 0; transform: scale(0.96); }
    to { opacity: 1; transform: scale(1); }
}

.context-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    font-size: 13px;
    color: var(--bs-body-color, #333);
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    user-select: none;
}

[data-bs-theme="dark"] .context-menu-item {
    color: #ced4da;
}

.context-menu-item:hover {
    background: rgba(28, 132, 238, 0.1);
    color: #1c84ee;
}

[data-bs-theme="dark"] .context-menu-item:hover {
    background: rgba(28, 132, 238, 0.2);
    color: #60a5fa;
}

.context-menu-item .ctx-icon {
    width: 15px;
    height: 15px;
}

.ctx-star-icon {
    color: #f1b44c;
}

.context-menu-divider {
    height: 1px;
    background: var(--sidebar-border, #e9ecef);
    margin: 4px 0;
}

[data-bs-theme="dark"] .context-menu-divider {
    background: #283038;
}

.fav-toast-notification {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 100000;
    background: #10b981;
    color: #ffffff;
    padding: 10px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
    display: flex;
    align-items: center;
    gap: 8px;
    opacity: 0;
    transform: translateY(12px);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    pointer-events: none;
}

.fav-toast-notification.show {
    opacity: 1;
    transform: translateY(0);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctxMenu = document.getElementById('sidebar-context-menu');
        const ctxFavToggle = document.getElementById('ctx-fav-toggle');
        const ctxFavText = document.getElementById('ctx-fav-text');
        const ctxOpenTab = document.getElementById('ctx-open-tab');
        const toast = document.getElementById('fav-toast-notification');
        const toastText = document.getElementById('fav-toast-text');

        let activeContextTarget = null;
        let toastTimeout = null;

        function showToast(message, isError = false) {
            if (!toast) return;
            if (toastTimeout) clearTimeout(toastTimeout);
            toastText.textContent = message;
            toast.style.backgroundColor = isError ? '#ef4444' : '#10b981';
            toast.classList.add('show');
            toastTimeout = setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        window.showToast = showToast;

        function hideContextMenu() {
            if (ctxMenu) ctxMenu.style.display = 'none';
            activeContextTarget = null;
        }

        // 1. Right Click (Context Menu) Handler
        document.addEventListener('contextmenu', function(e) {
            const sidebarItem = e.target.closest('#side-menu li[data-menu-id]');
            const pillItem = e.target.closest('#quick-favorites-bar .quick-fav-pill');

            if (!sidebarItem && !pillItem) {
                hideContextMenu();
                return;
            }

            e.preventDefault();

            let menuId = null;
            let href = '#';
            let title = '';
            let isFav = false;

            if (sidebarItem) {
                menuId = sidebarItem.getAttribute('data-menu-id');
                const linkEl = sidebarItem.querySelector('a');
                href = linkEl ? linkEl.getAttribute('href') : '#';
                const nameEl = sidebarItem.querySelector('.menu-name');
                title = nameEl ? nameEl.textContent.trim() : '';

                const starBtn = sidebarItem.querySelector('.star-btn');
                isFav = starBtn && starBtn.classList.contains('active');
            } else if (pillItem) {
                menuId = pillItem.getAttribute('data-menu-id');
                const linkEl = pillItem.querySelector('a');
                href = linkEl ? linkEl.getAttribute('href') : '#';
                const nameEl = pillItem.querySelector('.pill-title');
                title = nameEl ? nameEl.textContent.trim() : '';
                isFav = true;
            }

            if (!menuId || href === 'javascript: void(0);' || href === 'javascript:void(0);') {
                hideContextMenu();
                return;
            }

            activeContextTarget = { menuId, isFav, href, title };

            if (isFav) {
                ctxFavText.textContent = 'Sık Kullanılanlardan Çıkar';
            } else {
                ctxFavText.textContent = 'Sık Kullanılanlara Ekle';
            }

            const mouseX = e.clientX;
            const mouseY = e.clientY;
            const winWidth = window.innerWidth;
            const winHeight = window.innerHeight;

            ctxMenu.style.display = 'block';
            const menuWidth = ctxMenu.offsetWidth || 190;
            const menuHeight = ctxMenu.offsetHeight || 90;

            let posX = mouseX;
            let posY = mouseY;

            if (mouseX + menuWidth > winWidth) {
                posX = winWidth - menuWidth - 10;
            }
            if (mouseY + menuHeight > winHeight) {
                posY = winHeight - menuHeight - 10;
            }

            ctxMenu.style.left = posX + 'px';
            ctxMenu.style.top = posY + 'px';
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('#sidebar-context-menu')) {
                hideContextMenu();
            }
        });

        document.addEventListener('scroll', hideContextMenu, true);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideContextMenu();
        });

        if (ctxFavToggle) {
            ctxFavToggle.addEventListener('click', function() {
                if (!activeContextTarget || !activeContextTarget.menuId) return;
                const menuId = activeContextTarget.menuId;
                hideContextMenu();
                toggleFavoriteAPI(menuId);
            });
        }

        if (ctxOpenTab) {
            ctxOpenTab.addEventListener('click', function() {
                if (!activeContextTarget || !activeContextTarget.href) return;
                window.open(activeContextTarget.href, '_blank');
                hideContextMenu();
            });
        }

        // 2. Star Button Click Handler in Sidebar
        document.addEventListener('click', function(e) {
            const starBtn = e.target.closest('.star-btn');
            if (!starBtn) return;

            e.preventDefault();
            e.stopPropagation();

            const menuId = starBtn.getAttribute('data-id');
            if (menuId) {
                toggleFavoriteAPI(menuId);
            }
        });

        // 3. Remove Button Click Handler in Quick Favorites Bar
        document.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.pill-remove-btn');
            if (!removeBtn) return;

            e.preventDefault();
            e.stopPropagation();

            const menuId = removeBtn.getAttribute('data-menu-id');
            if (menuId) {
                toggleFavoriteAPI(menuId);
            }
        });

        function toggleFavoriteAPI(menuId) {
            fetch('api/menu-favorites.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'menu_id=' + menuId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    syncFavoritesUI(data);
                    showToast(data.message || 'İşlem başarılı');
                } else {
                    showToast(data.message || 'İşlem gerçekleştirilemedi', true);
                }
            })
            .catch(error => {
                console.error('Error toggling favorite:', error);
                showToast('Sunucu ile iletişim kurulamadı', true);
            });
        }

        function syncFavoritesUI(data) {
            const favoriteIds = (data.favorite_ids || []).map(id => parseInt(id, 10));
            const favoritesList = data.favorites || [];

            // Update Sidebar Stars
            const allStarBtns = document.querySelectorAll('.star-btn');
            allStarBtns.forEach(star => {
                const id = parseInt(star.getAttribute('data-id'), 10);
                const isFav = favoriteIds.includes(id);
                if (isFav) {
                    star.classList.add('active');
                    const icon = star.querySelector('i');
                    if (icon) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                    }
                    star.setAttribute('title', 'Favorilerden Kaldır');
                } else {
                    star.classList.remove('active');
                    const icon = star.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                    }
                    star.setAttribute('title', 'Favorilere Ekle');
                }
            });

            // Update Top Quick Favorites Bar
            const quickFavItems = document.getElementById('quick-fav-items');
            const emptyHint = document.getElementById('quick-fav-empty-hint');
            const currentUrlParams = new URLSearchParams(window.location.search);
            const currentPage = currentUrlParams.get('p') || '';

            if (quickFavItems) {
                const existingPills = quickFavItems.querySelectorAll('.quick-fav-pill');
                existingPills.forEach(pill => pill.remove());

                favoritesList.forEach(fav => {
                    const hrefUrl = (fav.link.indexOf('http') === 0 || fav.link.indexOf('javascript:') === 0) ? fav.link : `index.php?p=${fav.link}`;
                    const isActive = (currentPage !== '' && (currentPage === fav.link || currentPage.indexOf(fav.link + '/') === 0 || fav.link.indexOf(currentPage) !== false));
                    
                    const pill = document.createElement('div');
                    pill.className = `quick-fav-pill ${isActive ? 'active' : ''}`;
                    pill.setAttribute('data-menu-id', fav.id);
                    pill.setAttribute('data-link', fav.link);

                    pill.innerHTML = `
                        <a href="${hrefUrl}" class="quick-fav-link">
                            <i data-feather="${fav.icon || 'circle'}" class="pill-icon"></i>
                            <span class="pill-title">${fav.title}</span>
                        </a>
                        <button type="button" class="pill-remove-btn" data-menu-id="${fav.id}" title="Sık kullanılanlardan çıkar">
                            <i data-feather="x" class="pill-remove-icon"></i>
                        </button>
                    `;

                    if (emptyHint) {
                        quickFavItems.insertBefore(pill, emptyHint);
                    } else {
                        quickFavItems.appendChild(pill);
                    }
                });

                if (emptyHint) {
                    emptyHint.style.display = favoritesList.length > 0 ? 'none' : '';
                }
            }

            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        }
    });
</script>
