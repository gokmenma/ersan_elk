<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;
use App\Helper\Security;
use App\Model\MaliyetRaporuModel;
use App\Model\MaliyetKalemEslestirmeModel;
use App\Model\ManuelGiderModel;
use App\Service\Gate;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$tl = static fn($n, $d = 2) => number_format((float) $n, $d, ',', '.');

$MaliyetRaporu = new MaliyetRaporuModel();
$ManuelGider   = new ManuelGiderModel();
$eslestirmeYetkisi = Gate::allows('maliyet_raporu_eslestirme');

$selectedYil = (int) ($_GET['yil'] ?? date('Y'));
if ($selectedYil < 2000 || $selectedYil > (int) date('Y') + 1) {
    $selectedYil = (int) date('Y');
}
$selectedAy = isset($_GET['ay']) ? (int) $_GET['ay'] : (int) date('n');
if ($selectedAy < 1 || $selectedAy > 12) {
    $selectedAy = 0;
}
$selectedKalem = (string) ($_GET['kalem'] ?? '');
if (!MaliyetRaporuModel::kalemGecerliMi($selectedKalem)) {
    $selectedKalem = '';
}

[$baslangic, $bitis] = MaliyetRaporuModel::tarihAraligi($selectedYil, $selectedAy);

$kalemler = MaliyetRaporuModel::raporKalemleri();
$ozet = $MaliyetRaporu->kalemOzet($baslangic, $bitis);
$matris = $MaliyetRaporu->aylikKalem($selectedYil);
$kv = $MaliyetRaporu->kurumlarVergisi($ozet, $baslangic, $bitis);
$eslesmemis = $MaliyetRaporu->eslesmemisFaturalar($baslangic, $bitis);
$manuelGiderler = $ManuelGider->getFiltered($baslangic, $bitis, null);

$genelToplam = array_sum(array_column($ozet, 'toplam'));
$grupToplam = static fn(array $keys) => array_sum(array_map(static fn($k) => $ozet[$k]['toplam'] ?? 0, $keys));
$personelToplam = $grupToplam(['maas', 'sgk', 'vergi']);
$aracToplam = $grupToplam(['arac_kirasi', 'yakit', 'arac_bakim']);
$digerToplam = $grupToplam(array_merge(['telefon_internet', 'malzeme_demirbas', 'ofis_diger'],
    array_values(array_filter(array_keys($kalemler), static fn($k) => strpos($k, 'ozel_') === 0))));
$eslesmemisToplam = array_sum(array_map(static fn($r) => (float) $r->toplam, $eslesmemis));
$eslesmemisAdet = array_sum(array_map(static fn($r) => (int) $r->adet, $eslesmemis));
$oran = static fn($v) => $genelToplam > 0 ? round($v / $genelToplam * 100, 1) : 0;

