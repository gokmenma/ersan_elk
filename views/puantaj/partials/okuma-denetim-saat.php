<?php

if (!defined("OKUMA_DENETIM_SAYFASI")) {
    header("HTTP/1.1 403 Forbidden");
    exit("Doğrudan erişim kapalıdır.");
}

use App\Helper\Date;
use App\Helper\Form;
use App\Helper\Security;
use App\Model\OkumaDetayModel;
use App\Service\OkumaMesaiAnalizService;

$Model = new OkumaDetayModel();
$firmaId = $_SESSION['firma_id'] ?? 0;

$aralik = $Model->getTarihAraligi($firmaId);
$veriVar = $aralik && (int) $aralik->toplam > 0;

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

$varsayilanBitis = $veriVar ? $aralik->son : date('Y-m-d');
$varsayilanBaslangic = $veriVar ? max($aralik->ilk, date('Y-m-d', strtotime($varsayilanBitis . ' -29 days'))) : date('Y-m-d', strtotime('-29 days'));

$baslangic = $tarihNormalize($_GET['start_date'] ?? '', $varsayilanBaslangic);
$bitis = $tarihNormalize($_GET['end_date'] ?? '', $varsayilanBitis);

if (strtotime($baslangic) > strtotime($bitis)) {
    [$baslangic, $bitis] = [$bitis, $baslangic];
}

$bolge = trim($_GET['bolge'] ?? '');
$ekipKodu = trim($_GET['ekip_kodu'] ?? '');
$arama = trim($_GET['arama'] ?? '');
$gorunum = $_GET['gorunum'] ?? 'tumu';
$esikDakika = (int) ($_GET['esik'] ?? 30);

$Analiz = new OkumaMesaiAnalizService($esikDakika);
$esikDakika = $Analiz->esikDakika();

$okumalar = $veriVar ? $Model->getOkumalar($firmaId, $baslangic, $bitis, $bolge, $ekipKodu, $arama) : [];
$ekipTanimlari = $Model->getEkipEslesmeleri();

$sonuclar = $Analiz->analizEt($okumalar, $ekipTanimlari);
$genelOzet = $Analiz->genelOzet($sonuclar);
$bolgeOzeti = $Analiz->bolgeOzeti($sonuclar);

$gosterilecek = $sonuclar;
if ($gorunum === 'supheli') {
    $gosterilecek = array_filter($sonuclar, fn($s) => !empty($s['bosluklar']));
} elseif ($gorunum === 'kritik') {
    $gosterilecek = array_filter($sonuclar, fn($s) => $s['kritik_bosluk'] > 0);
}

$bolgeGruplari = [];
foreach ($gosterilecek as $satir) {
    $bolgeAnahtari = !empty($satir['bolgeler']) ? (string) array_key_first($satir['bolgeler']) : 'TANIMSIZ';
    $bolgeGruplari[$bolgeAnahtari][] = $satir;
}
ksort($bolgeGruplari);

$dosyalar = $Model->getDosyalar($firmaId);

$bolgeOptions = ['' => 'Tüm Bölgeler'];
foreach ($Model->getDistinctBolgeler($firmaId) as $b) {
    $bolgeOptions[$b] = $b;
}

$ekipOptions = ['' => 'Tüm Ekipler'];
foreach ($Model->getDistinctEkipler($firmaId) as $e) {
    $ekipOptions[$e->ekip_kodu] = $e->ekip_kodu . ($e->ekip_adi ? ' — ' . $e->ekip_adi : '');
}

$gorunumOptions = [
    'tumu' => 'Tüm ekipler',
    'supheli' => 'Sadece şüpheli boşluğu olanlar',
    'kritik' => 'Sadece kritik boşluğu olanlar',
];

$dateRangeValue = Date::dmY($baslangic) . ' - ' . Date::dmY($bitis);

$excelQuery = http_build_query([
    'start_date' => $baslangic,
    'end_date' => $bitis,
    'bolge' => $bolge,
    'ekip_kodu' => $ekipKodu,
    'arama' => $arama,
    'gorunum' => $gorunum,
    'esik' => $esikDakika,
]);

