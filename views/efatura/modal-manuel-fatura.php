<?php
use App\Helper\Form;
use App\Service\Gate;
use App\Service\ManuelFaturaService;

if (!Gate::allows('efatura/manuel-fatura')) {
    return;
}

$manuelTurler = ManuelFaturaService::BELGE_TURLERI;
$manuelBirimler = ManuelFaturaService::BIRIMLER;
$manuelKdvOranlari = ['20' => '%20', '10' => '%10', '1' => '%1', '0' => '%0', '18' => '%18', '8' => '%8'];
?>
<style>
#modalManuelFatura .manuel-section-title { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); margin-bottom: .75rem; display: flex; align-items: center; gap: .5rem; }
#modalManuelFatura .manuel-ai-panel { border: 1px dashed rgba(19, 91, 236, .35); background: rgba(19, 91, 236, .04); border-radius: 14px; }
#modalManuelFatura .manuel-satir { border: 1px solid var(--bs-border-color); border-radius: 12px; padding: .6rem; position: relative; }
#modalManuelFatura .manuel-satir + .manuel-satir { margin-top: .5rem; }
#modalManuelFatura .manuel-satir-no { position: absolute; top: -9px; left: 12px; background: var(--bs-body-bg); padding: 0 6px; font-size: 11px; font-weight: 700; color: var(--bs-secondary-color); }
#modalManuelFatura .manuel-satir-tutar { font-weight: 700; font-size: 12px; white-space: nowrap; }
#modalManuelFatura .btn-manuel-satir-sil { position: absolute; top: -12px; right: 10px; width: 24px; height: 24px; padding: 0; background: var(--bs-body-bg); }
#modalManuelFatura .manuel-toplam-tablo th { font-weight: 500; color: var(--bs-secondary-color); }
#modalManuelFatura .manuel-toplam-tablo td { text-align: right; font-weight: 600; }
#modalManuelFatura .manuel-toplam-tablo tr.manuel-genel-toplam td,
#modalManuelFatura .manuel-toplam-tablo tr.manuel-genel-toplam th { font-size: 15px; font-weight: 700; color: var(--bs-emphasis-color); }
#modalManuelFatura .manuel-stopaj-alani { display: none; }
#modalManuelFatura.manuel-stopajli .manuel-stopaj-alani { display: block; }
#modalManuelFatura.manuel-stopajli tr.manuel-stopaj-alani { display: table-row; }
</style>

