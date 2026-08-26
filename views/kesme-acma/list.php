<?php
use App\Helper\Form;
use App\Model\KesmeAcmaRaporModel;
use App\Model\KesmeNobetModel;
use App\Model\MahalleModel;
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Service\Gate;

$Rapor = new KesmeAcmaRaporModel();
$Nobet = new KesmeNobetModel();
$MahalleKural = new MahalleModel();
$PersonelRapor = new PersonelModel();
$TanimlamalarRapor = new TanimlamalarModel();

$ekipler = $Rapor->ekipler();
$ekipUyeleri = $Rapor->ekipUyeHaritasi();
$personeller = $Nobet->telefonHavuzu();
$bugun = date('Y-m-d');
$mesajBeklemeGun = $MahalleKural->mesajBeklemeGun();
$sahaPersonelleri = $Nobet->sahaPersonelleri($bugun, array_map(function ($ekip) {
    return (int) $ekip['id'];
}, $ekipler));

$yetkiTanim = Gate::allows('kesme_mahalle_tanim') || Gate::isSuperAdmin();
$yetkiMesaj = Gate::allows('kesme_mesaj') || Gate::isSuperAdmin();
$yetkiAtama = Gate::allows('kesme_atama') || Gate::isSuperAdmin();
$yetkiKalanIs = Gate::allows('kesme_kalan_is') || Gate::isSuperAdmin();
$yetkiNobet = Gate::allows('kesme_nobet') || Gate::isSuperAdmin();
$yetkiAnalizYonetim = Gate::allows('kesme_analiz_yonetim') || Gate::isSuperAdmin();

$ekipEtiketi = function (array $ekip) use ($ekipUyeleri): string {
    $uyeler = $ekipUyeleri[(int) $ekip['id']] ?? '';
    return $uyeler !== '' ? $uyeler . ' (' . $ekip['tur_adi'] . ')' : $ekip['tur_adi'];
};

$ekipOptions = ['' => 'Ekip Seçiniz'];
$ekipFiltreOptions = ['' => 'Tüm Ekipler'];
foreach ($ekipler as $ekip) {
    $ekipOptions[$ekip['id']] = $ekipEtiketi($ekip);
    $ekipFiltreOptions[$ekip['id']] = $ekipEtiketi($ekip);
}

$ekipKisaAd = function (string $ad): string {
    return preg_match('/(EK[İI]P[\s-]?\d+.*|KOORD[İI]NAT[ÖO]R[\s-]?\d+.*)$/ui', $ad, $eslesme)
        ? trim($eslesme[1])
        : $ad;
};

$nobetEkipOptions = ['' => 'Boş bırak'];
$ilceEkipOptions = ['' => '-'];
foreach ($ekipler as $ekip) {
    $personelAdi = $ekipUyeleri[(int) $ekip['id']] ?? '';
    $etiket = $personelAdi !== '' ? $personelAdi : $ekipKisaAd($ekip['tur_adi']);
    $ilceEkipOptions[$ekip['id']] = $etiket;
}
foreach ($sahaPersonelleri as $sahaPersonel) {
    $nobetEkipOptions[$sahaPersonel['id']] = $sahaPersonel['adi_soyadi'] . ' (' . $ekipKisaAd($sahaPersonel['ekip_adi']) . ')';
}

$ilceOptions = ['' => 'İlçe Seçiniz'] + MahalleModel::ILCELER;
$ilceFiltreOptions = ['' => 'Tüm İlçeler'] + MahalleModel::ILCELER;
$havuzOptions = [1 => 'Havuzda (atanabilir)', 0 => 'Girilmiyor (havuz dışı)'];

$personelOptions = ['' => 'Personel Seçiniz'];
foreach ($personeller as $personel) {
    $personelOptions[$personel['id']] = $personel['adi_soyadi'];
}

$ayBasi = date('Y-m-01');
$raporYilOptions = [];
for ($yil = (int) date('Y'); $yil >= 2020; $yil--) {
    $raporYilOptions[$yil] = (string) $yil;
}
$raporAyOptions = [
    '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
    '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
    '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık',
];
$analizAyOptions = [];
for ($i = 0; $i < 18; $i++) {
    $anahtar = date('Y-m', strtotime("-$i month"));
    $analizAyOptions[$anahtar] = $raporAyOptions[date('m', strtotime($anahtar . '-01'))] . ' ' . date('Y', strtotime($anahtar . '-01'));
}
$raporPersonelOptions = ['' => 'Tüm Personeller'];
foreach ($PersonelRapor->all(false, 'puantaj', date('Y-m-t')) as $raporPersonel) {
    $raporPersonelOptions[$raporPersonel->id] = $raporPersonel->adi_soyadi;
}
$raporBolgeOptions = ['' => 'Tüm Bölgeler'];
foreach ($TanimlamalarRapor->getFilteredEkipBolgeleri() as $raporBolge) {
    $raporBolgeOptions[$raporBolge] = $raporBolge;
}
$raporDefterOptions = ['' => 'Tüm Defterler'];
foreach ($TanimlamalarRapor->getDefterKodlari() as $raporDefter) {
    $raporDefterOptions[$raporDefter] = $raporDefter;
}
?>

