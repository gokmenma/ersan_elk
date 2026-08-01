<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Route;
use App\Model\MenuModel;

$Menus = new MenuModel();
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);

// Tüm menü verisini tek bir fonksiyona göndererek hiyerarşik yapıyı oluştur.
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

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <style>
                :root {
                    --sidebar-bg: #ffffff;
                    --sidebar-border: #f1f1f4;
                    --sidebar-item-hover: #f4f4f5;
                    --sidebar-item-active: #f4f4f5;
                    --sidebar-foreground: #3f3f46;
                    --sidebar-muted: #71717a;
                    --sidebar-accent: #18181b;
                    --sidebar-font: "Geist", sans-serif;
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

                .vertical-menu {
                    background-color: var(--sidebar-bg) !important;
                    background-image: linear-gradient(rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.05)) !important;
                    border-right: 1px solid var(--sidebar-border) !important;
                    box-shadow: none !important;
                    font-family: var(--sidebar-font);
                    top: 0 !important; /* Ensure it starts from top */
                }

                /* Hide topbar brand box since we have it in sidebar */
                .navbar-brand-box {
                    display: none !important;
                }

                #page-topbar {
                    left: 250px !important; /* Standard sidebar width */
                    background-color: var(--sidebar-bg) !important;
                    background-image: linear-gradient(rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.05)) !important;
                    border-bottom: 1px solid var(--sidebar-border) !important;
                    box-shadow: none !important;
                }

                body[data-sidebar-size="sm"] #page-topbar {
                    left: 60px !important;
                }

                @media (max-width: 992px) {
                    #page-topbar {
                        left: 0 !important;
                    }
                    .navbar-brand-box {
                        display: flex !important; /* Show on mobile if needed */
                    }
                    .sidebar-brand-box {
                        display: none !important;
                    }
                }

                #sidebar-menu {
                    padding: 8px;
                    min-height: 100%;
                }

                /* Sticky Sidebar Header (Brand + Search) */
                .sidebar-sticky-top {
                    position: sticky;
                    top: 0;
                    z-index: 100;
                    background-color: var(--sidebar-bg);
                    background-image: linear-gradient(rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.05)) !important;
                    padding: 12px 8px 16px 8px;
                    margin: -8px -8px 0 -8px;
                    border-bottom: 1px solid transparent;
                    transition: all 0.2s ease;
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
                 body[data-sidebar="dark"] .sidebar-sticky-top { background-color: var(--sidebar-bg) !important; }
                body[data-sidebar="light"] .sidebar-sticky-top { background-color: #ffffff !important; }

                /* Themed Search Input Styling */
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

                /* Hide brand logo on small sidebar */
                body[data-sidebar-size="sm"] .sidebar-sticky-top {
                    position: static;
                    padding: 12px 8px;
                }

                /* Sidebar Brand/Header Section */
                .sidebar-brand-box {
                    padding: 0 12px 24px 12px;
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

                /* Dark/Colored sidebar themes brand text compatibility override */
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

                /* Sidebar Search */
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
                    background-color: #fff !important;
                }

                .sidebar-search-container .search-icon {
                    position: absolute !important;
                    left: 20px !important;
                    top: 50% !important;
                    width: 14px;
                    height: 14px;
                    color: var(--sidebar-muted);
                    pointer-events: none;
                    transform: translateY(-50%);
                }

                /* Menu Items Styling */
                #side-menu {
                    padding: 0;
                }

                #side-menu .menu-title {
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: none;
                    color: var(--sidebar-muted);
                    padding: 16px 12px 8px 12px;
                    letter-spacing: 0.01em;
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

                #side-menu li a {
                    padding: 8px 12px !important;
                    border-radius: 8px;
                    font-size: 14px;
                    color: var(--sidebar-foreground) !important;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    transition: background-color 0.2s ease, color 0.2s ease, padding 0.2s ease;
                    margin: 0 8px 2px 0; /* Menu backgrounds more space from right */
                    position: relative;
                }

                #side-menu li a:hover {
                    background-color: var(--sidebar-item-hover) !important;
                }

                #side-menu li.mm-active > a,
                #side-menu li a.active {
                    background-color: var(--sidebar-item-active) !important;
                    font-weight: 500;
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

                /* Sub-menu Indentation (Shadcn style with requested padding) */
                .sub-menu {
                    padding: 0 0 0 12px !important; /* Indent text by 12px from the line */
                    margin: 0 0 0 28px !important; /* Align the line with parent icons (28-12=16 actually? No, 28px is the line) */
                    list-style: none;
                    border-left: 1px solid var(--sidebar-border) !important;
                    position: relative;
                }

                .sub-menu li a {
                    font-size: 13px !important;
                    padding-left: 1rem !important; /* As requested: 1rem */
                    color: var(--sidebar-foreground) !important;
                    border-radius: 6px;
                    margin: 0 12px 2px 0;
                    white-space: nowrap !important; /* As requested */
                }

                .sub-menu li a:hover {
                    padding-left: 1.2rem !important; /* As requested: 1.2rem padding on hover */
                }

                /* Arrow styling (Chevron) - Accurate Shadcn placement */
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
                    transform: translateY(-30%) rotate(-45deg) !important; /* Point down when open */
                }

                /* Sidebar Icons Refresh */
                [data-feather] {
                    width: 16px;
                    height: 16px;
                }

                /* Star styling refinement */
                .star-btn {
                    position: absolute;
                    right: 16px; /* Moved slightly more to the left as requested */
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

                /* Shift star left if there is an arrow */
                .has-arrow + .star-btn {
                    right: 44px !important; /* Adjusted slightly more to the left as requested */
                }

                /* Star: show on hover of the li's direct child */
                #side-menu li:hover > .star-btn {
                    opacity: 1 !important;
                }

                /* CRITICAL FIX: When hovering inside sub-menu, HIDE the parent li's star */
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

                /* Link padding adjustments to accommodate moved stars */
                #side-menu li a {
                    padding-right: 72px !important;
                }
                
                .sub-menu li a {
                    padding-right: 52px !important;
                }

                /* Scrollbar Refinement */
                .simplebar-track.simplebar-vertical {
                    background-color: transparent;
                    width: 6px;
                }
                .simplebar-scrollbar:before {
                    background: var(--sidebar-border);
                    opacity: 0.5;
                }

                /* Specific for mobile and collapsed */
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

                body[data-sidebar-size="sm"] #sidebar-collapse-btn {
                    display: none !important;
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
                    <button type="button" class="btn btn-sm p-1 border-0 bg-transparent text-muted" id="sidebar-collapse-btn" style="display: none !important;">
                        <i data-feather="menu" style="width: 20px; height: 20px;"></i>
                    </button>
                </div>

                <div class="sidebar-search-container">
                    <div class="position-relative">
                        <input type="text" class="form-control sidebar-search" id="menu-search-input"
                            placeholder="Menüde ara...">
                        <i data-feather="search" class="search-icon"></i>
                    </div>
                </div>
            </div>
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">

                <?php foreach ($menu_data as $group_name => $menus): ?>

                    <li class="menu-title" data-key="t-menu"><?php echo htmlspecialchars($group_name); ?></li>

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
                        <li class="<?php echo $active_class; ?>" data-menu-id="<?php echo $menu->id; ?>">
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
                                <ul class="sub-menu" aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>">
                                    <?php
                                    foreach ($visibleChildren as $sub_menu):
                                        $is_sub_active = in_array((int) $sub_menu->id, $activeMenuIds);
                                        $isSubFavorited = in_array((int) $sub_menu->id, $favoriteMenuIds);
                                        ?>
                                        <li class="<?php echo $is_sub_active ? 'mm-active' : ''; ?>" data-menu-id="<?php echo $sub_menu->id; ?>">
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

                <?php endforeach; ?>

            </ul>


        </div>
        <!-- Sidebar -->
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        const sidebarCollapseBtn = document.getElementById('sidebar-collapse-btn');
        if (sidebarCollapseBtn) {
            sidebarCollapseBtn.addEventListener('click', function (e) {
                e.preventDefault();
                document.getElementById('vertical-menu-btn')?.click();
            });
        }

        const searchInput = document.getElementById('menu-search-input');
        if (!searchInput) return;

        searchInput.addEventListener('input', function () {
            const filter = this.value.toLowerCase().trim();
            const sideMenu = document.getElementById('side-menu');
            const allLi = sideMenu.querySelectorAll('li:not(.menu-title)');
            const titles = sideMenu.querySelectorAll('.menu-title');

            if (filter === '') {
                allLi.forEach(li => {
                    li.style.display = '';
                });
                titles.forEach(t => t.style.display = '');
                return;
            }

            allLi.forEach(li => li.style.display = 'none');

            allLi.forEach(li => {
                const anchor = li.querySelector('a');
                if (!anchor) return;

                const text = anchor.textContent.toLowerCase();
                if (text.includes(filter)) {
                    li.style.display = '';

                    let parent = li.parentElement.closest('li');
                    while (parent) {
                        parent.style.display = '';
                        parent.classList.add('mm-active');
                        const subMenu = parent.querySelector('ul.sub-menu');
                        if (subMenu) {
                            subMenu.classList.add('mm-show');
                            subMenu.style.display = 'block';
                        }
                        parent = parent.parentElement.closest('li');
                    }
                }
            });

            titles.forEach(title => {
                let next = title.nextElementSibling;
                let hasVisible = false;
                while (next && !next.classList.contains('menu-title')) {
                    if (next.style.display !== 'none') {
                        hasVisible = true;
                        break;
                    }
                    next = next.nextElementSibling;
                }
                title.style.display = hasVisible ? '' : 'none';
            });
        });
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

        let activeContextTarget = null; // { menuId, isFav, href, title }
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

            // A. Update Sidebar Stars
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

            // B. Update Top Quick Favorites Bar
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