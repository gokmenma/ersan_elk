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
                    --sidebar-bg: #111827;
                    --sidebar-border: #1f2937;
                    --sidebar-item-hover: #1f2937;
                    --sidebar-item-active: #1f2937;
                    --sidebar-foreground: #e5e7eb;
                    --sidebar-muted: #9ca3af;
                    --sidebar-accent: #f9fafb;
                }

                .vertical-menu {
                    background-color: var(--sidebar-bg) !important;
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
                }

                /* Sticky Sidebar Header (Brand + Search) */
                .sidebar-sticky-top {
                    position: sticky;
                    top: 0;
                    z-index: 100;
                    background-color: var(--sidebar-bg);
                    padding: 12px 8px 16px 8px;
                    margin: -8px -8px 0 -8px;
                    border-bottom: 1px solid transparent;
                    transition: all 0.2s ease;
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
                    gap: 12px;
                    position: relative;
                }

                .brand-logo {
                    width: 32px;
                    height: 32px;
                    background-color: var(--sidebar-accent);
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
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
                    transform: translateY(-30%) rotate(45deg) !important; /* Point up when open */
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
            </style>

            <div class="sidebar-sticky-top">
                <div class="sidebar-brand-box">
                    <div class="brand-logo">
                        <i data-feather="box"></i>
                    </div>
                    <div class="brand-info">
                        <span class="brand-name">Ersan ELK</span>
                        <span class="brand-sub">Yönetim Paneli</span>
                    </div>
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

                <!-- Sık Kullanılanlar Başlığı -->
                <li class="menu-title fav-title" data-key="t-favorites" style="<?php echo empty($favoriteMenus) ? 'display:none;' : ''; ?>">Favoriler</li>
                
                <div id="favorites-container">
                    <?php foreach ($favoriteMenus as $fav): ?>
                        <li class="fav-item" data-id="<?php echo $fav->id; ?>">
                            <a href="<?php echo Route::Link($fav->menu_link); ?>" class="waves-effect">
                                <?php if (!empty($fav->menu_icon)): ?>
                                    <i data-feather="<?php echo htmlspecialchars($fav->menu_icon); ?>"></i>
                                <?php endif; ?>
                                <span class="menu-name"><?php echo htmlspecialchars($fav->menu_name); ?></span>
                            </a>
                            <div class="star-btn active" data-id="<?php echo $fav->id; ?>" title="Favorilerden Kaldır">
                                <i class="fas fa-star" style="font-size: 11px;"></i>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </div>

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

        // Favori Yıldız İşlemi (Real-time)
        document.addEventListener('click', function(e) {
            const starBtn = e.target.closest('.star-btn');
            if (!starBtn) return;

            e.preventDefault();
            e.stopPropagation();

            const menuId = starBtn.getAttribute('data-id');
            const isActive = starBtn.classList.contains('active');
            
            // UI'ı hemen güncelle (Optimistic Update)
            toggleStarUI(menuId, !isActive);

            fetch('api/menu-favorites.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'menu_id=' + menuId
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Hata olursa geri al
                    toggleStarUI(menuId, isActive);
                    alert(data.message);
                }
            })
            .catch(error => {
                toggleStarUI(menuId, isActive);
                console.error('Error:', error);
            });
        });

        function toggleStarUI(menuId, setActive) {
            const stars = document.querySelectorAll(`.star-btn[data-id="${menuId}"]`);
            stars.forEach(s => {
                if (setActive) {
                    s.classList.add('active');
                    s.querySelector('i').classList.replace('far', 'fas');
                    s.setAttribute('title', 'Favorilerden Kaldır');
                } else {
                    s.classList.remove('active');
                    s.querySelector('i').classList.replace('fas', 'far');
                    s.setAttribute('title', 'Favorilere Ekle');
                }
            });

            const favoritesContainer = document.getElementById('favorites-container');
            const favTitle = document.querySelector('.fav-title');

            if (setActive) {
                // Sık kullanılanlara ekle
                if (!document.querySelector(`.fav-item[data-id="${menuId}"]`)) {
                    // Ana menüdeki öğeyi bulup kopyala
                    const mainMenuItem = document.querySelector(`li[data-menu-id="${menuId}"]`);
                    if (mainMenuItem) {
                        const clone = document.createElement('li');
                        clone.className = 'fav-item';
                        clone.setAttribute('data-id', menuId);
                        
                        const link = mainMenuItem.querySelector('a').cloneNode(true);
                        link.classList.remove('has-arrow', 'mm-active', 'active');
                        link.href = mainMenuItem.querySelector('a').getAttribute('href'); // Re-set because cloneNode might lose some properties depending on browser
                        
                        // Icon ve yazı düzeltme (Eğer alt menü ise ikon olmayabilir, ana menü ikonunu alabiliriz)
                        // Şimdilik sadece mevcut link içeriğini alıyoruz.
                        
                        const star = document.createElement('div');
                        star.className = 'star-btn active';
                        star.setAttribute('data-id', menuId);
                        star.innerHTML = '<i class="fas fa-star" style="font-size: 11px;"></i>';
                        
                        clone.appendChild(link);
                        clone.appendChild(star);
                        favoritesContainer.appendChild(clone);
                        
                        // Feather icons refresh
                        if (typeof feather !== 'undefined') feather.replace();
                    }
                }
            } else {
                // Sık kullanılanlardan kaldır
                const favItem = document.querySelector(`.fav-item[data-id="${menuId}"]`);
                if (favItem) favItem.remove();
            }

            // Başlığı göster/gizle
            const hasFavs = favoritesContainer.querySelectorAll('.fav-item').length > 0;
            favTitle.style.display = hasFavs ? '' : 'none';
        }
    });
</script>