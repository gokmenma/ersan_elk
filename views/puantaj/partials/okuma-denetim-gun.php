<?php

if (!defined("OKUMA_DENETIM_SAYFASI")) {
    header("HTTP/1.1 403 Forbidden");
    exit("Doğrudan erişim kapalıdır.");
}

use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Security;
use App\Model\EndeksOkumaModel;
use App\Service\OkumaDenetimService;

$EndeksOkuma = new EndeksOkumaModel();

$varsayilanBitis = date('Y-m-d');
$varsayilanBaslangic = date('Y-m-d', strtotime('-29 days'));

$tarihNormalize = function ($deger, $varsayilan) {
    $deger = trim((string) $deger);
    if ($deger === '') {
        return $varsayilan;
    }
    foreach (['Y-m-d', 'd.m.Y', 'd/m/Y'] as $bicim) {
        $nesne = DateTime::createFromFormat($bicim, $deger);
        if ($nesne && $nesne->format($bicim) === $deger) {
            return $nesne->format('Y-m-d');
        }
    }
    return $varsayilan;
};

$baslangic = $tarihNormalize($_GET['start_date'] ?? '', $varsayilanBaslangic);
$bitis = $tarihNormalize($_GET['end_date'] ?? '', $varsayilanBitis);

if (strtotime($baslangic) > strtotime($bitis)) {
    [$baslangic, $bitis] = [$bitis, $baslangic];
}

$gunFarki = (int) round((strtotime($bitis) - strtotime($baslangic)) / 86400);
if ($gunFarki > 180) {
    $baslangic = date('Y-m-d', strtotime($bitis . ' -180 days'));
}

$bolge = trim($_GET['bolge'] ?? '');
$ekipKoduId = trim($_GET['ekip_kodu_id'] ?? '');
$arama = trim($_GET['arama'] ?? '');
$gorunum = $_GET['gorunum'] ?? 'tumu';
$dusukVerimEsigi = (int) ($_GET['dusuk_verim_esigi'] ?? 50);
$evdeYokEsigi = (int) ($_GET['evde_yok_esigi'] ?? 35);
$haftaSonuDahil = !empty($_GET['hafta_sonu_dahil']);

$Denetim = new OkumaDenetimService($dusukVerimEsigi, $evdeYokEsigi, $haftaSonuDahil);

$ekipGunler = $EndeksOkuma->getDenetimEkipGun($baslangic, $bitis, $bolge, $ekipKoduId, $arama);
$tumEkipler = $EndeksOkuma->getDenetimTanimliEkipler();
$tanimliEkipler = $tumEkipler;
$sayacKirilim = $EndeksOkuma->getDenetimSayacDurumKirilim($baslangic, $bitis, $bolge, $ekipKoduId);
$defterKirilim = $EndeksOkuma->getDenetimEkipDefterleri($baslangic, $bitis, $bolge, $ekipKoduId);

if ($bolge !== '' || $ekipKoduId !== '' || $arama !== '') {
    $gorunenEkipIdleri = array_unique(array_map(fn($s) => (int) $s->ekip_kodu_id, $ekipGunler));
    $tanimliEkipler = array_values(array_filter(
        $tanimliEkipler,
        fn($e) => in_array((int) $e->id, $gorunenEkipIdleri, true)
    ));
}

$ekipler = $Denetim->analizEt($ekipGunler, $tanimliEkipler, $baslangic, $bitis);
$genelOzet = $Denetim->genelOzet($ekipler, $baslangic, $bitis);
$bolgeOzeti = $Denetim->bolgeOzeti($ekipler);
$takvim = $Denetim->takvim($baslangic, $bitis);

$sayacHaritasi = [];
foreach ($sayacKirilim as $satir) {
    $sayacHaritasi[(int) $satir->ekip_kodu_id][$satir->tarih][] = $satir;
}

