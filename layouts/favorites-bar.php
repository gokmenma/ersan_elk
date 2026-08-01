<?php
use App\Model\MenuModel;
use App\Model\UserModel;
use App\Helper\Helper;

$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$favoriteMenus = [];
$currentPage = $_GET['p'] ?? '';
$showFavoritesBar = 1;

if ($userId > 0) {
    if (isset($_SESSION['show_favorites_bar'])) {
        $showFavoritesBar = (int) $_SESSION['show_favorites_bar'];
    } elseif (isset($_SESSION['user']) && is_object($_SESSION['user']) && isset($_SESSION['user']->show_favorites_bar)) {
        $showFavoritesBar = (int) $_SESSION['user']->show_favorites_bar;
    } else {
        $userModel = new UserModel();
        $currentUser = $userModel->find($userId);
        $showFavoritesBar = (int) ($currentUser->show_favorites_bar ?? 1);
        $_SESSION['show_favorites_bar'] = $showFavoritesBar;
    }

    if ($showFavoritesBar === 0) {
        echo '<style>body:not(:has(#quick-favorites-bar)) .main-content .page-content { padding-top: 84px !important; }</style>';
        return;
    }

    $menuModel = new MenuModel();
    $favoriteMenus = $menuModel->getFavoriteMenus($userId);
} else {
    return;
}
?>

<!-- Sık Kullanılanlar Çubuğu (Quick Access Bar) -->
<div id="quick-favorites-bar" class="quick-favorites-bar">
    <div class="quick-fav-inner">
        <div class="quick-fav-label" title="Sık Kullanılan Menüler (Sağ tık ile ekleyip çıkarabilirsiniz)">
            <i data-feather="star" class="fav-header-icon"></i>
            <span class="fav-header-text">Sık Kullanılanlar</span>
        </div>
        
        <div id="quick-fav-items" class="quick-fav-items">
            <?php if (!empty($favoriteMenus)): ?>
                <?php foreach ($favoriteMenus as $fav): 
                    $link = htmlspecialchars($fav->menu_link ?? $fav->link ?? '#', ENT_QUOTES, 'UTF-8');
                    $title = htmlspecialchars($fav->menu_name ?? $fav->title ?? '', ENT_QUOTES, 'UTF-8');
                    $icon = htmlspecialchars($fav->menu_icon ?? $fav->icon ?? 'circle', ENT_QUOTES, 'UTF-8');
                    $id = (int) $fav->id;
                    $isActive = ($currentPage !== '' && (
                        $currentPage === $link || 
                        strpos($currentPage, $link . '/') === 0 || 
                        strpos($link, $currentPage) !== false
                    ));
                    $hrefUrl = (strpos($link, 'http') === 0 || strpos($link, 'javascript:') === 0) ? $link : "index.php?p={$link}";
                ?>
                    <div class="quick-fav-pill <?php echo $isActive ? 'active' : ''; ?>" data-menu-id="<?php echo $id; ?>" data-link="<?php echo $link; ?>">
                        <a href="<?php echo $hrefUrl; ?>" class="quick-fav-link">
                            <i data-feather="<?php echo !empty($icon) ? $icon : 'circle'; ?>" class="pill-icon"></i>
                            <span class="pill-title"><?php echo $title; ?></span>
                        </a>
                        <button type="button" class="pill-remove-btn" data-menu-id="<?php echo $id; ?>" title="Sık kullanılanlardan çıkar">
                            <i data-feather="x" class="pill-remove-icon"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div id="quick-fav-empty-hint" class="quick-fav-empty-hint" style="<?php echo !empty($favoriteMenus) ? 'display: none;' : ''; ?>">
                <span class="hint-text">Menüdeki herhangi bir başlığa sağ tıklayarak buraya ekleyebilirsiniz.</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern Sık Kullanılanlar Çubuğu CSS */
