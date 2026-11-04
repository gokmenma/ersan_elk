<?php
/**
 * Personel PWA - Bordro Sayfası
 * Resmî bordro listesi
 */
if (!isset($personel_id) || !$personel_id) { http_response_code(403); return; }
if (($personel->personel_tipi ?? '') === 'kaski_kacak') {
    echo '<div class="px-4 py-4">';
    require dirname(__DIR__) . '/bordro-yayin-panel.php';
    echo '</div>';
    return;
}
?>

<div class="flex flex-col min-h-screen">
    <?php pwaPageHeader([
        'eyebrow' => 'KAZANÇLARIM',
        'title' => 'Bordrolar',
        'subtitle' => 'Resmî bordrolarınızı inceleyin',
    ]); ?>

    <div class="flex-1 px-4 py-4">
        <?php require dirname(__DIR__) . '/bordro-yayin-panel.php'; ?>
    </div>
</div>