$defterHaritasi = [];
foreach ($defterKirilim as $satir) {
    $defterHaritasi[(int) $satir->ekip_kodu_id][$satir->tarih][] = $satir;
}

$okumasizEkipler = array_filter($ekipler, fn($e) => $e['calisilan_gun'] === 0);

$gosterilecekEkipler = $ekipler;
if ($gorunum === 'supheli') {
    $gosterilecekEkipler = array_filter($ekipler, fn($e) => ($e['supheli_gun'] + $e['kritik_gun']) > 0);
} elseif ($gorunum === 'kritik') {
    $gosterilecekEkipler = array_filter($ekipler, fn($e) => $e['kritik_gun'] > 0);
} elseif ($gorunum === 'okumasiz') {
    $gosterilecekEkipler = array_filter($ekipler, fn($e) => $e['okumasiz_gun_sayisi'] > 0);
}
$gosterilecekEkipler = array_filter($gosterilecekEkipler, fn($e) => $e['calisilan_gun'] > 0);

$bolgeGruplari = [];
foreach ($gosterilecekEkipler as $ekip) {
    $bolgeGruplari[$ekip['gosterilecek_bolge']][] = $ekip;
}
ksort($bolgeGruplari);

$bolgeOptions = ['' => 'Tüm Bölgeler'];
foreach ($EndeksOkuma->getDistinctBolges() as $b) {
    $bolgeOptions[$b] = $b;
}

$ekipOptions = ['' => 'Tüm Ekipler'];
foreach ($tumEkipler as $e) {
    $ekipOptions[$e->id] = $e->tur_adi;
}

$gorunumOptions = [
    'tumu' => 'Tüm ekipler',
    'supheli' => 'Sadece şüpheli günü olanlar',
    'kritik' => 'Sadece kritik günü olanlar',
    'okumasiz' => 'Okuma yapmayan günü olanlar',
];

$dateRangeValue = Date::dmY($baslangic) . ' - ' . Date::dmY($bitis);

$excelQuery = http_build_query([
    'start_date' => $baslangic,
    'end_date' => $bitis,
    'bolge' => $bolge,
    'ekip_kodu_id' => $ekipKoduId,
    'arama' => $arama,
    'gorunum' => $gorunum,
    'dusuk_verim_esigi' => $dusukVerimEsigi,
    'evde_yok_esigi' => $evdeYokEsigi,
    'hafta_sonu_dahil' => $haftaSonuDahil ? 1 : 0,
]);