<style>
    :root {
        --reg-color-0: rgb(227, 242, 253);
        --reg-color-1: rgb(243, 229, 245);
        --reg-color-2: rgb(232, 245, 233);
        --reg-color-3: rgb(255, 243, 224);
        --reg-color-4: rgb(255, 235, 238);
        --reg-color-5: rgb(224, 247, 250);
        --reg-color-6: rgb(252, 228, 236);
        --reg-color-7: rgb(241, 248, 233);
        --reg-color-8: rgb(255, 248, 225);
        --reg-color-9: rgb(237, 231, 246);
    }

    [data-bs-theme="dark"] {
        --reg-color-0: rgba(30, 41, 59, .4);
        --reg-color-1: rgba(88, 28, 135, .2);
        --reg-color-2: rgba(20, 83, 45, .2);
        --reg-color-3: rgba(120, 53, 4, .2);
        --reg-color-4: rgba(153, 27, 27, .2);
        --reg-color-5: rgba(21, 94, 117, .2);
        --reg-color-6: rgba(131, 24, 67, .2);
        --reg-color-7: rgba(101, 163, 13, .2);
        --reg-color-8: rgba(146, 64, 14, .2);
        --reg-color-9: rgba(107, 33, 168, .2);
    }

    .ka-kart {
        position: relative;
        overflow: hidden;
        border: 1px solid var(--bs-border-color-translucent, var(--bs-border-color)) !important;
        transition: opacity .18s ease, transform .18s ease, box-shadow .18s ease;
    }

    .ka-kart::after {
        content: '';
        position: absolute;
        width: 92px;
        height: 92px;
        right: -34px;
        bottom: -48px;
        border-radius: 50%;
        background: var(--ka-kart-renk);
        opacity: .08;
        pointer-events: none;
    }

    .ka-kart:hover {
        transform: translateY(-2px);
        box-shadow: 0 .45rem 1rem rgba(18, 38, 63, .1) !important;
    }

    .ka-kart-mor { --ka-kart-renk: #8b5cf6; }
    .ka-kart-mavi { --ka-kart-renk: #556ee6; }
    .ka-kart-yesil { --ka-kart-renk: #34c38f; }
    .ka-kart-turuncu { --ka-kart-renk: #f1b44c; }

    /* Detaylı Rapor & Günlük İşlem Minimal KPI / Stat Kartları */
    .ka-stat-card {
        position: relative;
        background: var(--bs-card-bg, #ffffff);
        border: 1px solid var(--bs-border-color-translucent, rgba(0, 0, 0, 0.08)) !important;
        border-radius: 8px !important;
        padding: 9px 12px !important;
        cursor: pointer;
        user-select: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease, background-color 0.15s ease;
    }

    .ka-stat-card:hover {
        border-color: color-mix(in srgb, var(--ka-stat-renk, #556ee6) 45%, var(--bs-border-color)) !important;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
        transform: translateY(-1px);
    }

    .ka-stat-card.active {
        border-color: var(--ka-stat-renk, #556ee6) !important;
        background-color: color-mix(in srgb, var(--ka-stat-renk, #556ee6) 7%, var(--bs-card-bg, #ffffff)) !important;
        box-shadow: 0 0 0 1.5px var(--ka-stat-renk, #556ee6), 0 3px 10px rgba(0, 0, 0, 0.05) !important;
    }

    .ka-stat-card.active .ka-stat-icon {
        opacity: 1;
    }

    .ka-stat-card.active .ka-stat-label {
        color: var(--bs-heading-color, #2a3042);
        font-weight: 600;
    }

    .ka-stat-dot {
        width: 6px;
        height: 6px;
        min-width: 6px;
        border-radius: 50%;
        background-color: var(--ka-stat-renk, #556ee6);
        display: inline-block;
    }

    .ka-stat-label {
        font-size: 0.68rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: var(--bs-secondary-color, #6c757d);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ka-stat-icon {
        font-size: 1.05rem;
        color: var(--ka-stat-renk, #556ee6);
        opacity: 0.65;
        transition: opacity 0.15s ease;
    }

    .ka-stat-card:hover .ka-stat-icon {
        opacity: 1;
    }

    .ka-stat-value {
        font-size: 1.25rem;
        font-weight: 600;
        line-height: 1.15;
        color: var(--bs-heading-color, #2a3042);
        font-variant-numeric: tabular-nums;
    }

    .ka-stat-ratio {
        font-size: 0.72rem;
        font-weight: 500;
        color: var(--bs-secondary-color, #74788d);
    }

    .ka-kart-ikon {
        position: absolute;
        top: 10px;
        right: 12px;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--ka-kart-renk);
        background: color-mix(in srgb, var(--ka-kart-renk) 13%, transparent);
    }

    .ka-kart-ikon i {
        font-size: 19px;
    }

    .ka-kart .card-body {
        position: relative;
        z-index: 1;
        padding-right: 54px !important;
    }

    .ka-kart .ka-etiket {
        font-size: .66rem;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: var(--bs-secondary-color);
        font-weight: 600;
    }

    .ka-kart .ka-deger {
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.2;
        font-variant-numeric: tabular-nums;
    }

    .ka-kart .ka-deger.metin {
        font-size: 1rem;
    }

    .ka-ozet-satir {
        overflow: hidden;
        max-height: 500px;
        opacity: 1;
        transition: max-height .28s ease, opacity .2s ease, margin .28s ease;
    }

    .ka-ozet-satir.ka-kapali {
        max-height: 0;
        opacity: 0;
        margin-bottom: 0 !important;
        pointer-events: none;
    }

    .ka-ozet-toggle {
        border: 1px solid var(--bs-border-color);
        background: var(--bs-body-bg);
        color: var(--bs-secondary-color);
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform .2s ease, color .15s ease;
    }

    .ka-ozet-toggle:hover {
        color: var(--bs-primary);
        border-color: var(--bs-primary);
    }

    .ka-ozet-toggle i {
        font-size: 15px;
        transition: transform .25s ease;
    }

    .ka-ozet-toggle.ka-donuk i {
        transform: rotate(180deg);
    }

    /* Sekmeler Sürükle - Bırak (Drag & Drop) */
    .nav-tabs-custom {
        user-select: none;
    }
    .nav-tabs-custom .nav-item:not(.ms-auto) {
        cursor: grab;
        position: relative;
        transition: transform .15s ease, opacity .15s ease;
    }
    .nav-tabs-custom .nav-item:not(.ms-auto):active {
        cursor: grabbing;
    }
    .nav-tabs-custom .nav-item.ka-tab-dragging {
        opacity: 0.35;
        transform: scale(0.96);
    }
    .nav-tabs-custom .nav-item.ka-tab-drag-over-left::before {
        content: '';
        position: absolute;
        left: -3px;
        top: 2px;
        bottom: 2px;
        width: 3px;
        background-color: var(--bs-primary, #556ee6);
        border-radius: 3px;
        z-index: 10;
        pointer-events: none;
    }
    .nav-tabs-custom .nav-item.ka-tab-drag-over-right::after {
        content: '';
        position: absolute;
        right: -3px;
        top: 2px;
        bottom: 2px;
        width: 3px;
        background-color: var(--bs-primary, #556ee6);
        border-radius: 3px;
        z-index: 10;
        pointer-events: none;
    }
    .ka-tab-reset-btn {
        border: 1px solid var(--bs-border-color);
        background: var(--bs-body-bg);
        color: var(--bs-secondary-color);
        width: 26px;
        height: 26px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform .2s ease, color .15s ease, border-color .15s ease;
    }
    .ka-tab-reset-btn:hover {
        color: var(--bs-primary);
        border-color: var(--bs-primary);
    }
    .ka-tab-reset-btn i {
        font-size: 14px;
    }

    .ka-rozet {
        display: inline-block;
        min-width: 22px;
        text-align: center;
        padding: 1px 7px;
        border-radius: 5px;
        font-size: .7rem;
        font-weight: 700;
        margin-right: 3px;
    }

    .ka-dul {
        background: rgba(139, 92, 246, .16);
        color: #7c58e6;
    }

    .ka-oni {
        background: rgba(85, 110, 230, .16);
        color: #4458c9;
    }

    .ka-ilce {
        background: rgba(116, 120, 141, .16);
        color: #626478;
    }

    .ka-cizelge-satir {
        display: grid;
        grid-template-columns: 210px 1fr;
        gap: 12px;
        align-items: center;
        padding: 6px 0;
        border-bottom: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
    }

    .ka-cizelge-yol {
        position: relative;
        height: 30px;
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
        border-radius: 7px;
        overflow: hidden;
    }

    .ka-cizelge-parca {
        position: absolute;
        top: 3px;
        bottom: 3px;
        border-radius: 5px;
        padding: 0 6px;
        font-size: .68rem;
        font-weight: 600;
        color: #fff;
        display: flex;
        align-items: center;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .ka-cizelge-parca.dul {
        background: #8b5cf6;
    }

    .ka-cizelge-parca.oni {
        background: #556ee6;
    }

    .ka-cizelge-parca.ilce {
        background: #74788d;
    }

    .ka-cizelge-parca.aktif {
        box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .75);
    }

    .ka-cizelge-eksen {
        position: relative;
        height: 16px;
        margin-left: 222px;
    }

    .ka-cizelge-eksen span {
        position: absolute;
        font-size: .68rem;
        color: var(--bs-secondary-color);
        transform: translateX(-50%);
    }

    .ka-takvim {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 6px;
    }

    .ka-gun {
        min-height: 92px;
        border: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
        border-radius: 8px;
        padding: 6px;
        background: var(--bs-tertiary-bg);
    }

    .ka-gun.bos {
        border-color: transparent;
        background: transparent;
    }

    .ka-gun.hafta-sonu {
        background: rgba(var(--bs-danger-rgb), .07);
    }

    .ka-gun.bugun {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 1px var(--bs-primary);
    }

    .ka-gun .gun-no {
        font-size: .7rem;
        color: var(--bs-secondary-color);
        text-align: right;
    }

    .ka-nobet-kutu {
        position: relative;
        margin-top: 4px;
        background: #8b5cf6;
        color: #fff;
        border-radius: 6px;
        padding: 3px 5px;
        font-size: .68rem;
        font-weight: 600;
        cursor: pointer;
        line-height: 1.2;
        text-align: center;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .ka-nobet-kutu:hover {
        box-shadow: 0 2px 6px rgba(139, 92, 246, 0.35);
    }

    .ka-nobet-kutu.bos-kutu {
        background: var(--bs-body-bg);
        color: var(--bs-secondary-color);
        border: 1px dashed var(--bs-border-color);
        font-weight: 500;
        box-shadow: none;
    }

    .ka-nobet-kutu.elle {
        box-shadow: inset 0 0 0 2px #f1a520, 0 0 0 1px #f1a520 !important;
        border: 1px solid #f1a520 !important;
    }

    .ka-nobet-kutu.canli-kayit {
        cursor: default;
        background: var(--bs-secondary-bg);
        color: var(--bs-body-color);
        border: 1px solid var(--bs-border-color);
    }

    .ka-nobet-kutu .ka-kaynak-etiket {
        font-size: .55rem;
        font-weight: 500;
        opacity: .75;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .ka-nobet-sil-btn {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.4);
        color: #ffffff !important;
        border: none;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        line-height: 1;
        opacity: 0;
        visibility: hidden;
        transform: scale(0.7);
        transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        z-index: 5;
    }

    .ka-nobet-kutu:hover .ka-nobet-sil-btn {
        opacity: 1;
        visibility: visible;
        transform: scale(1);
    }

    .ka-nobet-sil-btn:hover {
        background: #dc3545 !important;
        color: #ffffff !important;
        transform: scale(1.15) !important;
    }

    .ka-telefon-kutu {
        position: relative;
        margin-top: 4px;
        font-size: .65rem;
        text-align: center;
        color: var(--bs-body-color);
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
        border-radius: 5px;
        padding: 2px 4px;
        cursor: pointer;
        transition: border-color 0.15s ease;
    }

    .ka-telefon-kutu:hover {
        border-color: var(--bs-primary);
    }

    .ka-telefon-kutu.elle {
        border: 1.5px solid #f1a520 !important;
        box-shadow: 0 0 0 1px #f1a520 !important;
        background: rgba(241, 165, 32, 0.08) !important;
    }

    .ka-telefon-sil-btn {
        position: absolute;
        top: 50%;
        right: 2px;
        transform: translateY(-50%) scale(0.7);
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: rgba(108, 117, 125, 0.25);
        color: var(--bs-secondary-color) !important;
        border: none;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        line-height: 1;
        opacity: 0;
        visibility: hidden;
        transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        z-index: 5;
    }

    .ka-telefon-kutu:hover .ka-telefon-sil-btn {
        opacity: 1;
        visibility: visible;
        transform: translateY(-50%) scale(1);
    }

    .ka-telefon-sil-btn:hover {
        background: #dc3545 !important;
        color: #ffffff !important;
        transform: translateY(-50%) scale(1.15) !important;
    }

    #kaTabloIlce .form-floating-custom {
        margin: 0;
    }

    #kaTabloIlce .form-floating-custom>label {
        display: none !important;
    }

    #kaTabloIlce .form-floating-icon {
        display: none !important;
    }

    #kaTabloIlce .select2-container {
        flex: 1 1 auto;
        min-width: 0;
    }

    #kaTabloIlce .select2-container .select2-selection--single {
        height: 34px !important;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    #kaTabloIlce select.border-warning + .select2-container .select2-selection--single,
    #kaTabloIlce select.is-elle + .select2-container .select2-selection--single,
    #kaTabloIlce .select2-container.is-elle .select2-selection--single {
        border: 1.5px solid #f1a520 !important;
        box-shadow: 0 0 0 1px rgba(241, 165, 32, 0.25) !important;
        background-color: rgba(241, 165, 32, 0.05) !important;
    }

    #kaTabloIlce .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px !important;
        padding-left: 10px !important;
        font-size: .78rem;
    }

    #kaTabloIlce .select2-selection__arrow {
        top: 3px !important;
    }

    .ka-dagilim-satir {
        display: grid;
        grid-template-columns: 120px 1fr 74px;
        gap: 8px;
        align-items: center;
        padding: 4px 0;
    }

    .ka-dagilim-ad {
        font-size: .76rem;
        font-weight: 600;
        color: var(--bs-body-color);
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .ka-dagilim-yol {
        background: var(--bs-tertiary-bg);
        border-radius: 5px;
        height: 16px;
        overflow: hidden;
    }

    .ka-dagilim-bar {
        background: #8b5cf6;
        height: 100%;
        border-radius: 5px;
        display: flex;
        justify-content: flex-end;
        min-width: 4px;
    }

    .ka-dagilim-hs {
        background: #5b21b6;
        height: 100%;
    }

    .ka-dagilim-sayi {
        font-size: .76rem;
        font-weight: 700;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .ka-matris {
        max-height: 560px;
        overflow: auto;
    }

    .ka-matris table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: .78rem;
    }

    .ka-matris th,
    .ka-matris td {
        white-space: nowrap;
        padding: 6px 8px;
        border-bottom: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
    }

    .ka-matris thead th {
        position: sticky;
        top: 0;
        z-index: 3;
        background: var(--bs-tertiary-bg);
        cursor: pointer;
        text-align: center;
    }

    .ka-matris .sabit {
        position: sticky;
        left: 0;
        z-index: 2;
        background: var(--bs-body-bg);
        text-align: left;
        min-width: 190px;
        cursor: pointer;
    }

    .ka-matris thead .sabit {
        z-index: 4;
        background: var(--bs-tertiary-bg);
    }

    .ka-matris td.deger {
        text-align: center;
        font-variant-numeric: tabular-nums;
    }

    .ka-matris .pazar {
        background: rgba(244, 106, 106, .06);
        color: #f46a6a;
    }

    .ka-matris .secili {
        background: rgba(85, 110, 230, .12) !important;
    }

    .ka-yapilacak-satir {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 4px;
        border-bottom: 1px solid var(--bs-border-color-translucent, var(--bs-border-color));
    }

    .ka-yapilacak-sayi {
        min-width: 52px;
        text-align: center;
        font-size: 1.1rem;
        font-weight: 700;
    }

    .ka-rapor-icerik {
        min-height: 260px;
        overflow: visible;
    }

    #kaOzetRaporIcerik .table-responsive {
        min-height: 280px;
        overflow-x: auto !important;
        overflow-y: auto !important;
        overscroll-behavior: contain;
    }

    #kaOzetRaporIcerik #raporTable tfoot td {
        position: sticky !important;
    }

    #kaOzetRaporIcerik #raporTable tfoot .tfoot-general td {
        bottom: 0 !important;
    }

    #kaOzetRaporIcerik #raporTable tfoot .tfoot-action td {
        bottom: 40px !important;
    }

    #kaOzetRaporIcerik:fullscreen,
    #kaOzetRaporIcerik:-webkit-full-screen {
        padding: 14px 18px;
        overflow: hidden;
        background: var(--bs-body-bg);
        display: flex;
        flex-direction: column;
        height: 100vh !important;
        width: 100vw !important;
    }

    #kaOzetRaporIcerik:fullscreen .table-responsive,
    #kaOzetRaporIcerik:-webkit-full-screen .table-responsive {
        flex: 1 1 auto !important;
        height: calc(100vh - 75px) !important;
        max-height: calc(100vh - 75px) !important;
        overflow: auto !important;
    }

    .footer {
        display: none !important;
    }

    .page-content {
        padding-bottom: 12px !important;
    }

    .ka-rapor-filtre {
        background: var(--bs-tertiary-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: 10px;
        padding: 10px;
    }

    .ka-rapor-filtre-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: linear-gradient(135deg, #252a34, #111318);
        color: #fff;
        border: 1px solid rgba(255, 255, 255, .12);
        border-radius: 9px;
        padding: 7px 11px;
        font-size: .72rem;
        font-weight: 600;
        box-shadow: 0 3px 8px rgba(18, 38, 63, .16);
    }

    .ka-rapor-accordion-baslik {
        cursor: pointer;
        transition: background-color .18s ease;
    }

    .ka-rapor-accordion-baslik:hover {
        background-color: var(--bs-tertiary-bg) !important;
    }

    .ka-rapor-accordion-ok {
        width: 28px;
        height: 28px;
        border: 1px solid var(--bs-border-color);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--bs-secondary-color);
    }

    .ka-rapor-accordion-baslik[aria-expanded="true"] .ka-rapor-accordion-ok i {
        transform: rotate(180deg);
    }

    .ka-rapor-accordion-ok i {
        transition: transform .2s ease;
    }

    #kaOzetRaporIcerik .report-legend {
        gap: 7px;
        padding: 10px !important;
        border: 1px solid var(--bs-border-color);
        border-radius: 10px;
        background: var(--bs-tertiary-bg);
    }

    #kaOzetRaporIcerik .legend-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        min-height: 32px;
        padding: 4px 7px !important;
        border: 1px solid var(--bs-border-color) !important;
        border-radius: 8px !important;
        background: var(--bs-body-bg);
        box-shadow: 0 2px 5px rgba(18, 38, 63, .07);
        transition: transform .15s ease, box-shadow .15s ease;
    }

    #kaOzetRaporIcerik .legend-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 9px rgba(18, 38, 63, .12);
    }

    #kaOzetRaporIcerik .legend-code {
        padding: 3px 6px;
        border-radius: 5px;
        background: var(--bs-secondary-bg);
        font-weight: 800;
    }

    #kaOzetRaporIcerik .legend-item .badge {
        min-width: 27px;
        padding: 5px 7px;
        border-radius: 6px;
        font-size: .72rem;
        box-shadow: inset 0 0 0 1px rgba(85, 110, 230, .12);
    }

    .ka-analiz-toolbar {display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .ka-analiz-toolbar .form-floating{min-width:190px}
    .ka-dashboard-bar{margin-bottom:10px}.ka-dashboard-metrikler .card{border:1px solid var(--bs-border-color)!important}.ka-analiz-metrik .card-body{padding:12px 14px}.ka-analiz-metrik strong{display:block;margin-top:7px;font-size:1.35rem;font-variant-numeric:tabular-nums}.ka-metrik-alt{font-size:.68rem;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.ka-dashboard-kolonlar>.col-xl-8>.card,.ka-dashboard-kolonlar>.col-xl-4>.card{border:1px solid var(--bs-border-color)!important}.ka-dashboard-kolonlar .card-header{padding:13px 16px;border-bottom:1px solid var(--bs-border-color)}.ka-dashboard-kolonlar .card-header h5{font-size:.9rem}.ka-dashboard-kolonlar .card-header p{font-size:.7rem!important;margin-top:3px}.ka-dashboard-kolonlar table th{font-size:.68rem;white-space:nowrap}.ka-dashboard-kolonlar table td{font-size:.75rem}
    .ka-analiz-grafik{min-height:220px;overflow-x:auto}.ka-analiz-grafik svg{min-width:650px}
    .ka-uyari{border-bottom:1px solid var(--bs-border-color);padding:12px 14px}.ka-uyari:last-child{border-bottom:0}
    .ka-uyari-puan{width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:rgba(244,106,106,.12);color:#f46a6a;font-weight:700;flex:none}
    .ka-uyari-bulgu{font-size:.75rem;color:var(--bs-secondary-color);margin-top:4px}.ka-analiz-scroll{max-height:430px;overflow:auto}
    .ka-kontrol-satir{display:flex;align-items:center;gap:9px;padding:9px 12px;border-bottom:1px solid var(--bs-border-color);font-size:.82rem}
    .ka-kontrol-satir:last-child{border-bottom:0}.ka-kural-grup{padding:10px 14px;background:rgba(var(--bs-primary-rgb),.07);border-bottom:1px solid var(--bs-border-color);font-size:.78rem}.ka-kural-satir{display:grid;grid-template-columns:minmax(230px,1fr) minmax(280px,420px) 90px;gap:12px;align-items:center;padding:11px 14px;border-bottom:1px solid var(--bs-border-color)}
    .ka-kural-secimler{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.ka-kural-chip{border:1px solid var(--bs-border-color);background:var(--bs-body-bg);color:var(--bs-secondary-color);border-radius:6px;padding:5px 9px;font-size:.72rem}.ka-kural-chip.aktif{border-color:var(--bs-primary);background:rgba(var(--bs-primary-rgb),.13);color:var(--bs-primary);font-weight:600}.ka-personel-ekle-btn{border:1px dashed var(--bs-border-color);background:transparent;color:var(--bs-secondary-color);border-radius:6px;padding:5px 9px;font-size:.72rem}.ka-personel-ekle-btn:hover{border-color:var(--bs-primary);color:var(--bs-primary)}.ka-personel-ekle-alan{min-width:230px}.ka-personel-ekle-alan .select2-container{width:100%!important}.ka-kural-sabit{display:grid;grid-template-columns:38px 1fr;gap:5px;align-items:center}.ka-kural-sabit .select2-container{width:100%!important}
    .ka-log-satir{display:grid;grid-template-columns:150px 100px 1fr 180px;gap:12px;padding:10px 14px;border-bottom:1px solid var(--bs-border-color);font-size:.8rem}
    #pane-ka-nobet>.card>.card-body{overflow-x:auto;padding:14px 16px 18px}
    #pane-ka-nobet .ka-takvim{min-width:760px;grid-template-columns:repeat(7,minmax(96px,1fr));gap:6px}
    #pane-ka-nobet .ka-gun{min-height:88px;padding:8px;background:var(--bs-tertiary-bg);border-radius:9px}
    #pane-ka-nobet .ka-gun.hafta-sonu{background:rgba(var(--bs-danger-rgb),.055);border-color:rgba(var(--bs-danger-rgb),.22)}
    #pane-ka-nobet .ka-nobet-kutu{margin-top:1px;padding:0 18px 0 0;background:transparent;color:var(--bs-body-color);border:0;border-radius:0;text-align:left;font-size:.76rem;line-height:1.35;box-shadow:none}
    #pane-ka-nobet .ka-nobet-kutu:hover{box-shadow:none;color:var(--bs-primary)}
    #pane-ka-nobet .ka-nobet-kutu.canli-kayit{background:transparent;color:var(--bs-body-color);border:0}
    #pane-ka-nobet .ka-nobet-kutu.bos-kutu{padding:5px;border:1px dashed var(--bs-border-color);border-radius:6px;text-align:center;color:var(--bs-secondary-color);background:var(--bs-body-bg)}
    #pane-ka-nobet .ka-nobet-kutu.elle{padding:4px 20px 4px 5px;border:1px solid #f1a520!important;border-radius:6px!important;box-shadow:0 0 0 1px rgba(241,165,32,.2)!important;background:rgba(241,165,32,.06)!important}
    #pane-ka-nobet .ka-nobet-kutu .ka-kaynak-etiket{display:none}
    #pane-ka-nobet .ka-telefon-kutu{margin-top:7px;padding:5px 16px 0 0;border:0;border-top:1px solid var(--bs-border-color);border-radius:0;background:transparent;text-align:left;font-size:.68rem;color:var(--bs-secondary-color)}
    #pane-ka-nobet .ka-telefon-kutu.elle{padding:5px 16px 3px 4px;border:1px solid #f1a520!important;border-radius:5px!important}
    #pane-ka-nobet .ka-gun .gun-no{font-size:.66rem}.ka-nobet-aciklama{font-size:.72rem;color:var(--bs-secondary-color)}
    @media(max-width:767.98px){.ka-kural-satir{grid-template-columns:1fr}.ka-kural-satir>.ka-kural-kaydet{width:100%}.ka-log-satir{grid-template-columns:1fr}.ka-analiz-toolbar .form-floating{width:100%}}
</style>

<div class="container-fluid">

    <div class="row g-2 mb-3 ka-ozet-satir" id="kaOzetSatir">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 rounded-3 ka-kart ka-kart-mor" style="border-left: 3px solid #8b5cf6 !important;">
                <div class="card-body p-2 px-3">
                    <span class="ka-kart-ikon"><i class="bx bx-moon"></i></span>
                    <div class="ka-etiket mb-1">Bugünün saha nöbetçisi</div>
                    <div class="ka-deger metin text-dark" id="kaNobetci">-</div>
                    <small class="text-muted" id="kaNobetciAlt">akşam gelen açma işlerine bakar</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 rounded-3 ka-kart ka-kart-mavi" style="border-left: 3px solid #556ee6 !important;">
                <div class="card-body p-2 px-3">
                    <span class="ka-kart-ikon"><i class="bx bx-phone"></i></span>
                    <div class="ka-etiket mb-1">Telefon bugün kimde</div>
                    <div class="ka-deger metin text-dark" id="kaTelefon">-</div>
                    <small class="text-muted">ofis telefonu mesai boyunca onda</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 rounded-3 ka-kart ka-kart-yesil" style="border-left: 3px solid #34c38f !important;">
                <div class="card-body p-2 px-3">
                    <span class="ka-kart-ikon"><i class="bx bx-map-alt"></i></span>
                    <div class="ka-etiket mb-1">Atanabilir mahalle</div>
                    <div class="ka-deger text-dark" id="kaAtanabilir">-</div>
                    <small class="text-muted">mesajı atıldı, <?= (int) $mesajBeklemeGun ?> günü doldu</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100 rounded-3 ka-kart ka-kart-turuncu" style="border-left: 3px solid #f1b44c !important;">
                <div class="card-body p-2 px-3">
                    <span class="ka-kart-ikon"><i class="bx bx-briefcase-alt-2"></i></span>
                    <div class="ka-etiket mb-1">Sahada kalan iş</div>
                    <div class="ka-deger text-dark" id="kaKalanIs">-</div>
                    <small class="text-muted" id="kaSahipsiz">&nbsp;</small>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#pane-ka-dashboard" role="tab">
                <i class="bx bx-tachometer me-1"></i> Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-mahalle" role="tab">
                <i class="bx bx-map me-1"></i> Mahalleler</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-ekip" role="tab">
                <i class="bx bx-group me-1"></i> Ekipler</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-gecmis" role="tab">
                <i class="bx bx-history me-1"></i> Geçmiş</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-nobet" role="tab">
                <i class="bx bx-calendar me-1"></i> Nöbet</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-ozet-rapor" role="tab">
                <i class="bx bx-bar-chart-square me-1"></i> Özet Rapor</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-detay-rapor" role="tab">
                <i class="bx bx-list-ul me-1"></i> Detaylı Rapor</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-matris" role="tab">
                <i class="bx bx-table me-1"></i> Günlük İşlem</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-normal" role="tab">
                <i class="bx bx-pulse me-1"></i> Normal Değerler</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-kurallar" role="tab">
                <i class="bx bx-slider-alt me-1"></i> Kurallar</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#pane-ka-islem-gunlugu" role="tab">
                <i class="bx bx-history me-1"></i> İşlem Günlüğü</a></li>
        <li class="nav-item ms-auto d-flex align-items-center gap-2">
            <span class="text-muted small" id="kaSonAktarim"></span>
            <button type="button" class="ka-tab-reset-btn" id="kaTabResetBtn" title="Sekme sırasını varsayılana döndür">
                <i class="bx bx-reset"></i>
            </button>
            <button type="button" class="ka-ozet-toggle" id="kaOzetToggle" title="Özet kartlarını gizle/göster">
                <i class="bx bx-chevron-up"></i>
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="pane-ka-dashboard">
            <div id="kaGrafik" class="d-none"></div>
            <div class="card border-0 shadow-sm ka-dashboard-bar">
                <div class="card-body py-2">
                    <div class="ka-analiz-toolbar">
                        <?= Form::FormSelect2('kaAnalizAy', $analizAyOptions, date('Y-m'), 'Analiz Ayı', 'calendar', 'key', '', 'form-select select2') ?>
                        <div class="btn-group btn-group-sm" role="group" aria-label="İşlem tabanı">
                            <button type="button" class="btn btn-primary ka-analiz-baz" data-baz="olumlu">Olumlu işler</button>
                            <button type="button" class="btn btn-outline-primary ka-analiz-baz" data-baz="tum">Tüm işlemler</button>
                        </div>
                        <div class="ms-lg-auto text-lg-end"><b class="small text-dark" id="kaAnalizDonem"></b><div class="text-muted" style="font-size:.7rem">Seçili dönem · önceki ay ile karşılaştırma</div></div>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-1 ka-dashboard-metrikler" id="kaAnalizMetrikler"></div>
            <div class="row g-3 mt-0 ka-dashboard-kolonlar">
                <div class="col-xl-8 d-flex flex-column gap-3">
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent d-flex flex-wrap align-items-start gap-2"><div><h5 class="mb-0 fw-bold text-dark">Günlük iş</h5><p class="small text-muted mb-0">Pazar günleri kırmızı; seçilen taban tüm metrik ve personel değerlerine uygulanır.</p></div><span class="badge bg-light text-muted ms-auto">Seçili dönem</span></div><div class="card-body ka-analiz-grafik" id="kaAnalizGrafik"></div></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">Personel karşılaştırması</h5><p class="small text-muted mb-0">Ödeme ve kapalı sapmaları personelin çalıştığı mahallelerin geçmiş normallerine göredir.</p></div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Personel</th><th class="text-end">Gün</th><th class="text-end">Toplam</th><th class="text-end">Olumlu</th><th class="text-end">G. Ort.</th><th class="text-end">Δ Toplam</th><th class="text-end">Δ G. Ort.</th><th class="text-end">Ödeme</th><th class="text-end">Ödeme Sapma</th><th class="text-end">Kapalı</th><th class="text-end">Kapalı Sapma</th><th class="text-end">Sonuç %</th></tr></thead><tbody id="kaAnalizPersonel"></tbody></table></div></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">Mahalle bazında kapalı ve ödeme oranı</h5></div><div class="table-responsive ka-analiz-scroll"><table class="table table-sm mb-0"><thead class="table-light"><tr><th>Mahalle</th><th class="text-end">İş</th><th class="text-end">Kapalı %</th><th class="text-end">Ödeme %</th></tr></thead><tbody id="kaAnalizMahalle"></tbody></table></div></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">İş sonucu dağılımı</h5><p class="small text-muted mb-0">Seçili dönemde girilen sonuçların adet dağılımı.</p></div><div class="card-body ka-analiz-scroll" id="kaAnalizSonuclar"></div></div>
                </div>
                <div class="col-xl-4 d-flex flex-column gap-3">
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">Bugün yapılacaklar</h5><p class="small text-muted mb-0">Bitişine 2 iş günü kalan ekipler ve sahipsiz işler.</p></div><div class="card-body pt-2" id="kaYapilacaklar"><div class="text-muted small">Yükleniyor...</div></div></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent d-flex align-items-center"><div><h5 class="mb-0 fw-bold text-dark">Dikkat listesi</h5><p class="small text-muted mb-0">Mahalle normaliyle düzeltilmiş açık sinyaller.</p></div><span class="badge bg-danger ms-auto" id="kaUyariSayisi">0</span></div><div class="ka-analiz-scroll" id="kaDikkatListesi"></div></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">Veri sağlığı</h5><p class="small text-muted mb-0">Rakamları yorumlamadan önce kontrol edin.</p></div><div class="card-body small" id="kaAnalizSaglik">Yükleniyor…</div></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">Aylık kontrol listesi</h5><p class="small text-muted mb-0">Her ay yapılması gereken işler.</p></div><div id="kaKontrolListesi"></div><?php if ($yetkiAnalizYonetim): ?><div class="card-footer bg-transparent d-flex gap-2"><input type="text" class="form-control form-control-sm" id="kaKontrolYeniAd" maxlength="120" placeholder="Yeni kontrol maddesi"><button type="button" class="btn btn-primary btn-sm text-nowrap" id="kaKontrolEkle">Ekle</button></div><?php endif; ?></div>
                    <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark">Veri kalitesi</h5><p class="small text-muted mb-0">Kişiye değil kayda ait sorunlar.</p></div><div class="card-body small" id="kaAnalizKalite"></div></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-mahalle">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-map me-1 text-primary"></i> Mahalle Havuzu</h5>
                        <p class="text-muted small mb-0 mt-1">Mahalleye önce mesaj atılır; mesajdan <b><?= (int) $mesajBeklemeGun ?> gün sonra</b> "atanabilir" olur ve ekip gönderilebilir.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        <?php if ($yetkiTanim): ?>
                            <button type="button" class="btn btn-primary btn-sm" id="kaBtnMahalleEkle">
                                <i class="bx bx-plus me-1"></i> Mahalle Ekle
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="kaBtnMahalleYenile" title="Yenile">
                            <i class="bx bx-refresh"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="kaMahalleFiltre"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 w-100" id="kaTabloMahalle" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Mahalle</th>
                                    <th>İlçe</th>
                                    <th>Mesaj Tarihi</th>
                                    <th>Durum</th>
                                    <th>Son Ziyaret</th>
                                    <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-ekip">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-group me-1 text-primary"></i> Ekipler</h5>
                        <p class="text-muted small mb-0 mt-1">
                            "Kalan iş" her sabah elle girilir; tahmini bitiş ve mahalle önerisi buna göre güncellenir.
                            Sıra <b>2 Dulkadiroğlu, 1 Onikişubat</b> şeklinde döner.
                        </p>
                    </div>
                    <div class="form-check form-switch ms-auto">
                        <input class="form-check-input" type="checkbox" id="kaTumEkipler">
                        <label class="form-check-label small text-muted" for="kaTumEkipler">Tüm ekipleri göster</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 w-100" id="kaTabloEkip">
                            <thead class="table-light">
                                <tr>
                                    <th>Ekip</th>
                                    <th>Şu Anki Mahalle</th>
                                    <th class="text-center">Kalan İş</th>
                                    <th class="text-center">Günde Bitiriyor</th>
                                    <th class="text-center">Tahmini Bitiş</th>
                                    <th class="text-center">Son 3 Atama</th>
                                    <th>Sıradaki Mahalle</th>
                                    <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-gecmis">
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 ka-kart">
                        <div class="card-body p-3">
                            <div class="ka-etiket mb-1">Tamamlanan atama</div>
                            <div class="ka-deger text-dark" id="kaGecmisTamamlanan">-</div>
                            <small class="text-muted">kapanan mahalle sayısı</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 ka-kart">
                        <div class="card-body p-3">
                            <div class="ka-etiket mb-1">Mahalle başına süre</div>
                            <div class="ka-deger text-dark" id="kaGecmisSure">-</div>
                            <small class="text-muted">ortalama · pazar hariç iş günü</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 ka-kart">
                        <div class="card-body p-3">
                            <div class="ka-etiket mb-1">Hiç gidilmemiş mahalle</div>
                            <div class="ka-deger text-dark" id="kaGecmisGidilmeyen">-</div>
                            <small class="text-muted" id="kaGecmisHavuz">havuzdaki mahalleler</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card border-0 shadow-sm h-100 ka-kart">
                        <div class="card-body p-3">
                            <div class="ka-etiket mb-1">En uzun süredir gidilmeyen</div>
                            <div class="ka-deger metin text-dark" id="kaGecmisEnEski">-</div>
                            <small class="text-muted" id="kaGecmisEnEskiAlt">&nbsp;</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-time-five me-1 text-primary"></i> Ekip Zaman Çizelgesi</h5>
                        <p class="text-muted small mb-0 mt-1">Hangi ekip hangi mahallede ne kadar kaldı. Beyaz çerçeveli kutu şu an sahada olunan mahalledir.</p>
                    </div>
                    <div class="d-flex align-items-center gap-3 small text-muted">
                        <span><i class="bx bxs-square" style="color:#8b5cf6"></i> Dulkadiroğlu</span>
                        <span><i class="bx bxs-square" style="color:#556ee6"></i> Onikişubat</span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="kaCizelge"></div>
                    <div class="ka-cizelge-eksen" id="kaCizelgeEksen"></div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-list-ul me-1 text-primary"></i> Atama Kayıtları</h5>
                                <p class="text-muted small mb-0 mt-1">En yeni atama üstte; süre pazar günleri hariç hesaplanır.</p>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto" style="min-width:320px">
                                <?= Form::FormSelect2('kaGecmisEkip', $ekipFiltreOptions, '', 'Ekip', 'users') ?>
                                <?= Form::FormSelect2('kaGecmisIlce', $ilceFiltreOptions, '', 'İlçe', 'map-pin') ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0 w-100" id="kaTabloGecmis">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mahalle</th>
                                            <th>İlçe</th>
                                            <th class="text-center">Başlangıç</th>
                                            <th class="text-center">Bitiş</th>
                                            <th class="text-center">İş Günü</th>
                                            <th class="text-end">İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-map-pin me-1 text-primary"></i> Mahalleye En Son Ne Zaman Gidildi</h5>
                            <p class="text-muted small mb-0 mt-1" id="kaZiyaretNot">En eski ziyaret üstte.</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="max-height:520px;overflow:auto">
                                <table class="table table-sm table-hover align-middle mb-0 w-100" id="kaTabloZiyaret">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mahalle</th>
                                            <th>Son Giden</th>
                                            <th class="text-center">Tarih</th>
                                            <th class="text-center">Gün Önce</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-nobet">
            <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <div class="row align-items-center g-2">
                                <div class="col-md-7 col-lg-9">
                                    <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-calendar me-1 text-primary"></i> <span id="kaNobetBaslik">Merkez Nöbeti</span></h5>
                                    <p class="text-muted small mb-0 mt-1" id="kaNobetHavuzNot">Her güne bir saha ve bir telefon nöbetçisi yazılır.</p>
                                </div>
                                <div class="col-md-5 col-lg-3 text-md-end">
                                    <div class="d-inline-flex align-items-center gap-2">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-secondary" id="kaAyGeri" title="Önceki Ay"><i class="bx bx-chevron-left"></i></button>
                                            <button type="button" class="btn btn-outline-secondary" id="kaAyIleri" title="Sonraki Ay"><i class="bx bx-chevron-right"></i></button>
                                        </div>
                                        <?php if ($yetkiNobet): ?>
                                            <button type="button" class="btn btn-primary btn-sm text-nowrap" id="kaBtnNobetUret">
                                                <i class="bx bx-refresh me-1"></i> Planı Yeniden Üret
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning d-flex align-items-center gap-2 py-2 px-3 small d-none" id="kaTelefonUyari">
                                <i class="bx bx-error-circle fs-5"></i>
                                <div class="flex-grow-1">
                                    Telefon nöbeti tutacak personel tanımlı değil; otomatik plan telefon satırını boş bırakır.
                                    Personel kartındaki <b>Telefon Nöbeti Tutar</b> ayarını "Evet" yapın.
                                </div>
                                <a href="index.php?p=personel/list" class="btn btn-sm btn-warning">Personel Listesi</a>
                            </div>
                            <div class="ka-takvim mb-2" id="kaTakvimBaslik"></div>
                            <div class="ka-takvim" id="kaTakvim"></div>
                        </div>
            </div>
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-car me-1 text-primary"></i> Haftalık İlçe Görevi</h5>
                            <p class="text-muted small mb-0 mt-1">İlçeye en uzun süredir gitmemiş ekip önce seçilir; şirket aracı kullanan personeli olan ekip gönderilmez.</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 w-100" id="kaTabloIlce">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Hafta</th>
                                            <th>Türkoğlu</th>
                                            <th>Pazarcık</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-bar-chart-alt-2 me-1 text-primary"></i> Nöbet Dağılımı</h5>
                            <p class="text-muted small mb-0 mt-1" id="kaNobetDagilimNot">Ay içinde personel başına düşen nöbet günü; koyu bölüm hafta sonudur.</p>
                        </div>
                        <div class="card-body pt-2">
                            <div id="kaNobetGrafik"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-ozet-rapor">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2 ka-rapor-accordion-baslik"
                     data-bs-toggle="collapse" data-bs-target="#kaOzetRaporFiltreCollapse"
                     aria-expanded="true" aria-controls="kaOzetRaporFiltreCollapse">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-bar-chart-square me-1 text-primary"></i> Kesme/Açma Özet Raporu</h5>
                        <p class="text-muted small mb-0 mt-1">Özet Raporlar ekranındaki ekip, personel ve işlem türü bazlı aylık görünüm.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto">
                        <div class="d-flex align-items-center gap-2" id="kaOzetRaporBadge"></div>
                        <span class="ka-rapor-accordion-ok"><i class="bx bx-chevron-up"></i></span>
                    </div>
                </div>
                <div class="collapse show" id="kaOzetRaporFiltreCollapse">
                    <div class="card-body border-bottom py-2">
                        <div class="ka-rapor-filtre">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2 flex-wrap">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="kaRaporFiltreTipi" id="kaRaporDonem" value="period" checked>
                                <label class="btn btn-sm btn-outline-primary" for="kaRaporDonem"><i class="bx bx-calendar me-1"></i>Dönem Bazlı</label>
                                <input type="radio" class="btn-check" name="kaRaporFiltreTipi" id="kaRaporAralik" value="range">
                                <label class="btn btn-sm btn-outline-primary" for="kaRaporAralik"><i class="bx bx-calendar-week me-1"></i>Tarih Aralığı</label>
                            </div>
                        </div>
                        <div class="row g-2 align-items-center">
                            <div class="col-md-2 ka-rapor-donem"><?= Form::FormSelect2('kaRaporYil', $raporYilOptions, date('Y'), 'Yıl', 'calendar', 'key', '', 'form-select select2') ?></div>
                            <div class="col-md-2 ka-rapor-donem"><?= Form::FormSelect2('kaRaporAy', $raporAyOptions, date('m'), 'Ay', 'calendar', 'key', '', 'form-select select2') ?></div>
                            <div class="col-md-2 ka-rapor-aralik d-none"><?= Form::FormDate('kaRaporBaslangic', date('d.m.Y', strtotime('-30 day')), 'Başlangıç') ?></div>
                            <div class="col-md-2 ka-rapor-aralik d-none"><?= Form::FormDate('kaRaporBitis', date('d.m.Y'), 'Bitiş') ?></div>
                            <div class="col-md-2"><?= Form::FormSelect2('kaRaporPersonel', $raporPersonelOptions, '', 'Personel', 'user', 'key', '', 'form-select select2') ?></div>
                            <div class="col-md-2"><?= Form::FormSelect2('kaRaporBolge', $raporBolgeOptions, '', 'Bölge', 'map-pin', 'key', '', 'form-select select2') ?></div>
                            <div class="col-md-2"><?= Form::FormSelect2('kaRaporDefter', $raporDefterOptions, '', 'Defter', 'book', 'key', '', 'form-select select2') ?></div>
                            <div class="col-md-2"><button type="button" class="btn btn-primary btn-sm w-100" id="kaBtnOzetRapor"><i class="bx bx-search me-1"></i> Sorgula</button></div>
                        </div>
                    </div>
                </div>
                </div>
                <div class="card-body ka-rapor-icerik" id="kaOzetRaporIcerik" data-fullscreen-container>
                    <div class="text-muted small">Rapor sekmesi açıldığında yüklenecek.</div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-detay-rapor">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-list-ul me-1 text-primary"></i> Kesme/Açma Detaylı Raporu</h5>
                        <p class="text-muted small mb-0 mt-1">Detaylı Rapor ekranındaki satır bazlı iş emri kayıtları.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto" style="min-width:390px">
                        <?= Form::FormDate('kaDetayBaslangic', date('d.m.Y', strtotime('-30 day')), 'Başlangıç') ?>
                        <?= Form::FormDate('kaDetayBitis', date('d.m.Y'), 'Bitiş') ?>
                        <button type="button" class="btn btn-primary btn-sm text-nowrap" id="kaBtnDetayRapor"><i class="bx bx-search me-1"></i> Getir</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3" id="kaDetayRaporOzet"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 w-100" id="kaTabloDetayRapor">
                            <thead class="table-light"><tr>
                                <th data-filter="date">Tarih</th>
                                <th data-filter="string">Ekip Kodu</th>
                                <th data-filter="string">Personel</th>
                                <th data-filter="select">İş Emri Tipi</th>
                                <th data-filter="select">İş Emri Sonucu</th>
                                <th data-filter="string">Abone No</th>
                                <th data-filter="string">İş Emri No</th>
                                <th class="text-center">Sonuçlanmış</th>
                                <th class="text-center">Açık Olanlar</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-matris">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-table me-1 text-primary"></i> Günlük İşlem Sayıları</h5>
                        <p class="text-muted small mb-0 mt-1"><b>Ekip adına tıkla</b> → o ekibin tür dağılımı; <b>gün başlığına tıkla</b> → o günün dağılımı.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 ms-auto" style="min-width:340px">
                        <?= Form::FormDate('kaMatrisBaslangic', date('d.m.Y', strtotime($ayBasi)), 'Başlangıç') ?>
                        <?= Form::FormDate('kaMatrisBitis', date('d.m.Y', strtotime($bugun)), 'Bitiş') ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3" id="kaMatrisFiltre"></div>
                    <div class="row g-2 mb-3" id="kaMatrisOzet"></div>
                    <div class="ka-matris" id="kaMatris"></div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="pane-ka-normal">
            <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark"><i class="bx bx-pulse me-1 text-primary"></i> Normal Değerler</h5><p class="small text-muted mb-0 mt-1">Personelin önceki sekiz aylık günlük değerlerinden hesaplanan ortanca ve %25–%75 alışılmış aralığı. Parantez içi seçili ay değeridir.</p></div><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Personel</th><th class="text-end">Günlük Olumlu İş</th><th class="text-end">Alışılmış Aralık</th><th class="text-end">Günlük Ödeme</th><th class="text-end">Alışılmış Aralık</th><th class="text-end">Ödeme Oranı</th><th class="text-end">Alışılmış Aralık</th></tr></thead><tbody id="kaNormalDegerler"></tbody></table></div></div>
        </div>

        <div class="tab-pane fade" id="pane-ka-kurallar">
            <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark"><i class="bx bx-slider-alt me-1 text-primary"></i> Kural Defteri</h5><p class="small text-muted mb-0 mt-1">Nöbet, mahalle atama, ödeme kontrolü ve analiz eşikleri veritabanından okunur. Her değişiklik işlem günlüğüne yazılır.</p></div><div id="kaKuralListesi"></div></div>
        </div>

        <div class="tab-pane fade" id="pane-ka-islem-gunlugu">
            <div class="card border-0 shadow-sm"><div class="card-header bg-transparent"><h5 class="mb-0 fw-bold text-dark"><i class="bx bx-history me-1 text-primary"></i> İşlem Günlüğü</h5><p class="small text-muted mb-0 mt-1">Kural, uyarı, kontrol listesi ve nöbet işlemlerinin kalıcı izi.</p></div><div id="kaAnalizGunluk"></div></div>
        </div>

    </div>
    <script>
    (function() {
        try {
            var nav = document.querySelector('.nav-tabs-custom');
            if (!nav) return;
            var msAuto = nav.querySelector('.nav-item.ms-auto');

            // 1. Sekme sıralamasını render öncesi DOM'da uygula
            var savedOrder = localStorage.getItem('ka_tab_order');
            if (savedOrder) {
                var order = JSON.parse(savedOrder);
                if (Array.isArray(order) && order.length) {
                    var items = Array.prototype.slice.call(nav.querySelectorAll('.nav-item:not(.ms-auto)'));
                    var itemMap = {};
                    items.forEach(function(el) {
                        var a = el.querySelector('a.nav-link');
                        if (a && a.getAttribute('href')) {
                            itemMap[a.getAttribute('href')] = el;
                        }
                    });
                    order.forEach(function(href) {
                        if (itemMap[href]) {
                            nav.insertBefore(itemMap[href], msAuto);
                        }
                    });
                }
            }

            // 2. Aktif sekmeyi render öncesi ayarla (flashback / FOUC önleme)
            var hash = window.location.hash;
            var savedTab = localStorage.getItem('ka_active_tab');
            var targetTab = (hash && nav.querySelector('a[href="' + hash + '"]')) ? hash : (savedTab || '');

            if (targetTab && targetTab !== '#pane-ka-dashboard' && nav.querySelector('a[href="' + targetTab + '"]')) {
                var curActiveLink = nav.querySelector('a.nav-link.active');
                if (curActiveLink) curActiveLink.classList.remove('active');
                var targetLink = nav.querySelector('a[href="' + targetTab + '"]');
                if (targetLink) targetLink.classList.add('active');

                var defaultPane = document.getElementById('pane-ka-dashboard');
                if (defaultPane) defaultPane.classList.remove('show', 'active');
                var targetPaneId = targetTab.replace('#', '');
                var targetPane = document.getElementById(targetPaneId);
                if (targetPane) targetPane.classList.add('show', 'active');
            }
        } catch(e) {}
    })();
    </script>
</div>

<div class="modal fade" id="kaModalUyariGerekce" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Uyarıyı Kontrol Et</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="kaFormUyariGerekce"><div class="modal-body"><input type="hidden" id="kaUyariKey"><p class="small text-muted" id="kaUyariOzet"></p><div class="form-floating"><textarea class="form-control" id="kaUyariGerekce" style="height:120px" minlength="5" required></textarea><label for="kaUyariGerekce">Kontrol gerekçesi</label></div></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button><button type="submit" class="btn btn-primary">Kontrol Edildi Olarak Kaydet</button></div></form>
    </div></div>
</div>

<div class="modal fade" id="kaModalMahalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mahalle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kaFormMahalle">
                <div class="modal-body">
                    <input type="hidden" name="id" id="kaMahalleId">
                    <div class="mb-3"><?= Form::FormFloatInput('text', 'kaMahalleAd', '', 'Mahalle adı', 'Mahalle Adı', 'map-pin', 'form-control', true, 120) ?></div>
                    <div class="mb-3"><?= Form::FormSelect2('kaMahalleIlce', $ilceOptions, '', 'İlçe', 'map', 'key', '', 'form-select select2', true) ?></div>
                    <div class="mb-3"><?= Form::FormFloatInput('text', 'kaMahalleKod', '', 'Örn. 140-144', 'Kod Aralığı', 'hash', 'form-control', false, 60) ?></div>
                    <div class="mb-1"><?= Form::FormSelect2('kaMahalleHavuz', $havuzOptions, 1, 'Havuz Durumu', 'filter') ?></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="kaModalMesaj" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mesaj Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kaFormMesaj">
                <div class="modal-body">
                    <input type="hidden" name="mahalle_id" id="kaMesajMahalleId">
                    <p class="text-muted small">Mahalle: <b id="kaMesajMahalleAd"></b><br>
                        Mesajdan <?= (int) $mesajBeklemeGun ?> gün sonra mahalle kendiliğinden "atanabilir" duruma geçer.</p>
                    <?= Form::FormDate('kaMesajTarih', date('d.m.Y'), 'Mesaj Tarihi') ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="kaModalAtama" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ekibe Mahalle Ata</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kaFormAtama">
                <div class="modal-body">
                    <div class="mb-3"><?= Form::FormSelect2('kaAtamaEkip', $ekipOptions, '', 'Ekip', 'users', 'key', '', 'form-select select2', true) ?></div>
                    <div class="mb-3">
                        <select class="form-select" id="kaAtamaMahalle" name="mahalle_id" required></select>
                        <div class="form-text">Yalnızca atanabilir durumdaki mahalleler listelenir.</div>
                    </div>
                    <?= Form::FormDate('kaAtamaBaslangic', date('d.m.Y'), 'Başlangıç Tarihi') ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        Ekibin açık ataması varsa bu kayıt açılırken kendiliğinden kapatılır.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Ata</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="kaModalSahaNobet" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nöbetçi Ekip — <span id="kaSahaNobetTarih"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kaFormSahaNobet">
                <div class="modal-body">
                    <input type="hidden" id="kaSahaNobetGun">
                    <?= Form::FormSelect2('kaSahaNobetEkip', $nobetEkipOptions, '', 'Nöbetçi Personel', 'user', 'key', '', 'form-select select2') ?>
                    <div class="alert alert-warning small mt-3 mb-0 d-none" id="kaSahaNobetUyari"></div>
                    <div class="text-muted small mt-3">
                        Elle seçilen günler sarı çerçeveyle işaretlenir ve "Planı Otomatik Oluştur" bu günleri değiştirmez.
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger" id="kaBtnSahaNobetSil"><i class="bx bx-trash me-1"></i> Nöbeti Kaldır</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="kaModalTelefonNobet" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Telefon Nöbeti — <span id="kaTelefonNobetTarih"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kaFormTelefonNobet">
                <div class="modal-body">
                    <input type="hidden" id="kaTelefonNobetGun">
                    <?php if (count($personelOptions) > 1): ?>
                        <?= Form::FormSelect2('kaTelefonNobetPersonel', $personelOptions, '', 'Telefon Nöbetçisi', 'phone', 'key', '', 'form-select select2') ?>
                        <div class="text-muted small mt-3">
                            Listede yalnızca personel kartında <b>Telefon Nöbeti Tutar</b> ayarı "Evet" olan kişiler görünür.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning small mb-0">
                            Telefon nöbeti tutacak personel tanımlı değil. Personel kartındaki <b>Telefon Nöbeti Tutar</b>
                            ayarını "Evet" yaptığınız kişiler bu listeye düşer.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger" id="kaBtnTelefonNobetSil"><i class="bx bx-trash me-1"></i> Nöbeti Kaldır</button>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                        <?php if (count($personelOptions) > 1): ?>
                            <button type="submit" class="btn btn-primary">Kaydet</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="kaModalPlanUret" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Haftalık Nöbet Planı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kaFormPlanUret">
                <div class="modal-body">
                    <?= Form::FormSelect2('kaPlanHafta', [], '', 'Hafta', 'calendar', 'key', '', 'form-select select2') ?>
                    <div class="text-muted small mt-3">
                        Seçilen hafta için ilçe görevi, merkez nöbeti ve telefon nöbeti birlikte üretilir.
                        <b>Bugünden önceki günlere yazılmaz</b>; elle değiştirilen günler korunur.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary">Planı Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="kaIlceSablon">
    <?= Form::FormSelect2('kaIlceEkip', $ilceEkipOptions, '', 'Görevli', '', 'key', '', 'form-select select2') ?>
</template>

<script>
    window.kaYetki = {
        tanim: <?= $yetkiTanim ? 'true' : 'false' ?>,
        mesaj: <?= $yetkiMesaj ? 'true' : 'false' ?>,
        atama: <?= $yetkiAtama ? 'true' : 'false' ?>,
        kalanIs: <?= $yetkiKalanIs ? 'true' : 'false' ?>,
        nobet: <?= $yetkiNobet ? 'true' : 'false' ?>,
        analiz: <?= $yetkiAnalizYonetim ? 'true' : 'false' ?>
    };
    window.kaBugun = '<?= $bugun ?>';
    window.kaMesajBeklemeGun = <?= (int) $mesajBeklemeGun ?>;
</script>
<script src="views/kesme-acma/js/list.js?v=<?= @filemtime(__DIR__ . '/js/list.js') ?>"></script>
