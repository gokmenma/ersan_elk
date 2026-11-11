<?php
use App\Helper\Form;
use App\Helper\Security;

$aracPersonelOptions = [];
foreach (($personeller ?? []) as $aracPersonel) {
    $aracIsAyrilmis = ($aracPersonel->aktif_mi == 0) || (!empty($aracPersonel->isten_cikis_tarihi) && $aracPersonel->isten_cikis_tarihi !== '0000-00-00' && $aracPersonel->isten_cikis_tarihi <= date('Y-m-d'));
    if ($aracIsAyrilmis) {
        continue;
    }
    $aracPersonelOptions[Security::encrypt($aracPersonel->id)] = $aracPersonel->adi_soyadi;
}

$aracTipOptions = ['standart' => 'Standart', 'hafta_sonu' => 'Hafta Sonu', 'resmi_tatil' => 'Resmi Tatil', 'ozel' => 'Özel'];
$aracStratejiOptions = ['adil' => 'Adil Dağıtım (en az nöbetli önce)', 'sirali' => 'Sıralı Dönüşüm (liste sırasıyla)'];
$aracYilOptions = [];
for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 4; $y--) {
    $aracYilOptions[$y] = (string) $y;
}
$aracGunler = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
?>

<style>
    .nobet-arac-modal .modal-title i { margin-right: 6px; }
    .nobet-arac-table { font-size: 12px; }
    .nobet-arac-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: #64748b; }
    .nobet-arac-scroll { max-height: 340px; overflow: auto; }
    .nobet-day-pill { display: inline-flex; align-items: center; gap: 4px; margin: 0 6px 6px 0; }
    .nobet-day-pill input { margin: 0; }
    .nobet-adalet-bar { height: 8px; border-radius: 99px; background: #e2e8f0; overflow: hidden; min-width: 90px; }
    .nobet-adalet-bar > span { display: block; height: 100%; background: #3b82f6; }
    .nobet-skor { font-size: 28px; font-weight: 800; line-height: 1; }
</style>

<div class="modal fade modern-settings-modal nobet-arac-modal" id="nobetDagitimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-magic-wand text-primary"></i>Otomatik Nöbet Dağıtımı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><?php echo Form::FormFloatInput('text', 'dag_baslangic', '', 'gg.aa.yyyy', 'Başlangıç', 'bx bx-calendar', 'form-control', true, null, 'off'); ?></div>
                    <div class="col-md-3"><?php echo Form::FormFloatInput('text', 'dag_bitis', '', 'gg.aa.yyyy', 'Bitiş', 'bx bx-calendar', 'form-control', true, null, 'off'); ?></div>
                    <div class="col-md-6"><?php echo Form::FormSelect2('dag_strateji', $aracStratejiOptions, 'adil', 'Dağıtım Yöntemi', 'bx bx-shuffle', 'key', '', 'form-control select2'); ?></div>
                    <div class="col-md-3"><?php echo Form::FormFloatInput('number', 'dag_kisi_sayisi', '1', '1', 'Günlük Nöbetçi Sayısı', 'bx bx-group', 'form-control', false, null, 'off', false, 'min="1" max="10"'); ?></div>
                    <div class="col-md-3"><?php echo Form::FormFloatInput('number', 'dag_aylik_limit', '0', '0', 'Aylık Üst Limit (0 = yok)', 'bx bx-bar-chart', 'form-control', false, null, 'off', false, 'min="0" max="31"'); ?></div>
                    <div class="col-md-6"><?php echo Form::FormSelect2('dag_personel_ids[]', $aracPersonelOptions, null, 'Personeller (boş = tüm nöbetçi personel)', 'bx bx-user', 'key', '', 'form-control select2', false, 'width:100%', 'multiple', 'dag_personel_ids'); ?></div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div>
                        <span class="text-muted font-size-12 me-2">Günler:</span>
                        <?php foreach ($aracGunler as $no => $ad): ?>
                            <label class="nobet-day-pill font-size-12"><input type="checkbox" class="dag-gun" value="<?php echo (int) $no; ?>" checked> <?php echo htmlspecialchars($ad, ENT_QUOTES, 'UTF-8'); ?></label>
                        <?php endforeach; ?>
                    </div>
                    <div class="form-check form-switch ms-auto">
                        <input class="form-check-input" type="checkbox" id="dag_ardisik_engel" checked>
                        <label class="form-check-label font-size-12" for="dag_ardisik_engel">Üst üste nöbeti engelle</label>
                    </div>
                </div>
                <div id="dagitimSonuc" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 font-size-13 fw-bold">Önerilen Çizelge <span class="badge bg-primary-subtle text-primary ms-1" id="dagitimAdet">0</span></h6>
                        <span class="text-muted font-size-12">Kaydedilene kadar hiçbir nöbet oluşturulmaz.</span>
                    </div>
                    <div class="alert alert-warning py-2 font-size-12 d-none" id="dagitimBosUyari"></div>
                    <div class="row g-3">
                        <div class="col-lg-8"><div class="nobet-arac-scroll border rounded-3"><table class="table table-sm table-hover mb-0 nobet-arac-table"><thead><tr><th>Tarih</th><th>Gün</th><th>Personel</th><th></th></tr></thead><tbody id="dagitimTablo"></tbody></table></div></div>
                        <div class="col-lg-4"><div class="nobet-arac-scroll border rounded-3"><table class="table table-sm mb-0 nobet-arac-table"><thead><tr><th>Personel</th><th class="text-end">Yeni Nöbet</th></tr></thead><tbody id="dagitimOzet"></tbody></table></div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnDagitimOner"><i class="bx bx-bulb"></i> Öneri Oluştur</button>
                <button type="button" class="btn btn-primary btn-sm d-none" id="btnDagitimKaydet"><i class="bx bx-save"></i> Önerileri Kaydet</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-settings-modal nobet-arac-modal" id="nobetTopluModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-calendar-plus text-primary"></i>Toplu Nöbet Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><?php echo Form::FormSelect2('toplu_personel', ['' => 'Personel seçiniz...'] + $aracPersonelOptions, '', 'Personel', 'bx bx-user', 'key', '', 'form-control select2', true); ?></div>
                <div class="mb-3"><?php echo Form::FormFloatInput('text', 'toplu_tarihler', '', 'Birden çok gün seçebilirsiniz', 'Nöbet Günleri', 'bx bx-calendar', 'form-control', true, null, 'off'); ?></div>
                <div class="mb-3"><?php echo Form::FormSelect2('toplu_tip', $aracTipOptions, 'standart', 'Nöbet Tipi', 'bx bx-tag', 'key', '', 'form-control select2'); ?></div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="toplu_uyari_onay">
                    <label class="form-check-label font-size-12" for="toplu_uyari_onay">İzin / üst üste nöbet / limit uyarısı olan günleri de ekle</label>
                </div>
                <div id="topluSonuc" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnTopluKaydet"><i class="bx bx-save"></i> Ekle</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-settings-modal nobet-arac-modal" id="nobetKopyalaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-copy text-primary"></i>Nöbetleri Kopyala</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted font-size-12">Kaynak aralıktaki nöbetler, hedef başlangıç tarihinden itibaren aynı gün aralıklarıyla kopyalanır. Örnek: bu haftayı bir sonraki haftaya ya da aya taşıyın.</p>
                <div class="row g-3">
                    <div class="col-6"><?php echo Form::FormFloatInput('text', 'kop_kaynak_bas', '', 'gg.aa.yyyy', 'Kaynak Başlangıç', 'bx bx-calendar', 'form-control', true, null, 'off'); ?></div>
                    <div class="col-6"><?php echo Form::FormFloatInput('text', 'kop_kaynak_bit', '', 'gg.aa.yyyy', 'Kaynak Bitiş', 'bx bx-calendar', 'form-control', true, null, 'off'); ?></div>
                    <div class="col-12"><?php echo Form::FormFloatInput('text', 'kop_hedef_bas', '', 'gg.aa.yyyy', 'Hedef Başlangıç', 'bx bx-calendar-check', 'form-control', true, null, 'off'); ?></div>
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" id="kop_uyari_onay">
                    <label class="form-check-label font-size-12" for="kop_uyari_onay">Uyarılı günleri de kopyala</label>
                </div>
                <div id="kopyalaSonuc" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnKopyalaKaydet"><i class="bx bx-copy"></i> Kopyala</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-settings-modal nobet-arac-modal" id="nobetGecmisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-history text-primary"></i>Değişiklik Geçmişi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-hover align-middle nobet-arac-table mb-0">
                    <thead><tr><th>Tarih</th><th>Kullanıcı</th><th>İşlem</th><th>Detay</th><th class="text-end"></th></tr></thead>
                    <tbody id="gecmisTablo"><tr><td colspan="5" class="text-center text-muted py-4">Yükleniyor...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-settings-modal nobet-arac-modal" id="nobetBordroModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-wallet text-primary"></i>Bordro Aktarım Özeti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3 align-items-center">
                    <div class="col-md-4"><?php echo Form::FormFloatInput('month', 'bordro_ay', date('Y-m'), '', 'Dönem', 'bx bx-calendar', 'form-control', false, null, 'off'); ?></div>
                    <div class="col-md-8 text-muted font-size-12">Yalnızca yönetici onaylı nöbetler sayılır. Bu ekran bilgi amaçlıdır; bordro hesaplarını değiştirmez.</div>
                </div>
                <table class="table table-sm table-hover nobet-arac-table mb-0">
                    <thead><tr><th>Personel</th><th>Departman</th><th class="text-end">Standart</th><th class="text-end">Hafta Sonu</th><th class="text-end">Resmi Tatil</th><th class="text-end">Özel</th><th class="text-end">Toplam</th></tr></thead>
                    <tbody id="bordroTablo"></tbody>
                    <tfoot id="bordroToplam"></tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade modern-settings-modal nobet-arac-modal" id="nobetAdaletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bx bx-balance text-primary"></i>Nöbet Adalet Raporu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3 align-items-center">
                    <div class="col-md-3"><?php echo Form::FormSelect2('adalet_yil', $aracYilOptions, (int) date('Y'), 'Yıl', 'bx bx-calendar', 'key', '', 'form-control select2'); ?></div>
                    <div class="col-md-9">
                        <div class="d-flex flex-wrap gap-4" id="adaletOzet"></div>
                    </div>
                </div>
                <div class="nobet-arac-scroll border rounded-3" style="max-height:420px">
                    <table class="table table-sm table-hover align-middle nobet-arac-table mb-0">
                        <thead><tr><th>Personel</th><?php foreach (['O', 'Ş', 'M', 'N', 'M', 'H', 'T', 'A', 'E', 'E', 'K', 'A'] as $i => $ayKisa): ?><th class="text-center" title="<?php echo (int) ($i + 1); ?>. ay"><?php echo $ayKisa; ?></th><?php endforeach; ?><th class="text-end">Toplam</th><th class="text-end">Özel Gün</th><th style="width:140px">Ortalamaya Göre</th></tr></thead>
                        <tbody id="adaletTablo"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
