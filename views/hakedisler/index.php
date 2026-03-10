<?php
use App\Helper\Form;

$aylar = [
    1 => 'Ocak',
    2 => 'Şubat',
    3 => 'Mart',
    4 => 'Nisan',
    5 => 'Mayıs',
    6 => 'Haziran',
    7 => 'Temmuz',
    8 => 'Ağustos',
    9 => 'Eylül',
    10 => 'Ekim',
    11 => 'Kasım',
    12 => 'Aralık'
];
?>
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">Sözleşmeler ve Hakedişler</h4>
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="?p=home">Ana Sayfa</a></li>
                    <li class="breadcrumb-item active">Sözleşmeler</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="card-title">Sözleşme Listesi</h4>
                    <div>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal"
                            data-bs-target="#yeniSozlesmeModal">
                            <i class="bx bx-plus me-1"></i> Yeni Sözleşme
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="sozlesmeTable"
                        class="table table-bordered dt-responsive nowrap w-100 table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>İdare Adı</th>
                                <th>İşin Adı</th>
                                <th>Sözleşme Tarihi</th>
                                <th>Bitiş Tarihi</th>
                                <th>Sözleşme Bedeli</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data will be loaded via DataTables AJAX -->
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Yeni Sözleşme Modal -->
<div class="modal fade" id="yeniSozlesmeModal" tabindex="-1" aria-labelledby="yeniSozlesmeModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form id="yeniSozlesmeForm" class="modal-content">
            <input type="hidden" name="id" id="sozlesme_id">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white" id="yeniSozlesmeModalLabel">Sözleşme Tanımla/Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs nav-tabs-custom nav-justified" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#sozlesme-bilgileri-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="fas fa-home"></i></span>
                            <span class="d-none d-sm-block">1. Sözleşme Bilgileri</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#birim-fiyat-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="far fa-list-alt"></i></span>
                            <span class="d-none d-sm-block">2. Birim Fiyat Teklif Cetveli</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#fiyat-farki-tab" role="tab">
                            <span class="d-block d-sm-none"><i class="bx bx-calculator"></i></span>
                            <span class="d-none d-sm-block">3. Fiyat Farkı ve Kesintiler</span>
                        </a>
                    </li>
                </ul>

                <!-- Tab panes -->
                <div class="tab-content p-3 text-muted">
                    <!-- SÖZLEŞME BİLGİLERİ TAB -->
                    <div class="tab-pane active" id="sozlesme-bilgileri-tab" role="tabpanel">
                        <div class="p-3">
                            <div class="row">
                                <!-- GRUP 1: GENEL BİLGİLER -->
                                <div class="col-md-12 mb-4">
                                    <h6 class="text-primary d-flex align-items-center mb-3 fw-bold">
                                        <i data-feather="info" class="me-2 text-primary" style="width: 18px;"></i>
                                        Genel İş ve İdare Bilgileri
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'idare_adi', '', 'T.C. KASKİ GENEL MÜDÜRLÜĞÜ', 'İdare Adı', icon: 'home', required: true) ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'idare_baskanlik_adi', '', 'ABONE İŞLERİ DAİRE BAŞKANLIĞI', 'İdare Başkanlık Adı', icon: 'layers') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'isin_yuklenicisi', 'ER-SAN ELEKTRİK İNŞ. TAAH.TİC.LTD.ŞTİ.', 'Yüklenici Firma', 'İşin Yüklenicisi', icon: 'briefcase', required: true) ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatTextarea('yuklenici_adres', '', 'Firma Adresi', 'Yüklenici Adres', icon: 'map', rows: 2, minHeight: '60px') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('tel', 'yuklenici_tel', '', '05xx xxx xx xx', 'Yüklenici Tel', icon: 'phone') ?>
                                        </div>
                                        <div class="col-md-12">
                                            <?= Form::FormFloatTextarea('isin_adi', '', 'Sözleşmede geçen tam iş adı', 'İşin Adı', icon: 'file-text', required: true, rows: 2, minHeight: '80px') ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- GRUP 2: İHALE VE SÜRE BİLGİLERİ -->
                                <div class="col-md-7 border-end">
                                    <h6 class="text-info d-flex align-items-center mb-3 fw-bold">
                                        <i data-feather="calendar" class="me-2 text-info" style="width: 18px;"></i>
                                        İhale ve Süre Bilgileri
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'ihale_kayit_no', '', '2025/1219715 vb.', 'İhale Kayıt No', icon: 'hash') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'ihale_tarihi', '', '', 'İhale Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'yer_teslim_tarihi', '', '', 'Yer Teslim Tarihi', icon: 'map-pin', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'sozlesme_tarihi', '', '', 'Sözleşme Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('text', 'isin_bitecegi_tarih', '', '', 'İşin Biteceği Tarih', icon: 'calendar', class: 'form-control flatpickr') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatInput('number', 'isin_suresi', '', '422', 'İşin Süresi (Gün)', icon: 'clock') ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- GRUP 3: MALİ BİLGİLER VE DURUM -->
                                <div class="col-md-5">
                                    <h6 class="text-success d-flex align-items-center mb-3 fw-bold">
                                        <i data-feather="dollar-sign" class="me-2 text-success"
                                            style="width: 18px;"></i> Mali Bilgiler ve Durum
                                    </h6>
                                    <div class="mb-3">
                                        <?= Form::FormFloatInput('number', 'kesif_bedeli', '', '0.00', 'Keşif Bedeli (TL)', icon: 'dollar-sign', attributes: 'step="0.01"') ?>
                                    </div>
                                    <div class="mb-3">
                                        <?= Form::FormFloatInput('number', 'ihale_tenzilati', '', '0.168769', 'İhale Tenzilatı (%)', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                    <div class="mb-3">
                                        <?= Form::FormFloatInput('number', 'sozlesme_bedeli', '', '0.00', 'Sözleşme Bedeli (TL)', icon: 'credit-card', required: true, attributes: 'step="0.01"') ?>
                                    </div>
                                    <div class="">
                                        <?= Form::FormSelect2('durum', [
                                            'aktif' => 'Aktif',
                                            'pasif' => 'Pasif',
                                            'tamamlandi' => 'Tamamlandı'
                                        ], 'aktif', 'Durum', icon: 'activity') ?>
                                    </div>
                                </div>

                                <div class="col-md-12 mt-4">
                                    <div class="accordion" id="accordionEkstra">
                                        <div class="accordion-item shadow-none border">
                                            <h2 class="accordion-header" id="headingEkstra">
                                                <button class="accordion-button collapsed fw-bold text-primary px-3 py-2 bg-light bg-opacity-50" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEkstra" aria-expanded="false" aria-controls="collapseEkstra">
                                                    <i data-feather="info" class="me-2 text-primary" style="width: 18px;"></i> Excel Ön Kapak ve Geçici Kabul Bilgileri (İsteğe Bağlı)
                                                </button>
                                            </h2>
                                            <div id="collapseEkstra" class="accordion-collapse collapse" aria-labelledby="headingEkstra">
                                                <div class="accordion-body px-3 py-3 pb-0">
                                                    <div class="row">
                                                        <div class="col-md-6 mb-3">
                                                            <?= Form::FormFloatInput('text', 'yuzde_yirmi_fazla_is', '', 'Tarih ve Sayılı Onay/Karar', '% 20 Fazla İş (Onay/Karar No)', icon: 'file-plus') ?>
                                                        </div>
                                                        <div class="col-md-6 mb-3">
                                                            <?= Form::FormFloatInput('text', 'son_sure_uzatimi', '', '... Tarihi ve ... Sayılı', 'Son Süre Uzatımı (Olur vb.)', icon: 'clock') ?>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <?= Form::FormFloatInput('text', 'gecici_kabul_tarihi', '', '', 'Geçici Kabul Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <?= Form::FormFloatInput('text', 'gecici_kabul_itibar_tarihi', '', '', 'Kabul İtibar Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                                        </div>
                                                        <div class="col-md-4 mb-3">
                                                            <?= Form::FormFloatInput('text', 'gecici_kabul_onanma_tarihi', '', '', 'Kabul Onanma Tarihi', icon: 'calendar', class: 'form-control flatpickr') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-12 mt-4">
                                    <hr>
                                    <h6 class="text-secondary d-flex align-items-center mb-3 mt-3 fw-bold">
                                        <i data-feather="users" class="me-2 text-secondary" style="width: 18px;"></i>
                                        İmza Yetkilileri (Kontrol ve Onay)
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <?= Form::FormFloatTextarea('kontrol_teskilati', '', "ÖMER FARUK YAŞAR - İDARİ İŞLER SORUMLUSU\nHARUN KAZANCI - OKUMA YÖNETİCİSİ", 'Kontrol Teşkilatı (İsim - Unvan)', icon: 'users', rows: 3, minHeight: '100px') ?>
                                            <small class="text-muted">Her yetkiliyi yeni bir satıra yazın</small>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <?= Form::FormFloatInput('text', 'tasvip_eden', '', 'AHMET BOLAT', 'Tasvip Eden (İsim)', icon: 'user-check') ?>
                                                </div>
                                                <div class="col-12">
                                                    <?= Form::FormFloatInput('text', 'tasvip_eden_unvan', '', 'ABONE KOORDİNASYON ŞUBE MÜDÜRÜ', 'Tasvip Eden Unvanı', icon: 'award') ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <?= Form::FormFloatInput('text', 'idare_onaylayan', '', 'KEMALETTİN GÜNEN', 'Tasdik Eden / Kesin Onaylayan (İsim)', icon: 'user') ?>
                                                </div>
                                                <div class="col-12">
                                                    <?= Form::FormFloatInput('text', 'idare_onaylayan_unvan', '', 'ABONE İŞLERİ DAİRE BAŞKANI', 'Tasdik Eden Unvanı', icon: 'award') ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BİRİM FİYAT CETVELİ TAB -->
                    <div class="tab-pane" id="birim-fiyat-tab" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6>Birim Fiyat Teklif Cetveli (Sözleşme Kalemleri)</h6>
                            <button type="button" class="btn btn-sm btn-success" onclick="satirEkle()">
                                <i class="bx bx-plus me-1"></i> Yeni Satır Ekle
                            </button>
                        </div>
                        <div class="alert alert-warning mb-3">Milyon/Bin ayıracı kullanmayınız, ondalık kısımları
                            nokta (.) ile ayırınız (Örn: 1000.50).</div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle" id="birimFiyatTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;">Sıra</th>
                                        <th style="width: 120px;">Poz No</th>
                                        <th>İşin Adı</th>
                                        <th style="width: 120px;">Ölçü Birimi</th>
                                        <th style="width: 120px;">Miktarı</th>
                                        <th style="width: 150px;">Teklif Edilen B.Fiyat</th>
                                        <th style="width: 150px;">Tutarı</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="birimFiyatBody">
                                    <!-- Satırlar JS ile eklenecek -->
                                </tbody>
                                <tfoot>
                                    <tr class="table-light fw-bold">
                                        <td colspan="6" class="text-end">GENEL TOPLAM:</td>
                                        <td id="genelToplamTutar" class="text-end text-primary">0,00 ₺</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- FİYAT FARKI VE KESİNTİLER TAB -->
                    <div class="tab-pane" id="fiyat-farki-tab" role="tabpanel">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <i class="bx bx-info-circle me-1"></i> Sözleşme genelindeki standart katsayıları
                                    ve
                                    temel endeksleri buradan tanımlayınız. Bu değerler her yeni hakedişte otomatik
                                    olarak getirilecektir.
                                </div>
                            </div>
                            <div class="col-md-6 border-end">
                                <h6 class="mb-3 text-primary"><i data-feather="sliders" style="width:16px;height:16px"
                                        class="me-1"></i> Fiyat Farkı Katsayıları
                                    (P1)</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'a1_katsayisi', '0.28000', '0.28000', 'a1 (İşçilik) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'b1_katsayisi', '0.22000', '0.22000', 'b1 (Motorin) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'b2_katsayisi', '0.25000', '0.25000', 'b2 (Yİ-ÜFE) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'c_katsayisi', '0.25000', '0.25000', 'c (Makine-Ekp) Katsayısı', icon: 'percent', attributes: 'step="0.000001"') ?>
                                    </div>
                                </div>

                                <h6 class="mt-4 mb-3 text-danger"><i data-feather="scissors"
                                        style="width:16px;height:16px" class="me-1"></i> Kesinti Oranları</h6>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('number', 'kdv_orani', '20.00', '20.00', 'KDV Oranı (%)', icon: 'percent', attributes: 'step="0.01"') ?>
                                    </div>
                                    <div class="col-md-6">
                                        <?= Form::FormFloatInput('text', 'tevkifat_orani', '4/10', '4/10', 'Tevkifat Oranı (Örn: 4/10)', icon: 'divide-circle') ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h6 class="mb-3 text-success"><i data-feather="calendar" style="width:16px;height:16px"
                                        class="me-1"></i> Temel (Sözleşme Ayı)
                                    Endeksleri (o)</h6>
                                <div class="row mb-3">
                                    <div class="col-md-7">
                                        <?= Form::FormSelect2('temel_endeks_ay', $aylar, '', 'Temel Endeks Ayı', icon: 'calendar', required: false) ?>
                                    </div>
                                    <div class="col-md-5">
                                        <?= Form::FormFloatInput('number', 'temel_endeks_yil', '', date('Y'), 'Temel Endeks Yılı', icon: 'calendar', required: false) ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'asgari_ucret_temel', '', 'Örn: 26005.50', 'İşçilik (Asgari Ücret) - Io', icon: 'dollar-sign', attributes: 'step="0.000001"') ?>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'motorin_temel', '', 'Örn: 54.13308', 'Motorin Endeksi - Mo', icon: 'droplet', attributes: 'step="0.000001"') ?>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'ufe_genel_temel', '', 'Örn: 4632.89', 'Yİ-ÜFE Genel Endeksi - ÜFEo', icon: 'trending-up', attributes: 'step="0.000001"') ?>
                                </div>
                                <div class="mb-3">
                                    <?= Form::FormFloatInput('number', 'makine_ekipman_temel', '', 'Örn: 3319.76', 'Makine-Ekipman Endeksi - Eo', icon: 'tool', attributes: 'step="0.000001"') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer px-0 pb-0 mt-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="submit" class="btn btn-dark"><i class="bx bx-save me-1"></i> Sözleşmeyi Kaydet</button>
            </div>
        </form>
    </div>
</div>