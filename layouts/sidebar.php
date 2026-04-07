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
                .sidebar-search-container {
                    padding: 12px 20px 12px 20px;
                    position: sticky;
                    top: 0;
                    z-index: 100;
                    background-color: #fff;
                    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                }

                [data-bs-theme="dark"] .sidebar-search-container,
                [data-theme-mode="dark"] .sidebar-search-container {
                    background-color: #282f36;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                }

                .sidebar-search {
                    background-color: rgba(0, 0, 0, 0.03) !important;
                    border: 1px solid rgba(0, 0, 0, 0.1) !important;
                    color: inherit !important;
                    border-radius: 8px !important;
                    padding-left: 38px !important;
                    height: 38px;
                    font-size: 13px;
                    transition: all 0.3s ease;
                }

                [data-bs-theme="dark"] .sidebar-search,
                [data-theme-mode="dark"] .sidebar-search {
                    background-color: rgba(255, 255, 255, 0.05) !important;
                    border: 1px solid rgba(255, 255, 255, 0.1) !important;
                    color: #ced4da !important;
                }

                .sidebar-search:focus {
                    background-color: rgba(0, 0, 0, 0.05) !important;
                    border-color: rgba(0, 0, 0, 0.15) !important;
                    box-shadow: none;
                }

                [data-bs-theme="dark"] .sidebar-search:focus,
                [data-theme-mode="dark"] .sidebar-search:focus {
                    background-color: rgba(255, 255, 255, 0.1) !important;
                    border-color: rgba(255, 255, 255, 0.2) !important;
                    color: #fff !important;
                }

                .sidebar-search-container .search-icon {
                    position: absolute;
                    left: 13px;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 15px;
                    height: 15px;
                    color: #74788d;
                    pointer-events: none;
                }

                /* Favori Yıldız Buton Stilleri */
                .star-btn {
                    position: absolute;
                    right: 3px;
                    top: 7px; /* Ana menü için ortalama */
                    z-index: 10;
                    color: #adb5bd;
                    transition: all 0.2s ease;
                    padding: 5px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                /* Ok işareti olduğunda yıldızı ok işaretinin sağına (en uçta) bırakıp oku sola çekiyoruz */
                .has-arrow:after {
                    right: 29px !important;
                    top: 19px !important; /* Oku da dikey ortalıyoruz */
                }

                .has-arrow + .star-btn {
                    right: 3px;
                    top: 8px;
                }

                .sub-menu .star-btn {
                    right: 3px;
                    top: 3px;
                }

                .star-btn:hover {
                    color: #f1b44c;
                    transform: scale(1.2);
                }

                .star-btn.active {
                    color: #f1b44c;
                }

                #side-menu li {
                    position: relative;
                }

                #side-menu li a {
                    padding-right: 45px !important;
                }

                [data-bs-theme="dark"] .star-btn {
                    color: #495057;
                }

                [data-bs-theme="dark"] .star-btn.active {
                    color: #f1b44c;
                }
            </style>
            <div class="sidebar-search-container">
                <div class="position-relative">
                    <input type="text" class="form-control sidebar-search" id="menu-search-input"
                        placeholder="Menüde ara...">
                    <i data-feather="search" class="search-icon"></i>
                </div>
            </div>
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">

                <!-- Sık Kullanılanlar Başlığı -->
                <li class="menu-title fav-title" data-key="t-favorites" style="<?php echo empty($favoriteMenus) ? 'display:none;' : ''; ?>">Sık Kullanılanlar</li>
                
                <div id="favorites-container">
                    <?php foreach ($favoriteMenus as $fav): ?>
                        <li class="fav-item" data-id="<?php echo $fav->id; ?>">
                            <a href="<?php echo Route::Link($fav->menu_link); ?>" class="waves-effect">
                                <?php if (!empty($fav->menu_icon)): ?>
                                    <i data-feather="<?php echo htmlspecialchars($fav->menu_icon); ?>"></i>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($fav->menu_name); ?></span>
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