$seviyeRenkleri = [
    OkumaDenetimService::SEVIYE_TEMIZ => ['sinif' => 'od-temiz', 'etiket' => 'Normal'],
    OkumaDenetimService::SEVIYE_SUPHELI => ['sinif' => 'od-supheli', 'etiket' => 'Şüpheli'],
    OkumaDenetimService::SEVIYE_KRITIK => ['sinif' => 'od-kritik', 'etiket' => 'Kritik'],
];
?>


    <style>
        .od-sayfa .page-content { padding-bottom: 10px !important; }
        .od-ozet-kutu {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            background: #fff;
            height: 100%;
        }
        .od-ozet-kutu .od-deger { font-size: 22px; font-weight: 700; color: #0f172a; line-height: 1.1; }
        .od-ozet-kutu .od-baslik { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-top: 4px; }
        .od-serit { display: flex; flex-wrap: wrap; gap: 3px; }
        .od-hucre {
            width: 22px; height: 26px; border-radius: 4px;
            border: 1px solid rgba(15,23,42,.08);
            display: flex; align-items: center; justify-content: center;
            font-size: 9px; font-weight: 600; color: #0f172a;
            cursor: default;
        }
        .od-temiz { background: #dcfce7; }
        .od-temiz-yogun { background: #86efac; }
        .od-supheli { background: #fef08a; }
        .od-kritik { background: #fca5a5; }
        .od-okumasiz { background: repeating-linear-gradient(45deg, #fee2e2, #fee2e2 3px, #fff 3px, #fff 6px); border-color: #fca5a5; }
        .od-haftasonu { background: #f1f5f9; color: #94a3b8; }
        .od-disi { background: #fff; border-style: dashed; color: #cbd5e1; }
        .od-rozet { font-size: 10px; padding: 2px 7px; border-radius: 20px; font-weight: 600; }
        .od-ekip-kart { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; background: #fff; }
        .od-ekip-baslik { padding: 12px 14px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .od-ekip-govde { border-top: 1px solid #f1f5f9; padding: 12px 14px; }
        .od-sayi { font-variant-numeric: tabular-nums; text-align: right; }
        .od-alt-bar {
            position: sticky; bottom: 0; z-index: 20;
            background: #fff; border-top: 1px solid #e2e8f0;
            padding: 10px 14px; margin-top: 14px;
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 -2px 10px rgba(15,23,42,.05);
        }
        .od-bolge-baslik {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; cursor: pointer; margin-bottom: 8px;
        }
        .od-cetvel { font-size: 9px; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 3px; margin-top: 3px; }
        .od-cetvel span { width: 22px; text-align: center; }
        .od-tablo th { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
        .od-tablo td { font-size: 12.5px; }
    </style>

    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm mb-0" role="alert">
                <div class="d-flex align-items-start">
                    <i class="bx bx-time-five fs-4 me-2"></i>
                    <div>
                        <strong>Bu sekme API verisiyle çalışır ve gün bazlıdır.</strong>
                        KASKİ okuma API'si okuma saatini döndürmediği için burada işe başlama/bitiş saati
                        ve okumalar arası boşluk analizi yapılamaz. Denetim; günlük okuma hacmi, sayaç durumu
                        dağılımı, bölge uyumu ve okuma yapılmayan günler üzerinden yürütülür.
                        Saat bazlı mesai analizi için yukarıdan
                        <strong>Excel yükle</strong> kaynağını seçin.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" id="odFiltreForm">
                        <input type="hidden" name="p" value="puantaj/okuma-denetim">
                        <input type="hidden" name="kaynak" value="api">
                        <input type="hidden" name="start_date" id="odStartDate" value="<?php echo Security::escape($baslangic); ?>">
                        <input type="hidden" name="end_date" id="odEndDate" value="<?php echo Security::escape($bitis); ?>">

                        <div class="row g-2 align-items-end">
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormDateRange('date_range', $dateRangeValue, 'Tarih Aralığı'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormSelect2('bolge', $bolgeOptions, $bolge, 'Bölge', 'map-pin', 'key', '', 'form-select select2'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormSelect2('ekip_kodu_id', $ekipOptions, $ekipKoduId, 'Ekip', 'users', 'key', '', 'form-select select2'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormFloatInput('text', 'arama', $arama, 'Ekip / Personel Ara', 'Ekip / Personel Ara', 'search'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormSelect2('gorunum', $gorunumOptions, $gorunum, 'Görünüm', 'filter', 'key', '', 'form-select select2'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm" style="padding: 11px 0;">
                                    <i class="mdi mdi-filter-variant me-1"></i> Filtrele
                                </button>
                            </div>
                        </div>

                        <div class="row g-2 align-items-end mt-1 pt-3 border-top">
                            <div class="col-lg-3 col-md-4">
                                <?php echo Form::FormFloatInput('number', 'dusuk_verim_esigi', $dusukVerimEsigi, 'Düşük Verim Eşiği (%)', 'Düşük Verim Eşiği (%)', 'trending-down', 'form-control', false, null, 'off', false, 'min="1" max="100"'); ?>
                                <small class="text-muted d-block mt-1">Ekibin kendi normal gününün bu oranın altında kalan günleri işaretlenir.</small>
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <?php echo Form::FormFloatInput('number', 'evde_yok_esigi', $evdeYokEsigi, 'Evde Yok Eşiği (%)', 'Evde Yok Eşiği (%)', 'alert-triangle', 'form-control', false, null, 'off', false, 'min="1" max="100"'); ?>
                                <small class="text-muted d-block mt-1">Günlük okumaların bu oranı "EVDE YOK" ise şüpheli sayılır.</small>
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="odHaftaSonu" name="hafta_sonu_dahil" value="1" <?php echo $haftaSonuDahil ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="odHaftaSonu">Hafta sonunu iş günü say</label>
                                </div>
                                <small class="text-muted d-block">Kapalıyken cumartesi-pazar "okuma yapılmadı" olarak sayılmaz.</small>
                            </div>
                            <div class="col-lg-3 col-md-12 text-lg-end">
                                <span class="badge bg-light text-dark border">
                                    <?php echo Security::escape(Date::dmY($baslangic)); ?> - <?php echo Security::escape(Date::dmY($bitis)); ?>
                                    &middot; <?php echo (int) $genelOzet['is_gunu_sayisi']; ?> iş günü
                                </span>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <?php
        $ozetKutulari = [
            ['deger' => number_format($genelOzet['toplam_abone'], 0, ',', '.'), 'baslik' => 'Toplam Okuma', 'renk' => '#0f172a'],
            ['deger' => number_format($genelOzet['okunan_abone'], 0, ',', '.'), 'baslik' => 'Endeks Alınan', 'renk' => '#15803d'],
            ['deger' => number_format($genelOzet['evde_yok_abone'], 0, ',', '.'), 'baslik' => 'Evde Yok', 'renk' => '#b45309'],
            ['deger' => number_format($genelOzet['ekip_sayisi'], 0, ',', '.'), 'baslik' => 'Çalışan Ekip', 'renk' => '#0f172a'],
            ['deger' => number_format($genelOzet['ekip_gun'], 0, ',', '.'), 'baslik' => 'Ekip-Gün Kaydı', 'renk' => '#0f172a'],
            ['deger' => number_format($genelOzet['supheli_gun'], 0, ',', '.'), 'baslik' => 'Şüpheli Gün', 'renk' => '#a16207'],
            ['deger' => number_format($genelOzet['kritik_gun'], 0, ',', '.'), 'baslik' => 'Kritik Gün', 'renk' => '#b91c1c'],
            ['deger' => number_format($genelOzet['okumasiz_gun'], 0, ',', '.'), 'baslik' => 'Okumasız İş Günü', 'renk' => '#b91c1c'],
        ];
        foreach ($ozetKutulari as $kutu): ?>
            <div class="col-lg col-md-3 col-6">
                <div class="od-ozet-kutu">
                    <div class="od-deger" style="color: <?php echo $kutu['renk']; ?>"><?php echo $kutu['deger']; ?></div>
                    <div class="od-baslik"><?php echo Security::escape($kutu['baslik']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($okumasizEkipler)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="alert alert-secondary border shadow-sm mb-0">
                    <div class="fw-bold mb-1">
                        <i class="mdi mdi-account-off me-1"></i>
                        Seçilen aralıkta hiç okuma kaydı olmayan ekipler (<?php echo count($okumasizEkipler); ?>)
                    </div>
                    <div class="small text-muted mb-2">
                        Bu ekipler kesme/açma, sayaç değişimi gibi başka işlere atanmış olabilir;
                        listede olmaları tek başına devamsızlık anlamına gelmez.
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($okumasizEkipler as $ekip): ?>
                            <span class="badge bg-white text-dark border">
                                <?php echo Security::escape($ekip['ekip_adi']); ?>
                                <?php if (!empty($ekip['personeller'])): ?>
                                    <span class="text-muted fw-normal">— <?php echo Security::escape($ekip['personeller']); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($bolgeOzeti)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Bölge Özeti</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle od-tablo mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Bölge</th>
                                        <th class="od-sayi">Ekip</th>
                                        <th class="od-sayi">Ekip-Gün</th>
                                        <th class="od-sayi">Toplam Okuma</th>
                                        <th class="od-sayi">Günlük Ort.</th>
                                        <th class="od-sayi">Evde Yok %</th>
                                        <th class="od-sayi">Şüpheli</th>
                                        <th class="od-sayi">Kritik</th>
                                        <th class="od-sayi">Okumasız Gün</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bolgeOzeti as $satir): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo Security::escape($satir['bolge']); ?></td>
                                            <td class="od-sayi"><?php echo (int) $satir['ekip_sayisi']; ?></td>
                                            <td class="od-sayi"><?php echo (int) $satir['calisilan_gun']; ?></td>
                                            <td class="od-sayi"><?php echo number_format($satir['toplam_abone'], 0, ',', '.'); ?></td>
                                            <td class="od-sayi"><?php echo number_format($satir['gunluk_ortalama'], 0, ',', '.'); ?></td>
                                            <td class="od-sayi"><?php echo number_format($satir['evde_yok_orani'], 1, ',', '.'); ?></td>
                                            <td class="od-sayi"><?php echo (int) $satir['supheli_gun']; ?></td>
                                            <td class="od-sayi text-danger fw-semibold"><?php echo (int) $satir['kritik_gun']; ?></td>
                                            <td class="od-sayi"><?php echo (int) $satir['okumasiz_gun']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row mb-2">
        <div class="col-12 d-flex flex-wrap gap-3 align-items-center">
            <span class="text-muted small fw-semibold">Gün şeridi:</span>
            <span class="d-flex align-items-center gap-1 small"><span class="od-hucre od-temiz-yogun"></span> Yoğun gün</span>
            <span class="d-flex align-items-center gap-1 small"><span class="od-hucre od-temiz"></span> Normal</span>
            <span class="d-flex align-items-center gap-1 small"><span class="od-hucre od-supheli"></span> Şüpheli</span>
            <span class="d-flex align-items-center gap-1 small"><span class="od-hucre od-kritik"></span> Kritik</span>
            <span class="d-flex align-items-center gap-1 small"><span class="od-hucre od-okumasiz"></span> Okuma yok</span>
            <span class="d-flex align-items-center gap-1 small"><span class="od-hucre od-haftasonu"></span> Hafta sonu</span>
        </div>
    </div>

    <?php if (empty($bolgeGruplari)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body text-center text-muted py-5">
                        <i class="mdi mdi-database-search-outline fs-1 d-block mb-2"></i>
                        Seçilen filtrelerde okuma kaydı bulunamadı.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($bolgeGruplari as $bolgeAdi => $bolgeEkipleri): ?>
        <?php
        $bolgeId = 'od-bolge-' . md5($bolgeAdi);
        $bolgeToplam = array_sum(array_column($bolgeEkipleri, 'toplam_abone'));
        $bolgeSupheli = array_sum(array_column($bolgeEkipleri, 'supheli_gun'));
        $bolgeKritik = array_sum(array_column($bolgeEkipleri, 'kritik_gun'));
        ?>
        <div class="od-bolge-baslik" data-bs-toggle="collapse" data-bs-target="#<?php echo $bolgeId; ?>">
            <div class="fw-bold">
                <i class="mdi mdi-chevron-down me-1"></i>
                <?php echo Security::escape($bolgeAdi); ?>
                <span class="text-muted fw-normal ms-2"><?php echo count($bolgeEkipleri); ?> ekip</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <span class="od-rozet bg-light text-dark border"><?php echo number_format($bolgeToplam, 0, ',', '.'); ?> okuma</span>
                <?php if ($bolgeSupheli > 0): ?>
                    <span class="od-rozet bg-warning text-dark"><?php echo $bolgeSupheli; ?> şüpheli gün</span>
                <?php endif; ?>
                <?php if ($bolgeKritik > 0): ?>
                    <span class="od-rozet bg-danger text-white"><?php echo $bolgeKritik; ?> kritik gün</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="collapse show" id="<?php echo $bolgeId; ?>">
            <?php foreach ($bolgeEkipleri as $ekip): ?>
                <?php $ekipId = 'od-ekip-' . (int) $ekip['ekip_kodu_id']; ?>
                <div class="od-ekip-kart">
                    <div class="od-ekip-baslik">
                        <div>
                            <div class="fw-bold">
                                <?php echo Security::escape($ekip['ekip_adi']); ?>
                                <?php if (!$ekip['listede_var']): ?>
                                    <span class="od-rozet bg-warning text-dark ms-1">Ekip tanımında yok</span>
                                <?php endif; ?>
                                <?php if ($ekip['kritik_gun'] > 0): ?>
                                    <span class="od-rozet bg-danger text-white ms-1"><?php echo $ekip['kritik_gun']; ?> kritik</span>
                                <?php endif; ?>
                                <?php if ($ekip['supheli_gun'] > 0): ?>
                                    <span class="od-rozet bg-warning text-dark ms-1"><?php echo $ekip['supheli_gun']; ?> şüpheli</span>
                                <?php endif; ?>
                                <?php if ($ekip['okumasiz_gun_sayisi'] > 0): ?>
                                    <span class="od-rozet bg-secondary text-white ms-1"><?php echo $ekip['okumasiz_gun_sayisi']; ?> gün okuma yok</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <?php echo Security::escape($ekip['personeller'] ?: 'Personel eşleşmemiş'); ?>
                            </div>
                        </div>
                        <div class="d-flex gap-3 text-end">
                            <div>
                                <div class="fw-bold od-sayi"><?php echo number_format($ekip['toplam_abone'], 0, ',', '.'); ?></div>
                                <div class="text-muted" style="font-size: 10px;">TOPLAM OKUMA</div>
                            </div>
                            <div>
                                <div class="fw-bold od-sayi"><?php echo $ekip['calisilan_gun']; ?></div>
                                <div class="text-muted" style="font-size: 10px;">ÇALIŞILAN GÜN</div>
                            </div>
                            <div>
                                <div class="fw-bold od-sayi"><?php echo number_format($ekip['gunluk_ortalama'], 0, ',', '.'); ?></div>
                                <div class="text-muted" style="font-size: 10px;">GÜNLÜK ORT.</div>
                            </div>
                            <div>
                                <div class="fw-bold od-sayi"><?php echo number_format($ekip['evde_yok_orani'], 1, ',', '.'); ?>%</div>
                                <div class="text-muted" style="font-size: 10px;">EVDE YOK</div>
                            </div>
                            <div class="align-self-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?php echo $ekipId; ?>">
                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Gün dökümü
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="od-ekip-govde">
                        <div class="od-serit">
                            <?php foreach ($takvim as $gun): ?>
                                <?php
                                $veri = $ekip['gunler'][$gun] ?? null;
                                $haftaSonu = $Denetim->haftaSonu($gun);
                                $gunNo = (int) date('j', strtotime($gun));

                                if ($veri) {
                                    $sinif = $seviyeRenkleri[$veri['seviye']]['sinif'];
                                    if ($veri['seviye'] === OkumaDenetimService::SEVIYE_TEMIZ
                                        && $ekip['referans'] > 0
                                        && $veri['toplam_abone'] >= $ekip['referans']) {
                                        $sinif = 'od-temiz-yogun';
                                    }
                                    $bayrakMetni = implode(' | ', array_column($veri['bayraklar'], 'etiket'));
                                    $baslik = Date::dmY($gun) . ' — ' . number_format($veri['toplam_abone'], 0, ',', '.') . ' okuma, '
                                        . 'endeks alınan ' . number_format($veri['okunan_abone'], 0, ',', '.') . ', '
                                        . 'evde yok %' . number_format($veri['evde_yok_orani'], 1, ',', '.')
                                        . ($bayrakMetni !== '' ? ' — ' . $bayrakMetni : '');
                                } elseif ($haftaSonu && !$haftaSonuDahil) {
                                    $sinif = 'od-haftasonu';
                                    $baslik = Date::dmY($gun) . ' — hafta sonu';
                                } elseif (in_array($gun, $ekip['okumasiz_gunler'], true)) {
                                    $sinif = 'od-okumasiz';
                                    $baslik = Date::dmY($gun) . ' — okuma kaydı yok';
                                } else {
                                    $sinif = 'od-disi';
                                    $baslik = Date::dmY($gun);
                                }
                                ?>
                                <div class="od-hucre <?php echo $sinif; ?>" title="<?php echo Security::escape($baslik); ?>">
                                    <?php echo $gunNo; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="collapse mt-3" id="<?php echo $ekipId; ?>">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle od-tablo mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 90px;">Tarih</th>
                                            <th style="width: 70px;">Gün</th>
                                            <th class="od-sayi">Toplam</th>
                                            <th class="od-sayi">Endeks Alınan</th>
                                            <th class="od-sayi">Evde Yok</th>
                                            <th class="od-sayi">Arızalı</th>
                                            <th class="od-sayi">İdari</th>
                                            <th class="od-sayi">Defter</th>
                                            <th>Okunan Bölgeler</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ekip['gunler'] as $tarih => $gunVerisi): ?>
                                            <?php
                                            $satirSinifi = '';
                                            if ($gunVerisi['seviye'] === OkumaDenetimService::SEVIYE_KRITIK) {
                                                $satirSinifi = 'table-danger';
                                            } elseif ($gunVerisi['seviye'] === OkumaDenetimService::SEVIYE_SUPHELI) {
                                                $satirSinifi = 'table-warning';
                                            }
                                            ?>
                                            <tr class="<?php echo $satirSinifi; ?>">
                                                <td><?php echo Security::escape(Date::dmY($tarih)); ?></td>
                                                <td><?php echo Security::escape(Date::gunAdi($tarih)); ?></td>
                                                <td class="od-sayi fw-semibold"><?php echo number_format($gunVerisi['toplam_abone'], 0, ',', '.'); ?></td>
                                                <td class="od-sayi"><?php echo number_format($gunVerisi['okunan_abone'], 0, ',', '.'); ?></td>
                                                <td class="od-sayi"><?php echo number_format($gunVerisi['evde_yok_abone'], 0, ',', '.'); ?> <span class="text-muted">(%<?php echo number_format($gunVerisi['evde_yok_orani'], 1, ',', '.'); ?>)</span></td>
                                                <td class="od-sayi"><?php echo number_format($gunVerisi['arizali_abone'], 0, ',', '.'); ?></td>
                                                <td class="od-sayi"><?php echo number_format($gunVerisi['idari_abone'], 0, ',', '.'); ?></td>
                                                <td class="od-sayi"><?php echo (int) $gunVerisi['defter_sayisi']; ?></td>
                                                <td><?php echo Security::escape($gunVerisi['okunan_bolgeler']); ?></td>
                                                <td>
                                                    <?php if (empty($gunVerisi['bayraklar'])): ?>
                                                        <span class="text-success small">Normal</span>
                                                    <?php else: ?>
                                                        <?php foreach ($gunVerisi['bayraklar'] as $bayrak): ?>
                                                            <span class="od-rozet <?php echo $bayrak['agirlik'] >= 2 ? 'bg-danger text-white' : ($bayrak['agirlik'] === 0 ? 'bg-light text-dark border' : 'bg-warning text-dark'); ?> me-1"
                                                                  title="<?php echo Security::escape($bayrak['aciklama']); ?>">
                                                                <?php echo Security::escape($bayrak['etiket']); ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (!empty($ekip['okumasiz_gunler'])): ?>
                                <div class="alert alert-light border mt-2 mb-0 py-2 px-3 small">
                                    <span class="fw-semibold text-danger">Okuma kaydı olmayan iş günleri:</span>
                                    <?php echo Security::escape(implode(', ', array_map(fn($g) => Date::dmY($g), $ekip['okumasiz_gunler']))); ?>
                                </div>
                            <?php endif; ?>

                            <?php $ekipSayac = $sayacHaritasi[(int) $ekip['ekip_kodu_id']] ?? []; ?>
                            <?php if (!empty($ekipSayac)): ?>
                                <?php
                                $sayacToplam = [];
                                foreach ($ekipSayac as $gunSatirlari) {
                                    foreach ($gunSatirlari as $s) {
                                        $sayacToplam[$s->sayac_durum] = ($sayacToplam[$s->sayac_durum] ?? 0) + (int) $s->adet;
                                    }
                                }
                                arsort($sayacToplam);
                                ?>
                                <div class="mt-3">
                                    <div class="text-muted small fw-semibold mb-1">Sayaç durumu dağılımı</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($sayacToplam as $durum => $adet): ?>
                                            <span class="od-rozet bg-light text-dark border">
                                                <?php echo Security::escape($durum); ?>:
                                                <strong><?php echo number_format($adet, 0, ',', '.'); ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php $ekipDefter = $defterHaritasi[(int) $ekip['ekip_kodu_id']] ?? []; ?>
                            <?php if (!empty($ekipDefter)): ?>
                                <?php
                                $defterToplam = [];
                                foreach ($ekipDefter as $gunSatirlari) {
                                    foreach ($gunSatirlari as $s) {
                                        $anahtar = $s->bolge . ' / ' . $s->defter;
                                        $defterToplam[$anahtar] = ($defterToplam[$anahtar] ?? 0) + (int) $s->adet;
                                    }
                                }
                                arsort($defterToplam);
                                $defterToplam = array_slice($defterToplam, 0, 20, true);
                                ?>
                                <div class="mt-3">
                                    <div class="text-muted small fw-semibold mb-1">En çok okunan defterler</div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($defterToplam as $defterAdi => $adet): ?>
                                            <span class="od-rozet bg-light text-dark border">
                                                <?php echo Security::escape($defterAdi); ?>:
                                                <strong><?php echo number_format($adet, 0, ',', '.'); ?></strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="od-alt-bar">
        <div class="small text-muted">
            <?php echo count($gosterilecekEkipler); ?> ekip listeleniyor &middot;
            Düşük verim eşiği %<?php echo $dusukVerimEsigi; ?> &middot;
            Evde yok eşiği %<?php echo $evdeYokEsigi; ?>
        </div>
        <a href="views/puantaj/okuma-denetim-excel.php?<?php echo Security::escape($excelQuery); ?>"
           class="btn btn-success fw-bold shadow-sm" target="_blank">
            <i class="mdi mdi-file-excel me-1"></i> Excel olarak indir
        </a>
    </div>

<script>
$(document).ready(function () {
    $(".flatpickr-range").flatpickr({
        mode: "range",
        locale: "tr",
        dateFormat: "d.m.Y",
        allowInput: true
    });

    $('#odFiltreForm').on('submit', function () {
        var deger = $('input[name="date_range"]').val() || '';
        var parcalar = deger.split(' - ');
        var basla = parcalar[0] || '';
        var bitir = parcalar[1] || parcalar[0] || '';

        function cevir(tarih) {
            var p = tarih.trim().split('.');
            if (p.length !== 3) return '';
            return p[2] + '-' + p[1] + '-' + p[0];
        }

        $('#odStartDate').val(cevir(basla));
        $('#odEndDate').val(cevir(bitir));
        $('input[name="date_range"]').prop('disabled', true);
    });

    $('.od-bolge-baslik').on('click', function () {
        $(this).find('.mdi-chevron-down, .mdi-chevron-right')
            .toggleClass('mdi-chevron-down mdi-chevron-right');
    });
});
</script>