.quick-favorites-bar {
    position: fixed;
    top: 70px;
    left: 270px;
    right: 0;
    height: 42px;
    z-index: 989;
    background-color: var(--bs-card-bg, #ffffff);
    background-image: linear-gradient(rgba(255, 255, 255, 0.03), rgba(255, 255, 255, 0.03));
    border-bottom: 1px solid var(--sidebar-border, #e9ecef);
    display: flex;
    align-items: center;
    padding: 0 16px;
    transition: left 0.2s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
}

[data-bs-theme="dark"] .quick-favorites-bar {
    background-color: #1c2228 !important;
    border-bottom-color: #283038 !important;
}

body[data-sidebar-size="sm"] .quick-favorites-bar {
    left: 70px !important;
}

@media (max-width: 991px) {
    .quick-favorites-bar {
        left: 0 !important;
    }
}

.quick-fav-inner {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 12px;
    overflow: hidden;
}

.quick-fav-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 600;
    font-size: 12px;
    color: #f1b44c;
    white-space: nowrap;
    user-select: none;
    padding-right: 10px;
    border-right: 1px solid rgba(120, 130, 140, 0.2);
    flex-shrink: 0;
}

.quick-fav-label .fav-header-icon {
    width: 15px;
    height: 15px;
    fill: #f1b44c;
    color: #f1b44c;
}

.quick-fav-items {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
    padding: 2px 0;
    width: 100%;
}

.quick-fav-items::-webkit-scrollbar {
    height: 3px;
}

.quick-fav-items::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.15);
    border-radius: 3px;
}

.quick-fav-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background-color: rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 20px;
    padding: 3px 10px 3px 10px;
    font-size: 12.5px;
    color: var(--bs-body-color, #495057);
    white-space: nowrap;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    user-select: none;
    flex-shrink: 0;
}

[data-bs-theme="dark"] .quick-fav-pill {
    background-color: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.08);
    color: #ced4da;
}

.quick-fav-pill:hover {
    background-color: rgba(28, 132, 238, 0.08);
    border-color: rgba(28, 132, 238, 0.3);
    color: #1c84ee;
    transform: translateY(-1px);
}

[data-bs-theme="dark"] .quick-fav-pill:hover {
    background-color: rgba(28, 132, 238, 0.18);
    border-color: rgba(28, 132, 238, 0.4);
    color: #60a5fa;
}

.quick-fav-pill.active {
    background-color: var(--bs-primary, #1c84ee) !important;
    border-color: var(--bs-primary, #1c84ee) !important;
    color: #ffffff !important;
    font-weight: 500;
    box-shadow: 0 2px 6px rgba(28, 132, 238, 0.3);
}

.quick-fav-pill.active .pill-icon,
.quick-fav-pill.active .pill-title,
.quick-fav-pill.active .pill-remove-icon {
    color: #ffffff !important;
}

.quick-fav-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: inherit;
    text-decoration: none;
}

.pill-icon {
    width: 13px;
    height: 13px;
    flex-shrink: 0;
}

.pill-title {
    font-size: 12px;
}

.pill-remove-btn {
    background: none;
    border: none;
    padding: 0;
    margin-left: 2px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    color: opacity 0.6;
    cursor: pointer;
    transition: all 0.15s ease;
    opacity: 0.6;
}

.pill-remove-btn:hover {
    background-color: rgba(239, 68, 68, 0.2);
    color: #ef4444 !important;
    opacity: 1;
}

.quick-fav-pill.active .pill-remove-btn:hover {
    background-color: rgba(255, 255, 255, 0.25);
    color: #ffffff !important;
}

.pill-remove-icon {
    width: 11px;
    height: 11px;
}

.quick-fav-empty-hint {
    display: flex;
    align-items: center;
    font-size: 11.5px;
    color: var(--bs-secondary-color, #878a99);
    font-style: italic;
    white-space: nowrap;
}

/* Adjust page content padding top to account for quick-favorites-bar */
body:has(#quick-favorites-bar) .main-content .page-content {
    padding-top: 112px !important;
}
</style>
