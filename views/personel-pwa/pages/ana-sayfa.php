<?php
/**
 * Personel PWA - Ana Sayfa
 * Özet bilgiler ve hızlı işlemler
 */
use App\Helper\Helper;

$anaSayfaSiralama = (new \App\Model\PersonelModel())->getAnaSayfaSiralama((int) $personel_id);
require_once __DIR__ . '/../partials/pwa-image-url.php';
$etkinlikSlider = (new \App\Model\DuyuruModel())->getPwaSlider((int) ($_SESSION['firma_id'] ?? 0), (int) $personel_id);
foreach ($etkinlikSlider as &$etkinlikKayit) {
    $etkinlikKayit['resim'] = getPwaImageUrl($etkinlikKayit['resim']);
}
unset($etkinlikKayit);
$kartSirasi = static fn(string $anahtar): int => (int) array_search($anahtar, $anaSayfaSiralama, true) + 1;
?>

<div class="flex flex-col min-h-screen">
    <a id="bordro-beyan-bekleyen" href="?page=bordro" class="hidden card p-4 m-4" role="status"></a>
    <script>
    document.addEventListener('DOMContentLoaded', async () => {
        try {
            const body = new FormData();
            body.append('action', 'bordro-yayin-liste');
            body.append('csrf_token', <?= json_encode(\App\Helper\Security::csrf()) ?>);
            const r = await fetch('api.php', {method:'POST', body, cache:'no-store'});
            const j = await r.json();
            if (!j.success) return;
            const adet = j.data.filter(d => ['yayinda', 'test'].includes(d.durum) && !d.beyan_tarihi).length;
            if (adet) {
                const kart = document.getElementById('bordro-beyan-bekleyen');
                kart.textContent = adet + ' resmî bordronuz okuma beyanınızı bekliyor. İncelemek için dokunun.';
                kart.classList.remove('hidden');
            }
        } catch (e) { console.error('Bordro bekleyenler okunamadı'); }
    });
    </script>

    <!-- iOS PWA Kurulum Rehberi (Sadece iOS Safari'de görünür) -->
    <div id="ios-install-guide" class="hidden px-4 pt-3">
        <div class="bg-blue-600/10 dark:bg-blue-400/10 border border-blue-600/30 dark:border-blue-400/30 rounded-2xl p-4 flex items-center justify-between gap-3 shadow-lg shadow-blue-500/10 active:scale-95 transition-transform" onclick="showInstallInstructions()">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center">
                    <span class="material-symbols-outlined">install_mobile</span>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-blue-700 dark:text-blue-400">Uygulama Olarak Kullanın</h4>
                    <p class="text-[11px] text-blue-600/80 dark:text-blue-400/80 font-medium">Bildirimler için ana ekrana ekleyin</p>
                </div>
            </div>
            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400">chevron_right</span>
        </div>
    </div>
    <!-- Header -->
    <?php
    $_gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    $_aylar = ['', 'OCAK', 'ŞUBAT', 'MART', 'NİSAN', 'MAYIS', 'HAZİRAN', 'TEMMUZ', 'AĞUSTOS', 'EYLÜL', 'EKİM', 'KASIM', 'ARALIK'];
    $_tarihEtiket = mb_strtoupper(date('j') . ' ' . $_aylar[(int) date('n')] . ' ' . $_gunler[(int) date('w')], 'UTF-8');
    $_adSoyad = trim((string) ($personel->adi_soyadi ?? 'Personel'));
    $_ilkAd = preg_split('/\s+/u', $_adSoyad)[0] ?? $_adSoyad;
    $_saat = (int) date('H');
    $_selam = $_saat < 12 ? 'Günaydın!' : ($_saat < 18 ? 'İyi günler!' : 'İyi akşamlar!');
    $pResim = $personel->resim_yolu ?? '';
    ?>
    <header class="pwa-home-hero">
        <div class="pwa-topbar pwa-topbar--home">
            <a href="?page=profil" class="pwa-avatar-circle pwa-avatar-circle--photo" aria-label="Profilim">
                <?php if (!empty($pResim)): ?>
                    <img src="<?php echo htmlspecialchars(Helper::base_url($pResim), ENT_QUOTES, 'UTF-8'); ?>" alt="Profil" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="material-symbols-outlined">person</span>
                <?php endif; ?>
            </a>
            <div class="pwa-topbar__actions">
<button type="button" onclick="Theme.toggleDarkMode()" class="pwa-circle-btn" aria-label="Tema değiştir">
                    <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                    <span class="material-symbols-outlined hidden dark:block">light_mode</span>
                </button>
                <button type="button" onclick="openNotificationModal()" class="pwa-circle-btn" aria-label="Bildirimler">
                    <span class="material-symbols-outlined">notifications</span>
                    <span id="notification-badge"
                        class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white rounded-full text-[10px] font-bold flex items-center justify-center hidden"
                        style="border:2px solid #fff"></span>
                </button>
            </div>
        </div>
        <span class="pwa-topbar__eyebrow"><?php echo htmlspecialchars($_tarihEtiket, ENT_QUOTES, 'UTF-8'); ?></span>
        <h1 class="pwa-home-hero__title">Merhaba, <?php echo htmlspecialchars($_ilkAd, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="pwa-topbar__subtitle"><?php echo htmlspecialchars($_selam, ENT_QUOTES, 'UTF-8'); ?> İşte bugünkü özet bilgileriniz.</p>
    </header>

    <!-- Etkinlik Slider -->
    <section class="px-4 relative z-20 mb-4" id="etkinlik-slider-section" data-home-card="etkinlik" style="<?php echo $etkinlikSlider ? '' : 'display: none; '; ?>order: <?php echo $kartSirasi('etkinlik'); ?>;">
        <div class="flex overflow-x-auto hide-scrollbar snap-x snap-mandatory gap-3 pb-2"
            id="etkinlik-slider-container">
            <?php foreach ($etkinlikSlider as $etkinlikIdx => $duyuru): ?>
                <div class="snap-center shrink-0 w-[85%] sm:w-[300px] rounded-2xl p-4 text-white shadow-lg relative overflow-hidden transition-transform active:scale-[0.98] cursor-pointer"
                    style="background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);"
                    onclick="showEtkinlikFullScreen(window.etkinlikSliderListe[<?php echo (int) $etkinlikIdx; ?>]);">
                    <?php if ($duyuru['kalan_gun'] !== null): ?>
                        <div class="absolute -top-6 -right-2 pointer-events-none select-none z-0 flex flex-col items-end opacity-80">
                            <span class="text-[9rem] font-black leading-[0.8] tracking-tighter bg-gradient-to-bl from-white/70 to-white/0 text-transparent bg-clip-text"><?php echo htmlspecialchars((string) $duyuru['kalan_gun'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="text-[10px] font-bold text-white/40 uppercase tracking-[0.2em] relative -top-6 pr-6">GÜN KALDI</span>
                        </div>
                    <?php endif; ?>
                    <div class="relative z-10 pr-2">
                        <span class="badge badge-primary bg-white/20 text-white border-none mb-2 text-[10px]"><?php echo htmlspecialchars((string) $duyuru['tarih'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <h3 class="font-bold text-lg leading-tight mb-1 text-white truncate max-w-[85%]"><?php echo htmlspecialchars((string) $duyuru['baslik'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="text-xs text-white/80 line-clamp-2 max-w-[85%]"><?php echo htmlspecialchars(strip_tags((string) $duyuru['icerik']), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <script>window.etkinlikSliderListe = <?php echo json_encode($etkinlikSlider, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;</script>
    </section>

    <!-- Görev Takip Bileşeni -->
    <?php if (($personel->saha_takibi ?? 0) == 1): ?>
        <section class="px-4 relative z-20 mb-4" data-home-card="gorev" style="order: <?php echo $kartSirasi('gorev'); ?>;">
            <div id="gorev-takip-card" class="card pwa-fx-card pwa-fx-card--green" data-gorev-state="idle">
                <div class="pwa-card-head"><div class="pwa-card-title"><span class="material-symbols-outlined">my_location</span><span>Saha Görevi</span></div></div>
                <!-- Loading State -->
                <div id="gorev-loading" class="p-4" style="min-height:300px" aria-hidden="true">
                    <span class="pwa-skel" style="height:170px;border-radius:24px"></span>
                    <span class="pwa-skel" style="height:18px;width:55%;margin-top:18px"></span>
                    <span class="pwa-skel" style="height:12px;width:75%;margin-top:10px"></span>
                    <span class="pwa-skel" style="height:52px;margin-top:18px"></span>
                </div>

                <!-- Görev Durumu Container -->
                <div id="gorev-durumu-container" class="hidden">
                    <div class="pwa-fx-scene-wrap">
                        <div class="pwa-fx-scene" aria-hidden="true">
                            <div class="pwa-fx-layer pwa-fx-layer--hills" style="--t:160px;--d:16s"></div>
                            <div class="pwa-fx-layer pwa-fx-layer--cones" style="--t:260px;--d:3.25s"></div>
                            <div class="pwa-fx-ground" style="--gd:0.6s"></div>
                            <span class="pwa-fx-shadow"></span>
                            <div class="pwa-fx-actor pwa-fx-worker">
                                <svg viewBox="0 0 120 140" fill="none">
                                    <g class="wk-body">
                                        <g transform="translate(58 58)"><g class="limb uarm uarm-b"><rect x="-5.5" y="-3" width="11" height="20" rx="5.5" fill="#cbd5e1"/><g transform="translate(0 14)"><g class="limb farm farm-b"><rect x="-5" y="0" width="10" height="17" rx="5" fill="#cbd5e1"/><circle cx="0" cy="18" r="5.8" fill="#e9ac80"/></g></g></g></g>
                                        <g transform="translate(57 86)"><g class="limb thigh thigh-b"><rect x="-7.5" y="-2" width="15" height="24" rx="7.5" fill="#1e40af"/><g transform="translate(0 19)"><g class="limb shin shin-b"><rect x="-6.8" y="0" width="13.6" height="23" rx="6.8" fill="#1e40af"/><path d="M-7.5 20h14c7 0 10 3 10 7v4h-24z" fill="#7c2d12"/></g></g></g></g>
                                        <rect x="46" y="50" width="30" height="40" rx="13" fill="#f1f5f9"/>
                                        <path d="M46 66c0-9 4-16 13-16h4c9 0 13 7 13 16v20c0 3-2 5-5 5H51c-3 0-5-2-5-5z" fill="#f97316"/>
                                        <path d="M55 50l6 11 6-11z" fill="#f1f5f9"/>
                                        <rect x="52" y="52" width="5" height="39" rx="2" fill="#94a3b8"/>
                                        <rect x="65" y="52" width="5" height="39" rx="2" fill="#94a3b8"/>
                                        <rect x="46" y="70" width="30" height="5" fill="#e2e8f0" opacity=".95"/>
                                        <rect x="46" y="84" width="30" height="7" rx="3.5" fill="#475569"/>
                                        <rect x="57" y="83" width="8" height="9" rx="2" fill="#fbbf24"/>
                                        <g transform="translate(60 86)"><g class="limb thigh thigh-f"><rect x="-7.5" y="-2" width="15" height="24" rx="7.5" fill="#2563eb"/><g transform="translate(0 19)"><g class="limb shin shin-f"><rect x="-6.8" y="0" width="13.6" height="23" rx="6.8" fill="#2563eb"/><path d="M-7.5 20h14c7 0 10 3 10 7v4h-24z" fill="#c2410c"/></g></g></g></g>
                                        <g class="wk-head">
                                            <rect x="56" y="44" width="11" height="12" rx="5" fill="#e9ac80"/>
                                            <circle cx="62" cy="30" r="18" fill="#f7c9a4"/>
                                            <circle cx="50" cy="34" r="4" fill="#f2b88f"/>
                                            <circle cx="79.6" cy="36" r="2" fill="#f2b88f"/>
                                            <g class="wk-blink"><ellipse cx="75" cy="31" rx="3" ry="4.2" fill="#fff"/><circle cx="76.2" cy="32" r="2.3" fill="#3b2418"/></g>
                                            <g class="wk-blink"><ellipse cx="67" cy="31" rx="4.2" ry="5" fill="#fff"/><circle cx="68.7" cy="32" r="3" fill="#3b2418"/><circle cx="69.7" cy="30.6" r="1" fill="#fff"/></g>
                                            <circle cx="66" cy="41" r="3.2" fill="#f9a8a0" opacity=".55"/>
                                            <path d="M68.2 42.4q3.6 4.2 7.2-.6z" fill="#fff" stroke="#9a3412" stroke-width="1.1" stroke-linejoin="round"/>
                                            <path d="M43 26c0-13 8-19 19-19s19 6 19 19z" fill="#fbbf24"/>
                                            <path d="M48 15c4-5 8-7 13-7v18H48z" fill="#fde68a" opacity=".55"/>
                                            <rect x="59" y="6" width="6" height="20" rx="3" fill="#f59e0b"/>
                                            <rect x="40" y="24" width="54" height="7" rx="3.5" fill="#f59e0b"/>
                                        </g>
                                        <g transform="translate(61 58)"><g class="limb uarm uarm-f"><rect x="-5.5" y="-3" width="11" height="20" rx="5.5" fill="#f8fafc"/><g transform="translate(0 14)"><g class="limb farm farm-f"><rect x="-5" y="0" width="10" height="17" rx="5" fill="#f8fafc"/><circle cx="0" cy="18" r="5.8" fill="#f7c9a4"/></g></g></g></g>
                                    </g>
                                </svg>
                            </div>
                            <span class="pwa-fx-bubble pwa-fx-bubble--start"><span class="material-symbols-outlined">touch_app</span>Göreve başla!</span>
                            <span class="pwa-fx-pin"><span class="material-symbols-outlined">location_on</span></span>
                        </div>
                    </div>

                    <!-- GÖREVE BAŞLA (Görev Yok) -->
                    <div id="gorev-basla-panel" class="pwa-fx-body hidden">
                        <div class="pwa-fx-scene-title">
                            <h3 class="pwa-fx-title">Saha Görev Takibi</h3>
                            <p class="pwa-fx-sub">Konumunuz kayıt altına alınacaktır</p>
                        </div>

                        <div id="konum-izni-uyari" onclick="requestKonumIzni()" class="hidden pwa-fx-notice">
                            <span class="material-symbols-outlined">location_off</span>
                            <div>
                                <strong>Konum İzni Gerekli</strong>
                                <span class="pwa-fx-notice-text">Göreve başlamak için buraya tıklayarak konum izni vermeniz gerekmektedir.</span>
                            </div>
                        </div>

                        <button id="btn-gorev-basla" onclick="gorevBasla()" class="pwa-fx-btn pwa-fx-btn--play">
                            <span class="material-symbols-outlined">play_arrow</span>
                            <span>Göreve Başla</span>
                        </button>
                    </div>

                    <!-- GÖREVİ BİTİR (Görev Var) -->
                    <div id="gorev-bitir-panel" class="hidden">
                        <div class="pwa-fx-live">
                            <div class="pwa-fx-live-dot">
                                <span class="material-symbols-outlined text-2xl">location_on</span>
                            </div>
                            <div class="flex-1">
                                <small>Aktif Görev Devam Ediyor</small>
                                <b id="gorev-baslangic-saat">--:--</b>
                            </div>
                            <div class="text-right">
                                <small>Geçen Süre</small>
                                <b id="gorev-gecen-sure">0 dk</b>
                            </div>
                        </div>

                        <div class="pwa-fx-body pwa-fx-card--red">
                            <button id="btn-gorev-bitir" onclick="gorevBitir()" class="pwa-fx-btn">
                                <span class="material-symbols-outlined">stop_circle</span>
                                <span>Görevi Bitir</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($aktifAracZimmeti): ?>
        <!-- ARAÇ KM TAKİBİ (Separate Premium Card) -->
        <?php
        $kmBildirimModel = new \App\Model\AracKmBildirimModel();
        $kmBugun = ['sabah' => [], 'aksam' => []];
        $kmAracSayisi = count($aktifAracZimmetleri ?? []);
        $kmSatirlari = $kmBildirimModel->getGunlukBildirimler(
            (int) $personel_id,
            array_map(static fn($kmZimmet) => (int) $kmZimmet->arac_id, $aktifAracZimmetleri ?? []),
            date('Y-m-d')
        );
        foreach ($kmSatirlari as $kmSatir) {
            if (isset($kmBugun[$kmSatir->tur]) && !isset($kmBugun[$kmSatir->tur][(int) $kmSatir->arac_id])) {
                $kmBugun[$kmSatir->tur][(int) $kmSatir->arac_id] = (int) $kmSatir->km;
            }
        }
        $kmSabahVar = $kmBugun['sabah'] !== [];
        $kmAksamVar = $kmBugun['aksam'] !== [];
        $kmSahneDurumu = $kmAksamVar ? 'done' : ($kmSabahVar ? 'driving' : 'parked');
        $kmButonlari = [];
        foreach (['sabah' => 'Sabah', 'aksam' => 'Akşam'] as $kmTur => $kmEtiket) {
            $kmBildirilen = count($kmBugun[$kmTur]);
            $kmTamam = $kmAracSayisi > 0 && $kmBildirilen >= $kmAracSayisi;
            if ($kmBildirilen === 0) {
                $kmAltMetin = 'KM bildir';
            } elseif ($kmTamam && $kmAracSayisi === 1) {
                $kmAltMetin = number_format((int) reset($kmBugun[$kmTur]), 0, ',', '.') . ' km';
            } elseif ($kmTamam) {
                $kmAltMetin = $kmBildirilen . ' araç bildirildi';
            } else {
                $kmAltMetin = $kmBildirilen . '/' . $kmAracSayisi . ' araç';
            }
            $kmButonlari[$kmTur] = ['etiket' => $kmEtiket, 'tamam' => $kmTamam, 'alt' => $kmAltMetin];
        }
        $kmSiradaki = !$kmButonlari['sabah']['tamam'] ? 'sabah' : (!$kmButonlari['aksam']['tamam'] ? 'aksam' : '');
        ?>
        <section class="px-4 mt-6" data-home-card="km" style="order: <?php echo $kartSirasi('km'); ?>;">
            <div id="km-card" class="card pwa-fx-card pwa-fx-card--indigo border-none shadow-xl shadow-indigo-500/10 bg-white dark:bg-slate-900" data-km-state="<?php echo htmlspecialchars($kmSahneDurumu, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="pwa-card-head"><div class="pwa-card-title"><span class="material-symbols-outlined">directions_car</span><span>Araç KM Yönetimi</span></div><span class="pwa-card-pill">AKTİF</span></div>
                <div class="pwa-fx-body">
                    <div class="pwa-fx-scene pwa-fx-scene--road" role="button" tabindex="0" aria-label="Günlük KM bildir" onclick="kmSiradakiBildir()" onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); kmSiradakiBildir(); }">
                        <div class="pwa-fx-layer pwa-fx-layer--hills" style="--t:160px;--d:7s"></div>
                        <div class="pwa-fx-layer pwa-fx-layer--trees" style="--t:200px;--d:1.5s"></div>
                        <div class="pwa-fx-ground" style="--gd:0.35s"></div>
                        <span class="pwa-fx-speed" style="left:12%;top:34px;width:46px;animation-delay:0s"></span>
                        <span class="pwa-fx-speed" style="left:6%;top:56px;width:64px;animation-delay:.3s"></span>
                        <span class="pwa-fx-speed" style="left:18%;top:78px;width:38px;animation-delay:.6s"></span>
                        <span class="pwa-fx-shadow" style="width:150px;margin-left:-75px;bottom:5px;animation-duration:.36s"></span>
                        <span class="pwa-fx-bubble pwa-fx-bubble--km-parked"><span class="material-symbols-outlined">speed</span>KM bildir</span>
                        <span class="pwa-fx-bubble pwa-fx-bubble--km-done"><span class="material-symbols-outlined">task_alt</span>KM tamam</span>
                        <div class="pwa-fx-actor pwa-fx-van">
                            <div class="pwa-fx-van-body">
                                <span class="pwa-fx-beam"></span>
                                <span class="pwa-fx-hazard pwa-fx-hazard--rear"></span>
                                <span class="pwa-fx-hazard pwa-fx-hazard--front"></span>
                                <img class="pwa-fx-van-img" src="assets/images/van.png?v=6" alt="" draggable="false">
                                <img class="pwa-fx-van-wheel pwa-fx-van-wheel--r" src="assets/images/van-wheel.png?v=3" alt="" draggable="false">
                                <img class="pwa-fx-van-wheel pwa-fx-van-wheel--f" src="assets/images/van-wheel.png?v=3" alt="" draggable="false">
                            </div>
                        </div>
                    </div>

                    <div class="pwa-fx-scene-title">
                        <p class="pwa-fx-title">Aktif Araçlarınız</p>
                        <div class="pwa-fx-chips">
                            <?php foreach (($aktifAracZimmetleri ?? []) as $zimmetIndex => $zimmet): ?>
                                <span class="pwa-fx-chip" style="animation-delay: <?php echo (int) ($zimmetIndex * 90 + 200); ?>ms;"><?php echo htmlspecialchars((string) $zimmet->plaka, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="pwa-fx-state pwa-fx-state--parked"><span class="material-symbols-outlined">schedule</span>Bugünkü KM bildiriminiz bekleniyor</p>
                        <p class="pwa-fx-state pwa-fx-state--driving"><span class="material-symbols-outlined">route</span>Sabah KM bildirildi · Akşam KM'yi gün sonunda girin</p>
                        <p class="pwa-fx-state pwa-fx-state--done"><span class="material-symbols-outlined">task_alt</span>Bugünkü KM bildirimleriniz tamam</p>
                    </div>

                    <div class="pwa-km-actions" id="km-actions">
                        <?php foreach ($kmButonlari as $kmTur => $kmBtn): ?>
                            <button type="button" id="km-btn-<?php echo $kmTur; ?>" data-tur="<?php echo $kmTur; ?>"
                                class="pwa-km-act pwa-km-act--<?php echo $kmTur; ?><?php echo $kmBtn['tamam'] ? ' is-done' : ($kmSiradaki === $kmTur ? ' is-next' : ''); ?>"
                                onclick="openKmBildirModal(null, false, false, false, '<?php echo $kmTur; ?>')"
                                <?php echo $kmBtn['tamam'] ? 'disabled' : ''; ?>>
                                <span class="pwa-km-act-ico"><span class="material-symbols-outlined"><?php echo $kmTur === 'sabah' ? 'wb_sunny' : 'nights_stay'; ?></span></span>
                                <span class="pwa-km-act-txt">
                                    <b><?php echo htmlspecialchars($kmBtn['etiket'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <small data-km-alt><?php echo htmlspecialchars($kmBtn['alt'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </span>
                                <span class="material-symbols-outlined pwa-km-act-ok">check_circle</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="pwa-fx-foot"><i></i><span>Resimli bildirim zorunludur</span><i></i></div>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>


    <?php if (!$isBuro): ?>
    <!-- Performance Summary -->
    <section class="px-4 mt-6" data-home-card="performans" style="order: <?php echo $kartSirasi('performans'); ?>;">
        <div class="card pwa-card-body">
            <div class="pwa-card-head pwa-card-head--flush"><div class="pwa-card-title"><span class="material-symbols-outlined">insights</span><span>Performans Özeti</span></div></div>
            <div id="work-stats-container" class="grid grid-cols-2 gap-3">
                <!-- Loading -->
                <div class="col-span-2"><div class="pwa-skel-grid" aria-hidden="true"><span class="pwa-skel" style="height:84px"></span><span class="pwa-skel" style="height:84px"></span><span class="pwa-skel" style="height:84px"></span><span class="pwa-skel" style="height:84px"></span></div></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($isEndeksOkuma ?? false): ?>
    <section class="px-4 mt-6" id="endeks-karsilastirma-section" data-home-card="endeks" style="order: <?php echo $kartSirasi('endeks'); ?>;">
        <div class="card pwa-card-body">
            <div class="pwa-card-head pwa-card-head--flush">
                <div class="pwa-card-title"><span class="material-symbols-outlined">compare_arrows</span><span>Endeks Karşılaştırması</span><span class="pwa-card-pill" id="endeks-kapsam-label">-</span></div>
            </div>
            <div id="endeks-karsilastirma-container">
                <div aria-hidden="true"><span class="pwa-skel" style="height:120px"></span></div>
            </div>
        </div>
    </section>
    <?php endif; ?>


    <!-- Özet Bilgiler (Combined) -->
    <section class="px-4 relative z-20 mt-5" data-home-card="ozet" style="order: <?php echo $kartSirasi('ozet'); ?>;">
        <div class="card pwa-card-body">
            <div class="pwa-card-head pwa-card-head--flush"><div class="pwa-card-title"><span class="material-symbols-outlined">calendar_month</span><span>Genel Özet</span><span class="pwa-card-pill" id="combined-donem-label">Mart 2026</span></div><a href="?page=izin" class="pwa-card-link">Detaylar <span class="material-symbols-outlined">chevron_right</span></a></div>

            <div class="pwa-seg-track" role="img" aria-label="Çalışılan, ücretsiz ve ücretli izin günleri">
                <span class="pwa-seg-pill" id="combined-seg-worked" style="background:#3b82f6;width:100%"></span>
                <span class="pwa-seg-pill" id="combined-seg-unpaid" style="background:#f59e0b;width:0"></span>
                <span class="pwa-seg-pill" id="combined-seg-paid" style="background:#10b981;width:0"></span>
            </div>

            <div class="pwa-metrics">
                <div class="pwa-metric"><span><i style="background:#3b82f6"></i>Çalışılan</span><strong id="combined-actual-worked">0</strong></div>
                <div class="pwa-metric"><span><i style="background:#f59e0b"></i>Ücretsiz</span><strong id="combined-unpaid-leave">0</strong></div>
                <div class="pwa-metric"><span><i style="background:#10b981"></i>Ücretli</span><strong id="combined-paid-leave">0</strong></div>
            </div>

            <p class="pwa-card-foot" id="combined-footer-label">Yükleniyor...</p>
        </div>
    </section>


    <?php
    $PersonelModelForActions = new \App\Model\PersonelModel();
    $userSavedActionKeys = $PersonelModelForActions->getHizliIslemler($personel_id);

    $allQuickActionCatalog = [
        'izin' => [
            'key' => 'izin',
            'title' => 'İzin Talebi',
            'desc' => 'Yeni izin planla',
            'url' => '?page=izin',
            'icon' => 'event_busy',
            'gradient' => 'from-indigo-500 to-indigo-700',
            'neon' => 'neon-indigo',
            'text_color' => 'text-indigo-100/80',
        ],
        'yardim' => [
            'key' => 'yardim',
            'title' => 'Destek Talebi',
            'desc' => 'Yardım ve Destek',
            'url' => '?page=yardim',
            'icon' => 'support_agent',
            'gradient' => 'from-emerald-500 to-emerald-700',
            'neon' => 'neon-emerald',
            'text_color' => 'text-emerald-100/80',
        ],
        'bordro' => [
            'key' => 'bordro',
            'title' => 'Avanslar',
            'desc' => 'Avans Talebi Yap',
            'url' => '?page=bordro',
            'icon' => 'receipt_long',
            'gradient' => 'from-orange-500 to-orange-700',
            'neon' => 'neon-orange',
            'text_color' => 'text-orange-100/80',
        ],
        'zimmetler' => [
            'key' => 'zimmetler',
            'title' => 'Zimmetler',
            'desc' => 'Demirbaş Takibi',
            'url' => '?page=zimmetler',
            'icon' => 'inventory_2',
            'gradient' => 'from-amber-500 to-amber-700',
            'neon' => 'neon-amber',
            'text_color' => 'text-amber-100/80',
        ],
        'ihbar' => [
            'key' => 'ihbar',
            'title' => 'İhbar Yap',
            'desc' => 'Kaçak Su İhbarı',
            'url' => '?page=ihbar',
            'icon' => 'campaign',
            'gradient' => 'from-red-500 to-red-700',
            'neon' => 'neon-red',
            'text_color' => 'text-red-100/80',
        ],
        'puantaj' => [
            'key' => 'puantaj',
            'title' => 'Puantajım',
            'desc' => 'Aylık Çalışma Özeti',
            'url' => '?page=puantaj',
            'icon' => 'calendar_month',
            'gradient' => 'from-blue-500 to-blue-700',
            'neon' => 'neon-blue',
            'text_color' => 'text-blue-100/80',
        ],
        'talep' => [
            'key' => 'talep',
            'title' => 'Taleplerim',
            'desc' => 'Talep ve Görevler',
            'url' => '?page=talep',
            'icon' => 'assignment',
            'gradient' => 'from-violet-500 to-violet-700',
            'neon' => 'neon-violet',
            'text_color' => 'text-violet-100/80',
        ],
        'km-bildirimleri' => [
            'key' => 'km-bildirimleri',
            'title' => 'KM Bildirimi',
            'desc' => 'Araç KM Girişi',
            'url' => '?page=km-bildirimleri',
            'icon' => 'speed',
            'gradient' => 'from-rose-500 to-rose-700',
            'neon' => 'neon-rose',
            'text_color' => 'text-rose-100/80',
        ],
        'etkinlikler' => [
            'key' => 'etkinlikler',
            'title' => 'Etkinlikler',
            'desc' => 'Duyuru & Etkinlik',
            'url' => '?page=etkinlikler',
            'icon' => 'event',
            'gradient' => 'from-fuchsia-500 to-fuchsia-700',
            'neon' => 'neon-fuchsia',
            'text_color' => 'text-fuchsia-100/80',
        ],
        'icralar' => [
            'key' => 'icralar',
            'title' => 'İcralarım',
            'desc' => 'İcra Dosya Takibi',
            'url' => '?page=icralar',
            'icon' => 'gavel',
            'gradient' => 'from-purple-500 to-purple-700',
            'neon' => 'neon-purple',
            'text_color' => 'text-purple-100/80',
            'condition' => $hasIcra ?? false,
        ],
        'ekip-takibi' => [
            'key' => 'ekip-takibi',
            'title' => 'Ekip Takibi',
            'desc' => 'Saha Ekip Durumu',
            'url' => '?page=ekip-takibi',
            'icon' => 'groups',
            'gradient' => 'from-cyan-500 to-cyan-700',
            'neon' => 'neon-cyan',
            'text_color' => 'text-cyan-100/80',
            'condition' => ($isEndeksOkuma ?? false) && ($isEkipSefi ?? false),
        ],
        'nobet' => [
            'key' => 'nobet',
            'title' => 'Nöbet Çizelgesi',
            'desc' => 'Nöbet Listesi',
            'url' => '?page=nobet',
            'icon' => 'nights_stay',
            'gradient' => 'from-teal-500 to-teal-700',
            'neon' => 'neon-teal',
            'text_color' => 'text-teal-100/80',
            'condition' => $isKesmeAcma ?? false,
        ],
        'aparat' => [
            'key' => 'aparat',
            'title' => 'Aparat Takip',
            'desc' => 'Kesme-Açma Kaydı',
            'url' => '?page=aparat',
            'icon' => 'build',
            'gradient' => 'from-indigo-500 to-indigo-700',
            'neon' => 'neon-indigo',
            'text_color' => 'text-indigo-100/80',
            'condition' => $isKesmeAcma ?? false,
        ],
        'kacak' => [
            'key' => 'kacak',
            'title' => 'Kaçak İşlemleri',
            'desc' => 'Tutanak Bildirimi',
            'url' => '?page=kacak',
            'icon' => 'policy',
            'gradient' => 'from-red-600 to-rose-700',
            'neon' => 'neon-red',
            'text_color' => 'text-red-100/80',
            'condition' => $isKacakKontrol ?? false,
        ],
    ];

    $availableQuickActions = [];
    foreach ($allQuickActionCatalog as $key => $action) {
        if (!isset($action['condition']) || $action['condition'] === true) {
            $availableQuickActions[$key] = $action;
        }
    }

    $activeQuickActions = [];
    foreach ($userSavedActionKeys as $key) {
        if (isset($availableQuickActions[$key])) {
            $activeQuickActions[] = $availableQuickActions[$key];
        }
    }
    ?>

    <section class="px-4 mt-6 overflow-hidden" data-home-card="hizli" style="order: <?php echo $kartSirasi('hizli'); ?>;">
        <div class="card pwa-card-body overflow-hidden">
        <div class="pwa-card-head pwa-card-head--flush"><div class="pwa-card-title"><span class="material-symbols-outlined">bolt</span><span>Hızlı İşlemler</span><?php if (!empty($activeQuickActions)): ?><span class="pwa-card-pill"><?php echo count($activeQuickActions); ?> Kısayol</span><?php endif; ?></div><button type="button" onclick="openHizliIslemlerModal()" class="pwa-card-pill pwa-card-pill--btn"><span class="material-symbols-outlined">tune</span><span>Düzenle</span></button></div>
        <?php
        $qaRenkler = [
            'amber' => '217,119,6', 'blue' => '2,132,199', 'cyan' => '8,145,178', 'emerald' => '16,185,129',
            'fuchsia' => '192,38,211', 'indigo' => '99,102,241', 'orange' => '249,115,22', 'purple' => '124,58,237',
            'red' => '239,68,68', 'rose' => '225,29,72', 'teal' => '13,148,136', 'violet' => '139,92,246',
        ];
        ?>
        <?php if (empty($activeQuickActions)): ?>
            <div class="w-full p-4 text-center text-xs text-slate-400 bg-slate-100 dark:bg-slate-800 rounded-2xl">
                Henüz eklenmiş bir hızlı işlem yok. "Düzenle" butonundan ekleyebilirsiniz.
            </div>
        <?php else: ?>
            <div class="pwa-qa-grid">
                <?php foreach ($activeQuickActions as $action): ?>
                    <?php
                    preg_match('/from-([a-z]+)-/', (string) ($action['gradient'] ?? ''), $renkEsles);
                    $qaRenk = $qaRenkler[$renkEsles[1] ?? ''] ?? '2,132,199';
                    ?>
                    <a href="<?php echo htmlspecialchars($action['url'], ENT_QUOTES, 'UTF-8'); ?>"
                        class="pwa-qa-tile" style="--qa: <?php echo htmlspecialchars($qaRenk, ENT_QUOTES, 'UTF-8'); ?>;">
                        <span class="pwa-qa-icon">
                            <span class="material-symbols-outlined"><?php echo htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </span>
                        <strong><?php echo htmlspecialchars($action['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </div>
    </section>


    <!-- Hızlı İşlemler Özelleştirme Modalı -->
    <div id="hizli-islemler-modal" class="modal-overlay">
        <div class="modal-content p-5 pt-3 max-h-[85vh] flex flex-col">
            <div class="modal-handle"></div>

            <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">tune</span>
                        Hızlı İşlemleri Özelleştir
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Sırasını değiştirin, yeni buton ekleyin veya çıkarın.</p>
                </div>
                <button type="button" onclick="Modal.close('hizli-islemler-modal')" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-5 pr-1 hide-scrollbar" id="hizli-islemler-modal-body">
                <!-- Aktif (Ekli) Butonlar -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aktif İşlemler (Sıralanabilir)</span>
                        <span class="text-[11px] font-semibold text-primary" id="active-count-badge">0 aktif</span>
                    </div>
                    <div id="active-actions-list" class="space-y-2">
                        <!-- Dynamic active items -->
                    </div>
                </div>

                <!-- Kullanılabilir (Eklenebilir) Butonlar -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kullanılabilir İşlemler (Eklenebilir)</span>
                    </div>
                    <div id="available-actions-list" class="space-y-2">
                        <!-- Dynamic available items -->
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <button type="button" onclick="resetHizliIslemlerToDefault()" class="px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold flex items-center gap-1 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                    <span class="material-symbols-outlined text-sm">restart_alt</span>
                    Sıfırla
                </button>
                <button type="button" onclick="saveHizliIslemlerConfig()" id="btn-save-hizli-islemler" class="flex-1 bg-primary text-white py-2.5 rounded-xl font-bold text-xs shadow-lg shadow-primary/30 flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
                    <span class="material-symbols-outlined text-base">save</span>
                    Değişiklikleri Kaydet
                </button>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notification-modal" class="modal-overlay">
        <div class="modal-content p-6 pt-3">
            <div class="modal-handle"></div>

            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bildirimler</h3>
                <div class="flex items-center gap-2">
                    <button onclick="markAllAsRead()" class="text-xs text-primary font-medium"
                        title="Tümünü Okundu İşaretle">
                        <span class="material-symbols-outlined text-lg">done_all</span>
                    </button>
                    <button onclick="deleteAllNotifications()" class="text-xs text-red-500 font-medium"
                        title="Tümünü Sil">
                        <span class="material-symbols-outlined text-lg">delete_sweep</span>
                    </button>
                </div>
            </div>

            <div id="notification-list" class="flex flex-col gap-3 max-h-[60vh] overflow-y-auto">
                <!-- Bildirimler buraya yüklenecek -->
                <div class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            </div>

            <button onclick="Modal.close('notification-modal')"
                class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>

    <!-- Notification Modal follows directly -->

    <!-- Notification Detail Modal removed and replaced by Generic Full Screen Modal in index.php -->

    <script>
        // Global data
        var userIsSef = <?php echo json_encode($isSef ?? false); ?>;
        var userHasSahaTakibi = <?php echo json_encode(($personel->saha_takibi ?? 0) == 1); ?>;
        var userIsBuro = <?php echo json_encode($isBuro ?? false); ?>;
        var lastUpdateDate = "<?php echo Helper::getLastUpdateDate(['yapilan_isler', 'endeks_okuma']); ?>";
        var allActivitiesData = [];
        var allNotificationsData = [];
        var currentNotificationIndex = -1;

        var gorevSureInterval = null;
        var gorevBaslangicZamani = null;

        document.addEventListener('DOMContentLoaded', function () {
            // iOS Kurulum Rehberi Kontrolü
            checkIOSInstallGuide();

            // Load görev durumu (saha takibi olanlar için)
            if (userHasSahaTakibi) {
                loadGorevDurumu();
            }
            // Load dashboard data
            loadDashboardData();
            // Load notification count
            loadNotificationCount();
            setInterval(loadNotificationCount, 30000);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') loadNotificationCount();
            });
            // Load work stats (büro personeli olmayanlar için)
            if (!userIsBuro) {
                loadWorkStats();
            }
            // Load çalışma bilgileri
            loadCalismaStats();
            if (document.getElementById('endeks-karsilastirma-section')) {
                loadEndeksKarsilastirma();
            }

            // --- ANLIK KONUM İSTEĞİ KONTROLÜ (Saha personeli için) ---
            if (userHasSahaTakibi) {
                checkKonumIstegi();
                setInterval(checkKonumIstegi, 10000);
                setInterval(canliKonumGuncelle, 120000);
                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') canliKonumGuncelle();
                });
            }
        });

        let konumIstegiKontrolEdiliyor = false;
        async function checkKonumIstegi() {
            if (!userHasSahaTakibi || konumIstegiKontrolEdiliyor || document.visibilityState !== 'visible') return;
            konumIstegiKontrolEdiliyor = true;
            try {
                // Bu işlem her 10 saniyede bir arka planda çalışır; tam ekran
                // preloader göstermek uygulama yenileniyormuş hissi oluşturur.
                const response = await API.request('checkKonumIstegi', {}, false);
                if (response.success && response.data && response.data.istek_id) {
                    const istekId = response.data.istek_id;
                    console.log('Anlık konum isteği alındı (ID: ' + istekId + '). Konum alınıyor...');

                    // getKonum() fonksiyonu aşağıda tanımlı olmalı
                    const konum = await getKonum();
                    if (konum) {
                        await API.request('yanitlaKonumIstegi', {
                            istek_id: istekId,
                            lat: konum.enlem,
                            lng: konum.boylam
                        }, false);
                        console.log('Anlık konum başarıyla iletildi.');
                    }
                }
            } catch (error) {
                console.error('Konum isteği kontrol hatası:', error);
            } finally {
                konumIstegiKontrolEdiliyor = false;
            }
        }

        let canliKonumGuncelleniyor = false;
        async function canliKonumGuncelle() {
            if (!userHasSahaTakibi || !gorevBaslangicZamani || canliKonumGuncelleniyor || document.visibilityState !== 'visible') return;
            canliKonumGuncelleniyor = true;
            try {
                const konum = await getKonum();
                await API.request('canliKonumGuncelle', {
                    lat: konum.enlem,
                    lng: konum.boylam,
                    hassasiyet: konum.hassasiyet
                }, false);
            } catch (error) {
                console.warn('Canlı konum güncellenemedi:', error);
            } finally {
                canliKonumGuncelleniyor = false;
            }
        }

        // iOS Kurulum Rehberi Fonksiyonları
        function checkIOSInstallGuide() {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone = window.navigator.standalone || window.matchMedia('(display-mode: standalone)').matches;

            const guideEl = document.getElementById('ios-install-guide');
            if (guideEl && isIOS && !isStandalone) {
                guideEl.classList.remove('hidden');
            }
        }

        function showInstallInstructions() {
            Swal.fire({
                title: 'Uygulamayı Yükleyin',
                html: `
                    <div class="text-left text-sm leading-relaxed p-2">
                        <p class="mb-3">Bildirimlerin çalışması ve tam uygulama deneyimi için Ersan Elektrik'i ana ekranınıza ekleyin:</p>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-bold text-xs">1</div>
                                <p>Safari alt çubuğundaki <b>Paylaş</b> simgesine <img src="https://simpleicons.org/icons/safari.svg" style="display:inline; width:14px;"/> tıklayın.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-bold text-xs">2</div>
                                <p>Menüyü yukarı kaydırıp <b>Ana Ekrana Ekle</b> seçeneğine dokunun.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="w-6 h-6 rounded-full bg-slate-100 flex items-center justify-center shrink-0 font-bold text-xs">3</div>
                                <p>Sağ üstten <b>Ekle</b>'ye basın ve uygulamayı ana ekranınızdan açın.</p>
                            </div>
                        </div>
                    </div>
                `,
                icon: 'info',
                confirmButtonText: 'Anladım',
                customClass: {
                    popup: 'swal-custom-popup',
                    title: 'swal-custom-title',
                    htmlContainer: 'swal-custom-content',
                    actions: 'swal-custom-actions',
                    confirmButton: 'swal-custom-confirm swal-confirm-primary',
                }
            });
        }

        // ===== GÖREV TAKİP FONKSİYONLARI =====

        async function loadGorevDurumu() {
            var loadingEl = document.getElementById('gorev-loading');
            var containerEl = document.getElementById('gorev-durumu-container');
            if (!loadingEl && !containerEl) return;

            try {
                var response = await API.request('getGorevDurumu');

                if (loadingEl) loadingEl.classList.add('hidden');
                if (containerEl) containerEl.classList.remove('hidden');

                if (response.success && response.data) {
                    if (response.data.gorev_var) {
                        // Aktif görev var - Bitir panelini göster
                        showGorevBitirPanel(response.data);
                    } else {
                        // Görev yok - Başla panelini göster
                        showGorevBaslaPanel();
                    }
                } else {
                    showGorevBaslaPanel();
                }
            } catch (error) {
                console.error('Görev durumu yüklenemedi:', error);
                if (loadingEl) loadingEl.classList.add('hidden');
                if (containerEl) containerEl.classList.remove('hidden');
                showGorevBaslaPanel();
            }
        }

        function setGorevSahne(durum) {
            var kart = document.getElementById('gorev-takip-card');
            if (kart) kart.dataset.gorevState = durum;
        }

        var kmBugunBildirilen = <?php echo json_encode(['sabah' => (object) ($kmBugun['sabah'] ?? []), 'aksam' => (object) ($kmBugun['aksam'] ?? [])]); ?>;
        var kmAracSayisi = <?php echo (int) ($kmAracSayisi ?? 0); ?>;

        function kmButonlariniGuncelle() {
            var siradaki = '';
            ['sabah', 'aksam'].forEach(function (tur) {
                var idler = Object.keys(kmBugunBildirilen[tur] || {});
                var tamam = kmAracSayisi > 0 && idler.length >= kmAracSayisi;
                if (!tamam && !siradaki) siradaki = tur;
                var btn = document.getElementById('km-btn-' + tur);
                if (!btn) return;
                var alt = 'KM bildir';
                if (tamam && kmAracSayisi === 1) {
                    alt = Number(kmBugunBildirilen[tur][idler[0]]).toLocaleString('tr-TR') + ' km';
                } else if (tamam) {
                    alt = idler.length + ' araç bildirildi';
                } else if (idler.length > 0) {
                    alt = idler.length + '/' + kmAracSayisi + ' araç';
                }
                var altEl = btn.querySelector('[data-km-alt]');
                if (altEl) altEl.textContent = alt;
                btn.disabled = tamam;
                btn.classList.toggle('is-done', tamam);
            });
            ['sabah', 'aksam'].forEach(function (tur) {
                var btn = document.getElementById('km-btn-' + tur);
                if (btn) btn.classList.toggle('is-next', !btn.disabled && tur === siradaki);
            });
            return siradaki;
        }

        function kmBildirimiKaydedildi(tur, aracId, km) {
            if (!kmBugunBildirilen[tur]) return;
            kmBugunBildirilen[tur][aracId] = km;
            kmButonlariniGuncelle();
            var aksamVar = Object.keys(kmBugunBildirilen.aksam).length > 0;
            var sabahVar = Object.keys(kmBugunBildirilen.sabah).length > 0;
            setKmSahneDurumu(aksamVar ? 'done' : (sabahVar ? 'driving' : 'parked'));
        }

        function kmSiradakiBildir() {
            var siradaki = kmButonlariniGuncelle();
            if (!siradaki) {
                Alert.success('Tamamlandı', 'Bugünkü sabah ve akşam KM bildirimleriniz tamam.');
                return;
            }
            openKmBildirModal(null, false, false, false, siradaki);
        }

        function setKmSahneDurumu(durum) {
            var kart = document.getElementById('km-card');
            if (kart) kart.dataset.kmState = durum;
        }

        function showGorevBaslaPanel() {
            setGorevSahne('idle');
            var baslaPanel = document.getElementById('gorev-basla-panel');
            var bitirPanel = document.getElementById('gorev-bitir-panel');
            if (baslaPanel) baslaPanel.classList.remove('hidden');
            if (bitirPanel) bitirPanel.classList.add('hidden');

            // Konum izni kontrolü
            checkKonumIzni();
        }

        function showGorevBitirPanel(data) {
            setGorevSahne('walking');
            var baslaPanel = document.getElementById('gorev-basla-panel');
            var bitirPanel = document.getElementById('gorev-bitir-panel');
            if (baslaPanel) baslaPanel.classList.add('hidden');
            if (bitirPanel) bitirPanel.classList.remove('hidden');

            // Başlangıç saatini göster
            var saatEl = document.getElementById('gorev-baslangic-saat');
            if (saatEl) saatEl.textContent = data.baslangic_saat || '--:--';

            // Süre takibini başlat
            // Safari ve bazı mobil tarayıcılar için ISO formatına (boşluk yerine T) dönüştür
            var zamanStr = data.baslangic_zamani;
            if (zamanStr && typeof zamanStr === 'string') {
                zamanStr = zamanStr.replace(' ', 'T');
            }
            gorevBaslangicZamani = new Date(zamanStr);
            canliKonumGuncelle();
            updateGecenSure();
            if (gorevSureInterval) clearInterval(gorevSureInterval);
            gorevSureInterval = setInterval(updateGecenSure, 60000); // Her dakika güncelle
        }

        function updateGecenSure() {
            if (!gorevBaslangicZamani) return;
            var sureEl = document.getElementById('gorev-gecen-sure');
            if (!sureEl) return;

            var simdi = new Date();
            // Safari uyumluluğu için NaN kontrolü ve güvenli tarih farkı hesaplama
            var diff = simdi.getTime() - gorevBaslangicZamani.getTime();

            if (isNaN(diff) || diff < 0) {
                sureEl.textContent = '...';
                return;
            }

            var dakika = Math.floor(diff / 60000);
            var saat = Math.floor(dakika / 60);
            dakika = dakika % 60;

            var sureText = '';
            if (saat > 0) {
                sureText = saat + ' sa ' + dakika + ' dk';
            } else {
                sureText = dakika + ' dk';
            }

            sureEl.textContent = sureText;
        }

        async function checkKonumIzni() {
            if (!navigator.geolocation) {
                showKonumUyari();
                disableGorevButton();
                return;
            }

            try {
                var permission = await navigator.permissions.query({ name: 'geolocation' });

                if (permission.state === 'denied') {
                    showKonumUyari();
                    disableGorevButton();
                } else {
                    hideKonumUyari();
                    enableGorevButton();
                }

                permission.onchange = function () {
                    if (this.state === 'denied') {
                        showKonumUyari();
                        disableGorevButton();
                    } else {
                        hideKonumUyari();
                        enableGorevButton();
                    }
                };
            } catch (error) {
                // Permissions API desteklenmiyorsa devam et
                hideKonumUyari();
                enableGorevButton();
            }
        }

        function showKonumUyari() {
            var el = document.getElementById('konum-izni-uyari');
            if (el) el.classList.remove('hidden');
        }

        function hideKonumUyari() {
            var el = document.getElementById('konum-izni-uyari');
            if (el) el.classList.add('hidden');
        }

        async function requestKonumIzni() {
            try {
                // getKonum() navigator.geolocation.getCurrentPosition() çağrısı yaptığı için 
                // tarayıcının izin penceresini tetikler.
                await getKonum();
                // İzin verildikten sonra kontrolü tekrar çalıştır
                checkKonumIzni();
            } catch (error) {
                console.error('Konum izni isteği hatası:', error);
                // Hata mesajını kullanıcıya göster (Reddedildi vs)
                Toast.show(error.message, 'error');
            }
        }

        function disableGorevButton() {
            var btn = document.getElementById('btn-gorev-basla');
            if (!btn) return;
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
        }

        function enableGorevButton() {
            var btn = document.getElementById('btn-gorev-basla');
            if (!btn) return;
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        }

        function getKonum() {
            return new Promise(function (resolve, reject) {
                if (!navigator.geolocation) {
                    reject(new Error('Konum servisi bu tarayıcıda desteklenmiyor.'));
                    return;
                }

                // Localhost testi için yardımcı mesaj
                if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                    console.log('Localhost üzerindesiniz, konum alma biraz zaman alabilir...');
                }

                navigator.geolocation.getCurrentPosition(
                    function (position) {
                        resolve({
                            enlem: position.coords.latitude,
                            boylam: position.coords.longitude,
                            hassasiyet: position.coords.accuracy
                        });
                    },
                    function (error) {
                        var message = '';
                        switch (error.code) {
                            case error.PERMISSION_DENIED:
                                message = 'Konum izni reddedildi. Lütfen tarayıcı ayarlarından konuma izin verin.';
                                break;
                            case error.POSITION_UNAVAILABLE:
                                message = 'Konum bilgisi şu an ulaşılamaz durumda. GPS sinyalini kontrol edin.';
                                break;
                            case error.TIMEOUT:
                                message = 'Konum isteği zaman aşımına uğradı. Tekrar deneyiniz.';
                                break;
                            default:
                                message = 'Konum alınırken bilinmeyen bir hata oluştu.';
                        }

                        // Localhost için özel durum: Gerçekten konum alınamıyorsa sabit bir konum önerelim mi?
                        // Şimdilik sadece hata mesajını detaylandırıyoruz.
                        reject(new Error(message));
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 20000, // 20 saniye
                        maximumAge: 0
                    }
                );
            });
        }

        async function gorevBasla() {
            // if (!userIsSef) {
            //     Toast.show('Bu işlemi gerçekleştirmek için "Şef" yetkisine sahip olmalısınız.', 'error');
            //     return;
            // }

            var btn = document.getElementById('btn-gorev-basla');
            if (!btn) return;
            var originalHtml = btn.innerHTML;

            // Butonu disable yap
            btn.disabled = true;
            btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Konum Alınıyor...</span>';

            try {
                // Konum al
                var konum = await getKonum();

                btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Kaydediliyor...</span>';

                // API'ye gönder
                var response = await API.request('baslaGorev', {
                    konum_enlem: konum.enlem,
                    konum_boylam: konum.boylam,
                    konum_hassasiyeti: konum.hassasiyet,
                    cihaz_bilgisi: navigator.userAgent
                });

                if (response.success) {
                    Toast.show(response.message || 'Göreve başarıyla başladınız!', 'success');

                    // Paneli güncelle
                    showGorevBitirPanel({
                        baslangic_saat: response.data.baslangic_saat,
                        baslangic_zamani: new Date().toISOString()
                    });
                } else {
                    if (response.data && response.data.requires_morning_km) {
                        Toast.show(response.message, 'warning');
                        openKmBildirModal(null, true, false, response.data.missing_yesterday_evening_km === true);
                    } else {
                        Toast.show(response.message || 'Görev başlatılamadı', 'error');
                    }
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Görev Başla Hatası:', error);
                Toast.show(error.message || 'Bir hata oluştu', 'error');

                // Konum izni reddedildiyse uyarı göster
                if (error.message && error.message.includes('izni')) {
                    showKonumUyari();
                }
            } finally {
                // Buton her durumda eski haline dönsün
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        async function gorevBitir(kmHatirlatmayiAtla = false) {
            if (!kmHatirlatmayiAtla) {
                try {
                    const kmStatus = await API.request('get-evening-km-status', {}, false);
                    if (kmStatus.success && kmStatus.data.has_active_vehicle && !kmStatus.data.reported) {
                        const kmBildir = await Alert.confirm(
                            'Akşam KM Bildirimi',
                            `${kmStatus.data.plaka} plakalı aracın akşam KM bildirimi henüz yapılmadı. Şimdi bildirmek ister misiniz?`,
                            'Akşam KM Bildir',
                            'Şimdi Değil'
                        );

                        if (kmBildir) {
                            openKmBildirModal(null, false, true);
                            return;
                        }

                        API.request('log-evening-km-skip', {}, false).catch(function (error) {
                            console.error('Akşam KM erteleme tercihi kaydedilemedi:', error);
                        });
                    }
                } catch (error) {
                    console.error('Akşam KM durumu alınamadı:', error);
                }
            }

            var confirmed = await Alert.confirm(
                'Görevi Bitir',
                'Görevinizi bitirmek istediğinize emin misiniz?',
                'Evet, Bitir',
                'Vazgeç'
            );

            if (!confirmed) return;

            var btn = document.getElementById('btn-gorev-bitir');
            if (!btn) return;
            var originalHtml = btn.innerHTML;

            try {
                // Butonu disable yap ve spinner göster
                btn.disabled = true;
                btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Konum Alınıyor...</span>';

                // Konum al
                var konum = await getKonum();

                btn.innerHTML = '<div class="animate-spin rounded-full h-6 w-6 border-b-2 border-white"></div><span>Kaydediliyor...</span>';

                // API'ye gönder
                var response = await API.request('bitirGorev', {
                    konum_enlem: konum.enlem,
                    konum_boylam: konum.boylam,
                    konum_hassasiyeti: konum.hassasiyet,
                    cihaz_bilgisi: navigator.userAgent
                });

                if (response.success) {
                    // Süre takibini durdur
                    if (gorevSureInterval) {
                        clearInterval(gorevSureInterval);
                        gorevSureInterval = null;
                    }
                    gorevBaslangicZamani = null;

                    Toast.show(response.message || 'Görev başarıyla tamamlandı!', 'success');

                    // Butonu temizle (panel gizlenecek olsa da UI tutarlılığı için)
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;

                    // Paneli güncelle ve verileri yenile
                    showGorevBaslaPanel();
                    loadDashboardData();
                } else {
                    Toast.show(response.message || 'Görev bitirilemedi', 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                }
            } catch (error) {
                console.error('Görev Bitir Hatası:', error);
                Toast.show(error.message || 'Bir hata oluştu', 'error');
            } finally {
                // Buton her durumda eski haline dönsün
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }

        async function loadWorkStats() {
            var container = document.getElementById('work-stats-container');
            if (!container) return;

            // Show loading if container is empty or has items (to show refresh)
            if (container.children.length > 1 || container.querySelector('.pwa-skel') === null) {
                container.innerHTML = '<div class="col-span-2"><div class="pwa-skel-grid" aria-hidden="true"><span class="pwa-skel" style="height:84px"></span><span class="pwa-skel" style="height:84px"></span><span class="pwa-skel" style="height:84px"></span><span class="pwa-skel" style="height:84px"></span></div></div>';
            }

            try {
                var response = await API.request('getWorkStats');
                if (response.success && response.data) {
                    var stat = response.data;

                    let todayBreakdown = '';
                    let monthBreakdown = '';

                    if (stat.is_sayac_ekibi) {
                        // Sayaç Ekipleri için özel etiketler
                        let dailyParts = [];
                        if (stat.details.daily_sekme) {
                            if (stat.details.daily_sekme.sokme_takma > 0) dailyParts.push(`${stat.details.daily_sekme.sokme_takma} Sayaç Değ.`);
                            if (stat.details.daily_sekme.kesme > 0) dailyParts.push(`${stat.details.daily_sekme.kesme} Kesme-Açma`);
                        }
                        if (stat.details && stat.details.daily_kacak > 0) dailyParts.push(`${stat.details.daily_kacak} Kaçak`);
                        if (dailyParts.length > 0) {
                            todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${dailyParts.join(' &bull; ')}</p>`;
                        }

                        let monthlyParts = [];
                        if (stat.details.monthly_sekme) {
                            if (stat.details.monthly_sekme.sokme_takma > 0) monthlyParts.push(`${stat.details.monthly_sekme.sokme_takma} Sayaç Değ.`);
                            if (stat.details.monthly_sekme.kesme > 0) monthlyParts.push(`${stat.details.monthly_sekme.kesme} Kesme-Açma`);
                        }
                        if (stat.details && stat.details.monthly_kacak > 0) monthlyParts.push(`${stat.details.monthly_kacak} Kaçak`);
                        if (monthlyParts.length > 0) {
                            monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${monthlyParts.join(' &bull; ')}</p>`;
                        }
                    } else if (stat.is_kacak_ekibi) {
                        // Kaçak Kontrol Ekipleri (Bekleyen Kaçaklar Dahil)
                        let dailyParts = [];
                        if (stat.details && stat.details.daily_kacak > 0) dailyParts.push(`${stat.details.daily_kacak} Kaçak Tutanak`);
                        if (stat.details && stat.details.daily_isler > 0) dailyParts.push(`${stat.details.daily_isler} İş`);
                        if (dailyParts.length > 0) {
                            todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${dailyParts.join(' &bull; ')}</p>`;
                        }

                        let monthlyParts = [];
                        if (stat.details && stat.details.monthly_kacak > 0) monthlyParts.push(`${stat.details.monthly_kacak} Kaçak Tutanak`);
                        if (stat.details && stat.details.monthly_isler > 0) monthlyParts.push(`${stat.details.monthly_isler} İş`);
                        if (monthlyParts.length > 0) {
                            monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${monthlyParts.join(' &bull; ')}</p>`;
                        }
                    } else {
                        // Normal ekipler için (Endeks + Kesme + Kaçak)
                        if (stat.details && (stat.details.daily_isler > 0 || stat.details.daily_endeks > 0 || stat.details.daily_kacak > 0)) {
                            let parts = [];
                            if (stat.details.daily_isler > 0) parts.push(`${stat.details.daily_isler} Kesme`);
                            if (stat.details.daily_endeks > 0) parts.push(`${stat.details.daily_endeks} Endeks`);
                            if (stat.details.daily_kacak > 0) parts.push(`${stat.details.daily_kacak} Kaçak`);
                            if (parts.length > 0) {
                                todayBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${parts.join(' &bull; ')}</p>`;
                            }
                        }

                        if (stat.details && (stat.details.monthly_isler > 0 || stat.details.monthly_endeks > 0 || stat.details.monthly_kacak > 0)) {
                            let parts = [];
                            if (stat.details.monthly_isler > 0) parts.push(`${stat.details.monthly_isler} Kesme`);
                            if (stat.details.monthly_endeks > 0) parts.push(`${stat.details.monthly_endeks} Endeks`);
                            if (stat.details.monthly_kacak > 0) parts.push(`${stat.details.monthly_kacak} Kaçak`);
                            if (parts.length > 0) {
                                monthBreakdown = `<p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1 font-medium leading-tight">${parts.join(' &bull; ')}</p>`;
                            }
                        }
                    }

                    let siralamaHtml = '';
                    if (stat.siralama) {
                        siralamaHtml = `
                            <div class="col-span-2 mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/50">
                                <h4 class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Aylık Performans Sıralaması</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800/80 rounded-2xl p-3 border border-slate-100 dark:border-slate-700 shadow-sm active:scale-[0.98] transition-all">
                                        <div class="relative shrink-0">
                                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 flex flex-col items-center justify-center shadow-lg shadow-blue-500/30">
                                                <span class="text-[10px] text-white/70 font-bold leading-none mb-0.5">SIRA</span>
                                                <span class="text-lg font-black text-white leading-none">#${stat.siralama.ekip_sira}</span>
                                            </div>
                                            <!-- Ring effect -->
                                            <div class="absolute -inset-1 rounded-2xl border border-blue-500/20 animate-pulse"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-tighter mb-0.5">BÖLGE GENELİ</p>
                                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 truncate" title="${stat.ekip_bolge || 'Bölge Bulunamadı'}">${stat.ekip_bolge || 'Bölge Bulunamadı'}</p>
                                            <p class="text-[9px] text-slate-400 font-medium">${stat.siralama.ekip_kisi} Kişi Arasında</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 bg-white dark:bg-slate-800/80 rounded-2xl p-3 border border-slate-100 dark:border-slate-700 shadow-sm active:scale-[0.98] transition-all">
                                        <div class="relative shrink-0">
                                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 flex flex-col items-center justify-center shadow-lg shadow-indigo-500/30">
                                                <span class="text-[10px] text-white/70 font-bold leading-none mb-0.5">SIRA</span>
                                                <span class="text-lg font-black text-white leading-none">#${stat.siralama.departman_sira}</span>
                                            </div>
                                            <!-- Ring effect -->
                                            <div class="absolute -inset-1 rounded-2xl border border-indigo-500/20 animate-pulse"></div>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-tighter mb-0.5">DEPARTMAN</p>
                                            <p class="text-[11px] font-bold text-slate-700 dark:text-slate-200 truncate" title="${stat.departman || 'Departman Bulunamadı'}">${stat.departman || 'Departman Bulunamadı'}</p>
                                            <p class="text-[9px] text-slate-400 font-medium">${stat.siralama.departman_kisi} Kişi Arasında</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    container.innerHTML = `
                        <div class="col-span-2 flex flex-col gap-2">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary text-xl">fact_check</span>
                                </div>
                                <div>
                                    <p class="text-slate-500 dark:text-slate-400 text-sm font-bold uppercase tracking-wider">Toplam Tamamlanan İş</p>
                                    <p class="text-[10px] text-slate-400 font-medium">Son Güncelleme: ${lastUpdateDate}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mt-2">
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Bugün</p>
                                    <p class="text-2xl font-black text-slate-900 dark:text-white">${stat.today}</p>
                                    ${todayBreakdown}
                                </div>
                                <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-3 border border-slate-100 dark:border-slate-700">
                                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-1">Bu Ay</p>
                                    <p class="text-2xl font-black text-slate-900 dark:text-white">${stat.month}</p>
                                    ${monthBreakdown}
                                </div>
                            </div>
                            ${siralamaHtml}
                        </div>
                    `;
                } else {
                    container.innerHTML = '<div class="col-span-2 card p-8 flex flex-col items-center justify-center text-center opacity-60"><span class="material-symbols-outlined text-4xl mb-2 text-slate-300">history_toggle_off</span><p class="text-sm text-slate-500">Bu dönemde henüz iş kaydı bulunmamaktadır.</p></div>';
                }
            } catch (error) {
                console.error('Work stats load error:', error);
                container.innerHTML = '<div class="col-span-2 card p-6 text-center text-red-500 text-sm">Veriler yüklenirken bir hata oluştu.</div>';
            }
        }

        var endeksFormatter = new Intl.NumberFormat('tr-TR');
        var endeksDetayAcik = false;

        function endeksFmt(value) {
            return endeksFormatter.format(Number(value) || 0);
        }

        function endeksTrend(current, previous) {
            if (!previous) {
                return current > 0 ? { pct: 100, text: 'Yeni', tone: 'new' } : { pct: 0, text: '-', tone: 'flat' };
            }
            var rounded = Math.round(((current - previous) / previous) * 1000) / 10;
            var tone = rounded <= -20 ? 'crit' : rounded <= -10 ? 'warn' : rounded < 0 ? 'soft' : rounded > 0 ? 'up' : 'flat';
            return { pct: rounded, text: (rounded > 0 ? '+' : '') + rounded.toFixed(1).replace('.', ',') + '%', tone: tone };
        }

        function endeksSigned(value) {
            return (value > 0 ? '+' : '') + endeksFmt(value);
        }

        async function loadEndeksKarsilastirma() {
            var container = document.getElementById('endeks-karsilastirma-container');
            if (!container) return;
            try {
                var response = await API.request('getEndeksKarsilastirma');
                if (!response.success) {
                    throw new Error(response.message || 'Endeks karşılaştırması yüklenemedi.');
                }
                renderEndeksKarsilastirma(response.data || {});
            } catch (error) {
                container.innerHTML = '<p class="pwa-endeks-note">' + escapeHtml(error.message || 'Endeks karşılaştırması yüklenemedi.')
                    + ' <button type="button" class="pwa-endeks-retry" onclick="loadEndeksKarsilastirma()">Tekrar dene</button></p>';
            }
        }

        function toggleEndeksDetay() {
            endeksDetayAcik = !endeksDetayAcik;
            var detay = document.getElementById('endeks-detay');
            var btn = document.getElementById('endeks-detay-btn');
            if (detay) detay.hidden = !endeksDetayAcik;
            if (btn) {
                btn.setAttribute('aria-expanded', endeksDetayAcik ? 'true' : 'false');
                btn.querySelector('.pwa-endeks-toggle-text').textContent = btn.getAttribute('data-baslik') + (endeksDetayAcik ? ' gizle' : ' göster');
                btn.classList.toggle('is-open', endeksDetayAcik);
            }
        }

        function renderEndeksKarsilastirma(data) {
            var container = document.getElementById('endeks-karsilastirma-container');
            var label = document.getElementById('endeks-kapsam-label');
            var periods = Array.isArray(data.periods) ? data.periods : [];
            var ekipMi = data.kapsam === 'ekip';
            var day = Math.max(1, Number(data.gun) || 1);

            if (label) label.textContent = ekipMi ? ('Ekibim' + (data.bolge ? ' · ' + data.bolge : '')) : 'Kişisel';

            var rows = (Array.isArray(data.personeller) ? data.personeller : []).map(function (p) {
                var values = periods.map(function (period) {
                    return Number(p.periods && p.periods[period.label] && p.periods[period.label].toplam) || 0;
                });
                var last = values[values.length - 1] || 0;
                var prev = values.length > 1 ? values[values.length - 2] : 0;
                return { name: p.personel_adi, sub: p.ekip_adi, kendisi: !!p.kendisi, values: values, diff: last - prev, trend: endeksTrend(last, prev) };
            }).sort(function (a, b) { return a.trend.pct - b.trend.pct; });

            if (!periods.length || !rows.length) {
                container.innerHTML = '<p class="pwa-endeks-note">Karşılaştırılacak okuma verisi bulunamadı.</p>';
                return;
            }

            var totals = periods.map(function (_, index) {
                return rows.reduce(function (sum, row) { return sum + (row.values[index] || 0); }, 0);
            });
            var current = totals[totals.length - 1] || 0;
            var previous = totals.length > 1 ? totals[totals.length - 2] : 0;
            var trend = endeksTrend(current, previous);
            var max = Math.max.apply(null, [1].concat(totals));

            var html = '<div class="pwa-metrics">'
                + '<div class="pwa-metric"><span>Bu ay</span><strong>' + endeksFmt(current) + '</strong><small>İlk ' + day + ' gün</small></div>'
                + '<div class="pwa-metric pwa-endeks-tone--' + trend.tone + '"><span>Önceki aya göre</span><strong class="pwa-endeks-trend">' + escapeHtml(trend.text) + '</strong><small>' + endeksSigned(current - previous) + ' okuma</small></div>'
                + '<div class="pwa-metric"><span>Günlük ort.</span><strong>' + endeksFmt(Math.round(current / day)) + '</strong><small>Takvim günü</small></div>'
                + '</div>';

            html += '<div class="pwa-endeks-periods">' + periods.map(function (period, index) {
                var width = totals[index] ? Math.max(4, (totals[index] / max) * 100) : 0;
                return '<div class="pwa-endeks-period' + (period.is_current ? ' is-current' : '') + '">'
                    + '<span>' + escapeHtml(period.short_label) + '</span>'
                    + '<i class="pwa-endeks-bar"><b style="width:' + width.toFixed(1) + '%"></b></i>'
                    + '<strong>' + endeksFmt(totals[index]) + '</strong>'
                    + '</div>';
            }).join('') + '</div>';

            if (ekipMi || rows.length > 1) {
                var detayBaslik = ekipMi ? 'Ekip detaylarını' : 'Ekip kodu detaylarını';
                html += '<button type="button" id="endeks-detay-btn" class="pwa-endeks-toggle' + (endeksDetayAcik ? ' is-open' : '') + '" data-baslik="' + detayBaslik + '" aria-expanded="' + (endeksDetayAcik ? 'true' : 'false') + '" onclick="toggleEndeksDetay()">'
                    + '<span class="material-symbols-outlined">expand_more</span>'
                    + '<span class="pwa-endeks-toggle-text">' + detayBaslik + ' ' + (endeksDetayAcik ? 'gizle' : 'göster') + '</span>'
                    + '</button>';

                html += '<div id="endeks-detay" class="pwa-endeks-list"' + (endeksDetayAcik ? '' : ' hidden') + '>' + rows.map(function (row) {
                    return '<div class="pwa-endeks-row pwa-endeks-tone--' + row.trend.tone + (row.kendisi && ekipMi ? ' is-self' : '') + '">'
                        + '<div class="pwa-endeks-row-head">'
                        + '<span class="pwa-endeks-dot"></span>'
                        + '<span class="pwa-endeks-name"><strong>' + escapeHtml(ekipMi ? row.name : row.sub) + (row.kendisi && ekipMi ? ' <em>Siz</em>' : '') + '</strong>'
                        + (ekipMi ? '<small>' + escapeHtml(row.sub) + '</small>' : '') + '</span>'
                        + '<span class="pwa-endeks-badge">' + escapeHtml(row.trend.text) + '<small>' + endeksSigned(row.diff) + '</small></span>'
                        + '</div>'
                        + '<div class="pwa-endeks-months">' + periods.map(function (period, index) {
                            return '<div class="pwa-endeks-month' + (period.is_current ? ' is-current' : '') + '">'
                                + '<span>' + escapeHtml(period.short_label) + '</span>'
                                + '<strong>' + endeksFmt(row.values[index]) + '</strong>'
                                + '</div>';
                        }).join('') + '</div>'
                        + '</div>';
                }).join('') + '</div>';
            }

            html += '<p class="pwa-card-foot">Her ayın 1\'i ile ' + day + '\'i arası okunan abone sayıları karşılaştırılmaktadır.</p>';
            container.innerHTML = html;
        }

        async function loadCalismaStats() {
            var donemLabel = document.getElementById('combined-donem-label');
            var footerLabel = document.getElementById('combined-footer-label');
            var workedEl = document.getElementById('combined-actual-worked');
            var unpaidEl = document.getElementById('combined-unpaid-leave');
            var paidEl = document.getElementById('combined-paid-leave');

            var aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
            var now = new Date();
            var year = now.getFullYear();
            var month = now.getMonth();
            var daysInMonth = new Date(year, month + 1, 0).getDate();

            var includeToday = now.getHours() >= 17;
            var totalDays = includeToday ? now.getDate() : Math.max(0, now.getDate() - 1);
            var limitDay = totalDays;

            if (donemLabel) donemLabel.textContent = aylar[month] + ' ' + year;

            try {
                var response = await API.request('getIzinler');
                var unpaidLeaveDays = 0;
                var paidLeaveDays = 0;

                if (response.success && response.data && response.data.length > 0 && totalDays > 0) {
                    var monthStart = new Date(year, month, 1);
                    var monthEnd = new Date(year, month, Math.max(1, limitDay));
                    monthStart.setHours(0, 0, 0, 0);
                    monthEnd.setHours(0, 0, 0, 0);

                    response.data.forEach(function (izin) {
                        var status = (izin.durum || '').toLowerCase();
                        if (status !== 'onaylandi' && status !== 'onaylandı') return;

                        var start = parseCalismaDate(izin.baslangic);
                        var end = parseCalismaDate(izin.bitis);
                        if (!start || !end) return;

                        start.setHours(0, 0, 0, 0);
                        end.setHours(0, 0, 0, 0);

                        if (start > monthEnd || end < monthStart) return;

                        var overlapStart = start < monthStart ? monthStart : start;
                        var overlapEnd = end > monthEnd ? monthEnd : end;

                        var diffTime = Math.abs(overlapEnd - overlapStart);
                        var overlapDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                        var typeName = (izin.izin_tipi_text || '').toLowerCase();
                        if (typeName.includes('ücretsiz') || typeName.includes('ucretsiz')) {
                            unpaidLeaveDays += overlapDays;
                        } else {
                            paidLeaveDays += overlapDays;
                        }
                    });
                }

                var actualWorked = Math.max(0, totalDays - (paidLeaveDays + unpaidLeaveDays));

                if (workedEl) workedEl.textContent = actualWorked;
                if (unpaidEl) unpaidEl.textContent = unpaidLeaveDays;
                if (paidEl) paidEl.textContent = paidLeaveDays;
                var segToplam = Math.max(1, actualWorked + unpaidLeaveDays + paidLeaveDays);
                [['combined-seg-worked', actualWorked], ['combined-seg-unpaid', unpaidLeaveDays], ['combined-seg-paid', paidLeaveDays]].forEach(function (seg) {
                    var segEl = document.getElementById(seg[0]);
                    if (segEl) segEl.style.width = (seg[1] / segToplam * 100) + '%';
                });
                if (footerLabel) footerLabel.textContent = (includeToday ? 'Bugün dahil' : 'Düne kadar') + ' · Toplam ' + totalDays + ' / ' + daysInMonth + ' gün';

            } catch (error) {
                console.error('Çalışma stats load error:', error);
            }
        }

        function parseCalismaDate(str) {
            if (!str) return null;
            var parts = str.split('.');
            if (parts.length === 3) {
                return new Date(parts[2], parseInt(parts[1]) - 1, parts[0]);
            }
            return new Date(str);
        }

        async function loadDashboardData() {
            try {
                var response = await API.request('getDashboardData');
                if (response.success) {
                    var totalEarningEl = document.getElementById('combined-total-earning');
                    var receivedPaymentEl = document.getElementById('combined-received-payment');
                    var remainingBalanceEl = document.getElementById('combined-remaining-balance');
                    var donemEl = document.getElementById('combined-hakedis-donem');

                    if (totalEarningEl) totalEarningEl.textContent = Format.currency(response.data.total_earning || 0);
                    if (receivedPaymentEl) receivedPaymentEl.textContent = Format.currency(response.data.received_payment || 0);
                    if (remainingBalanceEl) remainingBalanceEl.textContent = Format.currency(response.data.remaining_balance || 0);

                    if (response.data.son_donem_adi && donemEl) {
                        donemEl.textContent = response.data.son_donem_adi;
                        donemEl.classList.remove('hidden');
                    }
                }
            } catch (error) {
                console.error('Dashboard data load error:', error);
            }
        }

        async function loadNotificationCount() {
            try {
                var response = await API.request('getMyNotifications', {}, false);
                if (response && response.success && Array.isArray(response.data)) {
                    var unreadCount = response.data.filter(function (n) { return !n.okundu; }).length;
                    var badge = document.getElementById('notification-badge');
                    if (badge) {
                        if (unreadCount > 0) {
                            badge.classList.remove('hidden');
                            badge.style.display = 'flex';
                            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                        } else {
                            badge.classList.add('hidden');
                            badge.style.display = 'none';
                        }
                    }
                }
            } catch (error) {
                console.error('Notification count error:', error);
            }
        }

        function getNotificationIconMeta(notification) {
            var t = ((notification.title || '') + ' ' + (notification.body || '')).toLowerCase();
            var type = notification.type || '';
            if (type === 'nobet_degisim') {
                return { icon: 'swap_horiz', bg: 'bg-amber-100 dark:bg-amber-900/30', color: 'text-amber-600 dark:text-amber-400' };
            }
            if (type === 'nobet_log' || t.includes('nöbet') || t.includes('nobet')) {
                return { icon: 'calendar_month', bg: 'bg-amber-100 dark:bg-amber-900/30', color: 'text-amber-600 dark:text-amber-400' };
            }
            if (t.includes('bordro') || t.includes('maaş') || t.includes('maas')) {
                return { icon: 'receipt_long', bg: 'bg-emerald-100 dark:bg-emerald-900/30', color: 'text-emerald-600 dark:text-emerald-400' };
            }
            if (t.includes('avans')) {
                return { icon: 'payments', bg: 'bg-green-100 dark:bg-green-900/30', color: 'text-green-600 dark:text-green-400' };
            }
            if (t.includes('izin')) {
                return { icon: 'event_available', bg: 'bg-blue-100 dark:bg-blue-900/30', color: 'text-blue-600 dark:text-blue-400' };
            }
            if (t.includes('ihbar')) {
                return { icon: 'campaign', bg: 'bg-red-100 dark:bg-red-900/30', color: 'text-red-600 dark:text-red-400' };
            }
            if (t.includes('kaçak') || t.includes('kacak') || t.includes('tutanak')) {
                return { icon: 'shield', bg: 'bg-purple-100 dark:bg-purple-900/30', color: 'text-purple-600 dark:text-purple-400' };
            }
            if (t.includes('km') || t.includes('araç') || t.includes('arac')) {
                return { icon: 'directions_car', bg: 'bg-cyan-100 dark:bg-cyan-900/30', color: 'text-cyan-600 dark:text-cyan-400' };
            }
            if (t.includes('destek') || t.includes('bilet') || t.includes('talep')) {
                return { icon: 'assignment', bg: 'bg-indigo-100 dark:bg-indigo-900/30', color: 'text-indigo-600 dark:text-indigo-400' };
            }
            return { icon: 'notifications', bg: 'bg-blue-100 dark:bg-blue-900/30', color: 'text-primary' };
        }

        function openNotificationModal() {
            Modal.open('notification-modal');
            loadNotifications();
        }

        async function loadNotifications() {
            var container = document.getElementById('notification-list');
            if (!container) return;
            container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';

            try {
                var response = await API.request('getMyNotifications');

                if (response && response.success && Array.isArray(response.data) && response.data.length > 0) {
                    allNotificationsData = response.data;
                    container.innerHTML = response.data.map(function (notification, index) {
                        var isUnread = !notification.okundu;
                        var unreadIndicator = isUnread ? '<span class="w-2.5 h-2.5 rounded-full bg-primary shrink-0 animate-pulse"></span>' : '';
                        var bgClass = isUnread 
                            ? 'bg-blue-50/80 dark:bg-blue-950/30 border border-primary/30 shadow-sm' 
                            : 'bg-slate-50/80 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800/80 opacity-90';
                        
                        var meta = getNotificationIconMeta(notification);
                        var icon = meta.icon;
                        var iconBg = meta.bg;
                        var iconColor = meta.color;

                        // Resim varsa küçük thumbnail göster
                        var thumbnailHtml = notification.image
                            ? '<img src="' + escapeHtml(notification.image) + '" class="w-10 h-10 rounded-lg object-cover flex-shrink-0" onerror="this.style.display=\'none\'">'
                            : '';

                        var urlBadge = notification.url 
                            ? '<span class="inline-flex items-center gap-0.5 text-[9px] font-semibold text-primary/80 bg-primary/10 px-1.5 py-0.5 rounded"><span class="material-symbols-outlined text-[11px]">link</span>Link</span>' 
                            : '';

                        return '<div class="relative flex items-start gap-3 p-3 ' + bgClass + ' rounded-xl cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700/60 active:scale-[0.99] transition-all" onclick="showNotificationDetail(' + index + ')">' +
                            '<div class="w-9 h-9 rounded-full ' + iconBg + ' flex items-center justify-center flex-shrink-0 mt-0.5 shadow-sm">' +
                            '<span class="material-symbols-outlined ' + iconColor + ' text-xl">' + icon + '</span>' +
                            '</div>' +
                            '<div class="flex-1 min-w-0">' +
                            '<div class="flex items-center justify-between gap-1 mb-0.5">' +
                            '<p class="text-[13px] text-slate-900 dark:text-white truncate ' + (isUnread ? 'font-bold' : 'font-medium text-slate-700 dark:text-slate-300') + '">' + escapeHtml(notification.title) + '</p>' +
                            '<div class="flex items-center gap-1 shrink-0">' + unreadIndicator + '<span class="text-[10px] text-slate-400">' + escapeHtml(notification.time_ago) + '</span></div>' +
                            '</div>' +
                            '<p class="text-xs text-slate-600 dark:text-slate-400 line-clamp-2 leading-relaxed">' + escapeHtml(notification.body) + '</p>' +
                            (urlBadge ? '<div class="mt-1.5 flex items-center gap-2">' + urlBadge + '</div>' : '') +
                            '</div>' +
                            thumbnailHtml +
                            '<span class="material-symbols-outlined text-slate-300 dark:text-slate-600 text-lg self-center shrink-0">chevron_right</span>' +
                            '</div>';
                    }).join('');
                } else {
                    container.innerHTML = '<div class="flex flex-col items-center justify-center py-10 text-center"><span class="material-symbols-outlined text-4xl text-slate-300 dark:text-slate-600 mb-2">notifications_off</span><p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Henüz bir bildiriminiz bulunmuyor</p></div>';
                }
            } catch (error) {
                console.error('Notifications load error:', error);
                container.innerHTML = '<div class="flex flex-col items-center justify-center py-10 text-center"><span class="material-symbols-outlined text-4xl text-red-300 mb-2">error</span><p class="text-sm text-slate-500 font-medium">Bildirimler yüklenemedi</p></div>';
            }
        }

        async function showNotificationDetail(index) {
            var notification = allNotificationsData[index];
            if (!notification) return;

            currentNotificationIndex = index;

            // Bildirimi okundu olarak işaretle
            if (!notification.okundu) {
                try {
                    await API.request('markNotificationRead', { notification_id: notification.id }, false);
                    allNotificationsData[index].okundu = true;
                    loadNotificationCount(); // Badge'i güncelle
                } catch (e) {
                    console.error('Mark read error:', e);
                }
            }

            const isNobet = notification.type === 'nobet_degisim';
            const meta = getNotificationIconMeta(notification);
            const bgImg = isNobet 
                ? `background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);`
                : `background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);`;
            
            let imageHtml = '';
            if (notification.image) {
                imageHtml = `
                    <div class="mt-6">
                        <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3 pl-1">EKLİ GÖRSEL</p>
                        <div class="rounded-2xl overflow-hidden shadow-sm border border-slate-100 dark:border-slate-800 relative bg-slate-100 dark:bg-slate-800">
                            <img src="${escapeHtml(notification.image)}" class="w-full h-auto object-cover max-h-[400px]" alt="Bildirim Görseli">
                        </div>
                    </div>
                `;
            }

            let targetLinkHtml = '';
            if (notification.url) {
                targetLinkHtml = `
                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <a href="${escapeHtml(notification.url)}" class="w-full py-3.5 bg-primary text-white font-semibold rounded-2xl flex items-center justify-center gap-2 shadow-lg shadow-primary/25 active:scale-95 transition-all">
                            <span>İlgili Sayfayı Aç</span>
                            <span class="material-symbols-outlined text-lg">arrow_forward</span>
                        </a>
                    </div>
                `;
            }

            let actionsHtml = '';
            if (isNobet) {
                actionsHtml = `
                    <div class="flex items-center gap-3">
                        <button onclick="reddetNotificationTalep('${notification.talep_id}')" class="w-10 h-10 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center pointer-events-auto active:scale-90 transition-transform" title="Talebi Reddet">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                        <button onclick="onaylaNotificationTalep('${notification.talep_id}')" class="px-6 h-10 rounded-full bg-white text-amber-600 font-bold text-sm flex items-center justify-center pointer-events-auto active:scale-95 transition-transform shadow-lg">
                            Onayla
                        </button>
                    </div>
                `;
            } else {
                actionsHtml = `
                    <button onclick="deleteCurrentNotification()" class="w-10 h-10 rounded-full bg-red-500/10 text-red-500 flex items-center justify-center pointer-events-auto active:scale-90 transition-transform" title="Bildirimi Sil">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                `;
            }

            const html = `
                <div class="header-main relative px-6 pt-12 pb-8 flex flex-col items-start shadow-xl rounded-b-[2.5rem] safe-area-top shrink-0 overflow-hidden" style="${bgImg}">
                    <div class="absolute inset-0 opacity-10 overflow-hidden rounded-b-[2.5rem] pointer-events-none">
                        <span class="material-symbols-outlined absolute -right-4 -top-4 text-[10rem] text-white opacity-10">${meta.icon}</span>
                    </div>
                    
                    <div class="relative w-full z-10 flex flex-col h-full">
                        <div class="flex items-center justify-between mb-6">
                            <div class="w-10 h-10"></div> <!-- Placeholder -->
                            <span class="bg-white/10 backdrop-blur-md border border-white/10 text-white/90 rounded-lg px-3 py-1 text-[11px] font-semibold tracking-wide shadow-sm">${escapeHtml(notification.time_ago)}</span>
                        </div>

                        <div class="flex flex-col justify-end mt-2">
                            <h1 class="text-white text-2xl font-black tracking-tight leading-[1.15] break-words" style="text-shadow: 0 4px 8px rgba(0,0,0,0.5);">${escapeHtml(notification.title)}</h1>
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-8 flex-1 bg-transparent -mt-5 relative z-20">
                    <div class="bg-white dark:bg-card-dark rounded-[2rem] p-6 shadow-xl shadow-black/5 dark:shadow-black/20 border border-slate-100 dark:border-slate-800">
                        <p class="text-slate-700 dark:text-slate-300 text-[15px] leading-relaxed whitespace-pre-wrap">${escapeHtml(notification.body)}</p>
                        ${imageHtml}
                        ${isNobet ? `
                            <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/10 rounded-2xl border border-amber-100 dark:border-amber-900/20">
                                <p class="text-xs text-amber-700 dark:text-amber-400 font-medium">Bu talebi onayladığınızda, ilgili tarihteki nöbet sizin üzerinize atanacak ve bir amirin onayına sunulacaktır.</p>
                            </div>
                        ` : ''}
                        ${targetLinkHtml}
                    </div>
                </div>
            `;

            Modal.close('notification-modal');
            showPwaFullModal({ 
                html: html,
                actionsHtml: actionsHtml
            });
        }

        async function onaylaNotificationTalep(talepId) {
            const confirmed = await Alert.confirm('Talebi Onayla', 'Bu nöbet değişim talebini onaylamak istediğinize emin misiniz?', 'Evet, Onayla', 'Vazgeç');
            if (!confirmed) return;

            try {
                const response = await API.request('onaylaNobetDegisimTalebi', { talep_id: talepId });
                if (response.success) {
                    Toast.show('Talep onaylandı. Yönetici onayını bekliyor.', 'success');
                    closePwaFullModal();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        async function reddetNotificationTalep(talepId) {
            const confirmed = await Alert.confirm('Talebi Reddet', 'Bu nöbet değişim talebini reddetmek istediğinize emin misiniz?', 'Evet, Reddet', 'Vazgeç');
            if (!confirmed) return;

            try {
                const response = await API.request('reddetNobetDegisimTalebi', { talep_id: talepId });
                if (response.success) {
                    Toast.show('Talep reddedildi', 'success');
                    closePwaFullModal();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        function closeNotificationDetail() {
            closePwaFullModal();
            setTimeout(function () {
                Modal.open('notification-modal');
                loadNotifications(); // Listeyi güncelle
            }, 200);
        }

        async function deleteCurrentNotification() {
            if (currentNotificationIndex < 0) return;

            var notification = allNotificationsData[currentNotificationIndex];
            if (!notification) return;

            var confirmed = await Alert.confirmDelete('Bildirimi Sil', 'Bu bildirimi silmek istediğinize emin misiniz?', 'Evet, Sil', 'Vazgeç');
            if (!confirmed) return;

            try {
                var response = await API.request('deleteNotification', { notification_id: notification.id });
                if (response.success) {
                    Toast.show('Bildirim silindi', 'success');
                    allNotificationsData.splice(currentNotificationIndex, 1);
                    currentNotificationIndex = -1;
                    loadNotificationCount();
                    closeNotificationDetail();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        async function markAllAsRead() {
            try {
                var response = await API.request('markAllNotificationsRead');
                if (response.success) {
                    Toast.show('Tüm bildirimler okundu olarak işaretlendi', 'success');
                    loadNotifications();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        async function deleteAllNotifications() {
            const confirmed = await Alert.confirmDelete('Tümünü Sil', 'Tüm bildirimlerinizi silmek istediğinize emin misiniz?', 'Evet, Tümünü Sil', 'Vazgeç');
            if (!confirmed) return;

            try {
                var response = await API.request('deleteAllNotifications');
                if (response.success) {
                    Toast.show('Tüm bildirimler silindi', 'success');
                    allNotificationsData = [];
                    currentNotificationIndex = -1;
                    loadNotifications();
                    loadNotificationCount();
                } else {
                    Toast.show(response.message || 'Bir hata oluştu', 'error');
                }
            } catch (error) {
                Toast.show('Bir hata oluştu', 'error');
            }
        }

        // ===== Ana Sayfa Kart Sürükle-Bırak Sıralaması =====
        (function() {
            const style = document.createElement('style');
            style.textContent = '[data-home-card]{position:relative}'
                + '.home-card-dragging{opacity:.55;transform:scale(.98);transition:transform .15s ease}'
                + 'body.is-card-dragging{user-select:none;-webkit-user-select:none;cursor:grabbing}'
                + '#home-card-drop-indicator{position:fixed;height:4px;border-radius:4px;background:#2563eb;z-index:60;pointer-events:none;display:none;box-shadow:0 0 8px rgba(37,99,235,.6)}';
            document.head.appendChild(style);

            const indicator = document.createElement('div');
            indicator.id = 'home-card-drop-indicator';
            document.body.appendChild(indicator);

            function kartlar() {
                return Array.prototype.slice.call(document.querySelectorAll('[data-home-card]'))
                    .filter(function(el) { return el.offsetParent !== null || el.getClientRects().length > 0; });
            }

            function siraliAnahtarlar() {
                return Array.prototype.slice.call(document.querySelectorAll('[data-home-card]'))
                    .sort(function(a, b) { return (parseInt(a.style.order, 10) || 0) - (parseInt(b.style.order, 10) || 0); })
                    .map(function(el) { return el.getAttribute('data-home-card'); });
            }

            function hedefBul(clientY, surukleyen) {
                const liste = kartlar().filter(function(el) { return el !== surukleyen; });
                for (let i = 0; i < liste.length; i++) {
                    const r = liste[i].getBoundingClientRect();
                    if (clientY >= r.top && clientY <= r.bottom) {
                        return { el: liste[i], ust: clientY < r.top + r.height / 2, rect: r };
                    }
                }
                return null;
            }

            async function kaydet(siralama) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'update_ana_sayfa_siralama');
                    formData.append('siralama', JSON.stringify(siralama));
                    const response = await fetch('api.php', { method: 'POST', body: formData });
                    const data = await response.json();
                    if (!data.success) throw new Error(data.message || 'Kaydedilemedi');
                } catch (err) {
                    console.error('Ana sayfa sıralama hatası:', err);
                    if (typeof Toast !== 'undefined') Toast.show('Sıralama kaydedilemedi', 'error');
                }
            }

            function surukle(kart, e) {
                if (e.button && e.button !== 0) return;
                e.preventDefault();
                const tutamac = e.currentTarget;
                let hedef = null;
                try { tutamac.setPointerCapture(e.pointerId); } catch (err) {}
                kart.classList.add('home-card-dragging');
                document.body.classList.add('is-card-dragging');
                if (navigator.vibrate) { try { navigator.vibrate(12); } catch (err) {} }

                function hareket(ev) {
                    hedef = hedefBul(ev.clientY, kart);
                    if (hedef) {
                        indicator.style.display = 'block';
                        indicator.style.left = (hedef.rect.left + 16) + 'px';
                        indicator.style.width = (hedef.rect.width - 32) + 'px';
                        indicator.style.top = ((hedef.ust ? hedef.rect.top : hedef.rect.bottom) - 2) + 'px';
                    } else {
                        indicator.style.display = 'none';
                    }
                    if (ev.clientY < 70) window.scrollBy(0, -8);
                    else if (ev.clientY > window.innerHeight - 70) window.scrollBy(0, 8);
                }

                function bitir() {
                    tutamac.removeEventListener('pointermove', hareket);
                    tutamac.removeEventListener('pointerup', bitir);
                    tutamac.removeEventListener('pointercancel', iptal);
                    temizle();
                    if (!hedef) return;
                    const anahtar = kart.getAttribute('data-home-card');
                    const hedefAnahtar = hedef.el.getAttribute('data-home-card');
                    const sira = siraliAnahtarlar().filter(function(k) { return k !== anahtar; });
                    const konum = sira.indexOf(hedefAnahtar) + (hedef.ust ? 0 : 1);
                    sira.splice(konum, 0, anahtar);
                    sira.forEach(function(k, i) {
                        const el = document.querySelector('[data-home-card="' + k + '"]');
                        if (el) el.style.order = i + 1;
                    });
                    kaydet(sira);
                }

                function iptal() {
                    tutamac.removeEventListener('pointermove', hareket);
                    tutamac.removeEventListener('pointerup', bitir);
                    tutamac.removeEventListener('pointercancel', iptal);
                    hedef = null;
                    temizle();
                }

                function temizle() {
                    kart.classList.remove('home-card-dragging');
                    document.body.classList.remove('is-card-dragging');
                    indicator.style.display = 'none';
                }

                tutamac.addEventListener('pointermove', hareket);
                tutamac.addEventListener('pointerup', bitir);
                tutamac.addEventListener('pointercancel', iptal);
            }

            document.querySelectorAll('[data-home-card]').forEach(function(kart) {
                const tutamac = document.createElement('div');
                tutamac.setAttribute('role', 'button');
                tutamac.setAttribute('aria-label', 'Kartı sürükleyip taşı');
                tutamac.title = 'Sürükle ve taşı';
                tutamac.innerHTML = '<span class="material-symbols-outlined">drag_indicator</span>';
                tutamac.addEventListener('pointerdown', function(e) { surukle(kart, e); });

                const baslik = kart.querySelector('.pwa-card-head');
                if (baslik) {
                    tutamac.className = 'pwa-card-handle';
                    baslik.insertBefore(tutamac, baslik.firstChild);
                } else {
                    tutamac.className = 'pwa-card-handle pwa-card-handle--float';
                    kart.insertBefore(tutamac, kart.firstChild);
                }
            });
        })();

        // ===== Hızlı İşlemler Özelleştirme Mantığı =====
        const ALL_ACTION_CATALOG = <?php echo json_encode(array_values($availableQuickActions), JSON_UNESCAPED_UNICODE); ?>;
        let currentActiveKeys = <?php echo json_encode(array_column($activeQuickActions, 'key'), JSON_UNESCAPED_UNICODE); ?>;
        const DEFAULT_KEYS = <?php echo json_encode(\App\Model\PersonelModel::DEFAULT_PWA_HIZLI_ISLEMLER, JSON_UNESCAPED_UNICODE); ?>;

        function openHizliIslemlerModal() {
            renderHizliIslemlerModalLists();
            Modal.open('hizli-islemler-modal');
        }

        function renderHizliIslemlerModalLists() {
            const activeContainer = document.getElementById('active-actions-list');
            const availableContainer = document.getElementById('available-actions-list');
            const activeBadge = document.getElementById('active-count-badge');

            if (!activeContainer || !availableContainer) return;

            activeContainer.innerHTML = '';
            availableContainer.innerHTML = '';

            const catalogMap = {};
            ALL_ACTION_CATALOG.forEach(function(act) { catalogMap[act.key] = act; });

            const validActiveKeys = currentActiveKeys.filter(function(k) { return catalogMap[k]; });
            currentActiveKeys = validActiveKeys;

            if (activeBadge) activeBadge.textContent = currentActiveKeys.length + ' aktif';

            if (currentActiveKeys.length === 0) {
                activeContainer.innerHTML = '<div class="text-center py-4 text-xs text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-300 dark:border-slate-700">Aktif hızlı işlem bulunmuyor. Aşağıdan ekleyebilirsiniz.</div>';
            } else {
                currentActiveKeys.forEach(function(key, index) {
                    const act = catalogMap[key];
                    const itemEl = document.createElement('div');
                    itemEl.className = 'quick-action-edit-item flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700/80 shadow-sm transition-all select-none';
                    itemEl.setAttribute('draggable', 'true');
                    itemEl.setAttribute('data-key', key);
                    itemEl.setAttribute('data-index', index);

                    const isFirst = index === 0;
                    const isLast = index === currentActiveKeys.length - 1;

                    itemEl.innerHTML = `
                        <div class="flex items-center gap-3">
                            <span class="drag-handle cursor-grab active:cursor-grabbing text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 material-symbols-outlined touch-none">drag_indicator</span>
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br ${act.gradient} flex items-center justify-center text-white shadow-sm">
                                <span class="material-symbols-outlined text-lg filled">${act.icon}</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-900 dark:text-white">${escapeHtml(act.title)}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">${escapeHtml(act.desc)}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" onclick="moveHizliIslemItem(${index}, -1)" ${isFirst ? 'disabled' : ''} class="w-7 h-7 rounded-lg flex items-center justify-center ${isFirst ? 'text-slate-300 dark:text-slate-600' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'}">
                                <span class="material-symbols-outlined text-base">arrow_upward</span>
                            </button>
                            <button type="button" onclick="moveHizliIslemItem(${index}, 1)" ${isLast ? 'disabled' : ''} class="w-7 h-7 rounded-lg flex items-center justify-center ${isLast ? 'text-slate-300 dark:text-slate-600' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700'}">
                                <span class="material-symbols-outlined text-base">arrow_downward</span>
                            </button>
                            <button type="button" onclick="removeHizliIslemItem('${key}')" class="w-7 h-7 rounded-lg flex items-center justify-center text-red-500 hover:bg-red-50 dark:hover:bg-red-950/30 ml-1" title="Çıkar">
                                <span class="material-symbols-outlined text-base">remove_circle_outline</span>
                            </button>
                        </div>
                    `;

                    setupDragAndDropEvents(itemEl, index);
                    activeContainer.appendChild(itemEl);
                });
            }

            const availableItems = ALL_ACTION_CATALOG.filter(function(act) { return !currentActiveKeys.includes(act.key); });

            if (availableItems.length === 0) {
                availableContainer.innerHTML = '<div class="text-center py-3 text-xs text-slate-400">Tüm kullanılabilir hızlı işlemler eklenmiş durumda.</div>';
            } else {
                availableItems.forEach(function(act) {
                    const itemEl = document.createElement('div');
                    itemEl.className = 'flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/40 transition-all';
                    itemEl.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-300">
                                <span class="material-symbols-outlined text-lg">${act.icon}</span>
                            </div>
                            <div>
                                <div class="text-xs font-semibold text-slate-800 dark:text-slate-200">${escapeHtml(act.title)}</div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">${escapeHtml(act.desc)}</div>
                            </div>
                        </div>
                        <button type="button" onclick="addHizliIslemItem('${act.key}')" class="px-2.5 py-1.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold flex items-center gap-1 transition-colors active:scale-95">
                            <span class="material-symbols-outlined text-sm">add</span>
                            Ekle
                        </button>
                    `;
                    availableContainer.appendChild(itemEl);
                });
            }
        }

        function moveHizliIslemItem(index, direction) {
            const targetIndex = index + direction;
            if (targetIndex < 0 || targetIndex >= currentActiveKeys.length) return;
            const temp = currentActiveKeys[index];
            currentActiveKeys[index] = currentActiveKeys[targetIndex];
            currentActiveKeys[targetIndex] = temp;
            renderHizliIslemlerModalLists();
        }

        function addHizliIslemItem(key) {
            if (!currentActiveKeys.includes(key)) {
                currentActiveKeys.push(key);
                renderHizliIslemlerModalLists();
            }
        }

        function removeHizliIslemItem(key) {
            currentActiveKeys = currentActiveKeys.filter(function(k) { return k !== key; });
            renderHizliIslemlerModalLists();
        }

        function resetHizliIslemlerToDefault() {
            currentActiveKeys = Array.from(DEFAULT_KEYS);
            renderHizliIslemlerModalLists();
        }

        let draggedIndex = null;
        function setupDragAndDropEvents(el, index) {
            el.addEventListener('dragstart', function(e) {
                draggedIndex = index;
                el.classList.add('opacity-40', 'scale-95');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', index);
            });

            el.addEventListener('dragend', function() {
                draggedIndex = null;
                el.classList.remove('opacity-40', 'scale-95');
            });

            el.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });

            el.addEventListener('drop', function(e) {
                e.preventDefault();
                if (draggedIndex === null || draggedIndex === index) return;
                const movedItem = currentActiveKeys.splice(draggedIndex, 1)[0];
                currentActiveKeys.splice(index, 0, movedItem);
                renderHizliIslemlerModalLists();
            });

            const handle = el.querySelector('.drag-handle');
            if (handle) {
                let startY = 0;
                let initialIndex = index;

                handle.addEventListener('touchstart', function(e) {
                    if (e.touches && e.touches[0]) {
                        startY = e.touches[0].clientY;
                        initialIndex = index;
                        el.classList.add('bg-primary/10', 'border-primary', 'shadow-md');
                    }
                }, { passive: true });

                handle.addEventListener('touchmove', function(e) {
                    if (!e.touches || !e.touches[0]) return;
                    const currentY = e.touches[0].clientY;
                    const diffY = currentY - startY;

                    if (Math.abs(diffY) > 36) {
                        const step = diffY > 0 ? 1 : -1;
                        const newIndex = initialIndex + step;
                        if (newIndex >= 0 && newIndex < currentActiveKeys.length) {
                            const temp = currentActiveKeys[initialIndex];
                            currentActiveKeys[initialIndex] = currentActiveKeys[newIndex];
                            currentActiveKeys[newIndex] = temp;
                            startY = currentY;
                            initialIndex = newIndex;
                            renderHizliIslemlerModalLists();
                        }
                    }
                }, { passive: true });

                handle.addEventListener('touchend', function() {
                    el.classList.remove('bg-primary/10', 'border-primary', 'shadow-md');
                });
            }
        }

        async function saveHizliIslemlerConfig() {
            const btn = document.getElementById('btn-save-hizli-islemler');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span> Kaydediliyor...';

            try {
                const formData = new FormData();
                formData.append('action', 'update_hizli_islemler');
                formData.append('hizli_islemler', JSON.stringify(currentActiveKeys));

                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    const savedKeys = data.data && Array.isArray(data.data.hizli_islemler)
                        ? data.data.hizli_islemler
                        : null;
                    if (!savedKeys || JSON.stringify(savedKeys) !== JSON.stringify(currentActiveKeys)) {
                        throw new Error('Hızlı işlem sırası sunucuda doğrulanamadı.');
                    }
                    if (typeof Toast !== 'undefined' && typeof Toast.show === 'function') {
                        Toast.show(data.message || 'Hızlı işlemler güncellendi', 'success');
                    }
                    Modal.close('hizli-islemler-modal');
                    setTimeout(function() { window.location.reload(); }, 300);
                } else {
                    if (typeof Toast !== 'undefined' && typeof Toast.show === 'function') {
                        Toast.show(data.message || 'Bir hata oluştu', 'error');
                    } else {
                        alert(data.message || 'Bir hata oluştu');
                    }
                }
            } catch (err) {
                console.error('Save quick actions error:', err);
                if (typeof Toast !== 'undefined' && typeof Toast.show === 'function') {
                    Toast.show('Bir sunucu hatası oluştu', 'error');
                } else {
                    alert('Bir sunucu hatası oluştu');
                }
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
