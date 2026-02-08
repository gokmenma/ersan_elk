<?php
use App\Helper\Form;

// Kategorileri al
use App\Model\DemirbasKategoriModel;
use App\Model\TanimlamalarModel;

$Kategori = new DemirbasKategoriModel();
$kategoriler = $Kategori->getActiveCategories();

// İş emri sonuçlarını al (otomatik zimmet ayarları için)
$Tanimlamalar = new TanimlamalarModel();
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
                                    echo Form::FormSelect2('kategori_id', $kategoriler, null, 'Kategori *', 'grid', 'id', 'kategori_adi', 'form-select select2', true);
                                    ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'demirbas_adi', null, 'Demirbaş adını giriniz', 'Demirbaş Adı *', 'box', 'form-control', true); ?>
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
                                    <?php echo Form::FormFloatInput('text', 'seri_no', null, 'Seri numarası', 'Seri No', 'cpu'); ?>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <?php echo Form::FormFloatInput('number', 'miktar', '1', null, 'Toplam Miktar *', 'hash', 'form-control', true, null, 'on', false, 'min="1"'); ?>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'edinme_tarihi', date('d.m.Y'), null, 'Edinme Tarihi', 'calendar', 'form-control flatpickr'); ?>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <?php echo Form::FormFloatInput('text', 'edinme_tutari', null, '0,00', 'Edinme Tutarı', 'dollar-sign', 'form-control money'); ?>
                                </div>
                                <div class="col-md-3 mb-3">
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
                            </div>

                            <div class="card border mb-3">
                                <div class="card-header bg-light py-2">
                                    <h6 class="mb-0"><i class="bx bx-transfer text-warning me-2"></i>Otomatik Zimmet
                                        Verme</h6>
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
                                        Bu iş emri sonucu geldiğinde, demirbaş personele otomatik olarak zimmetlenir.
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
                                        (tüketildi olarak işaretlenir).
                                    </small>
                                </div>
                            </div>

                            <div class="alert alert-warning mt-3 mb-0">
                                <i class="bx bx-bulb me-2"></i>
                                <strong>Örnek Kullanım:</strong><br>
                                <ul class="mb-0 mt-2">
                                    <li><strong>Aparat:</strong> İade İş Emri = "APARATLA KESİM YAPILDI" → Kesme işi
                                        yapıldığında aparat tüketilir</li>
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