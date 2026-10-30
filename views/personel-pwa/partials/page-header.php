<?php

if (!function_exists('pwaPageHeader')) {
    function pwaPageHeader(array $options = []): void
    {
        global $personel;

        $eyebrow = (string) ($options['eyebrow'] ?? '');
        $title = (string) ($options['title'] ?? '');
        $subtitle = (string) ($options['subtitle'] ?? '');
        $backHref = $options['back'] ?? '?page=ana-sayfa';
        $eyebrowId = (string) ($options['eyebrow_id'] ?? '');
        $titleId = (string) ($options['title_id'] ?? '');
        $subtitleId = (string) ($options['subtitle_id'] ?? '');
        $idAttr = static fn(string $id): string => $id !== '' ? ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"' : '';
        $actionsHtml = (string) ($options['actions'] ?? '');

        $adSoyad = trim((string) ($personel->adi_soyadi ?? ''));
        $initials = '';
        foreach (preg_split('/\s+/u', $adSoyad, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $parca) {
            $initials .= mb_strtoupper(mb_substr($parca, 0, 1, 'UTF-8'), 'UTF-8');
            if (mb_strlen($initials, 'UTF-8') >= 2) {
                break;
            }
        }
        if ($initials === '') {
            $initials = 'P';
        }

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        ?>
        <header class="pwa-topbar">
            <div class="pwa-topbar__main">
                <?php if ($backHref !== false): ?>
                    <a href="<?= $esc((string) $backHref) ?>" class="pwa-circle-btn" aria-label="Geri dön">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </a>
                <?php endif; ?>
                <div class="pwa-topbar__titles">
                    <?php if ($eyebrow !== '' || $eyebrowId !== ''): ?>
                        <span class="pwa-topbar__eyebrow"<?= $idAttr($eyebrowId) ?>><?= $esc($eyebrow) ?></span>
                    <?php endif; ?>
                    <h1 class="pwa-topbar__title"<?= $idAttr($titleId) ?>><?= $esc($title) ?></h1>
                    <?php if ($subtitle !== '' || $subtitleId !== ''): ?>
                        <p class="pwa-topbar__subtitle"<?= $idAttr($subtitleId) ?>><?= $esc($subtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pwa-topbar__actions">
                <?= $actionsHtml ?>
                <a href="?page=profil" class="pwa-avatar-circle" aria-label="Profilim"><?= $esc($initials) ?></a>
            </div>
        </header>
        <?php
    }
}
