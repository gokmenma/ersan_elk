<?php
/**
 * Personel PWA - Aparat Takip
 * Kesme/açma saha kaydı, ekip stoğu ve ekipler arası aparat transferi.
 */

use App\Model\AparatStokModel;
use App\Model\AparatTipiModel;
use App\Model\KesmeAcmaIslemModel;

$AparatStok = new AparatStokModel();
$AparatTip = new AparatTipiModel();

$aktifEkip = $AparatStok->aktifEkip((int) $personel_id);
$aparatTipleri = $AparatTip->listele(true);
$aparatDurumlari = KesmeAcmaIslemModel::APARAT_DURUMLARI;

$ekipUyeleri = $aktifEkip ? $AparatStok->ekipUyeleri((int) $aktifEkip['id']) : [];
$uyeAdlari = implode(', ', array_column($ekipUyeleri, 'adi_soyadi'));
?>

<style>
    /* Bu ekranın renk/konum sınıfları statik Tailwind build'ine bağlı kalmasın diye
       burada tanımlanır; build'de olmayan bir utility sessizce çalışmıyor. */
    .aparat-fab {
        position: fixed;
        right: 1rem;
        bottom: 5.25rem;
        z-index: 45;
        width: 3.5rem;
        height: 3.5rem;
        border-radius: 9999px;
        background: var(--primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        box-shadow: 0 10px 24px rgba(0, 0, 0, .28);
    }

    .aparat-fab:active {
        transform: scale(.94);
    }

    .aparat-sheet-katman {
        position: fixed;
        inset: 0;
        z-index: 60;
        background: rgba(15, 23, 42, .55);
        display: none;
    }

    .aparat-sheet-katman.acik {
        display: block;
    }

    .aparat-sheet {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        max-height: 90vh;
        overflow-y: auto;
        background: #fff;
        border-radius: 1.25rem 1.25rem 0 0;
        padding: .75rem 1rem calc(2rem + env(safe-area-inset-bottom));
    }

    .dark .aparat-sheet {
        background: #1e1e1f;
    }

    .apf-yardim-btn { display: inline-grid; place-items: center; width: 36px; height: 36px; margin-left: 6px; border: 0; border-radius: 999px; background: rgba(255, 255, 255, .16); color: inherit; }
    .apf-yardim-btn .material-symbols-outlined { font-size: 21px; }
    .apf-tanitim { display: flex; align-items: center; gap: 12px; width: 100%; margin-bottom: 12px; padding: 12px 14px; border: 0; border-radius: 14px; background: #eef2ff; color: #1e293b; text-align: left; }
    .dark .apf-tanitim { background: #252b3b; color: #e2e8f0; }
    .apf-tanitim__ikon { display: grid; place-items: center; flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; background: var(--primary); color: #fff; }
    .apf-tanitim__metin { display: flex; flex: 1; min-width: 0; flex-direction: column; }
    .apf-tanitim__metin strong { font-size: 14px; }
    .apf-tanitim__metin small { font-size: 12px; color: #64748b; margin-top: 2px; }
    .apf-tanitim__ok { color: #94a3b8; }

    body > .aparat-sheet-katman { z-index: 2000; }
    .apf-pencere { padding-left: .875rem; padding-right: .875rem; }
    .apf-pencere__ust { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
    .apf-pencere__ust h2 { margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; }
    .apf-pencere__ust p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
    .dark .apf-pencere__ust h2 { color: #fff; }
    .apf-kapat { display: grid; place-items: center; flex: 0 0 40px; width: 40px; height: 40px; border: 0; border-radius: 12px; background: #f1f5f9; color: #334155; }
    .dark .apf-kapat { background: #2a2a2c; color: #e2e8f0; }

    .apf { border: 1px solid #e2e8f0; border-radius: 16px; background: #f8fafc; overflow: hidden; }
    .dark .apf { background: #161618; border-color: #2a2a2c; }
    .apf-sahne { position: relative; height: 230px; margin: 4px 6px 0; }
    .apf-yollar { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .apf-yollar line { stroke: #cbd5e1; stroke-width: 2; stroke-dasharray: 4 5; vector-effect: non-scaling-stroke; }
    .dark .apf-yollar line { stroke: #3f3f46; }
    .apf-yollar line.aktif { stroke: var(--apf-renk, #475569); stroke-width: 3; animation: apf-akis .6s linear infinite; }
    @keyframes apf-akis { to { stroke-dashoffset: -18; } }
    .apf-dugum { position: absolute; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; gap: 3px; width: 72px; text-align: center; z-index: 1; }
    .apf-ikon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 13px; background: #fff; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(15, 23, 42, .08); transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
    .dark .apf-ikon { background: #232326; border-color: #34343a; }
    .apf-ikon .material-symbols-outlined { font-size: 22px; }
    .apf-ikon--tedarik { color: #64748b; }
    .apf-ikon--depo { color: #2563eb; }
    .apf-ikon--sen { color: #fff; background: var(--primary); border-color: var(--primary); }
    .apf-ikon--ekip { color: #059669; }
    .apf-ikon--abone { color: #d97706; }
    .apf-dugum.vurgu .apf-ikon { transform: scale(1.1); border-color: var(--apf-renk, #475569); box-shadow: 0 0 0 4px rgba(100, 116, 139, .18); }
    .apf-ad { font-size: 11.5px; font-weight: 800; line-height: 1.1; color: #1e293b; }
    .dark .apf-ad { color: #e2e8f0; }
    .apf-sayi { position: absolute; top: -7px; right: 6px; min-width: 21px; height: 21px; padding: 0 5px; border-radius: 999px; background: #1e293b; color: #fff; font-size: 11px; font-weight: 800; line-height: 21px; }
    .apf-sayi.artti { animation: apf-art .5s ease; background: #059669; }
    .apf-sayi.azaldi { animation: apf-art .5s ease; background: #d97706; }
    @keyframes apf-art { 40% { transform: scale(1.4); } }
    .apf-aparat { position: absolute; z-index: 2; display: grid; place-items: center; width: 28px; height: 28px; margin: -14px 0 0 -14px; border-radius: 9px; background: var(--apf-renk, #475569); color: #fff; box-shadow: 0 6px 14px rgba(15, 23, 42, .28); transition: left 1.2s cubic-bezier(.45, .05, .25, 1), top 1.2s cubic-bezier(.45, .05, .25, 1); }
    .apf-aparat .material-symbols-outlined { font-size: 17px; }
    .apf-anlatim { padding: 4px 14px 6px; text-align: center; min-height: 118px; }
    .apf-kim { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; }
    .apf-kim.sen, .apf-etiket--sen { background: #dbeafe; color: #1d4ed8; }
    .apf-kim.depo, .apf-etiket--depo { background: #e2e8f0; color: #334155; }
    .apf-kim.ekip { background: #d1fae5; color: #047857; }
    .apf-baslik { margin: 6px 0 2px; font-size: 16px; font-weight: 800; color: #0f172a; }
    .dark .apf-baslik { color: #fff; }
    .apf-metin { margin: 0; font-size: 13px; line-height: 1.45; color: #475569; }
    .dark .apf-metin { color: #a1a1aa; }
    .apf-kontrol { display: flex; align-items: center; gap: 6px; padding: 10px 12px 12px; border-top: 1px solid #e2e8f0; background: #fff; }
    .dark .apf-kontrol { background: #1e1e1f; border-color: #2a2a2c; }
    .apf-btn { display: grid; place-items: center; flex: 0 0 44px; width: 44px; height: 44px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; color: #334155; }
    .dark .apf-btn { background: #232326; border-color: #34343a; color: #e2e8f0; }
    .apf-btn--ana { background: var(--primary); border-color: var(--primary); color: #fff; }
    .apf-adimlar { display: flex; flex: 1; gap: 4px; margin-left: 4px; }
    .apf-adimlar button { flex: 1; height: 24px; border: 0; padding: 0; background: transparent; position: relative; }
    .apf-adimlar button::before { content: ""; position: absolute; left: 0; right: 0; top: 9px; height: 6px; border-radius: 999px; background: #e2e8f0; }
    .dark .apf-adimlar button::before { background: #34343a; }
    .apf-adimlar button.bitti::before { background: #94a3b8; }
    .apf-adimlar button.aktif::after { content: ""; position: absolute; left: 0; top: 9px; height: 6px; width: 0; border-radius: 999px; background: var(--apf-renk, #475569); animation: apf-dolum var(--apf-sure, 4200ms) linear forwards; }
    .apf.durdu .apf-adimlar button.aktif::after { animation: none; width: 100%; }
    @keyframes apf-dolum { to { width: 100%; } }
    .apf-ozet { display: grid; gap: 8px; margin: 12px 2px; }
    .apf-ozet__satir { display: flex; align-items: flex-start; gap: 8px; font-size: 12.5px; color: #334155; }
    .dark .apf-ozet__satir { color: #d4d4d8; }
    .apf-etiket { flex: 0 0 auto; padding: 2px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 800; }
    .apf-tamam { width: 100%; min-height: 48px; border: 0; border-radius: 14px; background: var(--primary); color: #fff; font-size: 15px; font-weight: 800; }
    @media (prefers-reduced-motion: reduce) {
        .apf-aparat, .apf-ikon { transition: none; }
        .apf-yollar line.aktif, .apf-sayi.artti, .apf-sayi.azaldi { animation: none; }
    }

    .aparat-sheet-tutamak {
        width: 2.5rem;
        height: .25rem;
        border-radius: 9999px;
        background: #cbd5e1;
        margin: 0 auto .75rem;
    }

    .aparat-segment {
        padding: 1rem .5rem;
        border-radius: .75rem;
        font-weight: 700;
        font-size: .875rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .25rem;
        background: #f1f5f9;
        color: #64748b;
        border: none;
    }

    .dark .aparat-segment {
        background: #27272a;
        color: #94a3b8;
    }

    .aparat-segment.aktif-kesme {
        background: #ef4444;
        color: #fff;
    }

    .aparat-segment.aktif-acma {
        background: #059669;
        color: #fff;
    }

    .aparat-secenek {
        padding: .75rem .5rem;
        border-radius: .75rem;
        font-weight: 700;
        font-size: .75rem;
        background: #f1f5f9;
        color: #64748b;
        border: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: .25rem;
        width: 100%;
    }

    .dark .aparat-secenek {
        background: #27272a;
        color: #94a3b8;
    }

    .aparat-secenek.secili {
        background: var(--primary);
        color: #fff;
    }

    .aparat-rozet {
        position: absolute;
        top: -.25rem;
        right: -.25rem;
        min-width: 1.1rem;
        height: 1.1rem;
        padding: 0 .25rem;
        border-radius: 9999px;
        background: #ef4444;
        color: #fff;
        font-size: .65rem;
        font-weight: 700;
        line-height: 1.1rem;
    }
</style>

<div class="flex flex-col min-h-screen bg-slate-50 dark:bg-background-dark pb-20">

    <?php pwaPageHeader([
        'eyebrow' => $aktifEkip ? (string) $aktifEkip['tur_adi'] : 'Ekip tanımlı değil',
        'eyebrow_id' => 'aparat-ekip-satiri',
        'title' => 'Aparat Takip',
        'subtitle' => $uyeAdlari !== '' ? $uyeAdlari : 'Personel atanmamış',
        'subtitle_id' => 'aparat-uye-satiri',
        'actions' => '<span class="pwa-topbar__chip">Bugün<strong id="aparat-bugun-sayi">0 işlem</strong></span>'
            . ($aktifEkip && !empty($aparatTipleri) ? '<button type="button" class="apf-yardim-btn" onclick="aparatAnlatimAc()" aria-label="Aparat nasıl takip edilir?"><span class="material-symbols-outlined">help</span></button>' : ''),
    ]); ?>

    <?php if (!$aktifEkip): ?>
        <section class="px-4 pt-6">
            <div class="bg-white dark:bg-card-dark rounded-xl p-6 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">group_off</span>
                <p class="text-sm font-bold text-slate-800 dark:text-white mt-3">Ekibiniz tanımlı değil</p>
                <p class="text-xs text-slate-400 mt-2">Aparat kaydı girebilmek için bir ekibe atanmanız gerekiyor. Şefinizle görüşün.</p>
            </div>
        </section>
    <?php elseif (empty($aparatTipleri)): ?>
        <section class="px-4 pt-6">
            <div class="bg-white dark:bg-card-dark rounded-xl p-6 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">inventory_2</span>
                <p class="text-sm font-bold text-slate-800 dark:text-white mt-3">Aparat tipi tanımlanmamış</p>
                <p class="text-xs text-slate-400 mt-2">Yönetici panelden aparat tiplerini tanımladıktan sonra kayıt girebilirsiniz.</p>
            </div>
        </section>
    <?php else: ?>

        <section id="aparat-kuyruk-serit" class="px-4 pt-4" style="display:none">
            <div class="bg-amber-50 dark:bg-slate-800 border border-amber-200 dark:border-slate-800 rounded-xl p-3 flex items-center justify-between gap-3">
                <div class="flex-1">
                    <p class="text-xs font-bold text-amber-700 dark:text-amber-400" id="aparat-kuyruk-baslik"></p>
                    <p class="text-xs text-slate-500 mt-1" id="aparat-kuyruk-alt"></p>
                </div>
                <button type="button" onclick="aparatKuyrugunuGonder()"
                    class="shrink-0 px-3 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Şimdi Gönder</button>
            </div>
        </section>

        <section class="px-4 pt-4 mb-3">
            <div class="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
                <button onclick="aparatSekme('ekibim', this)" id="aparat-tab-ekibim"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-primary bg-white dark:bg-card-dark">Ekibim</button>
                <button onclick="aparatSekme('transfer', this)" id="aparat-tab-transfer"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500 relative">
                    Transfer
                    <span id="aparat-transfer-rozet" class="aparat-rozet" style="display:none">0</span>
                </button>
                <button onclick="aparatSekme('gecmis', this)" id="aparat-tab-gecmis"
                    class="aparat-tab flex-1 py-2 rounded-xl text-xs font-bold text-slate-500">Geçmiş</button>
            </div>
        </section>

        <!-- ============ EKİBİM ============ -->
        <section id="aparat-pane-ekibim" class="px-4 flex-1">
            <button type="button" class="apf-tanitim" onclick="aparatAnlatimAc()">
                <span class="apf-tanitim__ikon"><span class="material-symbols-outlined">play_circle</span></span>
                <span class="apf-tanitim__metin">
                    <strong>Aparat nasıl takip edilir?</strong>
                    <small>Zimmetin ne zaman düşer, ne zaman artar? 30 saniyede izle.</small>
                </span>
                <span class="material-symbols-outlined apf-tanitim__ok">chevron_right</span>
            </button>
            <div class="space-y-3" id="aparat-stok-listesi">
                <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
            </div>
        </section>

        <!-- ============ TRANSFER ============ -->
        <section id="aparat-pane-transfer" class="px-4 flex-1" style="display:none">
            <button type="button" onclick="aparatTransferModalAc()"
                class="w-full py-3 rounded-xl bg-primary text-white font-bold text-sm mb-4 flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-lg">send</span>
                Aparat Gönder
            </button>
            <div class="space-y-3" id="aparat-transfer-listesi">
                <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
            </div>
        </section>

        <!-- ============ GEÇMİŞ ============ -->
        <section id="aparat-pane-gecmis" class="px-4 flex-1" style="display:none">
            <div class="space-y-3" id="aparat-gecmis-listesi">
                <div class="text-center py-10 text-sm text-slate-400">Yükleniyor...</div>
            </div>
        </section>

        <button type="button" class="aparat-fab" onclick="aparatSheetAc()" aria-label="Yeni işlem">
            <span class="material-symbols-outlined text-3xl">add</span>
        </button>

        <div id="apf-katman" class="aparat-sheet-katman" role="dialog" aria-modal="true" aria-labelledby="apf-pencere-baslik">
            <div class="aparat-sheet apf-pencere">
                <div class="aparat-sheet-tutamak"></div>
                <div class="apf-pencere__ust">
                    <div>
                        <h2 id="apf-pencere-baslik">Aparat Nasıl Takip Edilir?</h2>
                        <p>Aparatın depodan aboneye yolculuğu</p>
                    </div>
                    <button type="button" class="apf-kapat" onclick="aparatAnlatimKapat()" aria-label="Kapat">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="apf" id="apf">
                    <div class="apf-sahne" aria-hidden="true">
                        <svg class="apf-yollar" viewBox="0 0 100 100" preserveAspectRatio="none">
                            <line data-yol="tedarik-depo" x1="10" y1="50" x2="30" y2="50"></line>
                            <line data-yol="depo-ekipA" x1="30" y1="50" x2="58" y2="22"></line>
                            <line data-yol="ekipA-abone" x1="58" y1="22" x2="87" y2="22"></line>
                            <line data-yol="abone-ekipB" x1="87" y1="22" x2="58" y2="80"></line>
                            <line data-yol="ekipB-ekipA" x1="58" y1="80" x2="58" y2="22"></line>
                            <line data-yol="ekipA-depo" x1="58" y1="22" x2="30" y2="50"></line>
                        </svg>
                        <div class="apf-dugum" data-dugum="tedarik" style="left:10%;top:50%">
                            <span class="apf-ikon apf-ikon--tedarik"><span class="material-symbols-outlined">shopping_cart</span></span>
                            <span class="apf-ad">Satın alma</span>
                        </div>
                        <div class="apf-dugum" data-dugum="depo" style="left:30%;top:50%">
                            <span class="apf-ikon apf-ikon--depo"><span class="material-symbols-outlined">warehouse</span></span>
                            <span class="apf-ad">Depo</span>
                            <span class="apf-sayi" data-sayi="depo">3</span>
                        </div>
                        <div class="apf-dugum" data-dugum="ekipA" style="left:58%;top:22%">
                            <span class="apf-ikon apf-ikon--sen"><span class="material-symbols-outlined">person</span></span>
                            <span class="apf-ad">Sen</span>
                            <span class="apf-sayi" data-sayi="ekipA">0</span>
                        </div>
                        <div class="apf-dugum" data-dugum="ekipB" style="left:58%;top:80%">
                            <span class="apf-ikon apf-ikon--ekip"><span class="material-symbols-outlined">group</span></span>
                            <span class="apf-ad">Diğer ekip</span>
                            <span class="apf-sayi" data-sayi="ekipB">0</span>
                        </div>
                        <div class="apf-dugum" data-dugum="abone" style="left:87%;top:22%">
                            <span class="apf-ikon apf-ikon--abone"><span class="material-symbols-outlined">home</span></span>
                            <span class="apf-ad">Abone</span>
                            <span class="apf-sayi" data-sayi="abone">0</span>
                        </div>
                        <div class="apf-aparat" id="apf-aparat" style="left:10%;top:50%"><span class="material-symbols-outlined">package_2</span></div>
                    </div>

                    <div class="apf-anlatim" aria-live="polite">
                        <span class="apf-kim" id="apf-kim">Depo yapar</span>
                        <p class="apf-baslik" id="apf-baslik">1. Depoya Giriş</p>
                        <p class="apf-metin" id="apf-metin"></p>
                    </div>

                    <div class="apf-kontrol">
                        <button type="button" class="apf-btn" id="apf-geri" aria-label="Önceki adım"><span class="material-symbols-outlined">chevron_left</span></button>
                        <button type="button" class="apf-btn apf-btn--ana" id="apf-oynat" aria-label="Duraklat"><span class="material-symbols-outlined">pause</span></button>
                        <button type="button" class="apf-btn" id="apf-ileri" aria-label="Sonraki adım"><span class="material-symbols-outlined">chevron_right</span></button>
                        <div class="apf-adimlar" id="apf-adimlar" role="tablist" aria-label="Adımlar"></div>
                    </div>
                </div>

                <div class="apf-ozet">
                    <div class="apf-ozet__satir"><span class="apf-etiket apf-etiket--sen">Sen yaparsın</span><span>Aparat gönderme ve gelen aparatı onaylama; kesme ve açma online sorgulamadan otomatik işlenir</span></div>
                    <div class="apf-ozet__satir"><span class="apf-etiket apf-etiket--depo">Depo yapar</span><span>Sana aparat verme, iade alma ve bozuk aparatı hurdaya ayırma</span></div>
                </div>

                <button type="button" class="apf-tamam" onclick="aparatAnlatimKapat()">Anladım</button>
            </div>
        </div>

        <!-- ============ YENİ İŞLEM (bottom sheet) ============ -->
        <div id="aparat-sheet-katman" class="aparat-sheet-katman">
            <div class="aparat-sheet">
                <div class="aparat-sheet-tutamak"></div>

                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-slate-800 dark:text-white">Yeni İşlem</h2>
                    <button type="button" onclick="aparatSheetKapat()" class="text-slate-400">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <form id="aparat-form" class="space-y-4">

                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" onclick="aparatIslemSec('kesme')" id="aparat-btn-kesme"
                            class="aparat-segment aktif-kesme">
                            <span class="material-symbols-outlined text-2xl">water_drop</span>
                            KESME
                        </button>
                        <button type="button" onclick="aparatIslemSec('acma')" id="aparat-btn-acma"
                            class="aparat-segment">
                            <span class="material-symbols-outlined text-2xl">water_full</span>
                            AÇMA
                        </button>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4 space-y-3">
                        <div>
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Abone No</label>
                            <input type="text" name="abone_no" id="aparat-abone-no" inputmode="numeric" required
                                class="w-full mt-1 px-3 py-3 rounded-xl bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 text-base font-semibold text-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Sayaç No</label>
                            <input type="text" name="sayac_no" id="aparat-sayac-no"
                                class="w-full mt-1 px-3 py-3 rounded-xl bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 text-base font-semibold text-slate-800 dark:text-white">
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
                        <div class="flex items-center justify-between mb-3">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat Tipi</label>
                            <label class="flex items-center gap-2 text-xs font-bold text-slate-500">
                                <input type="checkbox" id="aparat-yok" class="rounded" style="width:1rem;height:1rem">
                                Aparat kullanılmadı
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-2" id="aparat-tip-listesi">
                            <?php foreach ($aparatTipleri as $tip): ?>
                                <button type="button" class="aparat-secenek aparat-tip-btn"
                                    data-id="<?= (int) $tip['id'] ?>" onclick="aparatTipSec(this)">
                                    <span><?= htmlspecialchars($tip['ad'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="font-normal aparat-tip-stok" data-stok-id="<?= (int) $tip['id'] ?>">-</span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex items-center justify-between mt-4" id="aparat-adet-satiri">
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Adet</span>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="aparatAdet(-1)"
                                    class="w-9 h-9 rounded-xl bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 font-bold">−</button>
                                <span class="text-lg font-bold text-slate-800 dark:text-white w-8 text-center" id="aparat-adet">1</span>
                                <button type="button" onclick="aparatAdet(1)"
                                    class="w-9 h-9 rounded-xl bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 font-bold">+</button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4" id="aparat-durum-kutusu" style="display:none">
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat geri alındı mı?</label>
                        <div class="space-y-2 mt-2">
                            <?php $ilk = true; foreach ($aparatDurumlari as $anahtar => $etiket): ?>
                                <button type="button" class="aparat-secenek aparat-durum-btn<?= $ilk ? ' secili' : '' ?>"
                                    data-durum="<?= htmlspecialchars($anahtar, ENT_QUOTES, 'UTF-8') ?>" onclick="aparatDurumSec(this)">
                                    <?= htmlspecialchars($etiket, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php $ilk = false; endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" id="aparat-sayac-foto-kutu">
                            <span class="material-symbols-outlined text-3xl text-slate-400">speed</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Sayaç Fotoğrafı</span>
                            <span class="text-xs text-slate-400" id="aparat-sayac-foto-durum">Zorunlu</span>
                            <input type="file" id="aparat-sayac-foto" accept="image/*" capture="environment" class="hidden">
                        </label>
                        <label class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" id="aparat-foto-kutu">
                            <span class="material-symbols-outlined text-3xl text-slate-400">build</span>
                            <span class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat Fotoğrafı</span>
                            <span class="text-xs text-slate-400" id="aparat-foto-durum">Zorunlu</span>
                            <input type="file" id="aparat-foto" accept="image/*" capture="environment" class="hidden">
                        </label>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Açıklama</label>
                        <textarea name="aciklama" id="aparat-aciklama" rows="2"
                            class="w-full mt-1 px-3 py-2 rounded-xl bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-800 text-sm text-slate-800 dark:text-white"></textarea>
                    </div>

                    <button type="submit" id="aparat-submit-btn"
                        class="w-full py-4 rounded-xl bg-primary text-white font-bold text-sm">
                        <span id="aparat-submit-text">KAYDET</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- ============ TRANSFER (bottom sheet) ============ -->
        <div id="aparat-transfer-katman" class="aparat-sheet-katman">
            <div class="aparat-sheet">
                <div class="aparat-sheet-tutamak"></div>

                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-bold text-slate-800 dark:text-white">Aparat Gönder</h2>
                    <button type="button" onclick="aparatTransferModalKapat()" class="text-slate-400">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Alan Ekip</label>
                        <select id="aparat-transfer-ekip"
                            class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-sm font-semibold text-slate-800 dark:text-white"></select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Aparat Tipi</label>
                        <select id="aparat-transfer-tip"
                            class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-sm font-semibold text-slate-800 dark:text-white">
                            <?php foreach ($aparatTipleri as $tip): ?>
                                <option value="<?= (int) $tip['id'] ?>"><?= htmlspecialchars($tip['ad'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-bold text-slate-500 dark:text-slate-400">Adet</label>
                        <input type="number" id="aparat-transfer-adet" min="1" value="1"
                            class="w-full mt-1 px-3 py-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 text-base font-semibold text-slate-800 dark:text-white">
                        <p class="text-xs font-semibold text-slate-400 mt-1" id="aparat-transfer-elde"></p>
                    </div>

                    <button type="button" onclick="aparatTransferGonder()" id="aparat-transfer-btn"
                        class="w-full py-3 rounded-xl bg-primary text-white font-bold text-sm">GÖNDER</button>
                    <p class="text-xs text-slate-400 text-center">Aparatlar karşı ekip onayladığında stoğunuzdan düşer.</p>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<?php if ($aktifEkip && !empty($aparatTipleri)): ?>
<script>
    (function () {
        'use strict';

        const APARAT_REF = 'aparat-referans';
        let aparatIslemTipi = 'kesme';
        let aparatTipId = 0;
        let aparatDurumu = 'alindi';
        let aparatAdedi = 1;
        let aparatSayacDosya = null;
        let aparatDosya = null;
        let aparatBilgi = null;
        let aparatKonum = null;

        function cevrimici() {
            return !window.OfflineQueue || OfflineQueue.cevrimici();
        }

        function kacir(deger) {
            const d = document.createElement('div');
            d.textContent = deger === null || deger === undefined ? '' : deger;
            return d.innerHTML;
        }

        // ----- Sekmeler -----
        window.aparatSekme = function (ad, btn) {
            ['ekibim', 'transfer', 'gecmis'].forEach(function (s) {
                const el = document.getElementById('aparat-pane-' + s);
                if (el) el.style.display = s === ad ? '' : 'none';
            });

            document.querySelectorAll('.aparat-tab').forEach(function (t) {
                const ekstra = t.id === 'aparat-tab-transfer' ? ' relative' : '';
                t.className = 'aparat-tab flex-1 py-2 rounded-xl text-xs font-bold '
                    + (t === btn ? 'text-primary bg-white dark:bg-card-dark' : 'text-slate-500')
                    + ekstra;
            });

            if (ad === 'ekibim') aparatStokYukle();
            if (ad === 'transfer') aparatTransferleriYukle();
            if (ad === 'gecmis') aparatGecmisYukle();
        };

        // ----- Bottom sheet -----
        window.aparatSheetAc = function () {
            document.getElementById('aparat-sheet-katman').classList.add('acik');
            document.body.style.overflow = 'hidden';
            konumAl();
        };

        window.aparatSheetKapat = function () {
            document.getElementById('aparat-sheet-katman').classList.remove('acik');
            document.body.style.overflow = '';
        };

        document.getElementById('aparat-sheet-katman').addEventListener('click', function (e) {
            if (e.target === this) aparatSheetKapat();
        });

        // ----- Form durumu -----
        window.aparatIslemSec = function (tip) {
            aparatIslemTipi = tip;

            document.getElementById('aparat-btn-kesme').className =
                'aparat-segment' + (tip === 'kesme' ? ' aktif-kesme' : '');
            document.getElementById('aparat-btn-acma').className =
                'aparat-segment' + (tip === 'acma' ? ' aktif-acma' : '');

            aparatDurumKutusu();
        };

        function aparatDurumKutusu() {
            const goster = aparatIslemTipi === 'acma' && !document.getElementById('aparat-yok').checked;
            document.getElementById('aparat-durum-kutusu').style.display = goster ? '' : 'none';
        }

        window.aparatTipSec = function (btn) {
            aparatTipId = parseInt(btn.dataset.id, 10);

            document.querySelectorAll('.aparat-tip-btn').forEach(function (b) {
                b.classList.remove('secili');
            });
            btn.classList.add('secili');
        };

        window.aparatDurumSec = function (btn) {
            aparatDurumu = btn.dataset.durum;

            document.querySelectorAll('.aparat-durum-btn').forEach(function (b) {
                b.classList.remove('secili');
            });
            btn.classList.add('secili');
        };

        window.aparatAdet = function (fark) {
            aparatAdedi = Math.max(1, aparatAdedi + fark);
            document.getElementById('aparat-adet').textContent = aparatAdedi;
        };

        document.getElementById('aparat-yok').addEventListener('change', function () {
            const kapali = this.checked;
            document.getElementById('aparat-tip-listesi').style.opacity = kapali ? '0.4' : '1';
            document.getElementById('aparat-adet-satiri').style.display = kapali ? 'none' : '';
            document.getElementById('aparat-foto-kutu').style.display = kapali ? 'none' : '';
            aparatDurumKutusu();
        });

        // ----- Fotoğraflar -----
        function fotoBagla(inputId, durumId, atayici) {
            const input = document.getElementById(inputId);
            input.addEventListener('change', function () {
                const dosya = this.files[0];
                atayici(dosya || null);
                document.getElementById(durumId).textContent = dosya ? 'Eklendi ✓' : 'Zorunlu';
            });
        }

        fotoBagla('aparat-sayac-foto', 'aparat-sayac-foto-durum', function (d) { aparatSayacDosya = d; });
        fotoBagla('aparat-foto', 'aparat-foto-durum', function (d) { aparatDosya = d; });

        // ----- Konum -----
        function konumAl() {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(function (p) {
                aparatKonum = { enlem: p.coords.latitude.toFixed(7), boylam: p.coords.longitude.toFixed(7) };
            }, function () { }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
        }

        // ----- Referans veri -----
        async function aparatBilgiYukle() {
            if (!cevrimici() && window.OfflineQueue) {
                const saklanan = await OfflineQueue.referansOku(APARAT_REF);
                if (saklanan) {
                    aparatBilgi = saklanan;
                    aparatBilgiUygula();
                    return;
                }
            }

            try {
                const res = await (await fetch('api.php?action=getAparatBilgi', { credentials: 'same-origin' })).json();
                if (!res.success) return;

                aparatBilgi = res.data;
                aparatBilgiUygula();

                if (window.OfflineQueue) {
                    OfflineQueue.referansKaydet(APARAT_REF, res.data);
                }
            } catch (e) {
                console.error('Aparat bilgisi alınamadı:', e);
            }
        }

        function aparatBilgiUygula() {
            if (!aparatBilgi) return;

            (aparatBilgi.tipler || []).forEach(function (t) {
                const el = document.querySelector(`[data-stok-id="${t.id}"]`);
                if (el) el.textContent = t.adet + ' adet';
            });

            if (aparatBilgi.ekip && aparatBilgi.ekip.uye_metni) {
                document.getElementById('aparat-uye-satiri').textContent = aparatBilgi.ekip.uye_metni;
            }

            const rozet = document.getElementById('aparat-transfer-rozet');
            const bekleyen = aparatBilgi.bekleyen_transfer || 0;
            rozet.textContent = bekleyen;
            rozet.style.display = bekleyen > 0 ? '' : 'none';

            const secim = document.getElementById('aparat-transfer-ekip');
            if (secim && !secim.options.length) {
                (aparatBilgi.ekipler || []).forEach(function (e) {
                    const opt = document.createElement('option');
                    opt.value = e.id;
                    opt.textContent = e.ad;
                    secim.appendChild(opt);
                });
            }
        }

        // ----- Ekip stoğu -----
        function aparatStokYukle() {
            const kutu = document.getElementById('aparat-stok-listesi');
            if (!aparatBilgi) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Bilgi yüklenemedi.</div>';
                return;
            }

            const uyeler = (aparatBilgi.ekip.uyeler || []);
            let uyeHtml = '';
            uyeler.forEach(function (u) {
                uyeHtml += `<span class="text-xs font-semibold text-slate-600 dark:text-slate-300">${kacir(u.ad)}${u.sef ? ' (şef)' : ''}</span>`;
            });

            let html = `<div class="bg-white dark:bg-card-dark rounded-xl p-4 mb-3">
                <p class="text-xs text-slate-400">Ekipte çalışanlar</p>
                <div class="flex flex-col gap-1 mt-1">${uyeHtml || '<span class="text-xs text-slate-400">Personel atanmamış</span>'}</div>
                <p class="text-xs text-slate-400 mt-2">${kacir(aparatBilgi.ekip.ad)}</p>
            </div>`;

            const kritikTipler = (aparatBilgi.tipler || []).filter(function (t) { return t.kritik; });
            if (kritikTipler.length) {
                const satirlar = kritikTipler.map(function (t) {
                    return `<p class="text-xs text-slate-500 mt-1">${kacir(t.ad)}: elinde ${t.adet} adet kaldı (sınır ${t.kritik_esik})</p>`;
                }).join('');
                html += `<div class="bg-amber-50 dark:bg-slate-800 border border-amber-200 dark:border-slate-800 rounded-xl p-3 mb-3">
                    <p class="text-xs font-bold text-amber-700 dark:text-amber-400">Aparatın azaldı, depodan almalısın</p>
                    ${satirlar}
                </div>`;
            }

            (aparatBilgi.tipler || []).forEach(function (t) {
                const negatif = t.adet < 0;
                const renk = negatif || (t.kritik && t.adet <= 0) ? 'text-red-500' : (t.kritik ? 'text-amber-600' : 'text-primary');
                const cerceve = t.kritik ? ' style="box-shadow: inset 0 0 0 1px #f59e0b;"' : '';
                html += `<div class="bg-white dark:bg-card-dark rounded-xl p-4 flex items-center justify-between"${cerceve}>
                    <div>
                        <p class="text-sm font-bold text-slate-800 dark:text-white">${kacir(t.ad)}</p>
                        <p class="text-xs text-slate-400 mt-1">${kacir(t.kod)}${t.kritik ? ' · azaldı' : ''}</p>
                    </div>
                    <p class="text-xl font-bold ${renk}">${t.adet}</p>
                </div>`;
            });

            kutu.innerHTML = html;
        }

        // ----- Transferler -----
        async function aparatTransferleriYukle() {
            const kutu = document.getElementById('aparat-transfer-listesi');

            if (!cevrimici()) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Transferleri görmek için bağlantı gerekli.</div>';
                return;
            }

            try {
                const res = await (await fetch('api.php?action=getAparatTransferler', { credentials: 'same-origin' })).json();
                if (!res.success) {
                    kutu.innerHTML = `<div class="text-center py-10 text-sm text-slate-400">${kacir(res.message)}</div>`;
                    return;
                }

                const liste = res.data.transferler || [];
                if (!liste.length) {
                    kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Transfer kaydı yok.</div>';
                    return;
                }

                let html = '';
                liste.forEach(function (t) {
                    const durumRenk = t.durum === 'beklemede' ? 'text-amber-600'
                        : (t.durum === 'onaylandi' ? 'text-emerald-600' : 'text-red-500');
                    const durumMetin = t.durum === 'beklemede' ? 'Bekliyor'
                        : (t.durum === 'onaylandi' ? 'Onaylandı' : (t.durum === 'reddedildi' ? 'Reddedildi' : 'İptal'));

                    html += `<div class="bg-white dark:bg-card-dark rounded-xl p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-bold text-slate-800 dark:text-white">
                                    ${t.yon === 'gelen' ? 'Gelen' : 'Giden'} · ${kacir(t.karsi_ekip)}
                                </p>
                                <p class="text-xs text-slate-400 mt-1">${kacir(t.aparat_adi)} · ${t.adet} adet</p>
                            </div>
                            <span class="text-xs font-bold ${durumRenk}">${durumMetin}</span>
                        </div>`;

                    if (t.onaylanabilir) {
                        html += `<div class="grid grid-cols-2 gap-2 mt-3">
                            <button type="button" onclick="aparatTransferYanit(${t.id}, 'onayla')"
                                class="py-2 rounded-xl bg-primary text-white text-xs font-bold">Onayla</button>
                            <button type="button" onclick="aparatTransferYanit(${t.id}, 'reddet')"
                                class="py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-bold">Reddet</button>
                        </div>`;
                    }

                    if (t.red_nedeni) {
                        html += `<p class="text-xs text-slate-400 mt-2">${kacir(t.red_nedeni)}</p>`;
                    }

                    html += '</div>';
                });

                kutu.innerHTML = html;
            } catch (e) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Transferler yüklenemedi.</div>';
            }
        }

        window.aparatTransferYanit = async function (id, karar) {
            if (karar === 'reddet') {
                const neden = prompt('Red gerekçesi:');
                if (!neden || !neden.trim()) return;

                await aparatTransferIstek({ id: id, karar: 'reddet', red_nedeni: neden.trim() });
                return;
            }

            const onay = await Alert.confirm('Transfer Onayı', 'Aparatlar ekibinizin stoğuna eklenecek. Onaylıyor musunuz?');
            if (!onay) return;

            await aparatTransferIstek({ id: id, karar: 'onayla' });
        };

        async function aparatTransferIstek(veri) {
            const fd = new FormData();
            fd.append('action', 'cevaplaAparatTransfer');
            Object.keys(veri).forEach(function (k) { fd.append(k, veri[k]); });

            try {
                const res = await (await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' })).json();
                if (!res.success) return Alert.error('Hata', res.message || 'İşlem tamamlanamadı.');

                Alert.success('Tamam', res.message);
                await aparatBilgiYukle();
                aparatTransferleriYukle();
            } catch (e) {
                Alert.error('Hata', 'Sunucuya ulaşılamadı.');
            }
        }

        function gonderilebilirAdet() {
            const tipId = parseInt(document.getElementById('aparat-transfer-tip').value, 10);
            const tip = ((aparatBilgi && aparatBilgi.tipler) || []).find(function (t) { return t.id === tipId; });
            if (!tip) return null;
            return typeof tip.gonderilebilir === 'number' ? tip.gonderilebilir : Math.max(0, tip.adet);
        }

        function transferEldeGoster() {
            const kutu = document.getElementById('aparat-transfer-elde');
            const girdi = document.getElementById('aparat-transfer-adet');
            const limit = gonderilebilirAdet();
            if (limit === null) {
                kutu.textContent = '';
                girdi.removeAttribute('max');
                return;
            }
            girdi.max = limit;
            kutu.textContent = limit > 0
                ? 'Zimmetinden en fazla ' + limit + ' adet gönderebilirsin.'
                : 'Zimmetinde gönderilebilecek bu aparat yok.';
            kutu.className = 'text-xs font-semibold mt-1 ' + (limit > 0 ? 'text-slate-400' : 'text-amber-600');
        }

        document.getElementById('aparat-transfer-tip').addEventListener('change', transferEldeGoster);

        window.aparatTransferModalAc = function () {
            transferEldeGoster();
            document.getElementById('aparat-transfer-katman').classList.add('acik');
            document.body.style.overflow = 'hidden';
        };

        window.aparatTransferModalKapat = function () {
            document.getElementById('aparat-transfer-katman').classList.remove('acik');
            document.body.style.overflow = '';
        };

        document.getElementById('aparat-transfer-katman').addEventListener('click', function (e) {
            if (e.target === this) aparatTransferModalKapat();
        });

        window.aparatTransferGonder = async function () {
            const ekip = document.getElementById('aparat-transfer-ekip').value;
            const tip = document.getElementById('aparat-transfer-tip').value;
            const adet = parseInt(document.getElementById('aparat-transfer-adet').value, 10);

            if (!ekip) return Alert.warning('Ekip Seçin', 'Aparatı göndereceğiniz ekibi seçin.');
            if (!adet || adet < 1) return Alert.warning('Adet Hatalı', 'En az 1 adet girmelisiniz.');
            const limit = gonderilebilirAdet();
            if (limit !== null && adet > limit) {
                return Alert.warning('Zimmet Yetersiz', limit > 0
                    ? 'Zimmetinden en fazla ' + limit + ' adet gönderebilirsin.'
                    : 'Zimmetinde gönderilebilecek bu aparat yok.');
            }

            const btn = document.getElementById('aparat-transfer-btn');
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', 'saveAparatTransfer');
            fd.append('alan_ekip_id', ekip);
            fd.append('aparat_tip_id', tip);
            fd.append('adet', adet);

            try {
                const res = await (await fetch('api.php', { method: 'POST', body: fd, credentials: 'same-origin' })).json();
                if (!res.success) return Alert.error('Gönderilemedi', res.message || 'İşlem başarısız.');

                aparatTransferModalKapat();
                Alert.success('Gönderildi', res.message);
                aparatTransferleriYukle();
                aparatBilgiYukle();
            } catch (e) {
                Alert.error('Hata', 'Sunucuya ulaşılamadı.');
            } finally {
                btn.disabled = false;
            }
        };

        // ----- Geçmiş -----
        async function aparatGecmisYukle() {
            const kutu = document.getElementById('aparat-gecmis-listesi');

            if (!cevrimici()) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Geçmişi görmek için bağlantı gerekli.</div>';
                return;
            }

            try {
                const res = await (await fetch('api.php?action=getAparatSonIslemler&gun=7', { credentials: 'same-origin' })).json();
                if (!res.success) return;

                const liste = res.data.islemler || [];
                if (!liste.length) {
                    kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Son 7 günde kaydınız yok.</div>';
                    return;
                }

                let html = '';
                liste.forEach(function (k) {
                    const kesme = k.islem_tipi === 'kesme';
                    html += `<div class="bg-white dark:bg-card-dark rounded-xl p-4 flex items-center justify-between ${k.durum === 'iptal' ? 'opacity-50' : ''}">
                        <div>
                            <p class="text-sm font-bold ${kesme ? 'text-red-500' : 'text-emerald-600'}">
                                ${kesme ? 'Kesme' : 'Açma'} · ${kacir(k.abone_no || '-')}
                            </p>
                            <p class="text-xs text-slate-400 mt-1">${kacir(k.aparat_adi || '-')} · ${k.adet} adet</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-400">${(k.tarih || '').split('-').reverse().join('.')}</p>
                            <p class="text-xs text-slate-400">${kacir(k.saat)}</p>
                            ${k.negatif_stok == 1 ? '<p class="text-xs font-bold text-red-500">negatif stok</p>' : ''}
                        </div>
                    </div>`;
                });

                kutu.innerHTML = html;
                document.getElementById('aparat-bugun-sayi').textContent =
                    liste.filter(function (k) { return k.tarih === new Date().toISOString().slice(0, 10); }).length + ' işlem';
            } catch (e) {
                kutu.innerHTML = '<div class="text-center py-10 text-sm text-slate-400">Geçmiş yüklenemedi.</div>';
            }
        }

        // ----- Çevrimdışı kuyruk şeridi -----
        async function aparatKuyrukDurumu() {
            if (!window.OfflineQueue) return;

            const tumu = await OfflineQueue.listele();
            const bizim = tumu.filter(function (k) { return k.action === 'saveAparatIslem'; });
            const serit = document.getElementById('aparat-kuyruk-serit');

            if (!bizim.length) {
                serit.style.display = 'none';
                return;
            }

            const hatali = bizim.filter(function (k) { return k.durum === 'hata'; }).length;
            serit.style.display = '';
            document.getElementById('aparat-kuyruk-baslik').textContent =
                bizim.length + ' kayıt gönderilmeyi bekliyor';
            document.getElementById('aparat-kuyruk-alt').textContent = hatali > 0
                ? hatali + ' kayıt sunucu tarafından kabul edilmedi.'
                : 'Bağlantı geldiğinde otomatik gönderilecek.';
        }

        window.aparatKuyrugunuGonder = async function () {
            if (!window.OfflineQueue) return;
            await OfflineQueue.flush({ elle: true });
            await aparatKuyrukDurumu();
            await aparatBilgiYukle();
        };

        window.addEventListener('kuyruk-degisti', function () {
            aparatKuyrukDurumu();
            aparatBilgiYukle();
        });

        // ----- Kayıt gönderimi -----
        document.getElementById('aparat-form').addEventListener('submit', async function (e) {
            e.preventDefault();

            const aparatsiz = document.getElementById('aparat-yok').checked;
            const aboneNo = document.getElementById('aparat-abone-no').value.trim();

            if (!aboneNo) return Alert.warning('Abone No Gerekli', 'Abone numarasını girin.');
            if (!aparatsiz && !aparatTipId) return Alert.warning('Aparat Seçin', 'Kullanılan aparat tipini seçin.');
            if (!aparatSayacDosya) return Alert.warning('Fotoğraf Gerekli', 'Sayaç fotoğrafı zorunludur.');
            if (!aparatsiz && !aparatDosya) return Alert.warning('Fotoğraf Gerekli', 'Aparat fotoğrafı zorunludur.');
            if (!window.OfflineQueue) return Alert.error('Hata', 'Uygulama bileşenleri yüklenemedi, sayfayı yenileyin.');

            const btn = document.getElementById('aparat-submit-btn');
            const btnText = document.getElementById('aparat-submit-text');
            btn.disabled = true;
            btnText.textContent = 'HAZIRLANIYOR...';

            try {
                const alanlar = {
                    islem_tipi: aparatIslemTipi,
                    abone_no: aboneNo,
                    sayac_no: document.getElementById('aparat-sayac-no').value.trim(),
                    aparat_tip_id: aparatsiz ? 0 : aparatTipId,
                    adet: aparatsiz ? 1 : aparatAdedi,
                    aparatsiz: aparatsiz ? 1 : 0,
                    aparat_durumu: aparatIslemTipi === 'acma' ? aparatDurumu : '',
                    aciklama: document.getElementById('aparat-aciklama').value.trim(),
                };

                if (aparatKonum) {
                    alanlar.enlem = aparatKonum.enlem;
                    alanlar.boylam = aparatKonum.boylam;
                }

                const sayac = await OfflineQueue.fotografKucult(aparatSayacDosya, 1600, 0.7);
                const dosyalar = [{ alan: 'sayac_foto', ad: sayac.ad, tip: sayac.tip, blob: sayac.blob }];

                const ekDosyalar = [];
                if (!aparatsiz && aparatDosya) {
                    const ap = await OfflineQueue.fotografKucult(aparatDosya, 1600, 0.7);
                    ekDosyalar.push({ ad: ap.ad, tip: ap.tip, blob: ap.blob });
                }

                const ozet = {
                    islem_tipi: aparatIslemTipi,
                    abone_no: aboneNo,
                    adet: alanlar.adet,
                };

                btnText.textContent = 'GÖNDERİLİYOR...';

                await OfflineQueue.ekle('saveAparatIslem', alanlar, dosyalar, ozet, {
                    action: 'addAparatFoto',
                    alan: 'foto',
                    dosyalar: ekDosyalar,
                });

                const sonuc = await OfflineQueue.flush({ elle: true });
                aparatFormuSifirla();
                aparatSheetKapat();
                await aparatBilgiYukle();
                await aparatKuyrukDurumu();

                if (sonuc && sonuc.gonderildi > 0) {
                    Alert.success('Kaydedildi', 'İşlem sunucuya iletildi.');
                } else {
                    Alert.success('Kuyruğa Alındı', 'Kayıt telefonunuza yazıldı, bağlantı gelince gönderilecek.');
                }
            } catch (hata) {
                console.error('Aparat kaydı hatası:', hata);
                Alert.error('Kaydedilemedi', 'Kayıt telefona yazılamadı. Cihazınızda yer açıp tekrar deneyin.');
            } finally {
                btn.disabled = false;
                btnText.textContent = 'KAYDET';
            }
        });

        function aparatFormuSifirla() {
            document.getElementById('aparat-abone-no').value = '';
            document.getElementById('aparat-sayac-no').value = '';
            document.getElementById('aparat-aciklama').value = '';
            document.getElementById('aparat-sayac-foto').value = '';
            document.getElementById('aparat-foto').value = '';
            document.getElementById('aparat-sayac-foto-durum').textContent = 'Zorunlu';
            document.getElementById('aparat-foto-durum').textContent = 'Zorunlu';
            aparatSayacDosya = null;
            aparatDosya = null;
            aparatAdedi = 1;
            document.getElementById('aparat-adet').textContent = '1';
        }

        // OfflineQueue ve Alert sayfa gövdesinden sonra yükleniyor; ilk çağrılar
        // DOM hazır olduğunda yapılır ki kuyruk şeridi ilk açılışta da dolsun.
        document.querySelectorAll('.aparat-sheet-katman').forEach(function (katman) {
            document.body.appendChild(katman);
        });

        const aparatAnlatim = (function () {
            const kok = document.getElementById('apf');
            if (!kok) return null;

            const SURE = 4200;
            const hareketAzalt = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const BASLANGIC = { depo: 3, ekipA: 0, ekipB: 0, abone: 0 };
            const ADIMLAR = [
                { kim: 'depo', etiket: 'Depo yapar', baslik: 'Depoya Giriş', metin: 'Satın alınan aparat depoya girilir.', yol: 'tedarik-depo', kaynak: 'tedarik', hedef: 'depo', renk: '#2563eb' },
                { kim: 'depo', etiket: 'Depo yapar', baslik: 'Sana Aparat Verilir', metin: 'Depo sana aparat verir, zimmetine geçer. Ekibim sekmesinde elindeki adedi görürsün.', yol: 'depo-ekipA', kaynak: 'depo', hedef: 'ekipA', renk: '#d97706' },
                { kim: 'ekip', etiket: 'Otomatik · online sorgulama', baslik: 'Kesme', metin: 'Aparatla kestiğin iş online sorgulamaya düşünce aparat zimmetinden kendiliğinden düşer, abonede takılı olur. Telefondan da kayıt girersen iki kez düşmez.', yol: 'ekipA-abone', kaynak: 'ekipA', hedef: 'abone', renk: '#dc2626' },
                { kim: 'ekip', etiket: 'Otomatik · online sorgulama', baslik: 'Açma', metin: 'Abone açılınca aparat açan ekibin zimmetine kendiliğinden döner. Hasarlı çıksa bile zimmete geçer; bozuk aparatı depoya getir.', yol: 'abone-ekipB', kaynak: 'abone', hedef: 'ekipB', renk: '#059669' },
                { kim: 'sen', etiket: 'Telefondan · onaylı', baslik: 'Ekipler Arası Değişim', metin: 'Diğer ekip Transfer sekmesinden sana aparat gönderir. Sen Onayla deyince zimmetine geçer. Kimse elinde olmayanı gönderemez.', yol: 'ekipB-ekipA', kaynak: 'ekipB', hedef: 'ekipA', renk: '#7c3aed' },
                { kim: 'depo', etiket: 'Depo yapar', baslik: 'İade', metin: 'Kullanmadığın ya da bozulan aparatı depoya teslim edersin. Depo İade Al girince zimmetinden düşer; bozuksa hurdaya depo ayırır, sen ayıramazsın.', yol: 'ekipA-depo', kaynak: 'ekipA', hedef: 'depo', renk: '#0891b2' }
            ];

            const aparat = document.getElementById('apf-aparat');
            const adimKutu = document.getElementById('apf-adimlar');
            const oynatBtn = document.getElementById('apf-oynat');
            let sira = 0;
            let calisiyor = false;
            let zamanlayici = null;
            let sayilar = {};

            ADIMLAR.forEach(function (adim, i) {
                const b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('role', 'tab');
                b.setAttribute('aria-label', (i + 1) + '. adım: ' + adim.baslik);
                b.addEventListener('click', function () { git(i); });
                adimKutu.appendChild(b);
            });

            function konum(ad) {
                const d = kok.querySelector('[data-dugum="' + ad + '"]');
                return { left: d.style.left, top: d.style.top };
            }

            function sayilariYaz(degisen) {
                Object.keys(sayilar).forEach(function (k) {
                    const el = kok.querySelector('[data-sayi="' + k + '"]');
                    if (!el) return;
                    el.textContent = sayilar[k];
                    el.classList.remove('artti', 'azaldi');
                    if (degisen && degisen[k]) {
                        void el.offsetWidth;
                        el.classList.add(degisen[k]);
                    }
                });
            }

            function git(n) {
                clearTimeout(zamanlayici);
                sira = (n + ADIMLAR.length) % ADIMLAR.length;
                const adim = ADIMLAR[sira];
                kok.style.setProperty('--apf-renk', adim.renk);
                kok.style.setProperty('--apf-sure', SURE + 'ms');

                sayilar = Object.assign({}, BASLANGIC);
                for (let i = 0; i < sira; i++) {
                    if (ADIMLAR[i].kaynak in sayilar) sayilar[ADIMLAR[i].kaynak]--;
                    if (ADIMLAR[i].hedef in sayilar) sayilar[ADIMLAR[i].hedef]++;
                }
                sayilariYaz();

                const kim = document.getElementById('apf-kim');
                kim.textContent = adim.etiket;
                kim.className = 'apf-kim ' + adim.kim;
                document.getElementById('apf-baslik').textContent = (sira + 1) + '. ' + adim.baslik;
                document.getElementById('apf-metin').textContent = adim.metin;

                kok.querySelectorAll('.apf-yollar line').forEach(function (l) {
                    l.classList.toggle('aktif', l.getAttribute('data-yol') === adim.yol);
                });
                kok.querySelectorAll('.apf-dugum').forEach(function (d) {
                    const ad = d.getAttribute('data-dugum');
                    d.classList.toggle('vurgu', ad === adim.kaynak || ad === adim.hedef);
                });
                Array.prototype.forEach.call(adimKutu.children, function (b, i) {
                    b.classList.remove('aktif', 'bitti');
                    if (i < sira) b.classList.add('bitti');
                    b.setAttribute('aria-selected', i === sira ? 'true' : 'false');
                });
                void adimKutu.children[sira].offsetWidth;
                adimKutu.children[sira].classList.add('aktif');

                const bas = konum(adim.kaynak);
                const son = konum(adim.hedef);
                aparat.style.transition = 'none';
                aparat.style.left = bas.left;
                aparat.style.top = bas.top;
                void aparat.offsetWidth;
                aparat.style.transition = '';

                const vardi = function () {
                    const degisen = {};
                    if (adim.kaynak in sayilar) { sayilar[adim.kaynak]--; degisen[adim.kaynak] = 'azaldi'; }
                    if (adim.hedef in sayilar) { sayilar[adim.hedef]++; degisen[adim.hedef] = 'artti'; }
                    sayilariYaz(degisen);
                };

                if (hareketAzalt) {
                    aparat.style.left = son.left;
                    aparat.style.top = son.top;
                    vardi();
                    return;
                }
                zamanlayici = setTimeout(function () {
                    aparat.style.left = son.left;
                    aparat.style.top = son.top;
                    zamanlayici = setTimeout(function () {
                        vardi();
                        if (calisiyor) zamanlayici = setTimeout(function () { git(sira + 1); }, SURE - 1550);
                    }, 1250);
                }, 300);
            }

            function oynatDurumu(acik) {
                calisiyor = acik && !hareketAzalt;
                kok.classList.toggle('durdu', !calisiyor);
                oynatBtn.innerHTML = '<span class="material-symbols-outlined">' + (calisiyor ? 'pause' : 'play_arrow') + '</span>';
                oynatBtn.setAttribute('aria-label', calisiyor ? 'Duraklat' : 'Oynat');
            }

            oynatBtn.addEventListener('click', function () {
                oynatDurumu(!calisiyor);
                git(calisiyor ? sira + 1 : sira);
            });
            document.getElementById('apf-ileri').addEventListener('click', function () { oynatDurumu(false); git(sira + 1); });
            document.getElementById('apf-geri').addEventListener('click', function () { oynatDurumu(false); git(sira - 1); });

            return {
                baslat: function () { oynatDurumu(true); git(0); },
                durdur: function () { clearTimeout(zamanlayici); oynatDurumu(false); }
            };
        })();

        const ANLATIM_ANAHTAR = 'aparat_anlatim_izlendi';

        window.aparatAnlatimAc = function () {
            if (!aparatAnlatim) return;
            document.getElementById('apf-katman').classList.add('acik');
            document.body.style.overflow = 'hidden';
            aparatAnlatim.baslat();
            try { localStorage.setItem(ANLATIM_ANAHTAR, '1'); } catch (e) {}
        };

        window.aparatAnlatimKapat = function () {
            document.getElementById('apf-katman').classList.remove('acik');
            document.body.style.overflow = '';
            if (aparatAnlatim) aparatAnlatim.durdur();
        };

        document.getElementById('apf-katman').addEventListener('click', function (e) {
            if (e.target === this) aparatAnlatimKapat();
        });

        document.addEventListener('DOMContentLoaded', function () {
            let izlendi = true;
            try { izlendi = localStorage.getItem(ANLATIM_ANAHTAR) === '1'; } catch (e) {}
            if (!izlendi) aparatAnlatimAc();
            konumAl();
            aparatBilgiYukle().then(aparatStokYukle);
            aparatKuyrukDurumu();
        });
    })();
</script>
<?php endif; ?>
