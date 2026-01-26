<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Route;
use App\Model\MenuModel;
use App\Helper\Security;

$Menus = new MenuModel();

// Tüm menü verisini tek bir fonksiyona göndererek hiyerarşik yapıyı oluştur.
$menu_data = $Menus->getHierarchicalMenuForRole($_SESSION['id']);

// Aktif menü tespiti
$currentPath = $_GET['p'] ?? '';
$currentMenu = $Menus->getMenuByLink($currentPath);
$activeMenuIds = $Menus->getActiveMenuIds($currentMenu);

?>

<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
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
                                    foreach ($menu->children as $sub_menu):
                                        if (isset($sub_menu->is_menu) && $sub_menu->is_menu == 0)
                                            continue;
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