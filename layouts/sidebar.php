<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Helper;
use App\Helper\Route;
use App\Model\MenuModel;

use App\Helper\Security;

$Menus = new MenuModel();

// Tüm menü verisini tek bir fonksiyona göndererek hiyerarşik yapıyı oluştur.
$menu_data = $Menus->getHierarchicalMenuForRole($_SESSION['id']);


?>

<div class="vertical-menu">

    <div data-simplebar class="h-100">

        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu list-unstyled" id="side-menu">

                    <?php foreach ($menu_data as $group_name => $menus) : ?>

                        <li class="menu-title" data-key="t-menu"><?php echo htmlspecialchars($group_name); ?></li>

                        <?php foreach ($menus as $menu) : ?>
                            <?php
                            $has_children = !empty($menu->children);
                            $has_arrow_class = $has_children ? 'has-arrow' : '';

                            // Eğer alt menüsü yoksa kendi linkini kullanır, varsa javascript:void(0) olur.
                            $link = $has_children ? 'javascript: void(0);' : Route::Link($menu->menu_link);
                            ?>
                            <li>
                                <a href="<?php echo $link; ?>" class="<?php echo $has_arrow_class; ?> waves-effect">
                                    <?php if (!empty($menu->menu_icon)): ?>
                                        <i data-feather="<?php echo htmlspecialchars($menu->menu_icon); ?>"></i>
                                    <?php endif; ?>
                                    <span data-key="t-users"><?php echo htmlspecialchars($menu->menu_name); ?></span>
                                </a>

                                <?php if ($has_children) : ?>
                                    <ul class="sub-menu" aria-expanded="false">
                                        <?php
                                        // Artık getSubMenus() sorgusuna da gerek yok! 'children' dizisini direkt kullanıyoruz.
                                        foreach ($menu->children as $sub_menu) :
                                            // $sub_enc_id = Security::encrypt($sub_menu->id);
                                        ?>
                                            <li>
                                                <a class="waves-effect" href="<?php echo Route::Link($sub_menu->menu_link); ?>" data-key="t-user-grid">
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