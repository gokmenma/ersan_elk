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

<div class="vertical-menu" id="navbar">
    <canvas id="sidebar-particles-canvas" class="sidebar-particles-canvas" style="display: none;"></canvas>

    <style>
        /* Sidebar Parçacık Ağı Tuvali */
        .sidebar-particles-canvas {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            pointer-events: none !important;
            z-index: 0 !important;
            opacity: 0.95;
            transition: opacity 0.3s ease;
        }

        /* Sidebar Kapsayıcısı */
        .sidebar, #navbar, .vertical-menu {
            position: fixed;
            overflow: hidden !important;
        }

        /* İçerik katmanı */
        .sidebar > .container-fluid,
        .sidebar > .sidebar-content,
        .sidebar-nav,
        .sidebar-menu-scroll,
        #sidebar-menu,
        #side-menu {
            position: relative !important;
            z-index: 1 !important;
        }

        .sidebar-sticky-top {
            position: relative !important;
            z-index: 1050 !important;
        }

        .menu-settings-dropdown {
            position: relative !important;
            z-index: 1060 !important;
        }

        .sidebar-menu-scroll,
        #sidebar-menu,
        #side-menu,
        .metismenu {
            background: transparent !important;
        }

        :root {
            --sidebar-bg: #ffffff;
            --sidebar-border: rgba(0, 0, 0, 0.06);
            --sidebar-item-hover: rgba(0, 0, 0, 0.05);
            --sidebar-item-active: rgba(0, 0, 0, 0.09);
            --sidebar-foreground: #3f3f46;
            --sidebar-muted: #71717a;
            --sidebar-accent: #18181b;
            --sidebar-font: var(--bs-font-sans-serif, inherit);
        }

        [data-bs-theme="dark"] {
            --sidebar-bg: #191e22;
            --sidebar-border: rgba(255, 255, 255, 0.08);
            --sidebar-item-hover: rgba(255, 255, 255, 0.08);
            --sidebar-item-active: rgba(255, 255, 255, 0.14);
            --sidebar-foreground: #94a3b8;
            --sidebar-muted: #64748b;
            --sidebar-accent: #1c84ee;
        }

        body[data-sidebar="dark"] {
            --sidebar-bg: #191e22;
            --sidebar-border: rgba(255, 255, 255, 0.08);
            --sidebar-item-hover: rgba(255, 255, 255, 0.08);
            --sidebar-item-active: rgba(255, 255, 255, 0.14);
            --sidebar-foreground: #94a3b8;
            --sidebar-muted: #64748b;
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

                body[data-sidebar="dark"] .vertical-menu,
                body[data-sidebar="dark"] .sidebar-sticky-top {
                    background-color: #191e22 !important;
                    border-right-color: #22292f !important;
                }

                body[data-sidebar="dark"] #sidebar-menu a {
                    color: #94a3b8 !important;
                }

                body[data-sidebar="dark"] #side-menu .menu-title {
                    color: #64748b !important;
                    font-size: 11px !important;
                    font-weight: 600 !important;
                    text-transform: uppercase !important;
                    letter-spacing: 0.5px !important;
                }

                body[data-sidebar="dark"] .brand-name {
                    color: #f1f5f9 !important;
                }

                body[data-sidebar="dark"] .brand-sub,
                body[data-sidebar="dark"] #sidebar-menu ul.sub-menu li a {
                    color: #8291a5 !important;
                }

                body[data-sidebar="dark"] #sidebar-menu a i,
                body[data-sidebar="dark"] #sidebar-menu a svg,
                body[data-sidebar="dark"] .sidebar-search-container .search-icon {
                    color: #8291a5 !important;
                    stroke: #8291a5 !important;
                }

                body[data-sidebar="dark"] .sidebar-search {
                    background-color: #262d35 !important;
                    border-color: #333c46 !important;
                    color: #e2e8f0 !important;
                }

                body[data-sidebar="dark"] .sidebar-search:focus {
                    background-color: #2e3742 !important;
                    border-color: rgba(255, 255, 255, 0.25) !important;
                    color: #ffffff !important;
                    box-shadow: none !important;
                }

                body[data-sidebar="dark"] .sidebar-search::placeholder {
                    color: #64748b !important;
                }

                body[data-sidebar="dark"] #sidebar-menu ul li a:hover {
                    background-color: rgba(255, 255, 255, 0.08) !important;
                    backdrop-filter: blur(3px);
                    -webkit-backdrop-filter: blur(3px);
                    color: #f1f5f9 !important;
                }

                body[data-sidebar="dark"] #sidebar-menu ul li a:hover i,
                body[data-sidebar="dark"] #sidebar-menu ul li a:hover svg {
                    color: #f1f5f9 !important;
                    stroke: #f1f5f9 !important;
                }

                body[data-sidebar="dark"] #sidebar-menu ul li a.active,
                body[data-sidebar="dark"] #sidebar-menu ul li.mm-active > a {
                    background-color: rgba(255, 255, 255, 0.14) !important;
                    backdrop-filter: blur(3px);
                    -webkit-backdrop-filter: blur(3px);
                    color: #ffffff !important;
                }

                body[data-sidebar="dark"] #sidebar-menu ul li a.active i,
                body[data-sidebar="dark"] #sidebar-menu ul li.mm-active > a i,
                body[data-sidebar="dark"] #sidebar-menu ul li a.active svg,
                body[data-sidebar="dark"] #sidebar-menu ul li.mm-active > a svg {
                    color: var(--bs-primary, #38bdf8) !important;
                    stroke: var(--bs-primary, #38bdf8) !important;
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
                    background-color: transparent !important;
                    background-image: none !important;
                    padding: 12px 8px 12px 8px;
                    margin: 0;
                    border-bottom: 1px solid var(--sidebar-border);
                    transition: all 0.2s ease;
                }

                .sidebar-menu-scroll {
                    flex: 1 1 auto;
                    min-height: 0;
                    height: 100%;
                    overflow-y: auto;
                }

                body[data-sidebar="red"] .sidebar-sticky-top,
                body[data-sidebar="purple"] .sidebar-sticky-top,
                body[data-sidebar="slate"] .sidebar-sticky-top,
                body[data-sidebar="emerald"] .sidebar-sticky-top,
                body[data-sidebar="orange"] .sidebar-sticky-top,
                body[data-sidebar="rose"] .sidebar-sticky-top,
                body[data-sidebar="ersan"] .sidebar-sticky-top,
                body[data-sidebar="teal"] .sidebar-sticky-top,
                body[data-sidebar="cyan"] .sidebar-sticky-top,
                body[data-sidebar="default"] .sidebar-sticky-top,
                body[data-sidebar="brand"] .sidebar-sticky-top,
                body[data-sidebar="dark"] .sidebar-sticky-top,
                body[data-sidebar="light"] .sidebar-sticky-top {
                    background-color: transparent !important;
                    background-image: none !important;
                }

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
                    padding-right: 32px !important;
                    height: 38px;
                    font-size: 13px;
                    backdrop-filter: blur(4px);
                    -webkit-backdrop-filter: blur(4px);
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
                    z-index: 2;
                }

                .sidebar-search-container .btn-clear-search {
                    position: absolute;
                    right: 8px;
                    top: 50%;
                    transform: translateY(-50%);
                    background: rgba(125, 138, 156, 0.18);
                    border: none;
                    color: var(--sidebar-muted, #8590a5);
                    width: 20px;
                    height: 20px;
                    border-radius: 50%;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    padding: 0;
                    cursor: pointer;
                    z-index: 5;
                    transition: all 0.15s ease;
                }

                .sidebar-search-container .btn-clear-search:hover {
                    color: #ffffff !important;
                    background: #ef4444 !important;
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
                    backdrop-filter: blur(4px);
                    -webkit-backdrop-filter: blur(4px);
                    transition: all 0.2s ease;
                }

                .macos-window-controls {
                    display: none !important;
                }

                .settings-icon-mac {
                    display: none !important;
                }

                .settings-icon-default {
                    display: inline-block !important;
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

                /* Sidebar Ayarlar Popover Menüsü - Global & Tema Uyumlu */
                .menu-settings-dropdown {
                    position: relative !important;
                    z-index: 1060 !important;
                }

                .menu-settings-dropdown .dropdown-menu,
                .dropdown-menu.mac-popover-menu,
                .mac-popover-menu {
                    position: absolute !important;
                    top: calc(100% + 8px) !important;
                    right: 0 !important;
                    left: auto !important;
                    transform: none !important;
                    background: #202531 !important;
                    background-color: #202531 !important;
                    border: 1px solid #363e52 !important;
                    border-radius: 10px !important;
                    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.75), 0 2px 8px rgba(0, 0, 0, 0.5) !important;
                    padding: 6px !important;
                    width: 230px !important;
                    min-width: 230px !important;
                    max-width: 230px !important;
                    margin: 0 !important;
                    z-index: 99999 !important;
                    opacity: 1 !important;
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                    overflow: visible !important;
                }

                /* Tooltip Oku */
                .menu-settings-dropdown .dropdown-menu::before,
                .dropdown-menu.mac-popover-menu::before {
                    content: '' !important;
                    position: absolute !important;
                    top: -5px !important;
                    right: 12px !important;
                    width: 10px !important;
                    height: 10px !important;
                    background: #202531 !important;
                    border-top: 1px solid #363e52 !important;
                    border-left: 1px solid #363e52 !important;
                    transform: rotate(45deg) !important;
                    z-index: 100000 !important;
                    border-top-left-radius: 2px !important;
                }

                .mac-dropdown-group {
                    background: rgba(255, 255, 255, 0.04) !important;
                    border: 1px solid rgba(255, 255, 255, 0.08) !important;
                    border-radius: 7px !important;
                    padding: 3px !important;
                    margin-bottom: 5px !important;
                }

                .mac-dropdown-group:last-child {
                    margin-bottom: 0 !important;
                }

                .dropdown-menu.mac-popover-menu .dropdown-item,
                .menu-settings-dropdown .dropdown-item,
                .mac-popover-item {
                    color: #f1f5f9 !important;
                    font-size: 12.5px !important;
                    font-weight: 500 !important;
                    line-height: 1.4 !important;
                    padding: 6px 10px !important;
                    border-radius: 5px !important;
                    margin: 0 !important;
                    width: 100% !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    background: transparent !important;
                    border: none !important;
                    cursor: pointer !important;
                    transition: background 0.15s ease, color 0.15s ease !important;
                }

                .dropdown-menu.mac-popover-menu .dropdown-item:hover,
                .dropdown-menu.mac-popover-menu .dropdown-item:focus,
                .menu-settings-dropdown .dropdown-item:hover,
                .menu-settings-dropdown .dropdown-item:focus,
                .mac-popover-item:hover,
                .mac-popover-item:focus {
                    background: rgba(255, 255, 255, 0.12) !important;
                    color: #ffffff !important;
                }

                .dropdown-menu.mac-popover-menu .dropdown-item.disabled,
                .menu-settings-dropdown .dropdown-item.disabled,
                .mac-popover-item.disabled {
                    color: rgba(255, 255, 255, 0.35) !important;
                    background: transparent !important;
                    cursor: default !important;
                    pointer-events: none !important;
                }

                .mac-item-shortcut {
                    font-size: 11px !important;
                    color: #94a3b8 !important;
                    font-family: inherit !important;
                    padding: 1px 6px !important;
                    border-radius: 4px !important;
                    background: rgba(255, 255, 255, 0.07) !important;
                    margin-left: auto !important;
                    display: inline-flex !important;
                    align-items: center !important;
                    line-height: 1.3 !important;
                }

                /* Açık Sidebar Temaları için Renk Uyarlaması */
                body[data-sidebar="light"] .menu-settings-dropdown .dropdown-menu,
                body[data-sidebar="light"] .dropdown-menu.mac-popover-menu {
                    background: #ffffff !important;
                    background-color: #ffffff !important;
                    border: 1px solid #cbd5e1 !important;
                    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12), 0 2px 6px rgba(0, 0, 0, 0.05) !important;
                }

                body[data-sidebar="light"] .menu-settings-dropdown .dropdown-menu::before,
                body[data-sidebar="light"] .dropdown-menu.mac-popover-menu::before {
                    background: #ffffff !important;
                    border-top: 1px solid #cbd5e1 !important;
                    border-left: 1px solid #cbd5e1 !important;
                }

                body[data-sidebar="light"] .mac-dropdown-group {
                    background: #f8fafc !important;
                    border: 1px solid #e2e8f0 !important;
                }

                body[data-sidebar="light"] .dropdown-menu.mac-popover-menu .dropdown-item,
                body[data-sidebar="light"] .menu-settings-dropdown .dropdown-item,
                body[data-sidebar="light"] .mac-popover-item {
                    color: #334155 !important;
                }

                body[data-sidebar="light"] .dropdown-menu.mac-popover-menu .dropdown-item:hover,
                body[data-sidebar="light"] .dropdown-menu.mac-popover-menu .dropdown-item:focus,
                body[data-sidebar="light"] .menu-settings-dropdown .dropdown-item:hover,
                body[data-sidebar="light"] .menu-settings-dropdown .dropdown-item:focus,
                body[data-sidebar="light"] .mac-popover-item:hover,
                body[data-sidebar="light"] .mac-popover-item:focus {
                    background: #e2e8f0 !important;
                    color: #0f172a !important;
                }

                body[data-sidebar="light"] .mac-item-shortcut {
                    color: #64748b !important;
                    background: #e2e8f0 !important;
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
                    transition: background-color 0.2s ease, color 0.2s ease, padding 0.2s ease, backdrop-filter 0.2s ease;
                    margin: 2px 12px 2px 4px;
                    position: relative;
                }

                #sidebar-menu #side-menu li a:hover,
                #sidebar-menu ul li a:hover {
                    background-color: var(--sidebar-item-hover) !important;
                    backdrop-filter: blur(3px);
                    -webkit-backdrop-filter: blur(3px);
                    color: #f1f5f9 !important;
                    border-radius: 8px !important;
                }

                #sidebar-menu #side-menu li.mm-active > a,
                #sidebar-menu #side-menu li a.active,
                #sidebar-menu ul li.mm-active > a,
                #sidebar-menu ul li a.active {
                    background-color: var(--sidebar-item-active) !important;
                    backdrop-filter: blur(3px);
                    -webkit-backdrop-filter: blur(3px);
                    color: #ffffff !important;
                    font-weight: 600;
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
                    background-color: var(--sidebar-item-hover) !important;
                    backdrop-filter: blur(3px);
                    -webkit-backdrop-filter: blur(3px);
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
                    transition: opacity 0.16s ease, color 0.16s ease, background-color 0.16s ease;
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

                /* macOS Finder Sidebar — neutral graphite, compact rows and monochrome icons (Tahoe style) */
                html[data-theme-preset="macos-dark"] body .vertical-menu,
                html[data-theme-preset="macos-dark"] body[data-sidebar] .vertical-menu,
                html[data-theme-preset="macos-dark"] body[data-sidebar="dark"] .vertical-menu {
                    --sidebar-bg: rgba(30, 31, 35, 0.96);
                    --sidebar-border: rgba(255, 255, 255, 0.10);
                    --sidebar-foreground: rgba(255, 255, 255, 0.85);
                    --sidebar-muted: rgba(235, 235, 245, 0.42);
                    --sidebar-item-hover: rgba(255, 255, 255, 0.08);
                    --sidebar-item-active: rgba(255, 255, 255, 0.16);
                    background:
                        linear-gradient(180deg, rgba(34, 35, 40, 0.97) 0%, rgba(26, 27, 30, 0.97) 100%) !important;
                    backdrop-filter: blur(24px) saturate(140%) !important;
                    -webkit-backdrop-filter: blur(24px) saturate(140%) !important;
                    border-color: rgba(255, 255, 255, 0.08) !important;
                    box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
                    contain: paint;
                    isolation: isolate;
                    transform: translateZ(0);
                    backface-visibility: hidden;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-sticky-top {
                    display: flex !important;
                    flex-direction: row !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    padding: 14px 14px 8px 14px !important;
                    background: transparent !important;
                    border-bottom: none !important;
                    box-shadow: none !important;
                    gap: 6px !important;
                    position: relative !important;
                    z-index: 1000 !important;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-brand-box {
                    min-height: auto !important;
                    height: auto !important;
                    padding: 0 !important;
                    margin: 0 !important;
                    gap: 10px !important;
                    display: flex !important;
                    align-items: center !important;
                    flex: 1 1 auto !important;
                    background: transparent !important;
                    border-bottom: none !important;
                }

                html[data-theme-preset="macos-dark"] body .macos-window-controls {
                    width: auto !important;
                    gap: 6px !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    display: flex !important;
                    align-items: center !important;
                    flex-shrink: 0 !important;
                }

                html[data-theme-preset="macos-dark"] body .macos-dot {
                    width: 11px !important;
                    height: 11px !important;
                    box-shadow: none;
                    border: 0.5px solid rgba(0, 0, 0, 0.25);
                }

                html[data-theme-preset="macos-dark"] body .sidebar-search-container {
                    padding: 0 !important;
                    margin: 0 !important;
                    flex-shrink: 0 !important;
                    position: relative !important;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-search-container .position-relative {
                    display: flex !important;
                    align-items: center !important;
                }

                /* Arama simgesi: Finder gibi çerçevesiz ikon, tıklandığında genişler */
                html[data-theme-preset="macos-dark"] body .sidebar-search {
                    width: 24px !important;
                    height: 24px !important;
                    padding: 0 0 0 24px !important;
                    background: transparent !important;
                    border: none !important;
                    border-radius: 4px !important;
                    color: #ffffff !important;
                    box-shadow: none !important;
                    font-size: 11.5px !important;
                    cursor: pointer !important;
                    transition: width 0.22s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.15s ease, padding 0.15s ease !important;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-search:hover {
                    background: rgba(255, 255, 255, 0.08) !important;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-search:focus,
                html[data-theme-preset="macos-dark"] body .sidebar-search:not(:placeholder-shown) {
                    width: 96px !important;
                    padding-left: 24px !important;
                    padding-right: 20px !important;
                    cursor: text !important;
                    background: rgba(255, 255, 255, 0.12) !important;
                    border: 1px solid rgba(255, 255, 255, 0.18) !important;
                    border-radius: 6px !important;
                    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.08) !important;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-search::placeholder {
                    color: rgba(235, 235, 245, 0.38) !important;
                }

                html[data-theme-preset="macos-dark"] body .sidebar-search-container .search-icon {
                    left: 5px !important;
                    top: 50% !important;
                    transform: translateY(-50%) !important;
                    width: 14px !important;
                    height: 14px !important;
                    color: rgba(255, 255, 255, 0.65) !important;
                    pointer-events: none !important;
                }

                html[data-theme-preset="macos-dark"] body .brand-wrapper {
                    min-width: 0;
                    margin: 0 !important;
                    gap: 0;
                    display: flex !important;
                    align-items: center !important;
                }

                html[data-theme-preset="macos-dark"] body .brand-logo,
                html[data-theme-preset="macos-dark"] body .brand-sub {
                    display: none !important;
                }

                html[data-theme-preset="macos-dark"] body .brand-name {
                    max-width: 90px !important;
                    overflow: hidden;
                    color: rgba(255, 255, 255, 0.88) !important;
                    font-size: 13px !important;
                    font-weight: 600 !important;
                    line-height: 1.2;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }

                /* Üç Nokta Ayarlar Butonu (Çerçevesiz Finder Stili) & Popover Menü */
                html[data-theme-preset="macos-dark"] body .menu-settings-dropdown {
                    flex-shrink: 0 !important;
                    position: relative !important;
                }

                html[data-theme-preset="macos-dark"] body .btn-sidebar-settings {
                    width: 24px !important;
                    height: 24px !important;
                    padding: 0 !important;
                    background: transparent !important;
                    border: none !important;
                    border-radius: 4px !important;
                    color: rgba(255, 255, 255, 0.65) !important;
                    box-shadow: none !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    flex-shrink: 0 !important;
                }

                html[data-theme-preset="macos-dark"] body .btn-sidebar-settings i,
                html[data-theme-preset="macos-dark"] body .btn-sidebar-settings svg {
                    width: 15px !important;
                    height: 15px !important;
                }

                html[data-theme-preset="macos-dark"] body .btn-sidebar-settings:hover,
                html[data-theme-preset="macos-dark"] body .btn-sidebar-settings[aria-expanded="true"] {
                    background: rgba(255, 255, 255, 0.14) !important;
                    color: #ffffff !important;
                }

                /* GNOME / macOS Finder Stili Açılır Menü (Popover Dropdown) - Tooltip Ok Desteği ve Merkezleme */
                html[data-theme-preset="macos-dark"] body .menu-settings-dropdown .dropdown-menu,
                html[data-theme-preset="macos-dark"] body .dropdown-menu.mac-popover-menu,
                html[data-theme-preset="macos-dark"] body .mac-popover-menu {
                    position: absolute !important;
                    top: calc(100% + 8px) !important;
                    right: -4px !important;
                    left: auto !important;
                    transform: none !important;
                    background: #303030 !important;
                    background-color: #303030 !important;
                    border: 1px solid #484848 !important;
                    border-radius: 12px !important;
                    box-shadow: 0 14px 32px rgba(0, 0, 0, 0.75), 0 2px 6px rgba(0, 0, 0, 0.5) !important;
                    padding: 7px !important;
                    width: 220px !important;
                    min-width: 220px !important;
                    max-width: 220px !important;
                    margin: 0 !important;
                    z-index: 99999 !important;
                    opacity: 1 !important;
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                    overflow: visible !important;
                }

                /* Üç Noktayı Hedefleyen Tooltip / Popover Üçgen Oku */
                html[data-theme-preset="macos-dark"] body .menu-settings-dropdown .dropdown-menu::before,
                html[data-theme-preset="macos-dark"] body .dropdown-menu.mac-popover-menu::before {
                    content: '' !important;
                    position: absolute !important;
                    top: -5px !important;
                    right: 11px !important;
                    width: 10px !important;
                    height: 10px !important;
                    background: #303030 !important;
                    border-top: 1px solid #484848 !important;
                    border-left: 1px solid #484848 !important;
                    transform: rotate(45deg) !important;
                    z-index: 100000 !important;
                    border-top-left-radius: 2px !important;
                }

                html[data-theme-preset="macos-dark"] body .mac-dropdown-group {
                    background: #242424 !important;
                    border: 1px solid #383838 !important;
                    border-radius: 8px !important;
                    padding: 3px !important;
                    margin-bottom: 6px !important;
                }

                html[data-theme-preset="macos-dark"] body .mac-dropdown-group:last-child {
                    margin-bottom: 0 !important;
                }

                html[data-theme-preset="macos-dark"] body .dropdown-item,
                html[data-theme-preset="macos-dark"] body .mac-popover-item {
                    color: #ffffff !important;
                    font-size: 13px !important;
                    font-weight: 400 !important;
                    line-height: 1.4 !important;
                    padding: 6px 10px !important;
                    border-radius: 6px !important;
                    margin: 0 !important;
                    width: 100% !important;
                    display: flex !important;
                    align-items: center !important;
                    justify-content: space-between !important;
                    background: transparent !important;
                    border: none !important;
                    cursor: pointer !important;
                    transition: background 0.12s ease, color 0.12s ease !important;
                }

                html[data-theme-preset="macos-dark"] body .dropdown-item:hover,
                html[data-theme-preset="macos-dark"] body .dropdown-item:focus,
                html[data-theme-preset="macos-dark"] body .mac-popover-item:hover,
                html[data-theme-preset="macos-dark"] body .mac-popover-item:focus {
                    background: #3b3b3b !important;
                    color: #ffffff !important;
                }

                html[data-theme-preset="macos-dark"] body .dropdown-item.disabled,
                html[data-theme-preset="macos-dark"] body .mac-popover-item.disabled {
                    color: #5a5a5a !important;
                    background: transparent !important;
                    cursor: default !important;
                    pointer-events: none !important;
                }

                html[data-theme-preset="macos-dark"] body .mac-item-shortcut {
                    font-size: 12px !important;
                    font-weight: 400 !important;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
                    color: #8c8c8c !important;
                    margin-left: auto !important;
                    padding-left: 8px !important;
                }

                html[data-theme-preset="macos-dark"] body .dropdown-item:hover .mac-item-shortcut,
                html[data-theme-preset="macos-dark"] body .mac-popover-item:hover .mac-item-shortcut {
                    color: #b0b0b0 !important;
                }

                html[data-theme-preset="macos-dark"] body .dropdown-item.disabled .mac-item-shortcut,
                html[data-theme-preset="macos-dark"] body .mac-popover-item.disabled .mac-item-shortcut {
                    color: #4a4a4a !important;
                }

                /* Sidebar içerisi tamamen kesintisiz ve akıcı (çizgisiz) */
                html[data-theme-preset="macos-dark"] body .sidebar-menu-scroll {
                    border-top: none !important;
                }

                html[data-theme-preset="macos-dark"] body #sidebar-menu {
                    padding: 4px 6px 16px !important;
                }

                html[data-theme-preset="macos-dark"] body .menu-section {
                    margin-bottom: 4px;
                }

                html[data-theme-preset="macos-dark"] body #sidebar-menu .menu-title,
                html[data-theme-preset="macos-dark"] body #side-menu .menu-title {
                    min-height: auto !important;
                    padding: 12px 10px 4px !important;
                    background: transparent !important;
                    color: rgba(235, 235, 245, 0.38) !important;
                    font-size: 10.5px !important;
                    font-weight: 600 !important;
                    letter-spacing: 0.04em !important;
                    text-transform: uppercase !important;
                }

                /* Ana menü satırları */
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li > a,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul > li > a {
                    min-height: 32px !important;
                    margin: 1px 6px !important;
                    padding: 6px 10px !important;
                    gap: 10px !important;
                    border: 1px solid transparent !important;
                    border-radius: 7px !important;
                    color: rgba(255, 255, 255, 0.85) !important;
                    font-size: 13px !important;
                    font-weight: 450 !important;
                    line-height: 18px;
                    transition: background-color .15s ease, color .12s ease !important;
                }

                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li > a i,
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li > a svg,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul > li > a i,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul > li > a svg {
                    width: 16px !important;
                    height: 16px !important;
                    min-width: 16px !important;
                    margin: 0 !important;
                    color: rgba(255, 255, 255, 0.75) !important;
                    stroke: currentColor !important;
                    stroke-width: 1.65 !important;
                }

                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu ul li > a:hover,
                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu #side-menu li > a:hover {
                    background: rgba(255, 255, 255, 0.08) !important;
                    border-color: transparent !important;
                    color: #ffffff !important;
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                }

                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu ul li > a:hover i,
                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu ul li > a:hover svg,
                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu #side-menu li > a:hover i,
                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu #side-menu li > a:hover svg {
                    color: #ffffff !important;
                    stroke: currentColor !important;
                }

                /* Alt Menü Kırılımları — Orijinal Hiyerarşi Korundu */
                html[data-theme-preset="macos-dark"] body .sub-menu {
                    padding: 0 0 0 12px !important;
                    margin: 0 0 0 28px !important;
                    list-style: none !important;
                    border-left: 1px solid rgba(255, 255, 255, 0.12) !important;
                    position: relative !important;
                }

                html[data-theme-preset="macos-dark"] body #sidebar-menu .sub-menu li a,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul li ul.sub-menu li a {
                    font-size: 13px !important;
                    padding-left: 1rem !important;
                    padding-right: 28px !important;
                    color: rgba(235, 236, 239, 0.78) !important;
                    border-radius: 7px !important;
                    margin: 2px 10px 2px 4px !important;
                    white-space: nowrap !important;
                    border: 1px solid transparent !important;
                    min-height: 30px !important;
                    transition: background-color .15s ease, color .12s ease, padding-left .15s ease !important;
                }

                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu .sub-menu li a:hover,
                html[data-theme-preset="macos-dark"] body .vertical-menu #sidebar-menu ul li ul.sub-menu li a:hover {
                    background-color: rgba(255, 255, 255, 0.08) !important;
                    color: #ffffff !important;
                    padding-left: 1.2rem !important;
                }

                /* SADECE GERÇEK AKTİF ÖĞE (Seçili Sayfa) Dolu Pill Alır */
                html[data-theme-preset="macos-dark"] body #sidebar-menu .sub-menu li a.active,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul li ul.sub-menu li a.active,
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li:not(:has(.sub-menu)) > a.active,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul > li:not(:has(.sub-menu)) > a.active {
                    background: rgba(255, 255, 255, 0.16) !important;
                    border-color: rgba(255, 255, 255, 0.04) !important;
                    color: #ffffff !important;
                    font-weight: 500 !important;
                    box-shadow: inset 0 0.5px 0.5px rgba(255, 255, 255, 0.14), 0 1px 2px rgba(0, 0, 0, 0.15) !important;
                    backdrop-filter: none !important;
                    -webkit-backdrop-filter: none !important;
                }

                html[data-theme-preset="macos-dark"] body #sidebar-menu .sub-menu li a.active {
                    padding-left: 1.2rem !important;
                }

                /* Alt menülü üst başlık açıkken çift kutu olmasını engelle, sadece açık/parlak göster */
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li.mm-active:has(.sub-menu) > a,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul > li.mm-active:has(.sub-menu) > a {
                    background: rgba(255, 255, 255, 0.05) !important;
                    color: #ffffff !important;
                    font-weight: 500 !important;
                    border-color: transparent !important;
                    box-shadow: none !important;
                }

                html[data-theme-preset="macos-dark"] body #sidebar-menu ul li a.active i,
                html[data-theme-preset="macos-dark"] body #sidebar-menu ul li a.active svg,
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu li a.active i,
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu li a.active svg,
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li.mm-active > a i,
                html[data-theme-preset="macos-dark"] body #sidebar-menu #side-menu > li.mm-active > a svg {
                    color: #ffffff !important;
                    stroke: currentColor !important;
                }

                html[data-theme-preset="macos-dark"] body .has-arrow::after {
                    right: 14px !important;
                    width: 5px !important;
                    height: 5px !important;
                    border-color: rgba(235, 235, 245, 0.45) !important;
                }

                html[data-theme-preset="macos-dark"] body .star-btn {
                    color: rgba(235, 235, 245, 0.35) !important;
                }

                html[data-theme-preset="macos-dark"] body .vertical-menu .sidebar-menu-scroll {
                    position: relative !important;
                    z-index: 1 !important;
                }

                html[data-theme-preset="macos-dark"] body .vertical-menu .sidebar-sticky-top {
                    position: relative !important;
                    z-index: 1000 !important;
                }

                /* Dar Finder sidebar: esit dis bosluk, gizli ince scrollbar ve yumusak koseler */
                @media (min-width: 992px) {
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu {
                        left: var(--mac-sidebar-left, 3px) !important;
                        width: 60px !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] #page-topbar,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .quick-favorites-bar {
                        left: calc(var(--mac-sidebar-left, 3px) + 60px + var(--mac-window-gap, 6px)) !important;
                        right: var(--mac-window-gap, 6px) !important;
                        width: calc(100% - var(--mac-sidebar-left, 3px) - 60px - (var(--mac-window-gap, 6px) * 2)) !important;
                        margin: 0 !important;
                        border-radius: 16px !important;
                        overflow: visible !important;
                        box-sizing: border-box !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] #page-topbar {
                        top: var(--mac-window-gap, 6px) !important;
                        height: 60px !important;
                        min-height: 60px !important;
                        max-height: 60px !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] #page-topbar .navbar-header {
                        height: 58px !important;
                        min-height: 58px !important;
                        max-height: 58px !important;
                        box-sizing: border-box !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] #page-topbar::before {
                        border-radius: 16px !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .main-content {
                        margin-left: calc(var(--mac-sidebar-left, 3px) + 60px) !important;
                        margin-right: 0 !important;
                        width: calc(100% - var(--mac-sidebar-left, 3px) - 60px) !important;
                        padding: 0 !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .main-content .page-content,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .main-content .container-fluid {
                        padding-left: var(--mac-window-gap, 6px) !important;
                        padding-right: var(--mac-window-gap, 6px) !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"]:not(:has(#quick-favorites-bar)) .main-content .page-content {
                        padding-top: calc(var(--mac-window-gap, 6px) + 60px + var(--mac-window-gap, 6px)) !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"]:has(#quick-favorites-bar) .main-content .page-content {
                        padding-top: calc(var(--mac-window-gap, 6px) + 60px + var(--mac-window-gap, 6px) + 42px + var(--mac-window-gap, 6px)) !important;
                    }

                    /* Bootstrap ic yatay payini dar modda bir kez uygula; ikinci 6px'i geri al. */
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .main-content .page-content > .container-fluid {
                        position: relative !important;
                        left: calc(var(--mac-window-gap, 6px) * -1) !important;
                        width: calc(100% + var(--mac-window-gap, 6px)) !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu .simplebar-track.simplebar-vertical {
                        width: 2px !important;
                        right: 0 !important;
                        opacity: 0 !important;
                        visibility: hidden !important;
                        transition: opacity .16s ease !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:hover .simplebar-track.simplebar-vertical {
                        opacity: 1 !important;
                        visibility: visible !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu .simplebar-scrollbar::before {
                        left: 0 !important;
                        right: 0 !important;
                        width: 2px !important;
                        background: rgba(255, 255, 255, .2) !important;
                        opacity: 1 !important;
                    }

                    /* data-simplebar host uzerindeki yerel scrollbar'i da ayni sekilde yonet. */
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu .sidebar-menu-scroll,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu .simplebar-content-wrapper {
                        scrollbar-width: none !important;
                        -ms-overflow-style: none !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu .sidebar-menu-scroll::-webkit-scrollbar,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu .simplebar-content-wrapper::-webkit-scrollbar {
                        width: 0 !important;
                        height: 0 !important;
                        display: none !important;
                    }

                    /* SimpleBar, kaydirma sonrasinda inline visible sinifi ekliyor.
                       Dar menude hover disinda track'i akis disina cikartarak bunu kesin olarak engelle. */
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:not(:hover) .simplebar-track,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:not(:hover) .simplebar-track.simplebar-vertical,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:not(:hover) .simplebar-scrollbar,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:not(:hover) .simplebar-scrollbar::before {
                        display: none !important;
                        width: 0 !important;
                        opacity: 0 !important;
                        visibility: hidden !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:hover .simplebar-track.simplebar-vertical {
                        display: block !important;
                        width: 2px !important;
                        opacity: 1 !important;
                        visibility: visible !important;
                        background: transparent !important;
                        border: 0 !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:hover .simplebar-scrollbar {
                        display: block !important;
                        width: 2px !important;
                        opacity: 1 !important;
                        visibility: visible !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu:hover .simplebar-scrollbar::before {
                        display: block !important;
                        width: 2px !important;
                        opacity: .45 !important;
                        visibility: visible !important;
                    }

                    /* Dar menude tum ikonlari gercek sidebar merkezine sabitle. */
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu {
                        width: 100% !important;
                        padding-left: 0 !important;
                        padding-right: 0 !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu #side-menu,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu #side-menu > li,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li {
                        width: 100% !important;
                        margin-left: 0 !important;
                        margin-right: 0 !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu #side-menu > li > a,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a {
                        width: 40px !important;
                        min-width: 40px !important;
                        max-width: 40px !important;
                        min-height: 36px !important;
                        height: 36px !important;
                        margin: 1px auto !important;
                        padding: 0 !important;
                        gap: 0 !important;
                        display: flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                        text-align: center !important;
                        box-sizing: border-box !important;
                        position: relative !important;
                        left: 5px !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu #side-menu > li > a::after,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a::after {
                        display: none !important;
                    }

                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu #side-menu > li > a i,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu #side-menu > li > a svg,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a i,
                    html[data-theme-preset="macos-dark"] body[data-sidebar-size="sm"] .vertical-menu #sidebar-menu .menu-items-list > li > a svg {
                        width: 18px !important;
                        height: 18px !important;
                        min-width: 18px !important;
                        margin: 0 !important;
                        position: static !important;
                        transform: none !important;
                    }
                }
            </style>

            <div class="sidebar-sticky-top">
                <div class="sidebar-brand-box">
                    <div class="macos-window-controls">
                        <span class="macos-dot macos-dot-red" title="Kapat"></span>
                        <span class="macos-dot macos-dot-yellow" title="Simge Durumuna Küçült"></span>
                        <span class="macos-dot macos-dot-green" title="Ekranı Kapla"></span>
                    </div>

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
                        <div class="position-relative flex-grow-1 search-input-wrapper">
                            <input type="text" class="form-control sidebar-search" id="menu-search-input"
                                placeholder="Menüde ara..." autocomplete="off">
                            <i data-feather="search" class="search-icon"></i>
                            <button type="button" class="btn-clear-search" id="btn-clear-menu-search" style="display: none;" title="Aramayı Temizle" aria-label="Aramayı Temizle">
                                <svg viewBox="0 0 24 24" width="12" height="12" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="dropdown menu-settings-dropdown">
                            <button class="btn btn-sidebar-settings dropdown-toggle" type="button" id="sidebarMenuSettingsBtn" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="Menü Ayarları">
                                <i data-feather="settings" class="settings-icon-default" style="width: 15px; height: 15px;"></i>
                                <i data-feather="more-horizontal" class="settings-icon-mac" style="width: 15px; height: 15px;"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end mac-popover-menu shadow-lg border-0" aria-labelledby="sidebarMenuSettingsBtn">
                                <!-- Grup 1: Menü İşlemleri -->
                                <div class="mac-dropdown-group">
                                    <button class="dropdown-item mac-popover-item" type="button" id="btn-reset-menu-order">
                                        <span>Varsayılan Menü Sırası</span>
                                        <span class="mac-item-shortcut">Sıfırla</span>
                                    </button>
                                    <button class="dropdown-item mac-popover-item" type="button" id="btn-collapse-all-menus">
                                        <span>Tüm Menüleri Kapat</span>
                                        <span class="mac-item-shortcut">Daralt</span>
                                    </button>
                                </div>

                                <!-- Grup 2: Görünüm Efekti -->
                                <div class="mac-dropdown-group">
                                    <button class="dropdown-item mac-popover-item" type="button" id="btn-toggle-sidebar-particles">
                                        <span>Parçacık Animasyonu</span>
                                        <span class="mac-item-shortcut" id="sidebar-particles-badge">Kapalı</span>
                                    </button>
                                </div>

                                <!-- Grup 3: Arama & Düzen -->
                                <div class="mac-dropdown-group">
                                    <button class="dropdown-item mac-popover-item" type="button" id="btn-focus-menu-search">
                                        <span>Menüde Ara</span>
                                        <span class="mac-item-shortcut">Ctrl+F</span>
                                    </button>
                                    <button class="dropdown-item mac-popover-item disabled" type="button" tabindex="-1">
                                        <span>Geri Al</span>
                                        <span class="mac-item-shortcut">Ctrl+Z</span>
                                    </button>
                                </div>

                                <!-- Grup 4: Tercihler & Bilgi -->
                                <div class="mac-dropdown-group">
                                    <button class="dropdown-item mac-popover-item" type="button" id="btn-toggle-shortcuts-info">
                                        <span>Klavye Kısayolları</span>
                                        <span class="mac-item-shortcut">Ctrl+?</span>
                                    </button>
                                    <button class="dropdown-item mac-popover-item" type="button" id="btn-about-system">
                                        <span>Ersan ELK Hakkında</span>
                                    </button>
                                </div>
                            </div>
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
        const clearSearchBtn = document.getElementById('btn-clear-menu-search');
        const sideMenu = document.getElementById('side-menu');

        // 1. Menüde Arama Filtreleme
        if (searchInput && sideMenu) {
            // Başlangıçtaki orijinal aktif ve açık menü durumlarını kaydet
            sideMenu.querySelectorAll('.menu-item-draggable').forEach(li => {
                if (li.classList.contains('mm-active')) {
                    li.dataset.origActive = 'true';
                }
                const topAnchor = li.querySelector(':scope > a');
                if (topAnchor && topAnchor.getAttribute('aria-expanded') === 'true') {
                    topAnchor.dataset.origExpanded = 'true';
                }
                const subMenu = li.querySelector(':scope > ul.sub-menu');
                if (subMenu && subMenu.classList.contains('mm-show')) {
                    subMenu.dataset.origShow = 'true';
                }
            });

            function resetMenuSearchState() {
                sideMenu.classList.remove('is-menu-searching');
                if (clearSearchBtn) clearSearchBtn.style.display = 'none';

                const sections = sideMenu.querySelectorAll('.menu-section');
                sections.forEach(s => s.style.display = '');

                sideMenu.querySelectorAll('li').forEach(li => {
                    li.style.display = '';
                });

                sideMenu.querySelectorAll('ul.sub-menu').forEach(sub => {
                    sub.style.display = '';
                    sub.style.height = '';
                });

                sideMenu.querySelectorAll('.menu-item-draggable').forEach(li => {
                    const topAnchor = li.querySelector(':scope > a');
                    const subMenu = li.querySelector(':scope > ul.sub-menu');

                    if (li.dataset.origActive === 'true') {
                        li.classList.add('mm-active');
                    } else {
                        li.classList.remove('mm-active');
                    }

                    if (topAnchor) {
                        if (topAnchor.dataset.origExpanded === 'true') {
                            topAnchor.setAttribute('aria-expanded', 'true');
                        } else {
                            topAnchor.setAttribute('aria-expanded', 'false');
                        }
                    }

                    if (subMenu) {
                        if (subMenu.dataset.origShow === 'true') {
                            subMenu.classList.add('mm-show');
                            subMenu.setAttribute('aria-expanded', 'true');
                        } else {
                            subMenu.classList.remove('mm-show');
                            subMenu.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            }

            searchInput.addEventListener('input', function () {
                const filter = this.value.toLowerCase().trim();

                if (filter === '') {
                    resetMenuSearchState();
                    return;
                }

                if (clearSearchBtn) clearSearchBtn.style.display = 'flex';
                sideMenu.classList.add('is-menu-searching');

                const sections = sideMenu.querySelectorAll('.menu-section');
                sections.forEach(section => {
                    let sectionHasVisible = false;
                    const topItems = section.querySelectorAll('.menu-items-list > li[data-menu-id]');

                    topItems.forEach(topLi => {
                        let topLiHasVisible = false;
                        const topAnchor = topLi.querySelector(':scope > a');
                        const topText = topAnchor ? topAnchor.textContent.toLowerCase() : '';
                        const isTopMatch = topText.includes(filter);

                        const subMenu = topLi.querySelector(':scope > ul.sub-menu');
                        const subItems = topLi.querySelectorAll('.sub-menu > li[data-menu-id]');
                        let subHasMatch = false;

                        subItems.forEach(subLi => {
                            const subAnchor = subLi.querySelector('a');
                            const subText = subAnchor ? subAnchor.textContent.toLowerCase() : '';
                            if (subText.includes(filter) || isTopMatch) {
                                subLi.style.display = '';
                                if (subText.includes(filter)) {
                                    subHasMatch = true;
                                }
                            } else {
                                subLi.style.display = 'none';
                            }
                        });

                        if (isTopMatch || subHasMatch) {
                            topLi.style.display = '';
                            topLiHasVisible = true;
                            if (subMenu) {
                                topLi.classList.add('mm-active');
                                if (topAnchor) topAnchor.setAttribute('aria-expanded', 'true');
                                subMenu.classList.add('mm-show');
                                subMenu.style.display = 'block';
                            }
                        } else {
                            topLi.style.display = 'none';
                            if (subMenu) {
                                subMenu.style.display = 'none';
                            }
                        }

                        if (topLiHasVisible) {
                            sectionHasVisible = true;
                        }
                    });

                    section.style.display = sectionHasVisible ? '' : 'none';
                });
            });

            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = '';
                    resetMenuSearchState();
                    searchInput.focus();
                });
            }

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    this.value = '';
                    resetMenuSearchState();
                    this.blur();
                }
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

        // 4. Tüm Menüleri Kapatma / Daraltma Butonu
        const btnCollapseAll = document.getElementById('btn-collapse-all-menus');
        if (btnCollapseAll) {
            btnCollapseAll.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof $ !== 'undefined') {
                    $('#side-menu ul.sub-menu.mm-show').removeClass('mm-show').css('height', '');
                    $('#side-menu li.mm-active').removeClass('mm-active');
                    $('#side-menu a[aria-expanded="true"]').attr('aria-expanded', 'false');
                }
                if (typeof showToast === 'function') {
                    showToast('Tüm alt menüler kapatıldı.');
                }
            });
        }

        // 4b. Parçacık Animasyonu Aç/Kapa Butonu
        function updateSidebarParticlesBadge() {
            const badge = document.getElementById('sidebar-particles-badge');
            const isEnabled = localStorage.getItem('sidebar-particles-enabled') === '1';
            if (badge) {
                badge.textContent = isEnabled ? 'Açık' : 'Kapalı';
                badge.style.color = isEnabled ? '#38bdf8' : '#94a3b8';
                badge.style.fontWeight = isEnabled ? '600' : '400';
            }
        }

        const btnParticles = document.getElementById('btn-toggle-sidebar-particles');
        if (btnParticles) {
            btnParticles.addEventListener('click', function(e) {
                e.preventDefault();
                const currentlyEnabled = localStorage.getItem('sidebar-particles-enabled') === '1';
                const newState = !currentlyEnabled;
                localStorage.setItem('sidebar-particles-enabled', newState ? '1' : '0');

                if (window.SidebarParticles) {
                    if (newState) {
                        window.SidebarParticles.start();
                    } else {
                        window.SidebarParticles.stop();
                    }
                }

                // Sağ tema çekmecesindeki radio butonunu da senkronize et
                const radioOff = document.getElementById('sidebar-particles-off');
                const radioOn = document.getElementById('sidebar-particles-on');
                if (radioOff && radioOn) {
                    if (newState) {
                        radioOn.checked = true;
                    } else {
                        radioOff.checked = true;
                    }
                }

                updateSidebarParticlesBadge();

                if (typeof showToast === 'function') {
                    showToast(newState ? 'Menü parçacık animasyonu açıldı.' : 'Menü parçacık animasyonu kapatıldı (performans modu).', 'info');
                }
            });
        }
        updateSidebarParticlesBadge();

        // 5. Menüde Arama Odağı
        const btnFocusSearch = document.getElementById('btn-focus-menu-search');
        if (btnFocusSearch) {
            btnFocusSearch.addEventListener('click', function(e) {
                e.preventDefault();
                const searchInput = document.getElementById('menu-search-input');
                if (searchInput) {
                    searchInput.focus();
                }
            });
        }

        // 6. Klavye Kısayolları Modal/Bilgilendirme
        const btnShortcuts = document.getElementById('btn-toggle-shortcuts-info');
        if (btnShortcuts) {
            btnShortcuts.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Klavye Kısayolları',
                        html: '<div style="text-align:left; font-size:13px; line-height:2; padding:8px;">' +
                              '<div><kbd style="background:#333; color:#fff; padding:2px 6px; border-radius:4px;">Ctrl + F</kbd> : Menüde Arama Yap</div>' +
                              '<div><kbd style="background:#333; color:#fff; padding:2px 6px; border-radius:4px;">Alt + C</kbd> : Tüm Menüleri Kapat</div>' +
                              '<div><kbd style="background:#333; color:#fff; padding:2px 6px; border-radius:4px;">Ctrl + Shift + R</kbd> : Menü Sırasını Sıfırla</div>' +
                              '</div>',
                        icon: 'info',
                        confirmButtonText: 'Tamam',
                        confirmButtonColor: '#1c84ee'
                    });
                }
            });
        }

        // 7. Sistem Hakkında
        const btnAbout = document.getElementById('btn-about-system');
        if (btnAbout) {
            btnAbout.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Ersan ELK',
                        text: 'Yönetim Paneli • Sürüm 2.5',
                        icon: 'info',
                        confirmButtonText: 'Kapat',
                        confirmButtonColor: '#1c84ee'
                    });
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