<div class="modal fade" id="modalManuelFatura" tabindex="-1" aria-labelledby="modalManuelFaturaLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-bottom">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bx bx-receipt fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-size-15 fw-bold mb-0" id="modalManuelFaturaLabel">Manuel Fatura / Makbuz Ekle</h5>
                        <p class="text-muted font-size-12 mb-0">Entegratör dışında gelen belgeyi yükleyin, bilgiler otomatik doldurulsun; kontrol edip kaydedin.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formManuelFatura" autocomplete="off" novalidate>
                    <input type="hidden" id="manuel_invoice_id" value="">

                    <div class="manuel-ai-panel p-3 mb-4" id="manuelAiPanel">
                        <div class="row g-2 align-items-center">
                            <div class="col-lg-8">
                                <?= Form::FormFileInput('manuel_belge', 'Fatura / makbuz belgesi (PDF, JPG, PNG, WEBP — en fazla 12 MB)', 'bx bx-upload', 'form-control', false, 'accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"') ?>
                            </div>
                            <div class="col-lg-4 d-grid">
                                <button type="button" class="btn btn-primary shadow-sm" id="btnManuelAiDoldur" style="min-height: 52px;">
                                    <i class="bx bx-bot me-1"></i> Yapay Zekâ ile Doldur
                                </button>
                            </div>
                        </div>
                        <div id="manuelAiSonuc" class="font-size-12 mt-2" role="status" aria-live="polite"></div>
                    </div>

                    <div class="manuel-section-title"><i class="bx bx-file"></i> Belge Bilgileri</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <?= Form::FormSelect2('manuel_belge_turu', $manuelTurler, 'KAGIT', 'Belge Türü', 'bx bx-category', 'key', '', 'form-select select2', true) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput('text', 'manuel_fatura_no', '', 'Belge numarası', 'Belge / Fatura No', 'bx bx-hash', 'form-control', false, 16) ?>
                        </div>
                        <div class="col-md-4">
                            <?= Form::FormFloatInput('text', 'manuel_fatura_tarihi', '', 'GG.AA.YYYY', 'Belge Tarihi', 'bx bx-calendar', 'form-control', true, 10) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('text', 'manuel_ettn', '', 'Varsa ETTN', 'ETTN (varsa)', 'bx bx-barcode', 'form-control font-monospace', false, 36) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'manuel_siparis_no', '', 'Sipariş numarası', 'Sipariş No', 'bx bx-cart', 'form-control', false, 50) ?>
                        </div>
                        <div class="col-md-3 manuel-stopaj-alani">
                            <?= Form::FormFloatInput('number', 'manuel_stopaj_orani', '', 'Stopaj oranı', 'Stopaj Oranı (%)', 'bx bx-cut', 'form-control', false, null, 'off', false, 'min="0" max="100" step="0.01"') ?>
                        </div>
                    </div>

                    <div class="manuel-section-title"><i class="bx bx-store"></i> Düzenleyen / Satıcı <span id="manuelCariDurum" class="ms-1"></span></div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('text', 'manuel_unvan', '', 'Ünvan / Ad Soyad', 'Ünvan / Ad Soyad', 'bx bx-buildings', 'form-control', true, 255) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'manuel_vkn', '', 'VKN / TCKN', 'VKN / TCKN', 'bx bx-id-card', 'form-control font-monospace', false, 11, 'off', false, 'inputmode="numeric"') ?>
                        </div>
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'manuel_vergi_dairesi', '', 'Vergi dairesi', 'Vergi Dairesi', 'bx bx-landmark', 'form-control', false, 100) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput('text', 'manuel_adres', '', 'Adres', 'Adres', 'bx bx-map', 'form-control', false, 1000) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'manuel_ilce', '', 'İlçe', 'İlçe', 'bx bx-map-pin', 'form-control', false, 50) ?>
                        </div>
                        <div class="col-md-3">
                            <?= Form::FormFloatInput('text', 'manuel_il', '', 'İl', 'İl', 'bx bx-map-alt', 'form-control', false, 50) ?>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="manuel-section-title mb-0"><i class="bx bx-list-ul"></i> Kalemler</div>
                        <button type="button" class="btn btn-sm btn-subtle-primary" id="btnManuelSatirEkle"><i class="bx bx-plus me-1"></i>Kalem Ekle</button>
                    </div>
                    <div id="manuelSatirlar" class="mb-4 pt-2"></div>

                    <div class="row g-3">
                        <div class="col-lg-7">
                            <?= Form::FormFloatTextarea('manuel_notlar', '', 'Notlar', 'Notlar (plaka, ödeme şekli vb.)', 'bx bx-note', 'form-control', false, '110px', 4, 'maxlength="5000"') ?>
                            <div id="manuelToplamUyari" class="font-size-12 mt-2"></div>
                        </div>
                        <div class="col-lg-5">
                            <table class="table table-sm manuel-toplam-tablo mb-0">
                                <tbody>
                                    <tr><th>Mal / Hizmet Toplamı</th><td id="manuelTopSatir">0,00 ₺</td></tr>
                                    <tr><th>Toplam İskonto</th><td id="manuelTopIskonto">0,00 ₺</td></tr>
                                    <tr><th>Matrah</th><td id="manuelTopMatrah">0,00 ₺</td></tr>
                                    <tr><th>Hesaplanan KDV</th><td id="manuelTopKdv">0,00 ₺</td></tr>
                                    <tr class="manuel-stopaj-alani"><th>Stopaj</th><td id="manuelTopStopaj" class="text-danger">0,00 ₺</td></tr>
                                    <tr class="manuel-genel-toplam"><th>Ödenecek Tutar</th><td id="manuelTopOdenecek">0,00 ₺</td></tr>
                                    <tr id="manuelBelgeToplamSatiri" class="d-none"><th>Belgede Yazan</th><td id="manuelBelgeToplam"></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <template id="manuelSatirSablon">
                    <div class="manuel-satir">
                        <span class="manuel-satir-no"></span>
                        <div class="row g-2 align-items-center">
                            <div class="col-lg-3 col-12">
                                <?= Form::FormFloatInput('text', 'satir_ad', '', 'Mal / hizmet', 'Mal / Hizmet', 'bx bx-package', 'form-control satir-ad', true, 255) ?>
                            </div>
                            <div class="col-lg-1 col-4">
                                <?= Form::FormFloatInput('number', 'satir_miktar', '1', 'Miktar', 'Miktar', '', 'form-control satir-miktar satir-hesap', true, null, 'off', false, 'min="0" step="0.0001"') ?>
                            </div>
                            <div class="col-lg-2 col-8">
                                <?= Form::FormSelect2('satir_birim', $manuelBirimler, 'C62', 'Birim', '', 'key', '', 'form-select select2 satir-birim') ?>
                            </div>
                            <div class="col-lg-2 col-6">
                                <?= Form::FormFloatInput('number', 'satir_fiyat', '', 'Birim fiyat', 'Birim Fiyat (KDV hariç)', '', 'form-control satir-fiyat satir-hesap', true, null, 'off', false, 'min="0" step="0.0001"') ?>
                            </div>
                            <div class="col-lg-1 col-3">
                                <?= Form::FormFloatInput('number', 'satir_iskonto', '0', 'İsk. %', 'İsk. %', '', 'form-control satir-iskonto satir-hesap', false, null, 'off', false, 'min="0" max="100" step="0.01"') ?>
                            </div>
                            <div class="col-lg-2 col-3">
                                <?= Form::FormSelect2('satir_kdv', $manuelKdvOranlari, '20', 'KDV', '', 'key', '', 'form-select select2 satir-kdv satir-hesap') ?>
                            </div>
                            <div class="col-lg-1 col-12 text-lg-end">
                                <div class="text-muted font-size-11">KDV dahil</div>
                                <div class="manuel-satir-tutar">0,00 ₺</div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-subtle-danger table-action-btn btn-manuel-satir-sil" title="Kalemi sil" aria-label="Kalemi sil"><i class="bx bx-trash font-size-15"></i></button>
                    </div>
                </template>
            </div>
            <div class="modal-footer border-top d-flex justify-content-between">
                <div class="text-muted font-size-12"><i class="bx bx-info-circle me-1"></i>Kayıt Gelen Faturalara "Manuel" olarak eklenir; eşleşen cari varsa otomatik işlenir.</div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="button" class="btn btn-primary shadow-sm" id="btnManuelKaydet"><i class="bx bx-save me-1"></i>Kaydet</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="views/efatura/js/manuel-fatura.js?v=<?= filemtime(__DIR__ . '/js/manuel-fatura.js') ?>"></script>