$yilSecenekleri = [];
for ($y = (int) date('Y'); $y >= (int) date('Y') - 5; $y--) {
    $yilSecenekleri[$y] = (string) $y;
}
$aySecenekleri = ['0' => 'Tüm Yıl', '1' => 'Ocak', '2' => 'Şubat', '3' => 'Mart', '4' => 'Nisan', '5' => 'Mayıs', '6' => 'Haziran',
    '7' => 'Temmuz', '8' => 'Ağustos', '9' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'];
$ayKisa = [1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz', 7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara'];
$donemEtiketi = $selectedAy ? $aySecenekleri[(string) $selectedAy] . ' ' . $selectedYil : $selectedYil . ' Yılı';

$kalemSecenekleri = [];
foreach (MaliyetRaporuModel::kalemler() as $k => $cfg) {
    $kalemSecenekleri[$k] = $cfg['ad'];
}
$yeniKalemSecenegi = ['__yeni__' => '+ Yeni Kalem Ekle'];
$kalemSecenekleriHaric = $kalemSecenekleri + [MaliyetRaporuModel::HARIC => 'Rapora Dahil Etme'] + (Gate::allows('maliyet_raporu_eslestirme') ? $yeniKalemSecenegi : []);
$atamaSecenekleri = ['' => 'Firma kuralına bırak'] + $kalemSecenekleriHaric;

$maintitle = 'Raporlar';
$title = 'Maliyet Raporu';
?>
<script>try { document.documentElement.classList.toggle('maliyet-summary-hidden', localStorage.getItem('maliyet_summary_cards_state') === 'hidden'); } catch (e) {}</script>
<style>
#summaryCardsContainer { overflow: hidden; max-height: 1100px; opacity: 1; transition: max-height .3s ease, opacity .3s ease, margin .3s ease; }
.maliyet-summary-hidden #summaryCardsContainer { max-height: 0 !important; opacity: 0; margin-top: 0 !important; margin-bottom: 0 !important; pointer-events: none; }
@media (prefers-reduced-motion: reduce) { #summaryCardsContainer { transition: none; } }
.maliyet-aylik-tablo th, .maliyet-aylik-tablo td { padding: .7rem .75rem; }
.maliyet-aylik-tablo tfoot td { background: #f8fafc; }
.yk-renk { outline: 2px solid transparent; outline-offset: 2px; transition: outline-color .15s; }
.yk-renk-input:checked + .yk-renk { outline-color: #0f172a; }
.mk-check { position: relative; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; margin: 0; }
.mk-check input { position: absolute; opacity: 0; width: 100%; height: 100%; margin: 0; cursor: pointer; }
.mk-check-box { width: 20px; height: 20px; border: 2px solid #cbd5e1; border-radius: 6px; background: #fff; display: inline-flex; align-items: center; justify-content: center; transition: background .15s, border-color .15s, box-shadow .15s; }
.mk-check-box::after { content: ''; width: 5px; height: 10px; border: solid #fff; border-width: 0 2px 2px 0; transform: rotate(45deg) scale(0); margin-top: -2px; transition: transform .15s; }
.mk-check:hover .mk-check-box { border-color: #475569; }
.mk-check input:focus-visible + .mk-check-box { box-shadow: 0 0 0 3px rgba(71, 85, 105, .25); }
.mk-check input:checked + .mk-check-box { background: #475569; border-color: #475569; }
.mk-check input:checked + .mk-check-box::after { transform: rotate(45deg) scale(1); }
.mk-check input:indeterminate + .mk-check-box { background: #475569; border-color: #475569; }
.mk-check input:indeterminate + .mk-check-box::after { width: 10px; height: 0; border-width: 0 0 2px 0; transform: none; margin-top: 0; }
#eslesmemisTable tbody tr.mk-secili td { background-color: #f1f5f9 !important; }
.kv-gider-kart { cursor: pointer; border-radius: 8px; padding: 6px 8px; margin: -6px -8px; transition: background-color .15s ease; }
.kv-gider-kart:hover, .kv-gider-kart:focus-visible { background-color: #f8fafc; outline: none; }
.kv-detay-tablo th { font-size: 12px; font-weight: 700; text-transform: uppercase; color: #64748b; background: #f8fafc; white-space: nowrap; }
.kv-detay-tablo td { font-size: 13px; color: #334155; vertical-align: middle; }
.kv-detay-fatura-kutu { max-height: 340px; overflow: auto; }
.kv-detay-fatura-kutu thead th { position: sticky; top: 0; z-index: 1; }
.maliyet-kalem-row { cursor: pointer; }
.maliyet-kalem-row.active td { background-color: #f1f5f9 !important; }
.maliyet-kalem-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.maliyet-oran-bar { height: 6px; border-radius: 999px; background: #f1f5f9; overflow: hidden; min-width: 80px; }
.maliyet-oran-bar span { display: block; height: 100%; border-radius: 999px; }
#maliyetDetayTable tbody tr { cursor: default; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>
<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-lg-5 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-pie-chart-alt-2 fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Maliyet Raporu</h4>
                <p class="text-muted mb-0 font-size-12">Bordro, fatura, yakıt ve kasa giderlerinin kalem bazlı dökümü · <?= $h($donemEtiketi) ?></p>
            </div>
        </div>
        <div class="personel-action-toolbar col-lg-7 col-12 d-flex align-items-center justify-content-lg-end flex-wrap gap-2 mt-2 mt-lg-0">
            <div style="min-width: 110px;">
                <?= Form::FormSelect2(name: 'yilSelect', options: $yilSecenekleri, selectedValue: (string) $selectedYil, label: 'Yıl', icon: 'calendar') ?>
            </div>
            <div style="min-width: 140px;">
                <?= Form::FormSelect2(name: 'aySelect', options: $aySecenekleri, selectedValue: (string) $selectedAy, label: 'Ay', icon: 'calendar') ?>
            </div>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" id="manuelGiderEkle" data-bs-toggle="modal" data-bs-target="#manuelGiderModal">
                <i class="bx bx-plus font-size-16"></i> Manuel Gider
            </button>
            <?php if ($eslestirmeYetkisi): ?>
            <button type="button" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" id="btnEslestirme">
                <i class="bx bx-link font-size-16 text-primary"></i> <span class="d-none d-sm-inline">Kalem Eşleştirme</span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" id="btnToggleSummaryCards" title="Özet kartları gizle" aria-label="Özet kartları gizle" aria-expanded="true">
                <i class="bx bx-chevron-up"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3 summary-cards-group" id="summaryCardsContainer">
        <?php
        $kartlar = [
            ['TOPLAM GİDER', $genelToplam, 'bx-trending-up', 'primary', $donemEtiketi . ' · tahmini kurumlar vergisi dahil'],
            ['PERSONEL', $personelToplam, 'bx-group', 'danger', 'Maaş + SGK + vergi · %' . $tl($oran($personelToplam), 1)],
            ['ARAÇ', $aracToplam, 'bx-car', 'info', 'Kira + yakıt + bakım · %' . $tl($oran($aracToplam), 1)],
            ['OFİS VE DİĞER', $digerToplam, 'bx-briefcase', 'warning', 'Telefon + malzeme + ofis · %' . $tl($oran($digerToplam), 1)],
        ];
        foreach ($kartlar as [$etiket, $deger, $ikon, $renk, $alt]):
        ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
                    <div class="d-flex justify-content-between align-items-start">
                        <div><span class="summary-kpi-label"><?= $h($etiket) ?></span><h3 class="summary-kpi-value my-1"><?= $tl($deger) ?> ₺</h3></div>
                        <div class="summary-kpi-icon bg-<?= $renk ?>-subtle text-<?= $renk ?>"><i class="bx <?= $ikon ?>"></i></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center border-top pt-1 mt-1">
                        <span class="summary-kpi-subtext"><?= $h($alt) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($eslesmemisAdet > 0): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 mb-3" role="alert">
        <i class="bx bx-error-circle font-size-18"></i>
        <span class="font-size-13">
            <strong><?= count($eslesmemis) ?></strong> firmadan gelen <strong><?= $eslesmemisAdet ?></strong> fatura
            (<?= $tl($eslesmemisToplam) ?> ₺) henüz bir maliyet kalemine bağlı değil ve rapora dahil edilmedi.
        </span>
        <?php if ($eslestirmeYetkisi): ?>
        <button type="button" class="btn btn-sm btn-warning ms-auto" id="btnEslesmemisAc">
            <i class="bx bx-link"></i> Firmaları Eşleştir
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 order-2">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-header d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-list-check font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Kalem Dağılımı</h5>
                        <p class="text-muted mb-0 font-size-12">Satıra tıklayarak detay listesini filtreleyin</p>
                    </div>
                </div>
                <div class="card-body p-0">
                  <div class="row g-0">
                    <div class="col-lg-4 px-3 pt-3 pb-2 border-end d-flex align-items-center"><div id="kalemDagilimChart" class="w-100"></div></div>
                    <div class="col-lg-8"><div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="kalemOzetTable">
                            <tbody>
                            <?php foreach ($kalemler as $k => $cfg):
                                $deger = $ozet[$k]['toplam'] ?? 0;
                                $yuzde = $oran($deger);
                            ?>
                                <tr class="maliyet-kalem-row<?= $selectedKalem === $k ? ' active' : '' ?>" data-kalem="<?= $h($k) ?>" title="<?= $h($cfg['kaynak']) ?>">
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="maliyet-kalem-dot" style="background: <?= $h($cfg['renk']) ?>;"></span>
                                            <div>
                                                <div class="fw-medium text-dark"><?= $h($cfg['ad']) ?></div>
                                                <div class="font-size-11 text-secondary"><?= $h($cfg['kaynak']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="width: 110px;">
                                        <div class="maliyet-oran-bar"><span style="width: <?= $h(min(100, $yuzde)) ?>%; background: <?= $h($cfg['renk']) ?>;"></span></div>
                                        <div class="font-size-11 text-secondary mt-1">%<?= $tl($yuzde, 1) ?></div>
                                    </td>
                                    <td class="text-end text-nowrap fw-semibold pe-3"><?= $tl($deger) ?> ₺</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td class="ps-3 fw-bold">TOPLAM</td>
                                    <td></td>
                                    <td class="text-end text-nowrap fw-bold pe-3"><?= $tl($genelToplam) ?> ₺</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div></div>
                  </div>
                </div>
            </div>
        </div>

        <div class="col-12 order-1">
            <div class="card summary-kpi-card h-100 mb-0">
                <div class="card-header d-flex align-items-center gap-2">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                        <i class="bx bx-bar-chart-alt-2 font-size-20"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Aylık Dağılım · <?= $h($selectedYil) ?></h5>
                        <p class="text-muted mb-0 font-size-12">Kalem bazında aylık gider toplamları (₺)</p>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0 font-size-14 maliyet-aylik-tablo">
                            <thead>
                                <tr>
                                    <th>Kalem</th>
                                    <?php for ($m = 1; $m <= 12; $m++): ?><th class="text-end"><?= $ayKisa[$m] ?></th><?php endfor; ?>
                                    <th class="text-end">Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $ayToplam = array_fill(1, 12, 0.0);
                            foreach ($kalemler as $k => $cfg):
                                $aylar = $matris[$k] ?? [];
                                if (!$aylar) {
                                    continue;
                                }
                            ?>
                                <tr>
                                    <td class="text-nowrap" title="<?= $h($cfg['kaynak'] ?? '') ?>"><span class="maliyet-kalem-dot me-1" style="background: <?= $h($cfg['renk']) ?>;"></span><?= $h($cfg['ad']) ?><?php if (!empty($cfg['kaynak'])): ?><div class="font-size-11 text-secondary fw-normal ms-3"><?= $h($cfg['kaynak']) ?></div><?php endif; ?></td>
                                    <?php for ($m = 1; $m <= 12; $m++):
                                        $v = $aylar[$m] ?? 0;
                                        $ayToplam[$m] += $v;
                                    ?>
                                        <td class="text-end text-nowrap<?= $v ? '' : ' text-muted' ?>"><?= $v ? $tl($v, 0) : '-' ?></td>
                                    <?php endfor; ?>
                                    <td class="text-end text-nowrap fw-semibold"><?= $tl(array_sum($aylar), 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold">
                                    <td>Toplam</td>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <td class="text-end text-nowrap"><?= $ayToplam[$m] ? $tl($ayToplam[$m], 0) : '-' ?></td>
                                    <?php endfor; ?>
                                    <td class="text-end text-nowrap"><?= $tl(array_sum($ayToplam), 0) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="kurumlarVergisiCard">
        <div class="card-header d-flex align-items-center gap-2">
            <div class="p-2 bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                <i class="bx bx-calculator font-size-20"></i>
            </div>
            <div>
                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Tahmini Kurumlar Vergisi · <?= $h($donemEtiketi) ?></h5>
                <p class="text-muted mb-0 font-size-12">(KDV'siz gelir − maaş − SGK − KDV'siz gelen faturalar) × %<?= $tl($kv['oran'] * 100, 0) ?></p>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-stretch">
                <div class="col-6 col-xl-3">
                    <div class="summary-kpi-label">Gelir (KDV'siz giden fatura)</div>
                    <div class="summary-kpi-value text-success"><?= $tl($kv['gelir']) ?> ₺</div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="kv-gider-kart" id="kvGiderKart" role="button" tabindex="0" data-yil="<?= (int) $selectedYil ?>" data-ay="<?= (int) $selectedAy ?>" title="Gider detayını göster" aria-label="Gider detayını göster">
                        <div class="summary-kpi-label">Gider (Maaş + SGK + Fatura) <i class="bx bx-info-circle align-middle"></i></div>
                        <div class="summary-kpi-value text-danger"><?= $tl($kv['gider']) ?> ₺</div>
                        <div class="font-size-11 text-secondary mt-1">Maaş <?= $tl($kv['maas'], 0) ?> · SGK <?= $tl($kv['sgk'], 0) ?> · Fatura <?= $tl($kv['fatura_gider'], 0) ?></div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="summary-kpi-label"><?= $kv['kar'] >= 0 ? 'Kurumlar vergisi matrahı' : 'Dönem zararı' ?></div>
                    <div class="summary-kpi-value <?= $kv['kar'] >= 0 ? 'text-dark' : 'text-danger' ?>"><?= $tl($kv['kar']) ?> ₺</div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="summary-kpi-label">Kurumlar vergisi (%<?= $tl($kv['oran'] * 100, 0) ?>)</div>
                    <div class="summary-kpi-value text-warning"><?= $tl($kv['vergi']) ?> ₺</div>
                </div>
            </div>
            <p class="font-size-11 text-secondary mb-0 mt-3">Tahmini değerdir; KKEG, indirim/istisna, geçmiş yıl zararı ve mahsup edilecek geçici vergi hesaba katılmamıştır. Zarar durumunda vergi 0 gösterilir.</p>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3" id="maliyetListCard">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <div class="p-2 bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bx bx-list-ul font-size-20"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Gider Kayıtları</h5>
                    <p class="text-muted mb-0 font-size-12" id="maliyetListAltBaslik">
                        <?= $selectedKalem ? $h($kalemler[$selectedKalem]['ad']) . ' kalemi' : 'Tüm kalemler' ?> · <span id="maliyetKayitSayisi">0</span> kayıt
                    </p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2 py-1 rounded-3 fw-semibold<?= $selectedKalem ? '' : ' d-none' ?>" id="btnKalemFiltreTemizle">
                    <i class="bx bx-x"></i> <span class="font-size-12">Filtreyi Kaldır</span>
                </button>
                <button type="button" class="btn btn-sm btn-subtle-secondary px-2 py-1 d-flex align-items-center gap-1 rounded-3 fw-semibold" id="btnHeaderPrint" title="Tabloyu Yazdır">
                    <i class="bx bx-printer font-size-15"></i> <span class="d-none d-sm-inline font-size-12">Yazdır</span>
                </button>
            </div>
        </div>
        <div class="card-body p-3 pt-0">
            <div class="table-responsive">
                <table id="maliyetDetayTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0"
                       data-yil="<?= $h($selectedYil) ?>" data-ay="<?= $h($selectedAy ?: '') ?>" data-kalem="<?= $h($selectedKalem) ?>"
                       data-atayabilir="<?= $eslestirmeYetkisi ? '1' : '0' ?>">
                    <thead>
                        <tr>
                            <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                            <th data-filter="string">TARİH</th>
                            <th data-filter="string">KALEM</th>
                            <th data-filter="string">ALT KATEGORİ</th>
                            <th data-filter="string" class="text-end">TUTAR</th>
                            <th data-filter="string">KAYNAK</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">FİLTRELİ TOPLAM</th>
                            <th class="text-end text-nowrap" id="maliyetFiltreliToplam">0,00 ₺</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <div class="p-2 bg-warning-subtle text-warning rounded-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                <i class="bx bx-edit font-size-20"></i>
            </div>
            <div>
                <h5 class="card-title mb-0 font-size-14 fw-bold text-dark">Manuel Giderler</h5>
                <p class="text-muted mb-0 font-size-12">Fatura veya kasa kaydı olmayan giderler · <?= count($manuelGiderler) ?> kayıt</p>
            </div>
        </div>
        <div class="card-body p-3 pt-0">
            <?php if ($manuelGiderler): ?>
            <div class="table-responsive">
                <table id="manuelGiderTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
                    <thead>
                        <tr>
                            <th data-filter="none" class="text-center" style="width: 50px;">SIRA</th>
                            <th data-filter="string">TARİH</th>
                            <th data-filter="string">KALEM</th>
                            <th data-filter="string">ALT KATEGORİ</th>
                            <th data-filter="string" class="text-end">TUTAR</th>
                            <th data-filter="string">BELGE NO</th>
                            <th data-filter="string">AÇIKLAMA</th>
                            <th data-filter="none" class="text-center" style="width: 120px;">İŞLEMLER</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($manuelGiderler as $i => $mg):
                        $encId = Security::encrypt($mg->id);
                        $mgKalem = MaliyetRaporuModel::kalemGecerliMi($mg->kategori) ? $mg->kategori : null;
                        $mgRenk = $mgKalem ? $kalemler[$mgKalem]['renk'] : '#64748b';
                    ?>
                        <tr>
                            <td class="text-center"><span class="row-index-badge"><?= $i + 1 ?></span></td>
                            <td class="text-nowrap"><?= $h($mg->tarih ? date('d.m.Y', strtotime($mg->tarih)) : '-') ?></td>
                            <td><span class="badge rounded-pill px-2 py-1 font-size-11 fw-semibold" style="background: <?= $h($mgRenk) ?>1a; color: <?= $h($mgRenk) ?>;"><?= $h(MaliyetRaporuModel::kalemAdi($mgKalem ?? $mg->kategori)) ?></span></td>
                            <td><?= $h($mg->alt_kategori ?: '-') ?></td>
                            <td class="text-end text-nowrap fw-medium"><?= $tl($mg->tutar) ?> ₺</td>
                            <td><?= $h($mg->belge_no ?: '-') ?></td>
                            <td class="text-truncate" style="max-width: 260px;" title="<?= $h($mg->aciklama ?? '') ?>"><?= $h($mg->aciklama ?: '-') ?></td>
                            <td class="text-center">
                                <div class="action-btn-group">
                                    <button type="button" class="btn btn-subtle-warning table-action-btn manuel-gider-duzenle" data-id="<?= $h($encId) ?>" title="Düzenle"><i class="bx bx-edit"></i></button>
                                    <button type="button" class="btn btn-subtle-danger table-action-btn manuel-gider-sil" data-id="<?= $h($encId) ?>" title="Sil"><i class="bx bx-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted text-center py-4 mb-0">Seçilen dönemde manuel gider kaydı bulunmuyor.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="manuelGiderModal" tabindex="-1" aria-labelledby="manuelGiderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manuelGiderModalLabel">Yeni Manuel Gider</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="manuelGiderForm">
                <div class="modal-body">
                    <input type="hidden" name="manuel_gider_id" id="manuel_gider_id" value="0">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <?= Form::FormSelect2(name: 'kategori', options: $kalemSecenekleri + (Gate::allows('maliyet_raporu_eslestirme') ? $yeniKalemSecenegi : []), selectedValue: 'malzeme_demirbas', label: 'Maliyet Kalemi', icon: 'layers', required: true, id: 'mg_kategori') ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('text', 'alt_kategori', '', 'Alt kategori', 'Alt Kategori', 'tag') ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('text', 'tutar', '', 'Tutar giriniz', 'Tutar (₺)', 'dollar-sign', 'form-control money', true) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('text', 'tarih', '', 'Tarih seçiniz', 'Tarih', 'calendar', 'form-control flatpickr', true) ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatInput('text', 'belge_no', '', 'Fatura / belge no', 'Belge No', 'file') ?>
                        </div>
                        <div class="col-12">
                            <?= Form::FormFloatTextarea('aciklama', '', 'Açıklama giriniz', 'Açıklama', 'edit', 'form-control', false, '80px', 3) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($eslestirmeYetkisi): ?>
<div class="modal fade" id="eslestirmeModal" tabindex="-1" aria-labelledby="eslestirmeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="eslestirmeModalLabel">Maliyet Kalem Eşleştirme</h5>
                    <p class="text-muted font-size-12 mb-0">Gelen fatura firmalarını (VKN) ve Gelir-Gider kategorilerini maliyet kalemlerine bağlayın.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="eslestirmeForm" class="row g-2 align-items-end mb-3 p-2 border rounded-3 bg-light">
                    <div class="col-lg-3 col-md-6">
                        <?= Form::FormSelect2(name: 'kaynak', options: MaliyetKalemEslestirmeModel::KAYNAKLAR, selectedValue: 'fatura', label: 'Kaynak', icon: 'database', id: 'es_kaynak') ?>
                    </div>
                    <div class="col-lg-4 col-md-6 es-fatura-alan">
                        <?= Form::FormFloatInput('text', 'deger_vkn', '', 'VKN / TCKN', 'VKN / TCKN', 'hash', 'form-control', false, 11, 'off', false, 'inputmode="numeric"') ?>
                    </div>
                    <div class="col-lg-4 col-md-6 es-gelir-alan d-none">
                        <?= Form::FormSelect2(name: 'deger_kategori', options: ['' => 'Kategori seçiniz'], selectedValue: '', label: 'Gelir-Gider Kategorisi', icon: 'tag', id: 'es_kategori') ?>
                    </div>
                    <div class="col-lg-3 col-md-6">
                        <?= Form::FormSelect2(name: 'kalem', options: $kalemSecenekleriHaric, selectedValue: 'malzeme_demirbas', label: 'Maliyet Kalemi', icon: 'layers', id: 'es_kalem') ?>
                    </div>
                    <div class="col-lg-2 col-md-6 d-grid">
                        <input type="hidden" name="etiket" id="es_etiket" value="">
                        <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Kaydet</button>
                    </div>
                    <div class="col-12 font-size-12 text-secondary" id="es_etiket_bilgi"></div>
                </form>

                <ul class="nav nav-tabs mb-2" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabEslesmemis" type="button" role="tab">
                            Eşleşmemiş Firmalar <span class="badge bg-light text-dark border ms-1" id="eslesmemisSayi">0</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKurallar" type="button" role="tab">
                            Tanımlı Eşleştirmeler <span class="badge bg-light text-dark border ms-1" id="kuralSayi">0</span>
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tabEslesmemis" role="tabpanel">
                        <p class="font-size-12 text-secondary mb-2"><?= $h($donemEtiketi) ?> döneminde hiçbir kaleme bağlı olmayan gelen faturalar. “Seç” ile üstteki forma aktarın.</p>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <button type="button" class="btn btn-primary btn-sm" id="btnTopluEslestir" disabled><i class="bx bx-link"></i> Seçilenleri Eşleştir (<span id="topluSecilenSayi">0</span>)</button>
                            <span class="font-size-12 text-secondary">Üstteki “Maliyet Kalemi” seçimi uygulanır.</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 font-size-13" id="eslesmemisTable">
                                <thead><tr><th class="text-center" style="width: 44px;"><label class="mk-check mb-0"><input type="checkbox" id="eslesmemisHepsi" aria-label="Tümünü seç"><span class="mk-check-box"></span></label></th><th data-filter="string">FİRMA</th><th data-filter="string">VKN</th><th class="text-end">FATURA</th><th class="text-end">TUTAR</th><th data-filter="date">SON FATURA</th><th class="text-center" style="width: 90px;">İŞLEM</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabKurallar" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0 font-size-13" id="kuralTable">
                                <thead><tr><th data-filter="select">KAYNAK</th><th data-filter="string">EŞLEŞME</th><th data-filter="string">DEĞER</th><th data-filter="select">MALİYET KALEMİ</th><th class="text-center" style="width: 90px;">İŞLEM</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="faturaKalemModal" tabindex="-1" aria-labelledby="faturaKalemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="faturaKalemModalLabel">Fatura Maliyet Kalemi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="faturaKalemForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="fk_id" value="">
                    <p class="font-size-13 mb-3" id="fk_bilgi"></p>
                    <?= Form::FormSelect2(name: 'kalem', options: $atamaSecenekleri, selectedValue: '', label: 'Bu fatura için kalem', icon: 'layers', id: 'fk_kalem') ?>
                    <p class="font-size-12 text-secondary mt-2 mb-0">Yalnızca bu faturayı etkiler. Firmanın tüm faturaları için Kalem Eşleştirme ekranını kullanın.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save"></i> Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="yeniKalemModal" tabindex="-1" aria-labelledby="yeniKalemModalLabel" aria-hidden="true" style="z-index: 1075;">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="yeniKalemModalLabel">Yeni Maliyet Kalemi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="yeniKalemForm" autocomplete="off">
                <div class="modal-body">
                    <?= Form::FormFloatInput('text', 'yk_ad', '', 'Kalem adı', 'Kalem adı', 'tag', 'form-control', true, 60, 'off') ?>
                    <div class="font-size-12 text-secondary mt-3 mb-2">Renk</div>
                    <div class="d-flex flex-wrap gap-2" id="yk_renkler">
                        <?php foreach (\App\Model\MaliyetOzelKalemModel::RENKLER as $i => $renk): ?>
                            <label class="m-0" style="cursor:pointer;">
                                <input type="radio" name="renk" value="<?= $h($renk) ?>" class="d-none yk-renk-input"<?= $i === 0 ? ' checked' : '' ?>>
                                <span class="yk-renk d-block rounded-circle" style="width: 28px; height: 28px; background: <?= $h($renk) ?>;"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-danger font-size-12 mt-2 d-none" id="yk_hata"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-plus"></i> Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="kvGiderModal" tabindex="-1" aria-labelledby="kvGiderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="kvGiderModalLabel">Gider Detayı · <?= $h($donemEtiketi) ?></h5>
                    <div class="font-size-12 text-secondary">Maaş + SGK + KDV'siz gelen faturalar</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body" id="kvGiderModalBody">
                <div class="text-center text-secondary py-5">Yükleniyor...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
window.maliyetKalemler = <?= json_encode($kalemler, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.maliyetKalemDeger = <?= json_encode(array_map(static fn($k) => round((float)($ozet[$k]['toplam'] ?? 0), 2), array_keys($kalemler)), JSON_HEX_TAG) ?>;
window.maliyetKalemAnahtar = <?= json_encode(array_keys($kalemler), JSON_HEX_TAG) ?>;
</script>
<script src="<?= \App\Helper\Helper::assetVersion('views/maliyet-raporu/js/maliyet-raporu.js') ?>"></script>
