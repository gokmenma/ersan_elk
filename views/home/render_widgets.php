<?php
/**
 * Bu dosya home.php'deki widget'ların hem ilk yüklemede hem de AJAX ile lazy-load 
 * edilmesinde ortak kullanılmasını sağlar.
 */

use App\Helper\Security;
use App\Service\Gate;

if (!isset($saved_settings) || !is_array($saved_settings)) {
    $saved_settings = isset($_COOKIE['dashboard_settings']) ? json_decode($_COOKIE['dashboard_settings'], true) : [];
}

if (!function_exists('getWidgetWidthClass')) {
    function getWidgetWidthClass($id, $defaultClass) {
        global $saved_settings, $dashboard_is_free;
        $is_free = $dashboard_is_free ?? ($_COOKIE['switch_free_layout'] ?? 'false') === 'true';
        if (!$is_free) return $defaultClass;
        
        $w = $saved_settings[$id]['width'] ?? '';
        if (empty($w) || !str_contains($w, 'col-')) {
            return $defaultClass;
        }
        return $w;
    }
}

if (!function_exists('getWidgetWidth')) {
    function getWidgetWidth($id, $default) {
        global $saved_settings, $dashboard_is_free;
        $is_free = $dashboard_is_free ?? ($_COOKIE['switch_free_layout'] ?? 'false') === 'true';
        if (!$is_free) return $default;
        return $saved_settings[$id]['width'] ?? $default;
    }
}

if (!function_exists('getWidgetHeight')) {
    function getWidgetHeight($id, $default) {
        global $saved_settings, $dashboard_is_free;
        $is_free = $dashboard_is_free ?? ($_COOKIE['switch_free_layout'] ?? 'false') === 'true';
        if (!$is_free) return $default;
        return $saved_settings[$id]['height'] ?? $default;
    }
}

if (!function_exists('getWidgetStyle')) {
    function getWidgetStyle($id) {
        global $saved_settings, $dashboard_is_free;
        $is_free = $dashboard_is_free ?? ($_COOKIE['switch_free_layout'] ?? 'false') === 'true';
        
        $w = $saved_settings[$id]['width'] ?? '';
        $h = $saved_settings[$id]['height'] ?? '';
        $left = $saved_settings[$id]['left'] ?? '';
        $top = $saved_settings[$id]['top'] ?? '';
        $hidden = $saved_settings[$id]['hidden'] ?? '';
        
        $style = '';
        
        // Only apply manual dimensions and positions if free layout is active
        if ($is_free) {
            if (!empty($w) && !str_contains($w, 'col-')) {
                $style .= "width: {$w} !important; flex: none !important; max-width: none !important; ";
            }
            if (!empty($h) && $h !== 'auto') {
                $style .= "height: {$h} !important; ";
            }
            if ($left !== '' && $top !== '') {
                $style .= "position: absolute !important; left: {$left} !important; top: {$top} !important; z-index: 100 !important; ";
            }
        }

        if ($hidden === 'true') {
            $style .= "display: none !important; ";
        }
        return $style;
    }
}

