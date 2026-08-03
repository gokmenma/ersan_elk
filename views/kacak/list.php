<?php
use App\Helper\Date;
use App\Helper\Form;
use App\Model\KacakKontrolModel;
use App\Model\PersonelModel;
use App\Service\Gate;

$Kacak = new KacakKontrolModel();
$Personel = new PersonelModel();

$bugun = date('Y-m-d');
$haftaBasi = date('Y-m-d', strtotime('monday this week'));
$son30Gun = date('Y-m-d', strtotime('-29 days'));

$personelOptions = $Personel->optionList('puantaj', $bugun);

$ilceler = KacakKontrolModel::ILCELER;
$bekleyenSayisi = $Kacak->getPendingCount();

$ilceOptions = ['' => 'Tüm İlçeler'];
foreach ($ilceler as $ilce) {
    $ilceOptions[$ilce] = $ilce;
}

$turOptions = ['' => 'Tüm Türler'];
foreach (KacakKontrolModel::TURLER as $tur) {
    $turOptions[$tur] = $tur;
}

// Modal içindeki satır şablonu için (boş seçenek metinleri farklı)
$ilceSatirOptions = ['' => 'İlçe Seçiniz'];
foreach ($ilceler as $ilce) {
    $ilceSatirOptions[$ilce] = $ilce;
}

$turSatirOptions = [];
foreach (KacakKontrolModel::TURLER as $tur) {
    $turSatirOptions[$tur] = $tur;
}

$yilOptions = [];
for ($y = (int) date('Y'); $y >= (int) date('Y') - 3; $y--) {
    $yilOptions[$y] = $y;
}

$ayAdlari = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
    7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
];

$yetkiDuzenle = Gate::allows('kacak_duzenle') || Gate::isSuperAdmin();
$yetkiOnay = Gate::allows('kacak_onay') || Gate::isSuperAdmin();
$yetkiIptal = Gate::allows('kacak_iptal') || Gate::isSuperAdmin();
$yetkiArsiv = Gate::allows('kacak_arsiv') || Gate::isSuperAdmin();
?>

