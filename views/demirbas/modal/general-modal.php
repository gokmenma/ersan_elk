<?php
use App\Helper\Form;

// Kategorileri al
use App\Model\TanimlamalarModel;

$Tanimlamalar = new TanimlamalarModel();
$kategoriler = $Tanimlamalar->getDemirbasKategorileri();

// İş emri sonuçlarını al (otomatik zimmet ayarları için)
$isEmriSonuclari = $Tanimlamalar->getIsEmriSonuclari();

// İş emri sonuçları dropdown için hazırla
$isEmriOptions = ['' => 'Seçiniz (Yok)'];
foreach ($isEmriSonuclari as $sonuc) {
    $isEmriOptions[$sonuc] = $sonuc;
}
?>

<div class="modal fade" id="demirbasModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i data-feather="package" class="me-2"></i>Demirbaş Ekle/Düzenle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="demirbasForm">
                <input type="hidden" name="demirbas_id" id="demirbas_id" value="0">
                <div class="modal-body">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs nav-tabs-custom nav-success mb-3" role="tablist" id="demirbasModalTabs">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#genel-bilgiler" role="tab"
                                data-no-url-update="true">
                                <i class="bx bx-info-circle me-1"></i> Genel Bilgiler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#modal-oto-zimmet" role="tab"
                                data-no-url-update="true">
                                <i class="bx bx-cog me-1"></i> Otomatik Zimmet Ayarları
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Genel Bilgiler Tab -->
                        <div class="tab-pane fade show active" id="genel-bilgiler" role="tabpanel">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'demirbas_no', null, 'Örn: DB-001', 'Demirbaş No', 'hash'); ?>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <?php
                                    echo Form::FormSelect2('kategori_id', $kategoriler, null, 'Kategori *', 'grid', 'id', 'tur_adi', 'form-select select2', true);
                                    ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'demirbas_adi', null, 'Demirbaş adını giriniz', 'Demirbaş Adı *', 'box', 'form-control', true); ?>
                                </div>
                            </div>

                            <!-- Kayıt Modu Seçimi (Hizayı bozmamak için üstte ayrı bir satır yapıldı) -->
                            <div class="row mb-2">
                                <div class="col-12">
                                    <div
                                        class="d-flex align-items-center justify-content-end gap-3 p-2 bg-light rounded-3">
                                        <span class="small text-muted fw-bold text-uppercase"
                                            style="letter-spacing: 0.5px;">Kayıt Modu:</span>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input shadow-none" type="radio" name="seri_mod"
                                                id="seriModTekli" value="tekli" checked>
                                            <label class="form-check-label small fw-medium" for="seriModTekli">Tekli
                                                Kayıt</label>
                                        </div>
                                        <div class="form-check form-check-inline mb-0">
                                            <input class="form-check-input shadow-none" type="radio" name="seri_mod"
                                                id="seriModToplu" value="toplu">
                                            <label class="form-check-label small fw-bold text-success"
                                                for="seriModToplu">
                                                <i class="bx bx-list-plus me-1"></i>Toplu Seri Girişi
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'marka', null, 'Marka', 'Marka', 'tag'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'model', null, 'Model', 'Model', 'layers'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <!-- Tekli Seri No Alanı -->
                                    <div id="seriTekliAlani">
                                        <?php echo Form::FormFloatInput('text', 'seri_no', null, 'Seri numarası', 'Seri No', 'cpu'); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Toplu Seri Girişi Alanları (başlangıçta gizli) -->
                            <div class="row" id="seriTopluAlani" style="display: none;">
                                <div class="col-12 mb-2">
                                    <div class="alert alert-soft-success d-flex align-items-center py-2 mb-2"
                                        style="font-size: 0.82rem;">
                                        <i class="bx bx-info-circle fs-5 me-2"></i>
                                        <div><strong>Hızlı Giriş:</strong> Başlangıç seri ve adet girmeniz yeterlidir.
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'seri_baslangic', null, 'Örn: 2025100', 'Başlangıç Seri No *', 'skip-forward', 'form-control'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'seri_bitis', null, 'Örn: 2025110', 'Bitiş Seri No', 'skip-back', 'form-control'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'seri_adet', null, 'Adet', 'Adet', 'hash', 'form-control', false, null, 'on', false, 'min="1" max="500"'); ?>
                                </div>
                                <div class="col-12 mb-2" id="seriOnizlemeContainer" style="display: none;">
                                    <div class="card border border-success border-opacity-25 mb-0 shadow-none bg-light">
                                        <div
                                            class="card-header bg-success bg-opacity-10 py-2 d-flex align-items-center justify-content-between">
                                            <span class="fw-semibold text-success small"><i
                                                    class="bx bx-list-check me-1"></i> Oluşturulacak Seriler</span>
                                            <span class="badge bg-success" id="seriToplamBadge">0 adet</span>
                                        </div>
                                        <div class="card-body py-2" style="max-height: 100px; overflow-y: auto;">
                                            <div id="seriOnizlemeList" class="d-flex flex-wrap gap-1"
                                                style="font-size: 0.75rem;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'miktar', '1', null, 'Toplam Miktar *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'minimun_stok_uyari_miktari', '0', null, 'Min. Stok Uyarısı', 'bell', 'form-control', false, null, 'on', false, 'min="0"'); ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <?php
                                    $durumlar = [
                                        'aktif' => 'Aktif',
                                        'pasif' => 'Pasif',
                                        'arizali' => 'Arızalı',
                                        'hurda' => 'Hurda'
                                    ];
                                    echo Form::FormSelect2('durum', $durumlar, 'aktif', 'Durum', 'activity');
                                    ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'edinme_tarihi', date('d.m.Y'), null, 'Edinme Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'edinme_tutari', null, '0,00', 'Edinme Tutarı', 'dollar-sign', 'form-control money'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <?php echo Form::FormFloatTextarea('aciklama', null, 'Açıklama giriniz', 'Açıklama', 'file-text'); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Otomatik Zimmet Ayarları Tab -->
                        <div class="tab-pane fade" id="modal-oto-zimmet" role="tabpanel">
                            <div class="alert alert-info mb-3">
                                <i class="bx bx-info-circle me-2"></i>
                                <strong>Otomatik Zimmet Sistemi:</strong> Puantaj verileri yüklendiğinde, belirtilen iş
                                emri sonuçlarına göre bu demirbaş otomatik olarak zimmetlenebilir veya iade alınabilir.
                                <br>
                                <small class="text-primary fw-bold mt-1 d-block">
                                    <i class="bx bx-plug me-1"></i> Aparat kategorisinde: Zimmet İş Emri = Personeldeki
                                    aparatı TÜKETİR (depoya dokunmaz).
                                    Aparatlar depoya MANUEL olarak eklenir, personele MANUEL olarak verilir.
                                </small>
                            </div>

                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0"><i class="bx bx-transfer text-warning me-2"></i>Otomatik Zimmet
                                        Verme / Tüketim</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12 mb-2">
                                            <?php echo Form::FormSelect2(
                                                'otomatik_zimmet_is_emri',
                                                $isEmriOptions,
                                                '',
                                                'Zimmetlenecek İş Emri Sonucu',
                                                'log-in',
                                                'key',
                                                '',
                                                'form-select select2',
                                                false
                                            ); ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bx bx-info-circle me-1"></i>
                                        <strong>Genel Demirbaş:</strong> Bu iş emri sonucu geldiğinde, demirbaş
                                        personele otomatik olarak zimmetlenir.<br>
                                        <i class="bx bx-plug me-1 text-warning"></i>
                                        <strong class="text-warning">Aparat:</strong> Bu iş emri sonucu geldiğinde,
                                        personeldeki mevcut aparat <strong>tüketildi</strong> olarak işaretlenir. Depoya
                                        dokunulmaz.
                                    </small>
                                </div>
                            </div>

                            <div class="card border">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0"><i class="bx bx-undo text-success me-2"></i>Otomatik İade Alma</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-12 mb-2">
                                            <?php echo Form::FormSelect2(
                                                'otomatik_iade_is_emri',
                                                $isEmriOptions,
                                                '',
                                                'İade Alınacak İş Emri Sonucu',
                                                'log-out',
                                                'key',
                                                '',
                                                'form-select select2',
                                                false
                                            ); ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        <i class="bx bx-info-circle me-1"></i>
                                        Bu iş emri sonucu geldiğinde, personelden demirbaş otomatik olarak iade alınır
                                        (depoya geri döner).
                                    </small>
                                </div>
                            </div>

                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="bx bx-bulb me-2"></i>
                                <strong>Örnek Kullanım:</strong><br>
                                <ul class="mb-0 mt-2">
                                    <li><strong>Aparat:</strong> Zimmet İş Emri = "APARATLA KESİM YAPILDI" → Kesme işi
                                        yapıldığında personeldeki aparat <strong>tüketilir</strong> (stok depoya döner)
                                    </li>
                                    <li><strong>Sayaç:</strong> Zimmet İş Emri = Normal zimmet (depodan personele)</li>
                                    <li><strong>Sayaç:</strong> İade İş Emri = "Sayaç Kullanıma açıldı" → Sayaç
                                        açıldığında zimmet iade alınır</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" id="demirbasKaydet" class="btn btn-success">
                        <i data-feather="save" class="me-1"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>