function renderWidget(string $widgetId, array $data = []) {
    $widgetDomId = htmlspecialchars($data['render_id'] ?? $widgetId, ENT_QUOTES, 'UTF-8');
    extract($data, EXTR_SKIP);
    ob_start();
    
    switch ($widgetId) {
        case 'widget-personel-ozeti':
        case 'widget-personel-ozet':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, 'col-md-4 col-xl-4'); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card" style="border-radius: 12px; background: #fff;">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 d-flex align-items-center" style="font-size: 1.1rem; letter-spacing: -0.01em;">
                                    <i class='bx bx-grid-vertical drag-handle me-1 text-muted'></i> Personel Durumu
                                </h6>
                                <p class="text-muted mb-0 ms-4" style="font-size: 0.8rem;">Toplam Personel</p>
                                <h4 class="mb-0 text-dark fw-bold ms-4"><?php echo $istatistik->aktif_personel ?? 0; ?> <span style="font-size: 0.9rem; font-weight: normal; color: #6c757d;">adet</span></h4>
                            </div>
                            <a href="index.php?p=personel/list" class="text-primary fw-semibold small text-decoration-none" style="font-size: 0.8rem;">Personel Listesine Git</a>
                        </div>
                        <?php
                        $aktif_p = $istatistik->aktif_personel ?? 0;
                        $toplam_p = $aktif_p ?: 1;
                        $saha_p = $extraStats->sahadaki_personel ?? 0;
                        $izinli_p = $extraStats->izinli_personel ?? 0;
                        $diger_p = max(0, $aktif_p - $saha_p - $izinli_p);
                        
                        $s_rate = ($saha_p / $toplam_p) * 100;
                        $d_rate = ($diger_p / $toplam_p) * 100;
                        $i_rate = ($izinli_p / $toplam_p) * 100;
                        ?>
                        <div class="progress mb-4" style="height: 20px; border-radius: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $s_rate; ?>%; background-color: #0d6efd;" title="Saha: <?php echo $saha_p; ?>"></div>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $d_rate; ?>%; background-color: #0dcaf0;" title="İçeride/Diğer: <?php echo $diger_p; ?>"></div>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $i_rate; ?>%; background-color: #ffc107;" title="İzinli: <?php echo $izinli_p; ?>"></div>
                        </div>
                        <div class="row mt-auto">
                            <div class="col-6 mb-3"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #0d6efd; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">Saha Görevlisi</p><h6 class="mb-0 fw-bold text-dark"><?php echo $saha_p; ?> Adet</h6></div></div></div>
                            <div class="col-6 mb-3"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #0dcaf0; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">İçeride / Diğer</p><h6 class="mb-0 fw-bold text-dark"><?php echo $diger_p; ?> Adet</h6></div></div></div>
                            <div class="col-6"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #ffc107; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">İzinli</p><h6 class="mb-0 fw-bold text-dark"><?php echo $izinli_p; ?> Adet</h6></div></div></div>
                            <div class="col-6"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #f46a6a; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">Geç Kalan</p><h6 class="mb-0 fw-bold text-dark"><?php echo $gec_kalan_sayisi; ?> Adet</h6></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-arac-ozeti':
        case 'widget-arac-ozet':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, 'col-md-4 col-xl-4'); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card" style="border-radius: 12px; background: #fff;">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h6 class="fw-bold text-dark mb-1 d-flex align-items-center" style="font-size: 1.1rem; letter-spacing: -0.01em;">
                                    <i class='bx bx-grid-vertical drag-handle me-1 text-muted'></i> Araç Durumu
                                </h6>
                                <p class="text-muted mb-0 ms-4" style="font-size: 0.8rem;">Toplam Aktif Araç</p>
                                <h4 class="mb-0 text-dark fw-bold ms-4"><?php echo $toplam_aktif_arac; ?> <span style="font-size: 0.9rem; font-weight: normal; color: #6c757d;">adet</span></h4>
                            </div>
                            <a href="index.php?p=arac-takip/list" class="text-primary fw-semibold small text-decoration-none" style="font-size: 0.8rem;">Araç Listesine Git</a>
                        </div>
                        <div class="progress mb-4" style="height: 20px; border-radius: 4px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $aktif_a_yuzde; ?>%; background-color: #198754;" title="Aktif/Saha: <?php echo $saha_arac; ?>"></div>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $servis_a_yuzde; ?>%; background-color: #dc3545;" title="Serviste: <?php echo $servisteki_arac; ?>"></div>
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $bosta_a_yuzde; ?>%; background-color: #ffc107;" title="Boşta: <?php echo $bosta_arac; ?>"></div>
                        </div>
                        <div class="row mt-auto">
                            <div class="col-4 mb-3"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #198754; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">Saha Aracı</p><h6 class="mb-0 fw-bold text-dark"><?php echo $saha_arac; ?> Adet</h6></div></div></div>
                            <div class="col-4 mb-3"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #dc3545; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">Serviste</p><h6 class="mb-0 fw-bold text-dark"><?php echo $servisteki_arac; ?> Adet</h6></div></div></div>
                            <div class="col-4 mb-3"><div class="d-flex align-items-center"><div style="width: 3px; height: 32px; background-color: #ffc107; margin-right: 12px;"></div><div><p class="text-muted small mb-0 font-size-11">Boşta</p><h6 class="mb-0 fw-bold text-dark"><?php echo $bosta_arac; ?> Adet</h6></div></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-bekleyen-talepler':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, 'col-6 col-md-2'); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card stat-card" style="--card-color: #f6c23e; border-bottom: 3px solid var(--card-color) !important; --delay: 0.6s">
                    <div class="card-body p-3 pb-2">
                        <div class="icon-label-container"><div class="icon-box" style="background: rgba(246, 194, 62, 0.1);"><i class="bx bx-time-five fs-4" style="color: #f6c23e;"></i></div><span class="text-muted small fw-bold" style="font-size: 0.65rem;">TALEP</span></div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BEKLEYEN TALEPLER</p>
                        <h4 class="mb-0 fw-bold bordro-text-heading"><?php echo $personel_talep_sayisi ?? 0; ?> <span class="trend-badge <?php echo $personel_talep_sayisi > 0 ? 'down' : 'up'; ?> ms-1"><?php echo $personel_talep_sayisi > 0 ? 'Dikkat' : 'Stabil'; ?></span></h4>
                        <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Onay bekleyen işlemler</div>
                        <div class="card-footer-actions mt-2 d-flex justify-content-end"><a href="index.php?p=talepler/list" class="btn btn-xs btn-soft-warning rounded-pill"><i class="bx bx-right-arrow-alt"></i> Git</a></div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-nobetciler':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, 'col-6 col-md-2'); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card stat-card" style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important; --delay: 0.75s">
                    <div class="card-body p-3">
                        <div class="icon-label-container"><div class="icon-box" style="background: rgba(85, 110, 230, 0.1);"><i class="bx bx-calendar-star fs-4" style="color: #556ee6;"></i></div><span class="text-muted small fw-bold" style="font-size: 0.65rem;">NÖBET</span></div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">BUGÜNKÜ NÖBETÇİLER</p>
                        <div class="grid-content-area">
                            <?php if (empty($nobetciler)): ?>
                                <div class="text-center py-2"><p class="text-muted mb-0 small">Kayıt yok</p></div>
                            <?php else: ?>
                                <div class="nobetci-list" style="max-height: 120px; overflow-y: auto;">
                                    <?php foreach (array_slice($nobetciler, 0, 5) as $nobet): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <img src="<?php echo !empty($nobet->resim_yolu) ? $nobet->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>" class="rounded-circle avatar-xs me-2" style="width: 28px; height: 28px;">
                                            <div class="flex-grow-1 overflow-hidden"><h6 class="mb-0 font-size-12 text-truncate"><?php echo $nobet->adi_soyadi; ?></h6><small class="text-muted font-size-11"><?php echo $nobet->cep_telefonu; ?></small></div>
                                            <?php if ($nobet->cep_telefonu): ?><a href="tel:<?php echo $nobet->cep_telefonu; ?>" class="text-success ms-1 bx-no-drag"><i class="bx bx-phone"></i></a><?php endif; ?>
                                            <a href="javascript:void(0);" class="text-primary ms-1 btn-send-nobet-reminder bx-no-drag" data-id="<?php echo Security::encrypt($nobet->personel_id); ?>" data-name="<?php echo $nobet->adi_soyadi; ?>" title="Bildirim Gönder"><i class="bx bx-bell"></i></a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer-actions mt-2"><div class="sub-text m-0" style="font-size: 10px; color: #858796;"><?php echo count($nobetciler); ?> personel nöbetçi</div><a href="index.php?p=nobet/list" class="btn btn-xs btn-soft-primary rounded-pill"><i class="bx bx-calendar"></i> Takvim</a></div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-gorevler':
        case 'widget-yaklasan-gorevler':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, ($width ?? 'col-md-6')); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card summary-card" style="background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(248,250,252,0.99)); border: 1px solid rgba(226,232,240,0.8); border-radius: 12px; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.05), 0 2px 5px -2px rgba(0,0,0,0.02);">
                    <div class="card-header align-items-center d-flex flex-wrap gap-2" style="border-bottom: 1px solid rgba(226,232,240,0.6); padding-bottom: 12px;">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2" style="font-family: 'Outfit', sans-serif;"><i class='bx bx-grid-vertical drag-handle' style="cursor: move;"></i><i class='bx bx-task' style="color: #6366f1;"></i> Yaklaşan Görevler <?php if (!empty($yaklasan_gorevler)): ?><span class="badge bg-light text-muted ms-1" style="font-size: 0.75rem; border: 1px solid var(--bs-border-color);"><?php echo count($yaklasan_gorevler); ?></span><?php endif; ?></h5>
                        <div class="d-flex align-items-center gap-2 ms-auto"><button type="button" class="btn btn-sm btn-soft-secondary rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;" onclick="location.reload();"><i class="bx bx-refresh fs-5"></i></button><a href="index.php?p=gorevler/list" class="btn btn-sm btn-soft-primary rounded-pill fw-semibold border-0" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">Tümü <i class="bx bx-right-arrow-alt ms-1"></i></a></div>
                    </div>
                    <div class="card-body" style="padding: 1rem; min-height: <?php echo ($height ?? 'auto'); ?>;">
                        <?php if (empty($yaklasan_gorevler)): ?>
                            <div class="text-center py-4"><i class="bx bx-check-circle" style="font-size: 48px; opacity: 0.2; color: #10b981;"></i><p class="text-muted mt-2 mb-0" style="font-weight: 500;">Yaklaşan görev bulunmuyor.</p></div>
                        <?php else: ?>
                            <div class="yaklasan-gorev-list">
                                <?php foreach ($yaklasan_gorevler as $gorev): ?>
                                    <?php
                                    $renk = $gorev->liste_renk ?: '#6366f1';
                                    $tarihVar = !empty($gorev->tarih);
                                    $isGecikti = $tarihVar && (strtotime($gorev->tarih . ' ' . ($gorev->saat ?? '23:59:59')) < time());
                                    $isBugun = $tarihVar && ($gorev->tarih == date('Y-m-d'));
                                    $status_color = $isGecikti ? '#ef4444' : ($isBugun ? '#f59e0b' : ($tarihVar ? '#10b981' : '#64748b'));
                                    $status_text = $tarihVar ? date('d M', strtotime($gorev->tarih)) : 'Tarih Yok';
                                    $hex = ltrim($renk, '#');
                                    $iconBg = "rgba(".hexdec(substr($hex, 0, 2)).", ".hexdec(substr($hex, 2, 2)).", ".hexdec(substr($hex, 4, 2)).", 0.1)";
                                    ?>
                                    <a href="index.php?p=gorevler/list&task_id=<?php echo $gorev->id; ?>" class="gorev-card p-3">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="icon-box" style="background: <?php echo $iconBg; ?>;"><i class="bx bx-check-double" style="color: <?php echo $renk; ?>; font-size: 1.1rem;"></i></div>
                                            <div class="flex-grow-1 overflow-hidden"><h6 class="mb-1 text-truncate fw-semibold" style="font-size: 0.9rem; color: var(--bs-heading-color);"><?php echo htmlspecialchars($gorev->baslik); ?></h6><div class="d-flex align-items-center gap-2"><i class="bx bx-folder" style="font-size: 0.75rem; color: <?php echo $renk; ?>;"></i><span class="text-muted text-truncate" style="font-size: 0.75rem;"><?php echo htmlspecialchars($gorev->liste_adi); ?></span></div></div>
                                            <div class="text-end flex-shrink-0 ms-2"><div class="d-flex align-items-center justify-content-end gap-2 mb-1"><span class="text-muted" style="font-size: 0.75rem;"><?php echo $status_text; ?></span><div class="status-dot" style="background-color: <?php echo $status_color; ?>;"></div><?php if ($isGecikti): ?><i class="bx bxs-error-circle text-danger" style="font-size: 0.9rem;"></i><?php endif; ?></div><i class="bx bx-chevron-right text-muted opacity-50"></i></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-bildirimler':
            $logMetaHelper = function($log) {
                $actionType = $log->action_type ?? 'Sistem Olayı';
                $desc = $log->description ?? '';
                $level = (int)($log->level ?? 0);

                // Platform tespiti
                $platform = null;
                $platformIcon = 'bx-desktop';
                if (preg_match('/^\[(Desktop|Mobile|PWA|Cron|API|Sistem|Web)\]/i', $desc, $matches)) {
                    $platform = ucfirst(strtolower($matches[1]));
                    if (strtolower($platform) === 'mobile') $platformIcon = 'bx-mobile-alt';
                    elseif (strtolower($platform) === 'pwa') $platformIcon = 'bx-devices';
                    elseif (strtolower($platform) === 'cron') $platformIcon = 'bx-sync';
                    elseif (strtolower($platform) === 'api') $platformIcon = 'bx-code-alt';
                    else $platformIcon = 'bx-desktop';
                    
                    $cleanDesc = trim(preg_replace('/^\[(Desktop|Mobile|PWA|Cron|API|Sistem|Web)\]\s*/i', '', $desc));
                } else {
                    $cleanDesc = $desc;
                }

                $lowerAction = mb_strtolower($actionType, 'UTF-8');
                $lowerDesc = mb_strtolower($desc, 'UTF-8');

                if ($level >= 2 || str_contains($lowerAction, 'hata') || str_contains($lowerAction, 'kritik') || str_contains($lowerAction, 'güvenlik')) {
                    $icon = 'bx-error-circle';
                    $accent = '#ef4444';
                    $category = 'Kritik Olay';
                } elseif (str_contains($lowerAction, 'sil') || str_contains($lowerDesc, 'silindi')) {
                    $icon = 'bx-trash';
                    $accent = '#f43f5e';
                    $category = 'Silme İşlemi';
                } elseif (str_contains($lowerAction, 'sayfa') || str_contains($lowerAction, 'görüntüle')) {
                    $icon = 'bx-window-alt';
                    $accent = '#6366f1';
                    $category = 'Gezinti';
                } elseif (str_contains($lowerAction, 'cron') || str_contains($lowerAction, 'otomasyon')) {
                    $icon = 'bx-sync';
                    $accent = '#0ea5e9';
                    $category = 'Otomasyon';
                } elseif (str_contains($lowerAction, 'personel') || str_contains($lowerAction, 'çalışan')) {
                    $icon = 'bx-user-check';
                    $accent = '#3b82f6';
                    $category = 'Personel';
                } elseif (str_contains($lowerAction, 'maaş') || str_contains($lowerAction, 'bordro') || str_contains($lowerAction, 'avans') || str_contains($lowerAction, 'hakediş') || str_contains($lowerAction, 'fatura') || str_contains($lowerAction, 'cari')) {
                    $icon = 'bx-wallet';
                    $accent = '#10b981';
                    $category = 'Finans';
                } elseif (str_contains($lowerAction, 'puantaj') || str_contains($lowerAction, 'nöbet') || str_contains($lowerAction, 'izin') || str_contains($lowerAction, 'mesai')) {
                    $icon = 'bx-calendar-event';
                    $accent = '#8b5cf6';
                    $category = 'Vardiya';
                } elseif (str_contains($lowerAction, 'araç') || str_contains($lowerAction, 'yakıt') || str_contains($lowerAction, 'km') || str_contains($lowerAction, 'filo')) {
                    $icon = 'bx-car';
                    $accent = '#f59e0b';
                    $category = 'Araç & Filo';
                } elseif (str_contains($lowerAction, 'endeks') || str_contains($lowerAction, 'sayaç') || str_contains($lowerAction, 'kesme') || str_contains($lowerAction, 'online')) {
                    $icon = 'bx-broadcast';
                    $accent = '#06b6d4';
                    $category = 'Saha';
                } else {
                    $icon = 'bx-info-circle';
                    $accent = '#3b82f6';
                    $category = 'Sistem';
                }

                $hex = ltrim($accent, '#');
                $iconBg = "rgba(" . hexdec(substr($hex, 0, 2)) . ", " . hexdec(substr($hex, 2, 2)) . ", " . hexdec(substr($hex, 4, 2)) . ", 0.12)";

                $time = strtotime($log->created_at);
                $diff = time() - $time;
                if ($diff < 60) {
                    $relTime = 'Az önce';
                } elseif ($diff < 3600) {
                    $relTime = floor($diff / 60) . ' dk önce';
                } elseif ($diff < 86400) {
                    $relTime = floor($diff / 3600) . ' saat önce';
                } elseif ($diff < 172800) {
                    $relTime = 'Dün';
                } else {
                    $relTime = date('d.m.Y', $time);
                }

                $userName = $log->adi_soyadi ?? 'Sistem';
                $initials = mb_strtoupper(mb_substr($userName, 0, 1, 'UTF-8'), 'UTF-8');

                return [
                    'icon' => $icon,
                    'accent' => $accent,
                    'iconBg' => $iconBg,
                    'category' => $category,
                    'platform' => $platform,
                    'platformIcon' => $platformIcon,
                    'cleanDesc' => $cleanDesc,
                    'relTime' => $relTime,
                    'user' => $userName,
                    'initials' => $initials,
                    'dateFormatted' => date('d.m.Y', $time),
                    'timeFormatted' => date('H:i', $time)
                ];
            };

            $parseBrowserInfo = function($ua) {
                $ua = $ua ?: '';
                if (stripos($ua, 'Chrome') !== false && stripos($ua, 'Edg') === false && stripos($ua, 'OPR') === false) {
                    return ['icon' => 'bxl-chrome', 'name' => 'Chrome', 'color' => '#ea4335', 'bg' => 'rgba(234,67,53,0.12)'];
                } elseif (stripos($ua, 'Edg') !== false) {
                    return ['icon' => 'bxl-edge', 'name' => 'Edge', 'color' => '#0078d7', 'bg' => 'rgba(0,120,215,0.12)'];
                } elseif (stripos($ua, 'Firefox') !== false) {
                    return ['icon' => 'bxl-firefox', 'name' => 'Firefox', 'color' => '#ff7118', 'bg' => 'rgba(255,113,24,0.12)'];
                } elseif (stripos($ua, 'Safari') !== false && stripos($ua, 'Chrome') === false) {
                    return ['icon' => 'bxl-apple', 'name' => 'Safari', 'color' => '#0f172a', 'bg' => 'rgba(15,23,42,0.12)'];
                } elseif (stripos($ua, 'Opera') !== false || stripos($ua, 'OPR') !== false) {
                    return ['icon' => 'bxl-opera', 'name' => 'Opera', 'color' => '#ff1b2d', 'bg' => 'rgba(255,27,45,0.12)'];
                }
                return ['icon' => 'bx-globe', 'name' => (!empty($ua) ? mb_strimwidth($ua, 0, 16, '...') : 'Web'), 'color' => '#64748b', 'bg' => 'rgba(100,116,139,0.12)'];
            };

            $parseAiPrompt = function($rawPrompt) {
                $rawPrompt = trim($rawPrompt ?? '');
                $payload = null;
                $mainPrompt = $rawPrompt;
                
                if (preg_match('/^(.*?)(?:\s*(?:Bordro Bağlamı|Bağlam|Context|Veri Seti|Veri|JSON|Payload)\s*:\s*|\s+)(\{.*)$/us', $rawPrompt, $matches)) {
                    $mainPrompt = trim($matches[1]);
                    $payload = trim($matches[2]);
                } elseif (preg_match('/^(.*?)(?:\n\s*|\s+)(\{.*)$/us', $rawPrompt, $matches)) {
                    $mainPrompt = trim($matches[1]);
                    $payload = trim($matches[2]);
                }
                
                if (empty($mainPrompt) && !empty($payload)) {
                    $mainPrompt = 'Yapay Zeka Analiz / Rapor Sorgusu';
                }
                
                $shortSummary = mb_strimwidth($mainPrompt, 0, 130, '...');
                
                return [
                    'mainPrompt' => $mainPrompt,
                    'shortSummary' => $shortSummary,
                    'hasPayload' => !empty($payload),
                    'payload' => $payload,
                    'fullPrompt' => $rawPrompt
                ];
            };
            ?>
            <style>
                #widget-bildirimler .summary-card { border:1px solid #dbe4ef !important; box-shadow:0 4px 18px rgba(15,23,42,.04) !important; }
                #widget-bildirimler .notification-list { display:flex; flex-direction:column; gap:8px; background:#f8fafc; }
                #widget-bildirimler .notification-card { 
                    position:relative; 
                    background:#fff; 
                    border:1px solid #e2e8f0; 
                    border-left:3.5px solid var(--activity-accent,#3b82f6); 
                    border-radius:10px; 
                    padding:11px 14px;
                    transition:all .18s cubic-bezier(.16,1,.3,1); 
                    box-shadow:0 1px 3px rgba(0,0,0,.02);
                }
                #widget-bildirimler .notification-card:hover { 
                    border-color:#bfdbfe; 
                    box-shadow:0 6px 18px -2px rgba(15,23,42,.08); 
                    transform:translateY(-2px); 
                }
                #widget-bildirimler .notification-card .icon-box { 
                    width:38px;height:38px;min-width:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;
                    transition:transform .18s ease;
                }
                #widget-bildirimler .notification-card:hover .icon-box { transform:scale(1.06); }
                #widget-bildirimler .finder-tabs-nav .nav-link.active,
                #widget-bildirimler .finder-tabs-nav .nav-link.active * { color:#1d4ed8 !important; }
                #widget-bildirimler .finder-tabs-nav .nav-link.active { background:#fff !important;border-color:#bfdbfe !important;box-shadow:0 2px 7px rgba(37,99,235,.12) !important; }
                #widget-bildirimler .finder-tabs-nav .nav-link.active .badge { background:#e0e7ff !important; color:#1e40af !important; }
                #widget-bildirimler .tab-content { background:#f8fafc; }
                #widget-bildirimler .tab-pane { padding: 12px; }
                #widget-bildirimler .log-subtle-table { width:100%; border-collapse:separate; border-spacing:0 8px; }
                #widget-bildirimler .log-subtle-table tr { 
                    background:#ffffff; 
                    border:1px solid #e2e8f0; 
                    border-radius:10px; 
                    transition:all .18s ease; 
                    box-shadow:0 1px 3px rgba(0,0,0,.02);
                }
                #widget-bildirimler .log-subtle-table tr:hover { 
                    border-color:#cbd5e1; 
                    box-shadow:0 4px 12px rgba(15,23,42,.06); 
                    transform:translateY(-1px);
                }
                #widget-bildirimler .log-subtle-table td { 
                    padding:10px 14px; 
                    vertical-align:middle; 
                    border-top:1px solid #e2e8f0; 
                    border-bottom:1px solid #e2e8f0; 
                }
                #widget-bildirimler .log-subtle-table td:first-child { 
                    border-left:1px solid #e2e8f0; 
                    border-top-left-radius:10px; 
                    border-bottom-left-radius:10px; 
                }
                #widget-bildirimler .log-subtle-table td:last-child { 
                    border-right:1px solid #e2e8f0; 
                    border-top-right-radius:10px; 
                    border-bottom-right-radius:10px; 
                }

                /* Dark mode */
                [data-bs-theme="dark"] #widget-bildirimler .notification-list,
                [data-bs-theme="dark"] #widget-bildirimler .tab-content { background:#151922; }
                [data-bs-theme="dark"] #widget-bildirimler .notification-card { background:#1e2430;border-color:#2e3646; }
                [data-bs-theme="dark"] #widget-bildirimler .notification-card:hover { border-color:#3e4a60; box-shadow:0 6px 18px rgba(0,0,0,.35); }
                [data-bs-theme="dark"] #widget-bildirimler .finder-tabs-nav .nav-link.active,
                [data-bs-theme="dark"] #widget-bildirimler .finder-tabs-nav .nav-link.active * { color:#60a5fa !important; }
                [data-bs-theme="dark"] #widget-bildirimler .finder-tabs-nav .nav-link.active .badge { background:#1e3a8a !important; color:#93c5fd !important; }
                [data-bs-theme="dark"] #widget-bildirimler .log-subtle-table tr { background:#1e2430; }
                [data-bs-theme="dark"] #widget-bildirimler .log-subtle-table td { border-color:#2e3646 !important; }
                [data-bs-theme="dark"] #widget-bildirimler .log-subtle-table tr:hover { border-color:#3e4a60; }
            </style>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, ($width ?? 'col-12')); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card summary-card" style="background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(248,250,252,0.99)); border: 1px solid rgba(226,232,240,0.8); border-radius: 12px; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.05), 0 2px 5px -2px rgba(0,0,0,0.02);">
                    <div class="card-body p-0" style="min-height: <?php echo ($height ?? 'auto'); ?>;">
                        <div class="finder-tabs-shell px-3 pt-3 pb-2" style="display: none; border-bottom: 1px solid rgba(226,232,240,0.9); background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="flex-grow-1">
                                    <ul class="nav nav-pills finder-tabs-nav gap-2 m-0" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link active d-flex align-items-center gap-2" data-bs-toggle="tab" href="#gorev-tab" role="tab" style="border-radius: 10px; padding: 0.5rem 0.85rem; background: rgba(255,255,255,0.72); border: 1px solid rgba(148,163,184,0.22); color: #334155; font-size: 0.78rem; font-weight: 600; box-shadow: inset 0 1px 0 rgba(255,255,255,0.65);">
                                                <i class="bx bx-bell" style="font-size: 1rem;"></i>
                                                <span>Görev ve Bildirimler</span>
                                                <?php if (!empty($recent_logs)): ?><span class="badge rounded-pill" style="background: #e2e8f0; color: #334155; font-size: 0.68rem;"><?php echo count($recent_logs); ?></span><?php endif; ?>
                                            </a>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#personel-giris-tab" role="tab" style="border-radius: 10px; padding: 0.5rem 0.85rem; background: rgba(255,255,255,0.52); border: 1px solid rgba(148,163,184,0.18); color: #475569; font-size: 0.78rem; font-weight: 600;">
                                                <i class="bx bx-id-card" style="font-size: 1rem;"></i>
                                                <span>Personel Girişleri</span>
                                                <?php if (!empty($personelLogs)): ?><span class="badge rounded-pill" style="background: #e2e8f0; color: #334155; font-size: 0.68rem;"><?php echo count($personelLogs); ?></span><?php endif; ?>
                                            </a>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#kullanici-giris-tab" role="tab" style="border-radius: 10px; padding: 0.5rem 0.85rem; background: rgba(255,255,255,0.52); border: 1px solid rgba(148,163,184,0.18); color: #475569; font-size: 0.78rem; font-weight: 600;">
                                                <i class="bx bx-shield-quarter" style="font-size: 1rem;"></i>
                                                <span>Yönetici Girişleri</span>
                                                <?php if (!empty($kullaniciLogs)): ?><span class="badge rounded-pill" style="background: #e2e8f0; color: #334155; font-size: 0.68rem;"><?php echo count($kullaniciLogs); ?></span><?php endif; ?>
                                            </a>
                                        </li>
                                        <?php if (\App\Service\Gate::allows('ai_is_ajani_arac_takip')): ?>
                                            <?php 
                                             if (!isset($aiAgentLogs)) {
                                                 $systemLogModel = new \App\Model\SystemLogModel();
                                                 $aiAgentLogs = $systemLogModel->getAiAgentLogs(10);
                                             }
                                             ?>
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link d-flex align-items-center gap-2" data-bs-toggle="tab" href="#ai-agent-tab" role="tab" style="border-radius: 10px; padding: 0.5rem 0.85rem; background: rgba(255,255,255,0.52); border: 1px solid rgba(148,163,184,0.18); color: #475569; font-size: 0.78rem; font-weight: 600;">
                                                    <i class="bx bx-bot" style="font-size: 1rem;"></i>
                                                    <span>Yapay Zeka Sorguları</span>
                                                    <?php if (!empty($aiAgentLogs)): ?><span class="badge rounded-pill" style="background: #e2e8f0; color: #334155; font-size: 0.68rem;"><?php echo count($aiAgentLogs); ?></span><?php endif; ?>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <div class="flex-shrink-0 ms-auto">
                                    <a href="index.php?p=logs/list" class="btn btn-sm rounded-pill" style="background: rgba(255,255,255,0.8); border: 1px solid rgba(148,163,184,0.22); color: #334155; font-weight: 600; padding: 0.45rem 0.9rem;">
                                        <i class="bx bx-list-ul me-1"></i> Tümünü Gör
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="tab-content" style="height: <?php echo ($height ?? 'auto'); ?>; overflow-y: auto;">
                            <!-- 1. Sekme: Görev & Bildirimler / Sistem Logları -->
                            <div class="tab-pane active" id="gorev-tab" role="tabpanel">
                                <div class="notification-list">
                                    <?php if (empty($recent_logs)): ?>
                                        <div class="text-center py-5" style="color: #64748b;">
                                            <div class="avatar-md mx-auto mb-3">
                                                <div class="avatar-title rounded-circle bg-light text-muted font-size-24"><i class="bx bx-bell-off"></i></div>
                                            </div>
                                            <h6 class="fw-semibold text-dark mb-1">Kayıt Bulunmamaktadır</h6>
                                            <p class="text-muted font-size-12 mb-0">Sistemde son döneme ait bildirim kaydı mevcut değil.</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recent_logs as $log): ?>
                                            <?php
                                            $meta = $logMetaHelper($log);
                                            ?>
                                            <div class="notification-card btn-log-detay" style="cursor:pointer;--activity-accent:<?php echo $meta['accent']; ?>;" data-title="<?php echo htmlspecialchars($log->action_type, ENT_QUOTES, 'UTF-8'); ?>" data-user="<?php echo htmlspecialchars($meta['user'], ENT_QUOTES, 'UTF-8'); ?>" data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>" data-content="<?php echo htmlspecialchars($log->description, ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="d-flex align-items-center gap-3">
                                                    <!-- İkon Kutusu -->
                                                    <div class="icon-box flex-shrink-0" style="background: <?php echo $meta['iconBg']; ?>;">
                                                        <i class="bx <?php echo $meta['icon']; ?>" style="color: <?php echo $meta['accent']; ?>; font-size: 1.25rem;"></i>
                                                    </div>

                                                    <!-- İçerik Bilgileri -->
                                                    <div class="flex-grow-1 overflow-hidden">
                                                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                                            <span class="fw-bold font-size-13 text-dark text-truncate" style="max-width: 220px;"><?php echo htmlspecialchars($log->action_type, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <span class="badge rounded-pill px-2 py-0.5 font-size-11" style="background: <?php echo $meta['iconBg']; ?>; color: <?php echo $meta['accent']; ?>; font-weight: 600;">
                                                                <?php echo $meta['category']; ?>
                                                            </span>
                                                            <?php if ($meta['platform']): ?>
                                                                <span class="badge bg-light text-muted border px-2 py-0.5 font-size-11">
                                                                    <i class="bx <?php echo $meta['platformIcon']; ?> me-1"></i><?php echo $meta['platform']; ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <p class="text-muted mb-0 text-truncate font-size-12" style="opacity:.92;" title="<?php echo htmlspecialchars($meta['cleanDesc'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo mb_strimwidth(htmlspecialchars($meta['cleanDesc'], ENT_QUOTES, 'UTF-8'), 0, 140, "..."); ?>
                                                        </p>
                                                    </div>

                                                    <!-- Sağ Taraf: Kullanıcı & Zaman -->
                                                    <div class="flex-shrink-0 text-end d-flex flex-column align-items-end gap-1 ps-2">
                                                        <div class="d-flex align-items-center gap-1.5 font-size-12 fw-semibold text-dark">
                                                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary fw-bold" style="width: 22px; height: 22px; font-size: 0.68rem;">
                                                                <?php echo $meta['initials']; ?>
                                                            </span>
                                                            <span class="text-truncate" style="max-width: 140px;"><?php echo htmlspecialchars($meta['user'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2 font-size-11 text-muted">
                                                            <span class="text-nowrap" title="<?php echo date('d.m.Y H:i:s', strtotime($log->created_at)); ?>">
                                                                <i class="bx bx-time-five me-1 opacity-75"></i><?php echo $meta['relTime']; ?>
                                                            </span>
                                                            <span class="badge bg-light text-muted border px-1.5 py-0.5 rounded font-size-11">
                                                                <?php echo $meta['timeFormatted']; ?>
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <!-- Detay Ok İkonu -->
                                                    <div class="flex-shrink-0 text-muted opacity-50 ms-1">
                                                        <i class="bx bx-chevron-right font-size-18"></i>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 2. Sekme: Personel Girişleri -->
                            <div class="tab-pane" id="personel-giris-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="log-subtle-table align-middle mb-0">
                                        <tbody>
                                            <?php if (empty($personelLogs)): ?>
                                                <tr><td colspan="4" class="text-center py-4 text-muted">Giriş kaydı bulunamadı.</td></tr>
                                            <?php else: foreach ($personelLogs as $ll): ?>
                                                <?php 
                                                $bInfo = $parseBrowserInfo($ll->tarayici ?? ''); 
                                                $pInitials = mb_strtoupper(mb_substr($ll->adi_soyadi ?? 'P', 0, 1, 'UTF-8'), 'UTF-8');
                                                ?>
                                                <tr>
                                                    <td style="width: 32%;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <div style="width: 36px; height: 36px; min-width: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%); color: #0284c7; flex-shrink: 0;">
                                                                <?php echo $pInitials; ?>
                                                            </div>
                                                            <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0;">
                                                                <span class="log-user-name" style="font-size: 0.82rem; font-weight: 700; color: #1e293b; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                                    <?php echo htmlspecialchars($ll->adi_soyadi); ?>
                                                                </span>
                                                                <div style="margin-top: 3px;">
                                                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill font-size-10 px-1.5 py-0.5">Personel</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="width: 25%;">
                                                        <div class="d-flex align-items-center gap-1.5 font-size-12 text-muted">
                                                            <i class="bx bx-calendar text-primary font-size-14"></i>
                                                            <span class="fw-semibold text-dark"><?php echo date('d.m.Y', strtotime($ll->tarih)); ?></span>
                                                            <span class="badge bg-light text-muted border px-1.5 py-0.5 font-size-11"><?php echo date('H:i', strtotime($ll->tarih)); ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="width: 23%;">
                                                        <span class="badge rounded-pill d-inline-flex align-items-center gap-1 font-size-11 px-2.5 py-1" style="background: <?php echo $bInfo['bg']; ?>; color: <?php echo $bInfo['color']; ?>; border: 1px solid rgba(0,0,0,0.06);">
                                                            <i class="bx <?php echo $bInfo['icon']; ?>"></i> <?php echo htmlspecialchars($bInfo['name']); ?>
                                                        </span>
                                                    </td>
                                                    <td style="width: 20%; text-align: right;">
                                                        <div class="d-inline-flex align-items-center gap-1 font-size-11 text-muted bg-light border rounded-pill px-2.5 py-1">
                                                            <i class="bx bx-network-chart text-info"></i>
                                                            <span class="font-monospace fw-medium"><?php echo htmlspecialchars($ll->ip_adresi); ?></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 3. Sekme: Yönetici Girişleri -->
                            <div class="tab-pane" id="kullanici-giris-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="log-subtle-table align-middle mb-0">
                                        <tbody>
                                            <?php if (empty($kullaniciLogs)): ?>
                                                <tr><td colspan="4" class="text-center py-4 text-muted">Giriş kaydı bulunamadı.</td></tr>
                                            <?php else: foreach ($kullaniciLogs as $ll): ?>
                                                <?php 
                                                $uInitials = mb_strtoupper(mb_substr($ll->adi_soyadi ?? 'Y', 0, 1, 'UTF-8'), 'UTF-8');
                                                ?>
                                                <tr>
                                                    <td style="width: 32%;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <div style="width: 36px; height: 36px; min-width: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a; flex-shrink: 0;">
                                                                <?php echo $uInitials; ?>
                                                            </div>
                                                            <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0;">
                                                                <span class="log-user-name" style="font-size: 0.82rem; font-weight: 700; color: #1e293b; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                                    <?php echo htmlspecialchars($ll->adi_soyadi); ?>
                                                                </span>
                                                                <div style="margin-top: 3px;">
                                                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-10 px-1.5 py-0.5">Yönetici</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="width: 26%;">
                                                        <div class="d-flex align-items-center gap-1.5 font-size-12 text-muted">
                                                            <i class="bx bx-calendar text-success font-size-14"></i>
                                                            <span class="fw-semibold text-dark"><?php echo date('d.m.Y', strtotime($ll->tarih)); ?></span>
                                                            <span class="badge bg-light text-muted border px-1.5 py-0.5 font-size-11"><?php echo date('H:i', strtotime($ll->tarih)); ?></span>
                                                        </div>
                                                    </td>
                                                    <td style="width: 22%;">
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill font-size-11 px-2.5 py-1">
                                                            <i class="bx bx-shield-check me-1"></i> Güvenli Oturum
                                                        </span>
                                                    </td>
                                                    <td style="width: 20%; text-align: right;">
                                                        <div class="d-inline-flex align-items-center gap-1 font-size-11 text-muted bg-light border rounded-pill px-2.5 py-1">
                                                            <i class="bx bx-shield-quarter text-success"></i>
                                                            <span class="font-monospace fw-medium"><?php echo htmlspecialchars($ll->ip_adresi); ?></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- 4. Sekme: Yapay Zeka Sorguları -->
                            <?php if (\App\Service\Gate::allows('ai_is_ajani_arac_takip')): ?>
                            <div class="tab-pane" id="ai-agent-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="log-subtle-table align-middle mb-0">
                                        <tbody>
                                            <?php if (empty($aiAgentLogs)): ?>
                                                <tr><td colspan="5" class="text-center py-4 text-muted">Yapay zeka sorgu kaydı bulunamadı.</td></tr>
                                            <?php else: foreach ($aiAgentLogs as $ail): ?>
                                                <?php 
                                                $aiInitials = mb_strtoupper(mb_substr($ail->adi_soyadi ?? 'A', 0, 1, 'UTF-8'), 'UTF-8');
                                                $parsed = $parseAiPrompt($ail->prompt ?? '');
                                                $modelName = $ail->model_used ?? 'gpt-4o-mini';
                                                ?>
                                                <tr class="btn-log-detay" style="cursor: pointer;"
                                                    data-title="Yapay Zeka Sorgusu (<?php echo htmlspecialchars($modelName, ENT_QUOTES, 'UTF-8'); ?>)"
                                                    data-user="<?php echo htmlspecialchars($ail->adi_soyadi ?? 'Kullanıcı', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-date="<?php echo date('d.m.Y H:i', strtotime($ail->created_at)); ?>"
                                                    data-content="<?php echo htmlspecialchars($ail->prompt, ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-response="<?php echo htmlspecialchars($ail->response ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <td style="width: 22%;">
                                                        <div style="display: flex; align-items: center; gap: 10px;">
                                                            <div style="width: 36px; height: 36px; min-width: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); color: #d97706; flex-shrink: 0;">
                                                                <?php echo $aiInitials; ?>
                                                            </div>
                                                            <div style="display: flex; flex-direction: column; justify-content: center; min-width: 0;">
                                                                <span class="log-user-name" style="font-size: 0.82rem; font-weight: 700; color: #1e293b; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                                    <?php echo htmlspecialchars($ail->adi_soyadi); ?>
                                                                </span>
                                                                <div style="margin-top: 3px;">
                                                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-size-10 px-1.5 py-0.5">Sorgulayan</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td style="width: 48%;">
                                                        <div class="d-flex flex-column gap-1">
                                                            <div class="d-flex align-items-start gap-1.5">
                                                                <i class="bx bx-bot text-warning font-size-15 mt-0.5 flex-shrink-0"></i>
                                                                <span class="font-size-12 fw-medium text-dark text-truncate" style="line-height: 1.4; max-width: 460px;" title="<?php echo htmlspecialchars($parsed['mainPrompt'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo htmlspecialchars($parsed['shortSummary'], ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($parsed['hasPayload']): ?>
                                                                <div class="d-flex align-items-center gap-1 mt-0.5 ms-4">
                                                                    <span class="badge bg-light text-muted border font-size-10 px-1.5 py-0.5 rounded-pill">
                                                                        <i class="bx bx-data me-1 text-primary"></i>Veri Seti Eki İçeriyor
                                                                    </span>
                                                                    <span class="font-size-11 text-primary fw-medium ms-1"><i class="bx bx-show-alt me-0.5"></i>Detay için tıklayın</span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                    <td style="width: 13%; text-align: center;">
                                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11">
                                                            <i class="bx bx-chip me-1"></i><?php echo htmlspecialchars($modelName); ?>
                                                        </span>
                                                    </td>
                                                    <td style="width: 14%; text-align: right;">
                                                        <div class="font-size-11 text-muted text-nowrap">
                                                            <i class="bx bx-time-five me-1"></i><?php echo date('d.m.Y H:i', strtotime($ail->created_at)); ?>
                                                        </div>
                                                    </td>
                                                    <td style="width: 3%; text-align: center;">
                                                        <i class="bx bx-chevron-right text-muted opacity-50 font-size-16"></i>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-gec-kalanlar':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, 'col-6 col-md-2'); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card border-0 shadow-sm h-100 bordro-summary-card animate-card stat-card"
                    style="--card-color: #f46a6a; border-bottom: 3px solid var(--card-color) !important; --delay: 0.9s">
                    <div class="card-body p-3 pb-2">
                        <div class="icon-label-container d-flex justify-content-between align-items-start">
                            <div class="icon-box" style="background: rgba(244, 106, 106, 0.1);">
                                <i class="bx bx-time fs-4" style="color: #f46a6a;"></i>
                            </div>
                            <span class="text-muted small fw-bold" style="font-size: 0.65rem;">GECİKME</span>
                        </div>
                        <p class="text-muted mb-1 small fw-bold" style="letter-spacing: 0.5px; opacity: 0.7;">GEÇ KALAN PERSONEL
                        </p>
                        <h4 class="mb-0 fw-bold bordro-text-heading"><?php echo (int)($gec_kalan_sayisi ?? 0); ?></h4>
                        <div class="sub-text mt-2" style="font-size: 10px; color: #858796;">Mesaiye geç kalanlar</div>
                        <div class="card-footer-actions mt-2 d-flex justify-content-end">
                            <a href="index.php?p=puantaj/raporlar" class="btn btn-xs btn-soft-danger rounded-pill">
                                <i class="bx bx-right-arrow-alt"></i> Git
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-talepler':
            ?>
            <div class="<?php echo getWidgetWidthClass('widget-talepler', 'col-md-6'); ?> widget-item" id="widget-talepler" style="<?php echo getWidgetStyle('widget-talepler'); ?>">
                <div class="card summary-card" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> Bekleyen Talepler</h5>
                    </div>
                    <div class="card-body p-0" style="height: <?php echo ($height ?? '300px'); ?>; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Personel</th>
                                        <th>Talep Tipi</th>
                                        <th>Detay</th>
                                        <th>Tarih</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $filtered_requests = [];
                                    if (!empty($recent_requests)) {
                                        $canAvans = \App\Service\Gate::allows('avans_talepleri');
                                        foreach ($recent_requests as $req) {
                                            if ($req->tip == 'Avans' && !$canAvans) {
                                                continue;
                                            }
                                            $filtered_requests[] = $req;
                                        }
                                    }
                                    ?>
                                    <?php if (empty($filtered_requests)): ?>
                                        <tr><td colspan="5" class="text-center py-4">Bekleyen talep bulunmamaktadır.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($filtered_requests as $req):
                                            $personel = $personel_map[$req->personel_id] ?? null;
                                            $badgeClass = 'badge-warning';
                                            if ($req->tip == 'Avans') $badgeClass = 'badge-success';
                                            if ($req->tip == 'İzin') $badgeClass = 'badge-primary';
                                            if ($req->tip == 'Talep') $badgeClass = 'badge-info';
                                            if ($req->tip == 'Nöbet Değişim') $badgeClass = 'bg-warning text-dark';
                                            if ($req->tip == 'Nöbet Mazeret') $badgeClass = 'bg-danger text-white';
                                            if ($req->tip == 'Nöbet Talebi') $badgeClass = 'bg-info text-white';

                                            $is_nobet = in_array($req->tip, ['Nöbet Değişim', 'Nöbet Mazeret', 'Nöbet Talebi']);
                                            $link_url = $is_nobet ? 'index.php?p=nobet/talepler' : 'index.php?p=talepler/list';
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <img src="<?php echo !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>" alt="" class="avatar-xs rounded-circle">
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="font-size-14 mb-1"><?php echo $personel ? $personel->adi_soyadi : 'Personel #'.$req->personel_id; ?></h5>
                                                            <p class="text-muted mb-0 font-size-12"><?php echo $personel ? $personel->departman : ''; ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><span class="badge <?php echo $badgeClass; ?> font-size-12"><?php echo $req->tip; ?></span></td>
                                                <td><?php echo $req->tip == 'Avans' ? number_format($req->detay, 2) . ' ₺' : $req->detay; ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($req->tarih)); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="<?php echo $link_url; ?>" class="btn btn-primary btn-sm"><i class='bx bx-right-arrow-alt'></i></a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-izindekiler':
            ?>
            <div class="<?php echo getWidgetWidthClass('widget-izindekiler', 'col-md-6'); ?> widget-item" id="widget-izindekiler" style="<?php echo getWidgetStyle('widget-izindekiler'); ?>">
                <div class="card summary-card" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> Şu Anda İzinde Olan Personeller</h5>
                    </div>
                    <div class="card-body p-0" style="height: <?php echo ($height ?? '300px'); ?>; overflow-y: auto;">
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Personel</th>
                                        <th>İzin Tipi</th>
                                        <th>Bitiş Tarihi</th>
                                        <th>Kalan Gün</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($izindekiler)): ?>
                                        <tr><td colspan="4" class="text-center py-4">Şu an izinde olan personel bulunmamaktadır.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($izindekiler as $izin): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="flex-shrink-0 me-3">
                                                            <img src="<?php echo !empty($izin->resim_yolu) ? $izin->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>" alt="" class="avatar-xs rounded-circle">
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <h5 class="font-size-14 mb-1"><?php echo $izin->adi_soyadi; ?></h5>
                                                            <p class="text-muted mb-0 font-size-12"><?php echo $izin->departman; ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo $izin->izin_tipi_adi; ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($izin->bitis_tarihi)); ?></td>
                                                <td>
                                                    <?php
                                                    $bitis = new DateTime($izin->bitis_tarihi);
                                                    $bugun = new DateTime();
                                                    $diff = $bugun->diff($bitis);
                                                    echo $diff->format('%a gün');
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-is-turu-istatistikleri':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, ($width ?? 'col-md-6')); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card summary-card" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> İş Türü İstatistikleri</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="stats-year-filter" style="width: 100px;">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
                                    echo "<option value='$y'>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body" style="height: <?php echo ($height ?? '400px'); ?>; overflow-y: auto;">
                        <div id="work-type-stats-chart" style="min-height: 400px; height: 100%;">
                            <!-- Chart will be initialized by JS -->
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;

        case 'widget-is-emri-sonucu-istatistikleri':
            ?>
            <div class="<?php echo getWidgetWidthClass($widgetDomId, ($width ?? 'col-md-6')); ?> widget-item" id="<?php echo $widgetDomId; ?>" style="<?php echo getWidgetStyle($widgetDomId); ?>">
                <div class="card summary-card" style="border-radius: 12px; overflow: hidden;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class='bx bx-grid-vertical drag-handle me-1'></i> İş Emri Sonuç İstatistikleri</h5>
                        <div class="d-flex align-items-center gap-2">
                            <select class="form-select form-select-sm" id="stats-result-month-filter" style="width: 120px;">
                                <?php
                                $aylar = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];
                                $currentMonth = date('n');
                                foreach ($aylar as $index => $ay) {
                                    $val = $index + 1;
                                    $selected = ($val == $currentMonth) ? 'selected' : '';
                                    echo "<option value='$val' $selected>$ay</option>";
                                }
                                ?>
                            </select>
                            <select class="form-select form-select-sm" id="stats-result-year-filter" style="width: 100px;">
                                <?php
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 4; $y--) {
                                    echo "<option value='$y'>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body" style="height: <?php echo ($height ?? '400px'); ?>; overflow-y: auto;">
                        <div id="work-result-stats-chart" style="min-height: 400px; height: 100%;">
                            <!-- Chart will be initialized by JS -->
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;
    }

    return ob_get_clean();
}

