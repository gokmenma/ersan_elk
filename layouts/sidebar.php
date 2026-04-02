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

?>

<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <style>
                .sidebar-search-container {
                    padding: 10px 20px 10px 20px;
                    position: sticky;
                    top: 0;
                    z-index: 100;
                    background-color: #fff;
                    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
                }

                [data-bs-theme="dark"] .sidebar-search-container,
                [data-theme-mode="dark"] .sidebar-search-container {
                    background-color: #2a3042;
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

                .sidebar-search-container .clear-icon {
                    position: absolute;
                    right: 13px;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 15px;
                    height: 15px;
                    color: #74788d;
                    cursor: pointer;
                    display: none;
                    transition: all 0.2s ease;
                }

                .sidebar-search-container .clear-icon:hover {
                    color: #f46a6a;
                }
            </style>
            <div class="sidebar-search-container">
                <div class="position-relative">
                    <input type="text" class="form-control sidebar-search" id="menu-search-input"
                        placeholder="Menüde ara...">
                    <i data-feather="search" class="search-icon"></i>
                    <i data-feather="x" class="clear-icon" id="menu-search-clear"></i>
                </div>
            </div>
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">

                <?php foreach ($menu_data as $group_name => $menus): ?>

                    <li class="menu-title" data-key="t-menu"><?php echo htmlspecialchars($group_name); ?></li>

                    <?php foreach ($menus as $menu): ?>
                        <?php
                        // DEBUG: echo "<!-- ID: " . $menu->id . " Name: " . $menu->menu_name . " is_menu: " . (isset($menu->is_menu) ? $menu->is_menu : 'NOT SET') . " -->";
                
                        if (isset($menu->is_menu) && $menu->is_menu == 0)
                            continue;

                        $has_children = !empty($menu->children);

                        // Runtime güvenlik katmanı: cache eski kalsa bile yetkisiz menüleri göstermeyelim.
                        $visibleChildren = [];
                        if ($has_children) {
                            foreach ($menu->children as $sub_menu) {
                                if (isset($sub_menu->is_menu) && $sub_menu->is_menu == 0) {
                                    continue;
                                }
                                if (!empty($sub_menu->menu_link) && !$Menus->userCanAccessMenuLink($currentUserId, $sub_menu->menu_link)) {
                                    continue;
                                }
                                $visibleChildren[] = $sub_menu;
                            }
                            $has_children = !empty($visibleChildren);
                        }

                        if (!$has_children && !empty($menu->menu_link) && !$Menus->userCanAccessMenuLink($currentUserId, $menu->menu_link)) {
                            continue;
                        }

                        $is_active = in_array((int) $menu->id, $activeMenuIds);
                        $active_class = $is_active ? 'mm-active' : '';
                        $has_arrow_class = $has_children ? 'has-arrow' : '';

                        // Eğer alt menüsü yoksa kendi linkini kullanır, varsa javascript:void(0) olur.
                        $link = $has_children ? 'javascript: void(0);' : Route::Link($menu->menu_link);
                        ?>
                        <li class="<?php echo $active_class; ?>">
                            <a href="<?php echo $link; ?>"
                                class="<?php echo $has_arrow_class; ?> waves-effect <?php echo $is_active ? 'active' : ''; ?>">
                                <?php if (!empty($menu->menu_icon)): ?>
                                    <i data-feather="<?php echo htmlspecialchars($menu->menu_icon); ?>"></i>
                                <?php endif; ?>
                                <span data-key="t-users"><?php echo htmlspecialchars($menu->menu_name); ?></span>
                            </a>

                            <?php if ($has_children): ?>
                                <ul class="sub-menu" aria-expanded="<?php echo $is_active ? 'true' : 'false'; ?>">
                                    <?php
                                    foreach ($visibleChildren as $sub_menu):
                                        $is_sub_active = in_array((int) $sub_menu->id, $activeMenuIds);
                                        ?>
                                        <li class="<?php echo $is_sub_active ? 'mm-active' : ''; ?>">
                                            <a class="waves-effect <?php echo $is_sub_active ? 'active' : ''; ?>"
                                                href="<?php echo Route::Link($sub_menu->menu_link); ?>" data-key="t-user-grid">
                                                <?php echo htmlspecialchars($sub_menu->menu_name); ?>
                                            </a>
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
        const searchClear = document.getElementById('menu-search-clear');
        if (!searchInput) return;

        searchInput.addEventListener('input', function () {
            const filter = this.value.toLowerCase().trim();
            const sideMenu = document.getElementById('side-menu');
            const allLi = sideMenu.querySelectorAll('li:not(.menu-title)');
            const titles = sideMenu.querySelectorAll('.menu-title');

            // Temizleme ikonunu göster/gizle
            if (searchClear) {
                searchClear.style.display = filter === '' ? 'none' : 'block';
            }

            if (filter === '') {
                allLi.forEach(li => {
                    li.style.display = '';
                    // mm-active ve mm-show sınıflarını temizlemiyoruz ki mevcut aktif menüler açık kalsın
                    // Ancak arama ile açılanları kapatmak isterseniz burada işlem yapmalısınız.
                });
                titles.forEach(t => t.style.display = '');
                return;
            }

            // Önce her şeyi gizle
            allLi.forEach(li => li.style.display = 'none');

            // Eşleşenleri bul ve göster
            allLi.forEach(li => {
                const anchor = li.querySelector('a');
                if (!anchor) return;

                const text = anchor.textContent.toLowerCase();
                if (text.includes(filter)) {
                    li.style.display = '';

                    // Üst menüleri aç ve göster (Parent items)
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

            // Grup başlıklarını güncelle
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

        // Temizle butonuna basınca
        if (searchClear) {
            searchClear.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.focus();
            });
        }
    });
</script>