$sure = fn($sn) => OkumaMesaiAnalizService::sureMetni($sn);

$serit = function (array $satir) {
    $gunBasi = strtotime(date('Y-m-d', $satir['ilk_okuma']));
    $baslangicSaat = 6;
    $bitisSaat = 22;
    $toplamSn = ($bitisSaat - $baslangicSaat) * 3600;
    $pencereBasi = $gunBasi + $baslangicSaat * 3600;

    $konum = function ($zaman) use ($pencereBasi, $toplamSn) {
        $oran = ($zaman - $pencereBasi) / $toplamSn;
        return max(0, min(100, $oran * 100));
    };

    return ['konum' => $konum, 'baslangic_saat' => $baslangicSaat, 'bitis_saat' => $bitisSaat];
};
?>


    <style>
        .om-sayfa .page-content { padding-bottom: 10px !important; }
        .om-ozet-kutu { border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; background: #fff; height: 100%; }
        .om-ozet-kutu .om-deger { font-size: 21px; font-weight: 700; color: #0f172a; line-height: 1.1; }
        .om-ozet-kutu .om-baslik { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-top: 4px; }
        .om-birak {
            border: 2px dashed #cbd5e1; border-radius: 12px; padding: 26px;
            text-align: center; background: #f8fafc; transition: all .15s ease; cursor: pointer;
        }
        .om-birak.om-aktif { border-color: #2563eb; background: #eff6ff; }
        .om-dosya-kutu {
            display: inline-flex; align-items: center; gap: 8px;
            border: 1px solid #e2e8f0; border-radius: 8px; padding: 7px 10px; background: #fff; font-size: 12px;
        }
        .om-dosya-kutu.om-hatali { border-color: #fca5a5; background: #fef2f2; }
        .om-serit-kutu { position: relative; height: 30px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
        .om-okuma-cizgi { position: absolute; top: 5px; width: 2px; height: 20px; background: #16a34a; opacity: .75; }
        .om-bosluk-alan { position: absolute; top: 0; height: 100%; }
        .om-bosluk-supheli { background: repeating-linear-gradient(45deg, rgba(250,204,21,.45), rgba(250,204,21,.45) 5px, rgba(250,204,21,.2) 5px, rgba(250,204,21,.2) 10px); }
        .om-bosluk-kritik { background: repeating-linear-gradient(45deg, rgba(239,68,68,.45), rgba(239,68,68,.45) 5px, rgba(239,68,68,.2) 5px, rgba(239,68,68,.2) 10px); }
        .om-cetvel { display: flex; font-size: 9px; color: #94a3b8; margin-top: 2px; }
        .om-cetvel span { flex: 1; text-align: left; border-left: 1px solid #e2e8f0; padding-left: 2px; }
        .om-saat-kutu {
            width: 30px; height: 30px; border-radius: 5px; border: 1px solid rgba(15,23,42,.08);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-size: 8px; line-height: 1.1; color: #0f172a;
        }
        .om-saat-bos { background: #fee2e2; color: #b91c1c; }
        .om-rozet { font-size: 10px; padding: 2px 7px; border-radius: 20px; font-weight: 600; }
        .om-kart { border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 10px; background: #fff; }
        .om-kart-baslik { padding: 12px 14px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .om-kart-govde { border-top: 1px solid #f1f5f9; padding: 12px 14px; }
        .om-sayi { font-variant-numeric: tabular-nums; text-align: right; }
        .om-tablo th { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
        .om-tablo td { font-size: 12.5px; }
        .om-bolge-baslik {
            display: flex; align-items: center; justify-content: space-between;
            padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; cursor: pointer; margin-bottom: 8px;
        }
        .om-alt-bar {
            position: sticky; bottom: 0; z-index: 20; background: #fff; border-top: 1px solid #e2e8f0;
            padding: 10px 14px; margin-top: 14px; display: flex; align-items: center;
            justify-content: space-between; gap: 10px; border-radius: 10px 10px 0 0;
            box-shadow: 0 -2px 10px rgba(15,23,42,.05);
        }
    </style>

    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info border-0 shadow-sm mb-0">
                <div class="d-flex align-items-start">
                    <i class="bx bx-upload fs-4 me-2"></i>
                    <div>
                        <strong>Saat bazlı denetim Excel ile yapılır.</strong>
                        KASKİ okuma API'si yalnızca gün bazlı özet döndürdüğü için işe başlama/bitiş saati ve
                        okumalar arası boşluk analizi, personelin verdiği okuma listesi Excel'inden üretilir.
                        Günlük okuma hacmi ve sayaç durumu denetimi için yukarıdan
                        <strong>API'den sorgula</strong> kaynağını seçin.
                    </div>
                </div>
            </div>
        </div>
    </div>


    <?php if (!$veriVar): ?>
        <div class="row">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body text-center text-muted py-5">
                        <i class="mdi mdi-clock-outline fs-1 d-block mb-2"></i>
                        Henüz okuma listesi yüklenmedi. Saat bazlı analizin başlaması için yukarıdaki alana en az bir Excel dosyası bırakın.
                    </div>
                </div>
            </div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="" id="omFiltreForm">
                        <input type="hidden" name="p" value="puantaj/okuma-denetim">
                        <input type="hidden" name="kaynak" value="excel">
                        <input type="hidden" name="start_date" id="omStartDate" value="<?php echo Security::escape($baslangic); ?>">
                        <input type="hidden" name="end_date" id="omEndDate" value="<?php echo Security::escape($bitis); ?>">

                        <div class="row g-2 align-items-end">
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormDateRange('date_range', $dateRangeValue, 'Tarih Aralığı'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormSelect2('bolge', $bolgeOptions, $bolge, 'Bölge', 'map-pin', 'key', '', 'form-select select2'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormSelect2('ekip_kodu', $ekipOptions, $ekipKodu, 'Ekip', 'users', 'key', '', 'form-select select2'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormFloatInput('text', 'arama', $arama, 'Ekip / Mahalle Ara', 'Ekip / Mahalle Ara', 'search'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <?php echo Form::FormFloatInput('number', 'esik', $esikDakika, 'Şüpheli Boşluk Eşiği (dk)', 'Şüpheli Boşluk Eşiği (dk)', 'clock', 'form-control', false, null, 'off', false, 'min="5" max="480"'); ?>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm" style="padding: 11px 0;">
                                    <i class="mdi mdi-filter-variant me-1"></i> Filtrele
                                </button>
                            </div>
                        </div>

                        <div class="row g-2 align-items-end mt-1 pt-3 border-top">
                            <div class="col-lg-3 col-md-6">
                                <?php echo Form::FormSelect2('gorunum', $gorunumOptions, $gorunum, 'Görünüm', 'filter', 'key', '', 'form-select select2'); ?>
                            </div>
                            <div class="col-lg-9 col-md-6 text-lg-end">
                                <span class="badge bg-light text-dark border">
                                    Eşik: <?php echo $esikDakika; ?> dk &middot;
                                    Kritik: <?php echo $esikDakika * 2; ?> dk üzeri &middot;
                                    Yüklü veri: <?php echo Security::escape(Date::dmY($aralik->ilk)); ?> - <?php echo Security::escape(Date::dmY($aralik->son)); ?>
                                    (<?php echo number_format((int) $aralik->toplam, 0, ',', '.'); ?> okuma)
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
            ['deger' => number_format($genelOzet['okuma_sayisi'], 0, ',', '.'), 'baslik' => 'Toplam Okuma', 'renk' => 'ok-notr'],
            ['deger' => number_format($genelOzet['ekip_sayisi'], 0, ',', '.'), 'baslik' => 'Ekip Sayısı', 'renk' => 'ok-notr'],
            ['deger' => number_format($genelOzet['ekip_gun'], 0, ',', '.'), 'baslik' => 'Ekip-Gün Kaydı', 'renk' => 'ok-notr'],
            ['deger' => number_format($genelOzet['bosluk_sayisi'], 0, ',', '.'), 'baslik' => 'Şüpheli Boşluk', 'renk' => 'ok-uyari'],
            ['deger' => number_format($genelOzet['kritik_bosluk'], 0, ',', '.'), 'baslik' => 'Kritik Boşluk', 'renk' => 'ok-kotu'],
            ['deger' => $sure($genelOzet['bosluk_suresi']), 'baslik' => 'Toplam Boşluk Süresi', 'renk' => 'ok-kotu'],
            ['deger' => $sure($genelOzet['net_calisma']), 'baslik' => 'Net Çalışma', 'renk' => 'ok-iyi'],
        ];
        foreach ($ozetKutulari as $kutu): ?>
            <div class="col-lg col-md-3 col-6">
                <div class="om-ozet-kutu">
                    <div class="om-deger <?php echo $kutu["renk"]; ?>"><?php echo Security::escape($kutu['deger']); ?></div>
                    <div class="om-baslik"><?php echo Security::escape($kutu['baslik']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($bolgeOzeti)): ?>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Bölge Özeti</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle om-tablo mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Bölge</th>
                                        <th class="om-sayi">Ekip</th>
                                        <th class="om-sayi">Ekip-Gün</th>
                                        <th class="om-sayi">Okuma</th>
                                        <th class="om-sayi">Boşluk</th>
                                        <th class="om-sayi">Kritik</th>
                                        <th class="om-sayi">Sahada</th>
                                        <th class="om-sayi">Net Çalışma</th>
                                        <th class="om-sayi">Boşluk Oranı</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bolgeOzeti as $satir): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo Security::escape($satir['bolge']); ?></td>
                                            <td class="om-sayi"><?php echo (int) $satir['ekip_sayisi']; ?></td>
                                            <td class="om-sayi"><?php echo (int) $satir['ekip_gun']; ?></td>
                                            <td class="om-sayi"><?php echo number_format($satir['okuma_sayisi'], 0, ',', '.'); ?></td>
                                            <td class="om-sayi"><?php echo (int) $satir['bosluk_sayisi']; ?></td>
                                            <td class="om-sayi text-danger fw-semibold"><?php echo (int) $satir['kritik_bosluk']; ?></td>
                                            <td class="om-sayi"><?php echo Security::escape($sure($satir['sahada_sure'])); ?></td>
                                            <td class="om-sayi"><?php echo Security::escape($sure($satir['net_calisma'])); ?></td>
                                            <td class="om-sayi">%<?php echo number_format($satir['bosluk_orani'], 1, ',', '.'); ?></td>
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

    <?php if (empty($bolgeGruplari)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card mb-0">
                    <div class="card-body text-center text-muted py-5">
                        <i class="mdi mdi-magnify fs-1 d-block mb-2"></i>
                        Seçilen filtrelerde okuma kaydı bulunamadı.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($bolgeGruplari as $bolgeAdi => $bolgeSatirlari): ?>
        <?php
        $bolgeId = 'om-bolge-' . md5($bolgeAdi);
        $bolgeBosluk = array_sum(array_map(fn($s) => count($s['bosluklar']), $bolgeSatirlari));
        $bolgeKritik = array_sum(array_column($bolgeSatirlari, 'kritik_bosluk'));
        ?>
        <div class="om-bolge-baslik" data-bs-toggle="collapse" data-bs-target="#<?php echo $bolgeId; ?>">
            <div class="fw-bold">
                <i class="mdi mdi-chevron-down me-1"></i>
                <?php echo Security::escape($bolgeAdi); ?>
                <span class="text-muted fw-normal ms-2"><?php echo count($bolgeSatirlari); ?> ekip-gün</span>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($bolgeBosluk > 0): ?>
                    <span class="om-rozet bg-warning text-dark"><?php echo $bolgeBosluk; ?> boşluk</span>
                <?php endif; ?>
                <?php if ($bolgeKritik > 0): ?>
                    <span class="om-rozet bg-danger text-white"><?php echo $bolgeKritik; ?> kritik</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="collapse show" id="<?php echo $bolgeId; ?>">
            <?php foreach ($bolgeSatirlari as $satir): ?>
                <?php
                $kartId = 'om-kart-' . md5($satir['anahtar']);
                $serItBilgi = $serit($satir);
                $konum = $serItBilgi['konum'];
                ?>
                <div class="om-kart">
                    <div class="om-kart-baslik">
                        <div>
                            <div class="fw-bold">
                                <?php echo Security::escape($satir['ekip_kodu']); ?>
                                <span class="text-muted fw-normal"><?php echo Security::escape($satir['ekip_adi']); ?></span>
                                <span class="ms-2"><?php echo Security::escape(Date::dmY($satir['tarih'])); ?></span>
                                <span class="text-muted fw-normal">(<?php echo Security::escape(Date::gunAdi($satir['tarih'])); ?>)</span>
                                <?php if ($satir['kritik_bosluk'] > 0): ?>
                                    <span class="om-rozet bg-danger text-white ms-1"><?php echo $satir['kritik_bosluk']; ?> kritik boşluk</span>
                                <?php endif; ?>
                                <?php if ($satir['supheli_bosluk'] > 0): ?>
                                    <span class="om-rozet bg-warning text-dark ms-1"><?php echo $satir['supheli_bosluk']; ?> şüpheli boşluk</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-3 text-end flex-wrap">
                            <div>
                                <div class="fw-bold om-sayi"><?php echo date('H:i', $satir['ilk_okuma']); ?> - <?php echo date('H:i', $satir['son_okuma']); ?></div>
                                <div class="text-muted" style="font-size: 10px;">BAŞLANGIÇ - BİTİŞ</div>
                            </div>
                            <div>
                                <div class="fw-bold om-sayi"><?php echo Security::escape($sure($satir['sahada_sure'])); ?></div>
                                <div class="text-muted" style="font-size: 10px;">SAHADA</div>
                            </div>
                            <div>
                                <div class="fw-bold om-sayi"><?php echo Security::escape($sure($satir['net_calisma'])); ?></div>
                                <div class="text-muted" style="font-size: 10px;">NET ÇALIŞMA</div>
                            </div>
                            <div>
                                <div class="fw-bold om-sayi"><?php echo number_format($satir['okuma_sayisi'], 0, ',', '.'); ?></div>
                                <div class="text-muted" style="font-size: 10px;">OKUMA</div>
                            </div>
                            <div>
                                <div class="fw-bold om-sayi"><?php echo number_format($satir['okuma_hizi'], 1, ',', '.'); ?></div>
                                <div class="text-muted" style="font-size: 10px;">OKUMA/SAAT</div>
                            </div>
                            <div class="align-self-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?php echo $kartId; ?>">
                                    <i class="mdi mdi-format-list-bulleted me-1"></i> Okuma dökümü
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="om-kart-govde">
                        <div class="om-serit-kutu">
                            <?php foreach ($satir['bosluklar'] as $bosluk): ?>
                                <?php
                                $sol = $konum($bosluk['baslangic']);
                                $sag = $konum($bosluk['bitis']);
                                $genislik = max(0.3, $sag - $sol);
                                $baslik = date('H:i', $bosluk['baslangic']) . ' - ' . date('H:i', $bosluk['bitis'])
                                    . ' (' . $sure($bosluk['sure']) . ')';
                                ?>
                                <div class="om-bosluk-alan <?php echo $bosluk['seviye'] === OkumaMesaiAnalizService::SEVIYE_KRITIK ? 'om-bosluk-kritik' : 'om-bosluk-supheli'; ?>"
                                     style="left: <?php echo $sol; ?>%; width: <?php echo $genislik; ?>%;"
                                     title="<?php echo Security::escape($baslik); ?>"></div>
                            <?php endforeach; ?>

                            <?php foreach ($satir['okumalar'] as $okuma): ?>
                                <?php $sol = $konum(strtotime($okuma->okuma_zamani)); ?>
                                <div class="om-okuma-cizgi" style="left: <?php echo $sol; ?>%;"></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="om-cetvel">
                            <?php for ($s = $serItBilgi['baslangic_saat']; $s < $serItBilgi['bitis_saat']; $s++): ?>
                                <span><?php echo str_pad($s, 2, '0', STR_PAD_LEFT); ?></span>
                            <?php endfor; ?>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mt-3">
                            <?php for ($s = 6; $s <= 21; $s++): ?>
                                <?php $adet = $satir['saatlik_dagilim'][$s] ?? 0; ?>
                                <div class="om-saat-kutu <?php echo $adet === 0 ? 'om-saat-bos' : ''; ?>"
                                     style="<?php echo $adet > 0 ? 'background: rgba(22,163,74,' . min(0.75, 0.12 + $adet / 60) . ');' : ''; ?>"
                                     title="<?php echo str_pad($s, 2, '0', STR_PAD_LEFT); ?>:00 - <?php echo $adet; ?> okuma">
                                    <span style="font-weight:600;"><?php echo str_pad($s, 2, '0', STR_PAD_LEFT); ?></span>
                                    <span><?php echo $adet; ?></span>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <?php if (empty($satir['bosluklar'])): ?>
                            <div class="alert alert-success border-0 mt-3 mb-0 py-2 px-3 small">
                                <i class="mdi mdi-check-circle me-1"></i>
                                <?php echo $esikDakika; ?> dakikadan uzun boşluk yok.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-bordered align-middle om-tablo mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 130px;">Boşluk</th>
                                            <th style="width: 90px;">Süre</th>
                                            <th style="width: 80px;">Seviye</th>
                                            <th>Önceki Okuma</th>
                                            <th>Sonraki Okuma</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($satir['bosluklar'] as $bosluk): ?>
                                            <tr class="<?php echo $bosluk['seviye'] === OkumaMesaiAnalizService::SEVIYE_KRITIK ? 'table-danger' : 'table-warning'; ?>">
                                                <td class="fw-semibold">
                                                    <?php echo date('H:i', $bosluk['baslangic']); ?> &rarr; <?php echo date('H:i', $bosluk['bitis']); ?>
                                                </td>
                                                <td class="om-sayi fw-semibold"><?php echo Security::escape($sure($bosluk['sure'])); ?></td>
                                                <td>
                                                    <span class="om-rozet <?php echo $bosluk['seviye'] === OkumaMesaiAnalizService::SEVIYE_KRITIK ? 'bg-danger text-white' : 'bg-warning text-dark'; ?>">
                                                        <?php echo $bosluk['seviye'] === OkumaMesaiAnalizService::SEVIYE_KRITIK ? 'Kritik' : 'Şüpheli'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo Security::escape($bosluk['onceki']['mahalle']); ?>
                                                    <span class="text-muted">
                                                        Abone <?php echo Security::escape($bosluk['onceki']['abone_no']); ?>
                                                        &middot; Defter <?php echo Security::escape($bosluk['onceki']['defter']); ?>/<?php echo Security::escape($bosluk['onceki']['sayfa']); ?>
                                                        &middot; Sıra <?php echo Security::escape($bosluk['onceki']['sira_no']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo Security::escape($bosluk['sonraki']['mahalle']); ?>
                                                    <span class="text-muted">
                                                        Abone <?php echo Security::escape($bosluk['sonraki']['abone_no']); ?>
                                                        &middot; Defter <?php echo Security::escape($bosluk['sonraki']['defter']); ?>/<?php echo Security::escape($bosluk['sonraki']['sayfa']); ?>
                                                        &middot; Sıra <?php echo Security::escape($bosluk['sonraki']['sira_no']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="collapse mt-3" id="<?php echo $kartId; ?>">
                            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-sm table-bordered align-middle om-tablo mb-0">
                                    <thead class="table-light" style="position: sticky; top: 0; z-index: 2;">
                                        <tr>
                                            <th style="width: 70px;">Saat</th>
                                            <th style="width: 80px;">Fark</th>
                                            <th>Abone No</th>
                                            <th>Abone</th>
                                            <th>Mahalle</th>
                                            <th>Defter/Sayfa</th>
                                            <th>Sıra</th>
                                            <th>Sayaç Durumu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $oncekiZaman = null;
                                        $limit = 0;
                                        foreach ($satir['okumalar'] as $okuma):
                                            if (++$limit > 3000) {
                                                break;
                                            }
                                            $zaman = strtotime($okuma->okuma_zamani);
                                            $fark = $oncekiZaman === null ? null : $zaman - $oncekiZaman;
                                            $vurgu = ($fark !== null && $fark >= $esikDakika * 60) ? 'table-warning' : '';
                                            $oncekiZaman = $zaman;
                                            ?>
                                            <tr class="<?php echo $vurgu; ?>">
                                                <td class="fw-semibold"><?php echo date('H:i:s', $zaman); ?></td>
                                                <td class="om-sayi"><?php echo $fark === null ? '-' : Security::escape($sure($fark)); ?></td>
                                                <td><?php echo Security::escape($okuma->abone_no); ?></td>
                                                <td><?php echo Security::escape($okuma->abone_adsoyad); ?></td>
                                                <td><?php echo Security::escape($okuma->mahalle); ?></td>
                                                <td><?php echo Security::escape($okuma->defter); ?>/<?php echo Security::escape($okuma->sayfa); ?></td>
                                                <td><?php echo Security::escape($okuma->sira_no); ?></td>
                                                <td><?php echo Security::escape($okuma->sayac_durum); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($satir['okumalar']) > 3000): ?>
                                <div class="text-muted small mt-2">
                                    Ekranda ilk 3.000 satır gösteriliyor (toplam <?php echo number_format(count($satir['okumalar']), 0, ',', '.'); ?>).
                                    Tamamı için Excel çıktısını kullanın.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="om-alt-bar">
        <div class="small text-muted">
            <?php echo count($gosterilecek); ?> ekip-gün listeleniyor &middot;
            Eşik <?php echo $esikDakika; ?> dk &middot;
            Dosyalar sunucuda işlenir, ham Excel saklanmaz
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="omTumSatirlar">
                <label class="form-check-label small" for="omTumSatirlar">Tüm okuma satırlarını da ekle</label>
            </div>
            <a href="views/puantaj/okuma-mesai-excel.php?<?php echo Security::escape($excelQuery); ?>"
               class="btn btn-success fw-bold shadow-sm" id="omExcelLink" target="_blank">
                <i class="mdi mdi-file-excel me-1"></i> Excel olarak indir
            </a>
        </div>
    </div>

<script>
$(document).ready(function () {
    $(".flatpickr-range").flatpickr({
        mode: "range",
        locale: "tr",
        dateFormat: "d.m.Y",
        allowInput: true
    });

    $('#omFiltreForm').on('submit', function () {
        var deger = $('input[name="date_range"]').val() || '';
        var parcalar = deger.split(' - ');

        function cevir(tarih) {
            var p = (tarih || '').trim().split('.');
            if (p.length !== 3) return '';
            return p[2] + '-' + p[1] + '-' + p[0];
        }

        $('#omStartDate').val(cevir(parcalar[0]));
        $('#omEndDate').val(cevir(parcalar[1] || parcalar[0]));
        $('input[name="date_range"]').prop('disabled', true);
    });

    $('.om-bolge-baslik').on('click', function () {
        $(this).find('.mdi-chevron-down, .mdi-chevron-right')
            .toggleClass('mdi-chevron-down mdi-chevron-right');
    });

    var temelExcelUrl = $('#omExcelLink').attr('href');
    $('#omTumSatirlar').on('change', function () {
        $('#omExcelLink').attr('href', temelExcelUrl + (this.checked ? '&tum_satirlar=1' : ''));
    });

});
</script>