function renderSkeleton(string $widgetId, string $width = 'col-md-6', string $height = '200px') {
    global $saved_settings, $dashboard_is_free;
    $is_free = $dashboard_is_free ?? ($_COOKIE['switch_free_layout'] ?? 'false') === 'true';

    $w = $saved_settings[$widgetId]['width'] ?? '';
    $h = $saved_settings[$widgetId]['height'] ?? '';
    $left = $saved_settings[$widgetId]['left'] ?? '';
    $top = $saved_settings[$widgetId]['top'] ?? '';
    $hidden = $saved_settings[$widgetId]['hidden'] ?? '';
    $style = '';
    $class = $width;

    if ($is_free) {
        if (!empty($w)) {
            if (!str_contains($w, 'col-')) {
                $style .= "width: {$w} !important; ";
            } else {
                $class = $w;
            }
        }

        if (!empty($h) && $h !== 'auto') {
            $style .= "height: {$h} !important; ";
        } else {
            $style .= "min-height: {$height}; ";
        }

        if ($left !== '' && $top !== '') {
            $style .= "position: absolute !important; left: {$left} !important; top: {$top} !important; ";
        }
    } else {
        $style .= "min-height: {$height}; ";
    }

    if ($hidden === 'true') {
        $style .= "display: none !important; ";
    }

    return '
    <div class="'.$class.' widget-item lazy-widget" id="'.$widgetId.'" data-lazy-load="true" style="'.$style.'">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
            <div class="card-body p-4">
                <div class="skeleton-shimmer" style="height: 20px; width: 40%; margin-bottom: 20px; border-radius: 4px; background: rgba(0,0,0,0.05);"></div>
                <div class="skeleton-shimmer" style="height: 15px; width: 100%; margin-bottom: 10px; border-radius: 4px; background: rgba(0,0,0,0.03);"></div>
                <div class="skeleton-shimmer" style="height: 15px; width: 90%; margin-bottom: 10px; border-radius: 4px; background: rgba(0,0,0,0.03);"></div>
                <div class="skeleton-shimmer" style="height: 15px; width: 95%; margin-bottom: 10px; border-radius: 4px; background: rgba(0,0,0,0.03);"></div>
            </div>
        </div>
    </div>';
}
