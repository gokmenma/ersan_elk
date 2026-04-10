<?php
/**
 * Bu dosya home.php'deki widget'ların hem ilk yüklemede hem de AJAX ile lazy-load 
 * edilmesinde ortak kullanılmasını sağlar.
 */

use App\Helper\Security;
use App\Service\Gate;

function renderWidget(string $widgetId, array $data = []) {
    extract($data);
    ob_start();
    
    switch ($widgetId) {
        case 'widget-personel-ozet':
            ?>
            <div class="col-md-6 col-xl-4 widget-item" id="widget-personel-ozet">
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

        case 'widget-arac-ozet':
            ?>
            <div class="col-md-6 col-xl-4 widget-item" id="widget-arac-ozet">
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
            <div class="col-6 col-md-2 widget-item" id="widget-bekleyen-talepler">
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
            <div class="col-6 col-md-2 widget-item" id="widget-nobetciler">
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

        case 'widget-yaklasan-gorevler':
            ?>
            <div class="<?php echo ($width ?? 'col-md-6'); ?> widget-item" id="widget-yaklasan-gorevler">
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
            ?>
            <div class="<?php echo ($width ?? 'col-12'); ?> widget-item" id="widget-bildirimler">
                <div class="card summary-card" style="background: linear-gradient(145deg, rgba(255,255,255,0.98), rgba(248,250,252,0.99)); border: 1px solid rgba(226,232,240,0.8); border-radius: 12px; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.05), 0 2px 5px -2px rgba(0,0,0,0.02);">
                    <div class="card-header align-items-center d-flex flex-wrap gap-2">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2"><i class='bx bx-grid-vertical drag-handle'></i></h5>
                        <div class="flex-shrink-0 flex-grow-1" style="align-self: flex-end;">
                            <ul class="nav nav-tabs card-header-tabs m-0" role="tablist">
                                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#gorev-tab" role="tab">Görev ve Bildirimler <?php if (!empty($recent_logs)): ?><span class="badge bg-light text-muted ms-1" style="font-size: 0.75rem; border: 1px solid var(--bs-border-color);"><?php echo count($recent_logs); ?></span><?php endif; ?></a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#personel-giris-tab" role="tab">Personel Girişleri <?php if (!empty($personelLogs)): ?><span class="badge bg-light text-muted ms-1" style="font-size: 0.75rem; border: 1px solid var(--bs-border-color);"><?php echo count($personelLogs); ?></span><?php endif; ?></a></li>
                                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#kullanici-giris-tab" role="tab">Yönetici Girişleri <?php if (!empty($kullaniciLogs)): ?><span class="badge bg-light text-muted ms-1" style="font-size: 0.75rem; border: 1px solid var(--bs-border-color);"><?php echo count($kullaniciLogs); ?></span><?php endif; ?></a></li>
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2 ms-auto"><a href="index.php?p=logs/list" class="btn btn-sm btn-soft-primary rounded-pill"><i class="bx bx-list-ul me-1"></i> Tümünü Gör</a></div>
                    </div>
                    <div class="card-body" style="padding: 0; min-height: <?php echo ($height ?? 'auto'); ?>;">
                        <div class="tab-content" style="height: <?php echo ($height ?? 'auto'); ?>; overflow-y: auto;">
                            <div class="tab-pane active" id="gorev-tab" role="tabpanel">
                                <div class="notification-list p-3">
                                    <?php if (empty($recent_logs)): ?>
                                        <div class="text-center py-5" style="color: #64748b;"><div class="avatar-sm mx-auto mb-3"><div class="avatar-title rounded-circle bg-light text-muted" style="font-size: 1.5rem;"><i class="bx bx-bell-off"></i></div></div><p class="mb-0 fw-medium">Kayıt bulunmamaktadır.</p></div>
                                    <?php else: ?>
                                        <?php foreach ($recent_logs as $log): ?>
                                            <?php
                                            $logLevel = $log->level ?? 0;
                                            $renk = $logLevel >= 2 ? '#ef4444' : ($logLevel >= 1 ? '#f59e0b' : '#3b82f6');
                                            $icon = $logLevel >= 2 ? 'bx-error-circle' : ($logLevel >= 1 ? 'bx-error' : 'bx-info-circle');
                                            $hex = ltrim($renk, '#');
                                            $iconBg = "rgba(".hexdec(substr($hex, 0, 2)).", ".hexdec(substr($hex, 2, 2)).", ".hexdec(substr($hex, 4, 2)).", 0.1)";
                                            $user_name = $log->adi_soyadi ?? 'Sistem';
                                            ?>
                                            <div class="notification-card p-3 btn-log-detay" style="cursor: pointer;" data-title="<?php echo htmlspecialchars($log->action_type); ?>" data-user="<?php echo htmlspecialchars($user_name); ?>" data-date="<?php echo date('d.m.Y H:i', strtotime($log->created_at)); ?>" data-content="<?php echo htmlspecialchars($log->description); ?>">
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="icon-box" style="background: <?php echo $iconBg; ?>;"><i class="bx <?php echo $icon; ?>" style="color: <?php echo $renk; ?>; font-size: 1.2rem;"></i></div>
                                                    <div class="flex-grow-1 overflow-hidden"><h6 class="mb-1 text-truncate fw-semibold" style="font-size: 0.9rem; color: var(--bs-heading-color);"><?php echo htmlspecialchars($log->action_type); ?></h6><p class="text-muted mb-0 text-truncate" style="font-size: 0.8rem; opacity: 0.85;"><?php echo mb_strimwidth(htmlspecialchars($log->description), 0, 150, "..."); ?></p></div>
                                                    <div class="flex-shrink-0 text-end d-flex flex-column align-items-end gap-1"><div class="text-dark fw-semibold" style="font-size: 0.8rem;"><i class="bx bx-user-circle me-1 text-muted" style="font-size: 0.9rem; vertical-align: middle;"></i><?php echo $user_name; ?></div><div class="text-muted d-flex align-items-center gap-2" style="font-size: 0.75rem; font-weight: 500;"><span><i class="bx bx-calendar me-1" style="font-size: 0.85rem; vertical-align: middle;"></i><?php echo date('d.m.Y', strtotime($log->created_at)); ?></span><span class="badge bg-light text-muted border px-1" style="font-size: 0.65rem; border-radius: 4px;"><?php echo date('H:i', strtotime($log->created_at)); ?></span></div></div>
                                                    <div class="flex-shrink-0 ms-1"><i class="bx bx-chevron-right text-muted opacity-50" style="font-size: 1.25rem;"></i></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Personel and Kullanici Giriş tabs... (Simplified for brevity or keep full if needed) -->
                             <div class="tab-pane" id="personel-giris-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-nowrap align-middle mb-0">
                                        <thead style="background: rgba(248,250,252,0.8); position: sticky; top: 0; z-index: 10;"><tr style="border-bottom: 2px solid #f1f5f9;"><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Ad Soyad</th><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Tarih</th><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Tarayıcı</th><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">IP</th></tr></thead>
                                        <tbody>
                                            <?php if (empty($personelLogs)): ?><tr><td colspan="4" class="text-center py-4">Kayıt bulunamadı.</td></tr><?php else: foreach ($personelLogs as $ll): ?>
                                                <tr><td><div class="d-flex align-items-center"><div class="avatar-sm me-3"><span class="avatar-title rounded-circle" style="background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #4f46e5;"><?php echo mb_substr($ll->adi_soyadi, 0, 1, 'UTF-8'); ?></span></div><div><h5 class="font-size-14 mb-0"><?php echo htmlspecialchars($ll->adi_soyadi); ?></h5></div></div></td><td><?php echo date('d.m.Y H:i', strtotime($ll->tarih)); ?></td><td><?php echo htmlspecialchars($ll->tarayici); ?></td><td><?php echo htmlspecialchars($ll->ip_adresi); ?></td></tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                             </div>
                             <div class="tab-pane" id="kullanici-giris-tab" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-borderless table-nowrap align-middle mb-0">
                                        <thead style="background: rgba(248,250,252,0.8);"><tr style="border-bottom: 2px solid #f1f5f9;"><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Ad Soyad</th><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">Tarih</th><th style="font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 600; padding: 0.75rem 1rem;">IP</th></tr></thead>
                                        <tbody>
                                            <?php if (empty($kullaniciLogs)): ?><tr><td colspan="3" class="text-center py-4">Kayıt bulunamadı.</td></tr><?php else: foreach ($kullaniciLogs as $ll): ?>
                                                <tr><td><div class="d-flex align-items-center"><div class="avatar-sm me-3"><span class="avatar-title rounded-circle" style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); color: #16a34a;"><?php echo mb_substr($ll->adi_soyadi, 0, 1, 'UTF-8'); ?></span></div><div><h5 class="font-size-14 mb-0"><?php echo htmlspecialchars($ll->adi_soyadi); ?></h5></div></div></td><td><?php echo date('d.m.Y H:i', strtotime($ll->tarih)); ?></td><td><?php echo htmlspecialchars($ll->ip_adresi); ?></td></tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
            break;
    }

    return ob_get_clean();
}

/**
 * Skeleton HTML returner
 */
function renderSkeleton(string $widgetId, string $width = 'col-md-6', string $height = '200px') {
    return '
    <div class="'.$width.' widget-item lazy-widget" id="'.$widgetId.'" data-lazy-load="true">
        <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; min-height: '.$height.';">
            <div class="card-body p-4">
                <div class="skeleton-shimmer" style="height: 20px; width: 40%; margin-bottom: 20px; border-radius: 4px; background: rgba(0,0,0,0.05);"></div>
                <div class="skeleton-shimmer" style="height: 15px; width: 100%; margin-bottom: 10px; border-radius: 4px; background: rgba(0,0,0,0.03);"></div>
                <div class="skeleton-shimmer" style="height: 15px; width: 90%; margin-bottom: 10px; border-radius: 4px; background: rgba(0,0,0,0.03);"></div>
                <div class="skeleton-shimmer" style="height: 15px; width: 95%; margin-bottom: 10px; border-radius: 4px; background: rgba(0,0,0,0.03);"></div>
            </div>
        </div>
    </div>';
}