<style>
    .kacak-ozet-serit {
        border-radius: 14px;
    }

    .kacak-ozet-ikon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .kacak-ozet-etiket {
        font-size: .68rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #94a3b8;
        white-space: nowrap;
    }

    .kacak-ozet-sayi {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .kacak-ozet-alt {
        font-size: .68rem;
        color: #94a3b8;
        white-space: nowrap;
    }

    .kacak-ozet-cip {
        font-size: .72rem;
        font-weight: 600;
        padding: .22rem .6rem;
        border-radius: 100px;
        border: 1px solid;
        white-space: nowrap;
    }

    .kacak-ozet-cip b {
        font-weight: 700;
        margin-left: .15rem;
    }

    /* Ayraçlar sadece tek satıra sığdığı geniş ekranlarda görünür */
    @media (min-width: 992px) {
        .kacak-ozet-ayrac {
            border-left: 1px solid var(--bs-border-color);
        }
    }

    .kacak-tabs .nav-link {
        border: none !important;
        border-radius: 100px !important;
        font-size: .78rem;
        font-weight: 600;
        padding: 8px 18px;
        color: #64748b;
    }

    .kacak-tabs .nav-link.active {
        background: #556ee6 !important;
        color: #fff !important;
        box-shadow: 0 4px 10px -2px rgba(85, 110, 230, .5);
    }

    .kacak-rapor-metin {
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .85rem;
        white-space: pre-wrap;
        line-height: 1.7;
        margin: 0;
    }

    .kacak-foto-thumb {
        width: 92px;
        height: 92px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid var(--bs-border-color);
    }

    .kacak-foto-item {
        position: relative;
    }

    .kacak-foto-item .btn-foto-sil {
        position: absolute;
        top: -6px;
        right: -6px;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        padding: 0;
        line-height: 1;
    }

    .ai-field-uncertain {
        background-color: #fff8e1 !important;
        border-color: #ffc107 !important;
    }

    .ai-field-confident {
        background-color: #f1fbf5 !important;
        border-color: #34c38f !important;
    }

    .ai-conf-badge {
        font-size: .62rem;
        font-weight: 700;
        padding: .12em .4em;
    }

    [data-bs-theme="dark"] .ai-field-uncertain {
        background-color: rgba(255, 193, 7, .12) !important;
    }

    [data-bs-theme="dark"] .ai-field-confident {
        background-color: rgba(52, 195, 143, .12) !important;
    }
</style>

<div class="container-fluid">
    <?php
    $maintitle = "İş Takip";
    $title = "Kaçak İşlemleri";
    include 'layouts/breadcrumb.php';
    ?>

    <!-- Özet şeridi: tek satır, üç grup -->
    <div class="card border-0 shadow-sm mb-3 kacak-ozet-serit">
        <div class="card-body py-3">
            <div class="row g-0 align-items-center">

                <!-- Aktif tutanaklar + tür kırılımı -->
                <div class="col-12 col-lg-6 d-flex align-items-center gap-3 px-3">
                    <span class="kacak-ozet-ikon bg-primary-subtle text-primary"><i class="bx bx-list-ul"></i></span>
                    <div>
                        <div class="kacak-ozet-etiket">Aktif Tutanak</div>
                        <div class="kacak-ozet-sayi" id="ozetAktif">0</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 ms-2">
                        <span class="kacak-ozet-cip text-danger border-danger-subtle">
                            Kaçak <b id="ozetKacak">0</b>
                        </span>
                        <span class="kacak-ozet-cip text-warning border-warning-subtle">
                            Abonesiz <b id="ozetAbonesiz">0</b>
                        </span>
                        <span class="kacak-ozet-cip text-info border-info-subtle">
                            Usülsüz <b id="ozetUsulsuz">0</b>
                        </span>
                    </div>
                </div>

                <!-- İptaller -->
                <div class="col-6 col-lg-3 d-flex align-items-center gap-3 px-3 kacak-ozet-ayrac">
                    <span class="kacak-ozet-ikon bg-secondary-subtle text-secondary"><i class="bx bx-x-circle"></i></span>
                    <div>
                        <div class="kacak-ozet-etiket">İptal</div>
                        <div class="kacak-ozet-sayi" id="ozetIptal">0</div>
                        <div class="kacak-ozet-alt">
                            hakedişten düşen: <b id="ozetIptalDusulen">0</b>
                        </div>
                    </div>
                </div>

                <!-- Bekleyen bildirimler (tıklayınca onay sekmesine gider) -->
                <div class="col-6 col-lg-3 d-flex align-items-center gap-3 px-3 kacak-ozet-ayrac">
                    <span class="kacak-ozet-ikon bg-info-subtle text-info"><i class="bx bx-time-five"></i></span>
                    <div>
                        <div class="kacak-ozet-etiket">Bekleyen Bildirim</div>
                        <div class="kacak-ozet-sayi" id="ozetBekleyen"><?= (int) $bekleyenSayisi ?></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-soft-info ms-auto" id="btnOnaylaraGit"
                        title="Bekleyen onaylara git">
                        <i class="bx bx-right-arrow-alt"></i>
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Sekmeler -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2">
            <ul class="nav nav-pills kacak-tabs" id="kacakTabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#pane-kayitlar"
                        type="button"><i class="bx bx-list-ul me-1"></i> Kayıtlar</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-onaylar"
                        type="button"><i class="bx bx-check-shield me-1"></i> Bekleyen Onaylar
                        <span class="badge bg-danger ms-1" id="bekleyenBadge"
                            <?= $bekleyenSayisi > 0 ? '' : 'style="display:none"' ?>><?= (int) $bekleyenSayisi ?></span>
                    </button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-iptaller"
                        type="button"><i class="bx bx-x-circle me-1"></i> İptaller</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-ekip-ozet"
                        type="button"><i class="bx bx-table me-1"></i> Ekip Özeti</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-gunluk"
                        type="button"><i class="bx bx-clipboard me-1"></i> Günlük Rapor</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-haftalik"
                        type="button"><i class="bx bx-bar-chart-alt-2 me-1"></i> Haftalık Rapor</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-teslim"
                        type="button"><i class="bx bx-printer me-1"></i> Teslim Alma Listesi</button></li>
                <?php if ($yetkiArsiv): ?>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pane-arsiv"
                            type="button"><i class="bx bx-archive me-1"></i> Fotoğraf Arşivi</button></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <div class="tab-content">

        <!-- ============ KAYITLAR ============ -->
        <div class="tab-pane fade show active" id="pane-kayitlar">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-2">
                            <?= Form::FormDate('filtre_baslangic', Date::dmY($son30Gun), 'Başlangıç Tarihi') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormDate('filtre_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormSelect2('filtre_ilce', $ilceOptions, '', 'İlçe', 'map-pin') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormSelect2('filtre_tur', $turOptions, '', 'Tür', 'tag') ?>
                        </div>
                        <div class="col-md-2">
                            <?= Form::FormFloatInput('text', 'filtre_arama', '', 'Tutanak no, abone, sayaç...', 'Ara', 'search') ?>
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1" id="btnFiltrele"><i
                                    class="bx bx-search me-1"></i>Filtrele</button>
                            <button class="btn btn-outline-success" id="btnKayitlarExcel" title="Excel İndir"><i
                                    class="bx bx-download"></i></button>
                            <?php if ($yetkiDuzenle): ?>
                                <button class="btn btn-success" id="btnYeniKacak" title="Yeni Kayıt"><i
                                        class="bx bx-plus"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="kacakTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Tutanak No</th>
                                    <th>Abone Adı</th>
                                    <th>İlçe</th>
                                    <th>Tür</th>
                                    <th>Sayaç No</th>
                                    <th class="text-center">Sayı</th>
                                    <th>Ekip</th>
                                    <th>Kaynak</th>
                                    <th class="text-center">Foto</th>
                                    <th class="text-center">Durum</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ BEKLEYEN ONAYLAR ============ -->
        <div class="tab-pane fade" id="pane-onaylar">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-center">
                        <i class="bx bx-info-circle fs-4 me-2"></i>
                        <div>Personel mobil uygulamasından gelen kaçak bildirimleri burada listelenir. Onaylanmayan
                            kayıtlar hakediş ve prim hesabına dahil edilmez.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="onayTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Bildiren</th>
                                    <th>Ekip</th>
                                    <th>Tutanak No</th>
                                    <th>Abone Adı</th>
                                    <th>İlçe</th>
                                    <th>Tür</th>
                                    <th class="text-center">Foto</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ İPTALLER ============ -->
        <div class="tab-pane fade" id="pane-iptaller">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-2"><?= Form::FormDate('iptal_baslangic', Date::dmY($son30Gun), 'Başlangıç') ?>
                        </div>
                        <div class="col-md-2"><?= Form::FormDate('iptal_bitis', Date::dmY($bugun), 'Bitiş') ?></div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100" id="btnIptalFiltrele"><i
                                    class="bx bx-search me-1"></i>Filtrele</button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="iptalTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarih</th>
                                    <th>Tutanak No</th>
                                    <th>Abone Adı</th>
                                    <th>İlçe</th>
                                    <th>Tür</th>
                                    <th class="text-center">Sayı</th>
                                    <th>İptal Açıklaması</th>
                                    <th class="text-center">Hakedişten Düş</th>
                                    <th>İptal Eden</th>
                                    <th class="text-center">Foto</th>
                                    <th class="text-center">İşlem</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ EKİP ÖZETİ (Ekip x Gün pivot) ============ -->
        <div class="tab-pane fade" id="pane-ekip-ozet">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="bx bx-table me-1 text-primary"></i> Ekip Bazlı Aylık Özet</h5>
                        <p class="text-muted small mb-0 mt-1">Seçilen ay için ekip × gün kırılımında tutanak sayıları. Gün kutucuğuna <strong>çift tıklayarak</strong> o tarih ve ekip için hızlıca yeni kayıt oluşturabilirsiniz.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
                        <div style="min-width: 150px; max-width: 175px;">
                            <?= Form::FormDate('ozet_donem_picker', date('Y-m'), 'Dönem', 'calendar', 'form-control flatpickr-month') ?>
                        </div>
                        <button type="button" class="btn btn-outline-warning btn-sm d-flex align-items-center gap-1" id="btnEkipOzetBolgeTopl">
                            <i class="bx bx-list-check me-1"></i> Bölge Topl. Göster
                        </button>
                        <?php if (\App\Service\Gate::allows('is_takip_ayarlar') || \App\Service\Gate::isSuperAdmin()): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm btn-tab-settings d-flex align-items-center justify-content-center" style="width: 34px; height: 34px; padding: 0;" data-tab="kacakkontrol" data-tab-name="Kaçak Kontrol" title="Kaçak Kontrol Ayarları">
                                <i class="bx bx-cog fs-5"></i>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-success btn-sm d-flex align-items-center justify-content-center" id="btnEkipOzetExcel" style="width: 34px; height: 34px; padding: 0;" title="Excel İndir">
                            <i class="bx bx-download fs-5"></i>
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <div id="ekipOzetIcerik">
                        <div class="text-center text-muted p-5">Raporu görmek için dönem seçin.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ GÜNLÜK RAPOR ============ -->
        <div class="tab-pane fade" id="pane-gunluk">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1"><i class="bx bx-clipboard me-1"></i> Günlük Rapor</h5>
                    <p class="text-muted small">Seçtiğiniz güne ait Kaçak, Sayaçlı Abonesiz ve Usülsüz tutanak sayılarını ilçe
                        bazında, panoya kopyalanabilir metin olarak üretir.</p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3"><?= Form::FormDate('gunluk_tarih', Date::dmY($bugun), 'Rapor Tarihi') ?>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="btnGunlukRapor"><i
                                    class="bx bx-refresh me-1"></i>Raporu Oluştur</button>
                        </div>
                    </div>

                    <div class="card bg-light border">
                        <div class="card-body position-relative">
                            <button class="btn btn-sm btn-soft-primary position-absolute top-0 end-0 m-2"
                                id="btnGunlukKopyala" title="Panoya kopyala"><i class="bx bx-copy"></i></button>
                            <pre class="kacak-rapor-metin" id="gunlukRaporMetin">Raporu oluşturmak için tarih seçip "Raporu Oluştur" butonuna basın.</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ HAFTALIK RAPOR ============ -->
        <div class="tab-pane fade" id="pane-haftalik">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1"><i class="bx bx-bar-chart-alt-2 me-1"></i> Haftalık Rapor</h5>
                    <p class="text-muted small">Seçtiğiniz tarih aralığı için "Bölge (İlçe) Bazlı Abonesiz / Kaçak / Usülsüz
                        Özeti" tablosunu görüntüler ve Excel olarak indirir.</p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <?= Form::FormDate('haftalik_baslangic', Date::dmY($haftaBasi), 'Başlangıç Tarihi') ?></div>
                        <div class="col-md-3"><?= Form::FormDate('haftalik_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="btnHaftalikRapor"><i
                                    class="bx bx-refresh me-1"></i>Raporu Getir</button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success w-100" id="btnHaftalikExcel"><i
                                    class="bx bx-download me-1"></i>Excel İndir</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle w-100" id="haftalikTable">
                            <thead class="table-light">
                                <tr>
                                    <th>İLÇE</th>
                                    <th class="text-end">ABONESİZ</th>
                                    <th class="text-end">KAÇAK</th>
                                    <th class="text-end">USÜLSÜZ</th>
                                    <th class="text-end">TOPLAM</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td>GENEL TOPLAM</td>
                                    <td class="text-end" id="haftalikToplamAbonesiz">0</td>
                                    <td class="text-end" id="haftalikToplamKacak">0</td>
                                    <td class="text-end" id="haftalikToplamUsulsuz">0</td>
                                    <td class="text-end" id="haftalikToplamGenel">0</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============ TESLİM ALMA LİSTESİ ============ -->
        <div class="tab-pane fade" id="pane-teslim">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-1"><i class="bx bx-printer me-1"></i> Haftalık Teslim Alma Listesi</h5>
                    <p class="small mb-1"><strong>Fiziki teslim alma:</strong> Onikişubat/Dulkadiroğlu'nda yazılan TÜM
                        tutanaklar (tüm türler) + diğer ilçelerdeki SADECE Kaçak evraklar.</p>
                    <p class="small text-muted">Foto çıktısı ise daha dar: sadece Onikişubat/Dulkadiroğlu'ndaki
                        <strong>Kaçak</strong> kayıtlar için — Abonesiz kayıtlarda evrak alınır ama fotoğraf çıktısı
                        gerekmez.
                    </p>

                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <?= Form::FormDate('teslim_baslangic', Date::dmY($haftaBasi), 'Başlangıç Tarihi') ?></div>
                        <div class="col-md-3"><?= Form::FormDate('teslim_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-primary w-100" id="btnTeslimListesi"><i
                                    class="bx bx-refresh me-1"></i>Listeyi Getir</button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-success w-100" id="btnTeslimExcel"><i
                                    class="bx bx-download me-1"></i>Excel İndir</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle nowrap w-100" id="teslimTable">
                            <thead class="table-light">
                                <tr>
                                    <th>TARİH</th>
                                    <th>TUTANAK NO</th>
                                    <th>MÜKELLEF ADI</th>
                                    <th>İLÇE</th>
                                    <th>DURUM</th>
                                    <th>EKİP</th>
                                    <th>SEBEP</th>
                                    <th class="text-center">FOTO ÇIKTISI</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($yetkiArsiv): ?>
            <!-- ============ FOTOĞRAF ARŞİVİ ============ -->
            <div class="tab-pane fade" id="pane-arsiv">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-1"><i class="bx bx-archive me-1"></i> Fotoğraf Arşivleme</h5>
                        <div class="alert alert-warning d-flex align-items-start mt-3">
                            <i class="bx bx-error-circle fs-4 me-2"></i>
                            <div>Belirttiğiniz dönemdeki kaçak fotoğrafları ilçe ve tür bazında klasörlenerek ZIP olarak
                                indirilecek ve <strong>sunucudan kalıcı olarak silinecektir</strong>. İndirilen dosyayı
                                güvenli bir yerde saklayın.</div>
                        </div>

                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <?= Form::FormDate('arsiv_baslangic', Date::dmY($son30Gun), 'Başlangıç Tarihi') ?></div>
                            <div class="col-md-3"><?= Form::FormDate('arsiv_bitis', Date::dmY($bugun), 'Bitiş Tarihi') ?>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-outline-primary w-100" id="btnArsivKontrol"><i
                                        class="bx bx-search me-1"></i>Dosyaları Kontrol Et</button>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-danger w-100" id="btnArsivle" disabled><i
                                        class="bx bx-archive-in me-1"></i>Arşivle ve Sunucudan Sil</button>
                            </div>
                        </div>

                        <div class="alert alert-secondary mt-3 mb-0 d-none" id="arsivSonuc"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ============ KAYIT MODALI ============ -->
<div class="modal fade" id="kacakModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="kacakModalTitle">Kaçak Kontrol Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="kacakForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="kacak_id" value="0">
                <div class="modal-body">

                    <div class="alert alert-info d-flex align-items-center d-none" id="bekleyenBildirimUyarisi">
                        <i class="bx bx-mobile-alt fs-4 me-2"></i>
                        <div>
                            Bu kayıt <strong id="bekleyenBildirenAdi"></strong> tarafından mobil uygulamadan
                            bildirildi ve <strong>onay bekliyor</strong>. Hatalı alanları düzeltip
                            "Kaydet ve Onayla" ile onaylayabilirsiniz.
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-3">
                        <h6 class="fw-bold text-primary mb-2"><i class="bx bx-bot me-1"></i> Yapay Zeka ile Tutanak Oku
                        </h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-9">
                                <?= Form::FormFileInput(
                                    name: 'tutanak_file',
                                    label: 'Tutanak Belgesi (Görsel, PDF veya Excel)',
                                    icon: 'upload',
                                    class: 'form-control'
                                ) ?>
                            </div>
                            <div class="col-md-3">
                                <button type="button" id="btnAnalizEt"
                                    class="btn btn-warning w-100 fw-bold d-flex align-items-center justify-content-center"
                                    style="height:38px">
                                    <i class="bx bx-play me-1 fs-5"></i> Analiz Et
                                </button>
                            </div>
                        </div>
                        <div class="form-text text-muted">Dosyayı seçtikten sonra "Analiz Et" butonuna basarak verileri
                            otomatik çıkartabilirsiniz. Seçilen dosya kayıtla birlikte tutanak belgesi olarak da
                            saklanır.</div>
                        <div id="analizSpinner" class="text-center p-2 mt-3 d-none">
                            <div class="spinner-border text-warning"></div>
                            <p class="mt-2 text-warning fw-bold mb-0">Tutanak analiz ediliyor, lütfen bekleyin...</p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <?= Form::FormDate('tarih', Date::today(), 'Tarih') ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormMultipleSelect2(
                                name: 'kacak_personel_ids',
                                options: $personelOptions,
                                selectedValues: [],
                                label: 'Personel Seçimi (En Fazla 2)',
                                icon: 'users',
                                valueField: 'key',
                                textField: '',
                                class: 'form-select select2-kacak-personel',
                                required: true,
                                id: 'kacak_personel_ids'
                            ) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFileInput(
                                name: 'saha_fotolari[]',
                                label: 'Saha Fotoğrafları (en fazla 4)',
                                icon: 'image',
                                class: 'form-control',
                                attributes: 'multiple accept="image/*"',
                                id: 'saha_fotolari'
                            ) ?>
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-bold text-primary"><i class="bx bx-list-plus me-1"></i> Giriş Detayları
                            </h6>
                            <button type="button" class="btn btn-sm btn-soft-success" id="btnSatirEkle"><i
                                    class="bx bx-plus me-1"></i> Satır Ekle</button>
                        </div>
                        <div id="kacakSatirlar"></div>

                        <!-- Satır şablonu: JS bu şablonu klonlar (alanlar App\Helper\Form ile üretilir) -->
                        <template id="kacakSatirTemplate">
                            <div class="border p-2 rounded mb-2 kacak-satir bg-white">
                                <div class="row g-2">
                                    <div class="col-md-2">
                                        <?= Form::FormSelect2('ilce[]', $ilceSatirOptions, '', 'İlçe', 'map-pin', 'key', '', 'form-select satir-ilce', true, 'width:100%', '', 'tpl_ilce') ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormSelect2('tur[]', $turSatirOptions, 'Kaçak', 'Tür', 'tag', 'key', '', 'form-select satir-tur', true, 'width:100%', '', 'tpl_tur') ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('text', 'tutanak_no[]', '', 'Tutanak No', 'Tutanak No', 'hash', 'form-control satir-tutanak_no', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-3">
                                        <?= Form::FormFloatInput('text', 'abone_adi[]', '', 'Abone Adı Soyadı', 'Abone Adı', 'user', 'form-control satir-abone_adi', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('text', 'sayac_no[]', '', 'Sayaç No', 'Sayaç No', 'cpu', 'form-control satir-sayac_no', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-1 d-flex align-items-center justify-content-end">
                                        <button type="button" class="btn btn-soft-danger btnSatirSil" title="Satırı sil">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </div>

                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('text', 'endeks[]', '', 'Endeks', 'Endeks', 'activity', 'form-control satir-endeks', false, null, 'off', false, '', false) ?>
                                    </div>
                                    <div class="col-md-2">
                                        <?= Form::FormFloatInput('number', 'sayi[]', '1', 'Sayı', 'Sayı', 'plus-circle', 'form-control satir-sayi', true, null, 'off', false, 'min="1"', false) ?>
                                    </div>
                                    <div class="col-md-8">
                                        <?= Form::FormFloatInput('text', 'aciklama[]', '', 'Açıklama', 'Açıklama', 'message-square', 'form-control satir-aciklama', false, null, 'off', false, '', false) ?>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div id="mevcutFotolarBolumu" class="d-none">
                        <h6 class="fw-bold text-primary mb-2"><i class="bx bx-images me-1"></i> Yüklü Belgeler</h6>
                        <div class="d-flex flex-wrap gap-3" id="mevcutFotolar"></div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                    <button type="button" class="btn btn-success d-none" id="btnKaydetVeOnayla">
                        <i class="bx bx-check me-1"></i>Kaydet ve Onayla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ İPTAL MODALI ============ -->
<div class="modal fade" id="iptalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tutanak İptali</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="iptalForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" id="iptal_id" value="0">
                <div class="modal-body">
                    <div class="alert alert-light border" id="iptalKayitBilgi"></div>

                    <div class="mb-3">
                        <?= Form::FormFloatTextarea(
                            name: 'iptal_aciklama',
                            value: '',
                            placeholder: 'Tutanağın neden iptal edildiğini yazın...',
                            label: 'İptal Açıklaması',
                            icon: 'edit-3',
                            required: true,
                            minHeight: '90px',
                            rows: 3
                        ) ?>
                    </div>

                    <div class="mb-3">
                        <?= Form::FormFileInput(
                            name: 'iptal_foto',
                            label: 'İptal Belgesi / Fotoğrafı',
                            icon: 'image',
                            class: 'form-control',
                            attributes: 'accept="image/*,application/pdf"'
                        ) ?>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="hakedisten_dus" name="hakedisten_dus"
                            value="1">
                        <label class="form-check-label fw-semibold" for="hakedisten_dus">Hakedişten düşülsün</label>
                        <div class="form-text">İşaretlenirse bu tutanak toplam sayıdan ve prim hesabından çıkarılır.
                            İşaretlenmezse kayıt iptal görünür ama hakedişte sayılmaya devam eder.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-danger">İptal Et</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============ FOTOĞRAF MODALI ============ -->
<div class="modal fade" id="fotoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kayıt Belgeleri</h5>
                <a href="#" id="btnFotoModalZip" class="btn btn-sm btn-success ms-auto me-2" title="Tüm Belgeleri ZIP İndir">
                    <i class="bx bx-download me-1"></i> ZIP İndir
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-3" id="fotoModalIcerik"></div>
            </div>
        </div>
    </div>
</div>

<!-- ============ KAÇAK KONTROL RAPOR AYARLARI MODALI ============ -->
<div class="modal fade" id="reportSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Kaçak Kontrol Rapor Ayarları</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reportSettingsForm">
                <input type="hidden" name="action" value="report-settings-kaydet">
                <div class="modal-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="bx bx-info-circle me-1"></i> Kaçak kontrol ekibi aralığını belirleryin.
                        <br><small>Birden fazla aralık için virgül kullanın (Örn: 51-60, 101-110)</small>
                    </div>
                    <div class="mb-3">
                        <?= \App\Helper\Form::FormFloatInput('text', 'ekip_aralik_kacak_kontrol', '51-60', '51-60', 'Kaçak Kontrol Ekip Aralığı', 'bx bx-search-alt') ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveReportSettings">Ayarları Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        const API = 'views/kacak/api.php';
        const ILCELER = <?= json_encode(KacakKontrolModel::ILCELER, JSON_UNESCAPED_UNICODE) ?>;
        const MAX_SAHA_FOTO = <?= KacakKontrolModel::MAX_SAHA_FOTO ?>;
        const YETKI = {
            duzenle: <?= $yetkiDuzenle ? 'true' : 'false' ?>,
            onay: <?= $yetkiOnay ? 'true' : 'false' ?>,
            iptal: <?= $yetkiIptal ? 'true' : 'false' ?>,
            arsiv: <?= $yetkiArsiv ? 'true' : 'false' ?>
        };

        let kacakTable, onayTable, iptalTable, teslimTable;

        function esc(v) {
            if (v === null || v === undefined) return '';
            return String(v).replace(/[&<>"']/g, m => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[m]));
        }

        function toIsoDate(str) {
            if (!str) return '';
            const p = String(str).trim().split(/[.\/-]/);
            if (p.length !== 3) return str;
            if (p[0].length === 4) return `${p[0]}-${p[1]}-${p[2]}`;
            return `${p[2]}-${p[1]}-${p[0]}`;
        }

        function apiGet(params) {
            return $.getJSON(API, params);
        }

        function hataGoster(res) {
            Swal.fire('Hata', (res && res.message) || 'İşlem tamamlanamadı.', 'error');
        }

        // ---------- Ortak DataTable ayarları ----------
        // Dil ve görünüm ayarları projenin merkezi helper'ından gelir (assets/js/datatables.init.js).
        function dtSecenekleri(ek) {
            var temel = (typeof getDatatableOptions === 'function') ? getDatatableOptions() : {};
            return $.extend(true, {}, temel, ek || {});
        }

        function turBadge(tur) {
            const renkler = { 'Kaçak': 'bg-danger', 'Abonesiz': 'bg-warning text-dark', 'Usülsüz': 'bg-info' };
            return `<span class="badge ${renkler[tur] || 'bg-secondary'}">${esc(tur)}</span>`;
        }

        function durumBadge(k) {
            if (k.onay_durumu === 'beklemede') return '<span class="badge bg-info">Onay Bekliyor</span>';
            if (k.onay_durumu === 'reddedildi') return '<span class="badge bg-secondary">Reddedildi</span>';
            if (k.durum === 'iptal') {
                return k.hakedisten_dus == 1
                    ? '<span class="badge bg-dark">İptal (Düşüldü)</span>'
                    : '<span class="badge bg-secondary">İptal</span>';
            }
            return '<span class="badge bg-success">Aktif</span>';
        }

        function kaynakBadge(kaynak) {
            const map = { pwa: ['bg-primary', 'Mobil'], masaustu: ['bg-light text-dark', 'Masaüstü'], excel: ['bg-light text-dark', 'Excel'] };
            const [cls, label] = map[kaynak] || ['bg-light text-dark', kaynak || '-'];
            return `<span class="badge ${cls}">${esc(label)}</span>`;
        }

        function fotoButonu(k) {
            const adet = parseInt(k.foto_sayisi || 0, 10);
            if (adet === 0) return '<span class="text-muted">-</span>';
            return `<div class="d-flex align-items-center justify-content-center gap-1">
                <button class="btn btn-sm btn-soft-info btn-foto" data-id="${k.id}" title="Fotoğrafları Görüntüle"><i class="bx bx-image me-1"></i>${adet}</button>
                <a href="${API}?action=download-zip&id=${k.id}" class="btn btn-sm btn-soft-success btn-foto-zip" title="Fotoğrafları ZIP Olarak İndir"><i class="bx bx-download"></i></a>
            </div>`;
        }

        // ---------- KAYITLAR ----------
        function kayitFiltreleri() {
            return {
                action: 'list',
                start_date: toIsoDate($('#filtre_baslangic').val()),
                end_date: toIsoDate($('#filtre_bitis').val()),
                ilce: $('#filtre_ilce').val(),
                tur: $('#filtre_tur').val(),
                arama: $('#filtre_arama').val(),
                durum: 'aktif',
                onay_durumu: 'onaylandi'
            };
        }

        function ozetGuncelle(ozet) {
            if (!ozet) return;
            $('#ozetAktif').text(ozet.aktif || 0);
            $('#ozetKacak').text(ozet.kacak || 0);
            $('#ozetAbonesiz').text(ozet.abonesiz || 0);
            $('#ozetUsulsuz').text(ozet.usulsuz || 0);
            $('#ozetIptal').text(ozet.iptal || 0);
            $('#ozetIptalDusulen').text(ozet.iptal_dusulen || 0);
            $('#ozetBekleyen').text(ozet.bekleyen || 0);
            const badge = $('#bekleyenBadge');
            if ((ozet.bekleyen || 0) > 0) badge.text(ozet.bekleyen).show(); else badge.hide();
        }

        function kayitlariYukle() {
            apiGet(kayitFiltreleri()).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                ozetGuncelle(res.ozet);

                const rows = res.data.map(k => [
                    esc(k.tarih_formatted),
                    esc(k.tutanak_no),
                    esc(k.abone_adi),
                    esc(k.ilce),
                    turBadge(k.tur),
                    esc(k.sayac_no),
                    esc(k.sayi),
                    esc(k.ekip_adi),
                    kaynakBadge(k.kaynak),
                    fotoButonu(k),
                    durumBadge(k),
                    kayitIslemButonlari(k)
                ]);

                if (kacakTable) {
                    kacakTable.clear().rows.add(rows).draw();
                } else {
                    kacakTable = $('#kacakTable').DataTable(dtSecenekleri({
                        data: rows, pageLength: 25, order: [[0, 'desc']],
                        columnDefs: [{ targets: [11], orderable: false }]
                    }));
                }
            }).fail(() => Swal.fire('Hata', 'Kayıtlar yüklenemedi.', 'error'));
        }

        function kayitIslemButonlari(k) {
            let html = '<div class="d-flex gap-1 justify-content-center">';
            if (YETKI.duzenle) {
                html += `<button class="btn btn-sm btn-soft-primary btn-duzenle" data-id="${k.id}" title="Düzenle"><i class="bx bx-edit"></i></button>`;
            }
            if (YETKI.iptal && k.durum !== 'iptal') {
                html += `<button class="btn btn-sm btn-soft-warning btn-iptal" data-id="${k.id}" title="İptal Et"><i class="bx bx-x-circle"></i></button>`;
            }
            if (YETKI.duzenle) {
                html += `<button class="btn btn-sm btn-soft-danger btn-sil" data-id="${k.id}" title="Sil"><i class="bx bx-trash"></i></button>`;
            }
            return html + '</div>';
        }

        // ---------- BEKLEYEN ONAYLAR ----------
        // Sahadan hatalı veri gelebildiği için yönetici onaylamadan önce kaydı düzeltebilir.
        function onayIslemButonlari(k) {
            if (!YETKI.onay && !YETKI.duzenle) {
                return '<span class="text-muted">-</span>';
            }

            let html = '<div class="d-flex gap-1 justify-content-center">';
            if (YETKI.duzenle) {
                html += `<button class="btn btn-sm btn-soft-primary btn-duzenle" data-id="${k.id}" title="Düzelt"><i class="bx bx-edit"></i></button>`;
            }
            if (YETKI.onay) {
                html += `<button class="btn btn-sm btn-success btn-onayla" data-id="${k.id}"><i class="bx bx-check"></i> Onayla</button>
                         <button class="btn btn-sm btn-danger btn-reddet" data-id="${k.id}"><i class="bx bx-x"></i> Reddet</button>`;
            }
            return html + '</div>';
        }

        function onaylariYukle() {
            apiGet({ action: 'list', start_date: '2000-01-01', end_date: '2099-12-31', onay_durumu: 'beklemede' })
                .done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);

                    const rows = res.data.map(k => [
                        esc(k.tarih_formatted),
                        esc(k.bildiren_adi || '-'),
                        esc(k.ekip_adi),
                        esc(k.tutanak_no),
                        esc(k.abone_adi),
                        esc(k.ilce),
                        turBadge(k.tur),
                        fotoButonu(k),
                        onayIslemButonlari(k)
                    ]);

                    if (onayTable) {
                        onayTable.clear().rows.add(rows).draw();
                    } else {
                        onayTable = $('#onayTable').DataTable(dtSecenekleri({
                            data: rows, pageLength: 25, order: [[0, 'desc']],
                            columnDefs: [{ targets: [8], orderable: false }]
                        }));
                    }
                });
        }

        // ---------- İPTALLER ----------
        function iptalleriYukle() {
            apiGet({
                action: 'list',
                start_date: toIsoDate($('#iptal_baslangic').val()),
                end_date: toIsoDate($('#iptal_bitis').val()),
                durum: 'iptal'
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                const rows = res.data.map(k => [
                    esc(k.tarih_formatted),
                    esc(k.tutanak_no),
                    esc(k.abone_adi),
                    esc(k.ilce),
                    turBadge(k.tur),
                    esc(k.sayi),
                    esc(k.iptal_aciklama),
                    k.hakedisten_dus == 1
                        ? '<span class="badge bg-dark">Düşüldü</span>'
                        : '<span class="badge bg-light text-dark">Düşülmedi</span>',
                    esc(k.iptal_eden_adi || '-'),
                    fotoButonu(k),
                    YETKI.iptal
                        ? `<button class="btn btn-sm btn-soft-success btn-iptal-geri" data-id="${k.id}"><i class="bx bx-undo"></i> Geri Al</button>`
                        : '<span class="text-muted">-</span>'
                ]);

                if (iptalTable) {
                    iptalTable.clear().rows.add(rows).draw();
                } else {
                    iptalTable = $('#iptalTable').DataTable(dtSecenekleri({
                        data: rows, pageLength: 25, order: [[0, 'desc']],
                        columnDefs: [{ targets: [10], orderable: false }]
                    }));
                }
            });
        }

        // ---------- MODAL: SATIR ----------
        function guvenSinifi(guven, alan) {
            if (!guven || guven[alan] === undefined) return '';
            return parseInt(guven[alan], 10) < 70 ? 'ai-field-uncertain' : 'ai-field-confident';
        }

        function guvenRozeti(guven, alan) {
            if (!guven || guven[alan] === undefined) return '';
            const v = parseInt(guven[alan], 10);
            const cls = v < 70 ? 'bg-warning text-dark' : 'bg-success';
            return `<span class="badge ai-conf-badge ${cls}">%${v}</span>`;
        }

        function satirEkle(data) {
            data = data || {};
            const guven = data.guven || null;
            const rowId = 'satir_' + Date.now() + '_' + Math.floor(Math.random() * 1000);

            const sablon = document.getElementById('kacakSatirTemplate');
            const $satir = $(sablon.content.cloneNode(true).firstElementChild);
            $satir.attr('id', rowId);

            // Şablondaki sabit id'ler klonlanınca tekilleştirilir (label[for] eşlemesiyle birlikte)
            $satir.find('[id]').each(function () {
                const eskiId = this.id;
                const yeniId = rowId + '_' + eskiId;
                $satir.find('label[for="' + eskiId + '"]').attr('for', yeniId);
                this.id = yeniId;
            });

            const alanlar = ['ilce', 'tur', 'tutanak_no', 'abone_adi', 'sayac_no', 'endeks', 'sayi', 'aciklama'];
            alanlar.forEach(function (alan) {
                const $el = $satir.find('.satir-' + alan);
                if (!$el.length) return;

                const deger = data[alan];
                if (deger !== undefined && deger !== null && deger !== '') {
                    $el.val(deger);
                }

                const sinif = guvenSinifi(guven, alan);
                if (sinif) $el.addClass(sinif);

                const rozet = guvenRozeti(guven, alan);
                if (rozet) {
                    $satir.find('label[for="' + $el.attr('id') + '"]').append(' ' + rozet);
                }
            });

            if (!$satir.find('.satir-sayi').val()) {
                $satir.find('.satir-sayi').val(1);
            }

            $('#kacakSatirlar').append($satir);

            $satir.find('.satir-ilce, .satir-tur').select2({
                dropdownParent: $('#kacakModal'),
                width: '100%'
            });

            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        }

        $(document).on('input change', '#kacakSatirlar .ai-field-uncertain', function () {
            $(this).removeClass('ai-field-uncertain').addClass('ai-field-confident');
        });

        $('#btnSatirEkle').on('click', () => satirEkle());

        $(document).on('click', '.btnSatirSil', function () {
            if ($('.kacak-satir').length > 1) {
                $(this).closest('.kacak-satir').remove();
            } else {
                Swal.fire('Uyarı', 'En az bir satır bulunmalıdır.', 'warning');
            }
        });

        // ---------- MODAL: AÇ / KAYDET ----------
        function modalSifirla() {
            $('#kacakForm')[0].reset();
            $('#kacak_id').val(0);
            $('#kacakSatirlar').empty();
            $('#mevcutFotolarBolumu').addClass('d-none');
            $('#mevcutFotolar').empty();
            $('#kacak_personel_ids').val(null).trigger('change');
        }

        $('#btnYeniKacak').on('click', function () {
            modalSifirla();
            $('#kacakModalTitle').text('Yeni Kaçak Kontrol Kaydı');
            satirEkle();
            $('#kacakModal').modal('show');
        });

        $(document).on('click', '.btn-duzenle', function () {
            const id = $(this).data('id');
            apiGet({ action: 'get-record', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const k = res.data;

                modalSifirla();
                $('#kacak_id').val(k.id);
                $('#kacakForm input[name="tarih"]').val(k.tarih_formatted);
                $('#kacak_personel_ids').val(k.personel_ids_array).trigger('change');
                satirEkle(k);
                fotolariBas($('#mevcutFotolar'), k.fotograflar, k.id);
                if ((k.fotograflar || []).length) $('#mevcutFotolarBolumu').removeClass('d-none');

                // Onay bekleyen (mobil) bildirimlerde düzelt-ve-onayla akışı
                const onayBekliyor = k.onay_durumu === 'beklemede';
                $('#kacakModalTitle').text(onayBekliyor
                    ? 'Bildirimi Düzelt ve Onayla'
                    : 'Kaçak Kontrol Kaydını Düzenle');

                $('#bekleyenBildirimUyarisi').toggleClass('d-none', !onayBekliyor)
                    .find('#bekleyenBildirenAdi').text(k.bildiren_adi || 'Bilinmiyor');

                $('#btnKaydetVeOnayla').toggleClass('d-none', !(onayBekliyor && YETKI.onay));

                $('#kacakModal').modal('show');
            });
        });

        function kacakFormGonder(onaylaSonrasinda) {
            const secili = $('#kacak_personel_ids').val() || [];
            if (secili.length === 0) {
                Swal.fire('Uyarı', 'En az bir personel seçmelisiniz.', 'warning');
                return;
            }
            if (secili.length > 2) {
                Swal.fire('Uyarı', 'En fazla 2 personel seçebilirsiniz.', 'warning');
                return;
            }

            const form = document.getElementById('kacakForm');
            if (!form.reportValidity()) return;

            const sahaInput = document.getElementById('saha_fotolari');
            if (sahaInput && sahaInput.files.length > MAX_SAHA_FOTO) {
                Swal.fire('Uyarı', `En fazla ${MAX_SAHA_FOTO} saha fotoğrafı yükleyebilirsiniz.`, 'warning');
                return;
            }

            const kayitId = parseInt($('#kacak_id').val(), 10) || 0;
            const fd = new FormData(form);
            fd.set('tarih', toIsoDate($('#kacakForm input[name="tarih"]').val()));

            const $butonlar = $('#kacakForm button[type="submit"], #btnKaydetVeOnayla');
            $butonlar.prop('disabled', true);

            $.ajax({
                url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                // Düzeltme sonrası doğrudan onaylama istendiyse aynı akışta onayı da geç
                if (onaylaSonrasinda && kayitId > 0) {
                    $.post(API, { action: 'approve', id: kayitId }, null, 'json').done(function (onay) {
                        if (onay.status !== 'success') return hataGoster(onay);
                        $('#kacakModal').modal('hide');
                        Swal.fire('Onaylandı', 'Kayıt düzeltildi ve onaylandı.', 'success');
                        listeleriTazele();
                    }).fail(() => Swal.fire('Hata', 'Onaylama sırasında bir hata oluştu.', 'error'))
                        .always(() => $butonlar.prop('disabled', false));
                    return;
                }

                $('#kacakModal').modal('hide');
                Swal.fire('Başarılı', res.message, 'success');
                listeleriTazele();
                $butonlar.prop('disabled', false);
            }).fail(function () {
                Swal.fire('Hata', 'Kayıt sırasında bir hata oluştu.', 'error');
                $butonlar.prop('disabled', false);
            });
        }

        function listeleriTazele() {
            kayitlariYukle();
            if (onayTable) onaylariYukle();
            if (ekipOzetYuklendi) ekipOzetiYukle();
        }

        $('#kacakForm').on('submit', function (e) {
            e.preventDefault();
            kacakFormGonder(false);
        });

        $('#btnKaydetVeOnayla').on('click', function () {
            kacakFormGonder(true);
        });

        // ---------- YAPAY ZEKA ANALİZİ ----------
        $('#btnAnalizEt').on('click', function () {
            const input = document.getElementById('tutanak_file');
            if (!input || input.files.length === 0) {
                return Swal.fire('Uyarı', 'Lütfen önce analiz edilecek tutanak dosyasını seçin.', 'warning');
            }

            const fd = new FormData();
            fd.append('action', 'analyze');
            fd.append('tutanak_file', input.files[0]);
            fd.append('tarih', toIsoDate($('#kacakForm input[name="tarih"]').val()));

            $('#analizSpinner').removeClass('d-none');
            $(this).prop('disabled', true);

            $.ajax({
                url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json'
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                $('#kacakSatirlar').empty();
                const satirlar = res.data || [];
                if (satirlar.length === 0) {
                    satirEkle();
                    return Swal.fire('Bilgi', 'Tutanaktan veri çıkartılamadı, lütfen elle doldurun.', 'info');
                }

                satirlar.forEach(s => satirEkle(s));

                if (satirlar[0].tarih) {
                    const p = satirlar[0].tarih.split('-');
                    $('#kacakForm input[name="tarih"]').val(`${p[2]}.${p[1]}.${p[0]}`);
                }

                const pIds = satirlar[0].personel_ids || [];
                if (pIds.length > 0) {
                    $('#kacak_personel_ids').val(pIds.map(String)).trigger('change');
                }

                Swal.fire('Analiz Tamamlandı', res.message, 'success');
            }).fail(() => Swal.fire('Hata', 'Analiz sırasında bir hata oluştu.', 'error'))
                .always(() => {
                    $('#analizSpinner').addClass('d-none');
                    $('#btnAnalizEt').prop('disabled', false);
                });
        });

        // ---------- ONAY / RED ----------
        $(document).on('click', '.btn-onayla', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Bildirimi onayla?',
                text: 'Onaylanan kayıt hakediş ve prim hesabına dahil edilir.',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'Onayla', cancelButtonText: 'Vazgeç'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'approve', id: id }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Onaylandı', res.message, 'success');
                    onaylariYukle();
                    kayitlariYukle();
                });
            });
        });

        $(document).on('click', '.btn-reddet', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Bildirimi reddet',
                input: 'textarea',
                inputLabel: 'Red nedeni',
                inputPlaceholder: 'Reddetme nedenini yazın...',
                showCancelButton: true,
                confirmButtonText: 'Reddet', cancelButtonText: 'Vazgeç',
                inputValidator: v => (!v || !v.trim()) ? 'Red nedeni zorunludur.' : undefined
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'reject', id: id, red_nedeni: r.value }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Reddedildi', res.message, 'success');
                    onaylariYukle();
                });
            });
        });

        // ---------- İPTAL ----------
        $(document).on('click', '.btn-iptal', function () {
            const id = $(this).data('id');
            apiGet({ action: 'get-record', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                const k = res.data;

                $('#iptalForm')[0].reset();
                $('#iptal_id').val(k.id);
                $('#iptalKayitBilgi').html(
                    `<strong>${esc(k.tarih_formatted)}</strong> &middot; Tutanak No: <strong>${esc(k.tutanak_no || '-')}</strong><br>
                     ${esc(k.abone_adi || '-')} &middot; ${esc(k.ilce || '-')} &middot; ${esc(k.tur)}`
                );
                $('#iptalModal').modal('show');
            });
        });

        $('#iptalForm').on('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            if (!$('#hakedisten_dus').is(':checked')) fd.set('hakedisten_dus', '0');

            const btn = $(this).find('button[type="submit"]');
            btn.prop('disabled', true);

            $.ajax({ url: API, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    $('#iptalModal').modal('hide');
                    Swal.fire('İptal Edildi', res.message, 'success');
                    kayitlariYukle();
                    iptalleriYukle();
                }).fail(() => Swal.fire('Hata', 'İptal işlemi başarısız.', 'error'))
                .always(() => btn.prop('disabled', false));
        });

        $(document).on('click', '.btn-iptal-geri', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'İptali geri al?',
                text: 'Kayıt yeniden aktif hâle gelecek ve hakedişte sayılacak.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Geri Al', cancelButtonText: 'Vazgeç'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'revert-cancel', id: id }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Başarılı', res.message, 'success');
                    iptalleriYukle();
                    kayitlariYukle();
                });
            });
        });

        // ---------- SİLME ----------
        $(document).on('click', '.btn-sil', function () {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Kayıt silinsin mi?',
                text: 'Bu işlem geri alınamaz.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#f46a6a'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'delete', id: id }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    Swal.fire('Silindi', res.message, 'success');
                    kayitlariYukle();
                });
            });
        });

        // ---------- FOTOĞRAFLAR ----------
        function fotolariBas($hedef, fotolar, kacakId) {
            $hedef.empty();
            if (!fotolar || fotolar.length === 0) {
                $hedef.html('<p class="text-muted mb-0">Bu kayıt için yüklü belge bulunmuyor.</p>');
                return;
            }

            const turEtiket = { tutanak: 'Tutanak', saha: 'Saha', iptal: 'İptal Belgesi' };

            fotolar.forEach(f => {
                const url = 'views/kacak/foto-goruntule.php?id=' + f.id;
                const pdfMi = /\.pdf$/i.test(f.dosya_yolu);
                const onizleme = pdfMi
                    ? `<div class="kacak-foto-thumb d-flex align-items-center justify-content-center bg-light"><i class="bx bxs-file-pdf fs-1 text-danger"></i></div>`
                    : `<img src="${url}" class="kacak-foto-thumb" alt="${esc(turEtiket[f.tur] || f.tur)}">`;

                const silBtn = YETKI.arsiv
                    ? `<button type="button" class="btn btn-danger btn-sm btn-foto-sil" data-foto-id="${f.id}" data-kacak-id="${kacakId}" title="Sil"><i class="bx bx-x"></i></button>`
                    : '';

                $hedef.append(`
                    <div class="kacak-foto-item text-center">
                        <a href="${url}" target="_blank" rel="noopener">${onizleme}</a>
                        ${silBtn}
                        <div class="small text-muted mt-1">${esc(turEtiket[f.tur] || f.tur)}</div>
                    </div>`);
            });
        }

        $(document).on('click', '.btn-foto', function () {
            const id = $(this).data('id');
            $('#btnFotoModalZip').attr('href', API + '?action=download-zip&id=' + id);
            apiGet({ action: 'get-photos', id: id }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                fotolariBas($('#fotoModalIcerik'), res.data, id);
                $('#fotoModal').modal('show');
            });
        });

        $(document).on('click', '.btn-foto-sil', function () {
            const fotoId = $(this).data('foto-id');
            const kacakId = $(this).data('kacak-id');
            const $item = $(this).closest('.kacak-foto-item');

            Swal.fire({
                title: 'Fotoğraf silinsin mi?',
                text: 'Dosya sunucudan kalıcı olarak silinecek.',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#f46a6a'
            }).then(r => {
                if (!r.isConfirmed) return;
                $.post(API, { action: 'delete-photo', foto_id: fotoId }, null, 'json').done(function (res) {
                    if (res.status !== 'success') return hataGoster(res);
                    $item.remove();
                    kayitlariYukle();
                });
            });
        });

        // ---------- GÜNLÜK RAPOR ----------
        $('#btnGunlukRapor').on('click', function () {
            apiGet({ action: 'gunluk-rapor', tarih: toIsoDate($('#gunluk_tarih').val()) }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                $('#gunlukRaporMetin').text(res.metin);
            });
        });

        $('#btnGunlukKopyala').on('click', function () {
            const metin = $('#gunlukRaporMetin').text();
            navigator.clipboard.writeText(metin).then(
                () => Swal.fire({ icon: 'success', title: 'Kopyalandı', timer: 1200, showConfirmButton: false }),
                () => Swal.fire('Hata', 'Panoya kopyalanamadı.', 'error')
            );
        });

        // ---------- HAFTALIK RAPOR ----------
        $('#btnHaftalikRapor').on('click', function () {
            apiGet({
                action: 'haftalik-rapor',
                start_date: toIsoDate($('#haftalik_baslangic').val()),
                end_date: toIsoDate($('#haftalik_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                const tbody = $('#haftalikTable tbody').empty();
                let tA = 0, tK = 0, tU = 0;

                (res.data || []).forEach(r => {
                    tA += parseInt(r.abonesiz, 10);
                    tK += parseInt(r.kacak, 10);
                    tU += parseInt(r.usulsuz, 10);
                    tbody.append(`<tr>
                        <td class="fw-semibold">${esc((r.ilce || 'Belirtilmemiş').toLocaleUpperCase('tr-TR'))}</td>
                        <td class="text-end">${esc(r.abonesiz)}</td>
                        <td class="text-end">${esc(r.kacak)}</td>
                        <td class="text-end">${esc(r.usulsuz)}</td>
                        <td class="text-end">${esc(r.toplam)}</td>
                    </tr>`);
                });

                if ((res.data || []).length === 0) {
                    tbody.append('<tr><td colspan="5" class="text-center text-muted">Kayıt bulunamadı.</td></tr>');
                }

                $('#haftalikToplamAbonesiz').text(tA);
                $('#haftalikToplamKacak').text(tK);
                $('#haftalikToplamUsulsuz').text(tU);
                $('#haftalikToplamGenel').text(tA + tK + tU);
            });
        });

        $('#btnHaftalikExcel').on('click', function () {
            const q = $.param({
                tip: 'ozet',
                start_date: toIsoDate($('#haftalik_baslangic').val()),
                end_date: toIsoDate($('#haftalik_bitis').val())
            });
            window.location.href = 'views/kacak/export-haftalik.php?' + q;
        });

        // ---------- TESLİM ALMA LİSTESİ ----------
        $('#btnTeslimListesi').on('click', function () {
            apiGet({
                action: 'teslim-alma-listesi',
                start_date: toIsoDate($('#teslim_baslangic').val()),
                end_date: toIsoDate($('#teslim_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);

                const rows = (res.data || []).map(r => [
                    esc(r.tarih_formatted),
                    esc(r.tutanak_no),
                    esc(r.abone_adi),
                    esc((r.ilce || '').toLocaleUpperCase('tr-TR')),
                    turBadge(r.tur),
                    esc(r.ekip_adi),
                    esc(r.sebep),
                    r.foto_cikti_gerekli == 1
                        ? '<span class="badge bg-primary">GEREKLİ</span>'
                        : '<span class="text-muted">-</span>'
                ]);

                if (teslimTable) {
                    teslimTable.clear().rows.add(rows).draw();
                } else {
                    teslimTable = $('#teslimTable').DataTable(dtSecenekleri({
                        data: rows, pageLength: 50, order: [[3, 'asc'], [0, 'asc']]
                    }));
                }
            });
        });

        $('#btnTeslimExcel').on('click', function () {
            const q = $.param({
                tip: 'teslim',
                start_date: toIsoDate($('#teslim_baslangic').val()),
                end_date: toIsoDate($('#teslim_bitis').val())
            });
            window.location.href = 'views/kacak/export-haftalik.php?' + q;
        });

        // ---------- EKİP ÖZETİ (Özet Raporlar'dan taşındı) ----------
        let ekipOzetYuklendi = false;
        let currentOzetYear = new Date().getFullYear();
        let currentOzetMonth = new Date().getMonth() + 1;

        function ekipOzetiYukle(year, month) {
            const y = year || currentOzetYear;
            const m = month || currentOzetMonth;

            currentOzetYear = y;
            currentOzetMonth = m;

            const $kutu = $('#ekipOzetIcerik');
            $kutu.html('<div class="text-center p-5"><div class="spinner-border text-primary"></div><p class="mt-2 text-muted">Rapor hazırlanıyor...</p></div>');

            $.get('views/puantaj/api.php', {
                action: 'get-report-table',
                tab: 'kacakkontrol',
                year: y,
                month: m,
                filter_type: 'period'
            }).done(function (html) {
                $kutu.html(html);
                ekipOzetYuklendi = true;

                // İç taraftaki varsayılan ikon/legend çubuğunu gizle
                $kutu.find('#workTypeLegend').addClass('d-none');

                // Bölge Toplamı buton durumunu sıfırla
                $('#btnEkipOzetBolgeTopl').removeClass('active btn-warning').addClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Göster');

                if (typeof feather !== 'undefined') feather.replace();
            }).fail(function () {
                $kutu.html('<div class="alert alert-danger mb-0">Rapor yüklenirken bir hata oluştu.</div>');
            });
        }

        // Flatpickr Ay Seçici İlklendirme
        function initOzetDonemPicker() {
            const el = document.getElementById('ozet_donem_picker');
            if (!el) return;

            if (el._flatpickr) {
                el._flatpickr.destroy();
            }

            const pluginFunc = window.monthSelectPlugin || (typeof monthSelectPlugin !== 'undefined' ? monthSelectPlugin : null);

            flatpickr(el, {
                locale: 'tr',
                dateFormat: 'Y-m',
                altInput: true,
                altFormat: 'F Y',
                defaultDate: `${currentOzetYear}-${String(currentOzetMonth).padStart(2, '0')}`,
                plugins: pluginFunc ? [new pluginFunc({ shorthand: false, dateFormat: "Y-m", altFormat: "F Y", theme: "light" })] : [],
                onChange: function (selectedDates, dateStr) {
                    if (dateStr) {
                        const parts = dateStr.split('-');
                        if (parts.length === 2) {
                            currentOzetYear = parseInt(parts[0], 10);
                            currentOzetMonth = parseInt(parts[1], 10);
                            ekipOzetiYukle(currentOzetYear, currentOzetMonth);
                        }
                    }
                }
            });
        }

        $('#btnEkipOzetExcel').on('click', function () {
            const q = $.param({
                tab: 'kacakkontrol',
                year: currentOzetYear,
                month: currentOzetMonth,
                filter_type: 'period'
            });
            window.location.href = 'views/puantaj/rapor-excel.php?' + q;
        });

        // Bölge Toplamları Göster/Gizle Butonu
        $(document).on('click', '#btnEkipOzetBolgeTopl', function () {
            const $reportBtn = $('#btnToggleRegionTotals');
            if ($reportBtn.length) {
                $reportBtn.trigger('click');
                const isHidden = $reportBtn.hasClass('active');
                if (isHidden) {
                    $(this).addClass('active btn-warning').removeClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Gizle');
                } else {
                    $(this).removeClass('active btn-warning').addClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Göster');
                }
            } else {
                const $btn = $(this);
                const $table = $('#raporTable');
                const rows = $table.find('tbody tr[data-region-id]');
                if (rows.length > 0) {
                    const isVisible = rows.first().is(':visible');
                    if (isVisible) {
                        rows.addClass('d-none');
                        $btn.removeClass('active btn-warning').addClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Göster');
                    } else {
                        rows.removeClass('d-none');
                        $btn.addClass('active btn-warning').removeClass('btn-outline-warning').html('<i class="bx bx-list-check me-1"></i> Bölge Topl. Gizle');
                    }
                }
            }
        });

        // Ayarlar Butonu ve Formu
        $(document).on('click', '.btn-tab-settings', function () {
            $('#reportSettingsModal').modal('show');
        });

        $('#reportSettingsForm').on('submit', function (e) {
            e.preventDefault();
            const btn = $('#btnSaveReportSettings');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Kaydediliyor...');

            $.ajax({
                url: 'views/puantaj/api.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json'
            }).done(function (res) {
                if (res.status === 'success') {
                    $('#reportSettingsModal').modal('hide');
                    Swal.fire('Başarılı', res.message, 'success');
                    if (ekipOzetYuklendi) ekipOzetiYukle();
                } else {
                    hataGoster(res);
                }
            }).fail(() => Swal.fire('Hata', 'Ayarlar kaydedilemedi.', 'error'))
                .always(() => btn.prop('disabled', false).text('Ayarları Kaydet'));
        });

        // Gün kutucuğuna çift tıklayınca o tarih + ekip için yeni kayıt modalı
        $(document).on('dblclick', '#ekipOzetIcerik .kacak-quick-cell', function () {
            if (!YETKI.duzenle) return;

            const $h = $(this);
            const tarih = $h.data('date');
            const personelIds = String($h.data('personel-ids') || '')
                .split(',').map(s => s.trim()).filter(Boolean);

            modalSifirla();
            $('#kacakModalTitle').text('Hızlı Kaçak Kontrol Kaydı');

            if (tarih) {
                const p = String(tarih).split('-');
                $('#kacakForm input[name="tarih"]').val(p.length === 3 ? `${p[2]}.${p[1]}.${p[0]}` : tarih);
            }
            if (personelIds.length) {
                $('#kacak_personel_ids').val(personelIds).trigger('change');
            }

            satirEkle();
            $('#kacakModal').modal('show');
        });

        // ---------- ARŞİV ----------
        $('#btnArsivKontrol').on('click', function () {
            apiGet({
                action: 'archive-preview',
                start_date: toIsoDate($('#arsiv_baslangic').val()),
                end_date: toIsoDate($('#arsiv_bitis').val())
            }).done(function (res) {
                if (res.status !== 'success') return hataGoster(res);
                $('#arsivSonuc').removeClass('d-none')
                    .html(`Seçilen tarih aralığında <b>${res.count}</b> adet dosya bulundu.`);
                $('#btnArsivle').prop('disabled', res.count === 0);
            });
        });

        $('#btnArsivle').on('click', function () {
            Swal.fire({
                title: 'Emin misiniz?',
                text: 'Bu işlem belirtilen tarih aralığındaki tüm fotoğrafları arşivleyip sunucudan kalıcı olarak silecektir!',
                icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Evet, arşivle ve sil', cancelButtonText: 'Vazgeç', confirmButtonColor: '#f46a6a'
            }).then(r => {
                if (!r.isConfirmed) return;
                const q = $.param({
                    action: 'archive-download',
                    start_date: toIsoDate($('#arsiv_baslangic').val()),
                    end_date: toIsoDate($('#arsiv_bitis').val())
                });
                window.location.href = API + '?' + q;
                $('#btnArsivle').prop('disabled', true);
                $('#arsivSonuc').html('Arşiv indiriliyor... İndirme tamamlandığında dosyalar sunucudan silinmiş olacaktır.');
            });
        });

        // ---------- BAŞLANGIÇ ----------
        $('#btnOnaylaraGit').on('click', function () {
            $('#kacakTabs button[data-bs-target="#pane-onaylar"]').tab('show');
        });

        $('#btnFiltrele').on('click', kayitlariYukle);
        $('#btnKayitlarExcel').on('click', function () {
            const params = kayitFiltreleri();
            params.tip = 'kayitlar';
            delete params.action;
            window.location.href = 'views/kacak/export-haftalik.php?' + $.param(params);
        });
        $('#btnIptalFiltrele').on('click', iptalleriYukle);
        $('#filtre_arama').on('keypress', e => { if (e.which === 13) kayitlariYukle(); });

        $('#kacakTabs button').on('shown.bs.tab', function () {
            const hedef = $(this).data('bs-target');
            if (hedef === '#pane-onaylar' && !onayTable) onaylariYukle();
            if (hedef === '#pane-iptaller' && !iptalTable) iptalleriYukle();
            if (hedef === '#pane-ekip-ozet' && !ekipOzetYuklendi) ekipOzetiYukle();
        });

        $(document).on('shown.bs.modal', '.modal', function () {
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });

        $(function () {
            $('.select2-kacak-personel').select2({
                dropdownParent: $('#kacakModal'),
                placeholder: 'Personel seçin (en fazla 2)',
                maximumSelectionLength: 2,
                width: '100%'
            });

            initOzetDonemPicker();

            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            kayitlariYukle();
        });
    })();
</script>
