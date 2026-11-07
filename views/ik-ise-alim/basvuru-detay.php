<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;
use App\Service\Gate;

$maintitle = 'İşe Alım';
$title = 'Başvuru Detayı';
$donusturYetki = Gate::allows('ik_basvuru_personele_donustur');
$mulakatYetki = Gate::allows('ik_basvuru_mulakat');
$mulakatTurOptions = ['yuz_yuze' => 'Yüz yüze', 'telefon' => 'Telefon', 'online' => 'Online'];
$mulakatSonucOptions = ['beklemede' => 'Beklemede', 'olumlu' => 'Olumlu', 'olumsuz' => 'Olumsuz'];
$mulakatPuanOptions = ['' => 'Puan verilmedi', '1' => '1 - Çok zayıf', '2' => '2 - Zayıf', '3' => '3 - Orta', '4' => '4 - İyi', '5' => '5 - Çok iyi'];
?>
<style>
.ik-puan-halka { --p: 0; --c: #94a3b8; width: 104px; height: 104px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; position: relative; background: conic-gradient(var(--c) calc(var(--p) * 1%), #e9edf3 0); }
.ik-puan-halka::before { content: ''; position: absolute; inset: 9px; border-radius: 50%; background: var(--bs-card-bg, #fff); }
.ik-puan-halka span { position: relative; font-size: 30px; font-weight: 800; line-height: 1; color: var(--c); }
.ik-puan-halka small { position: relative; display: block; font-size: 10px; color: #74788d; text-align: center; margin-top: 2px; }
.ik-puan-halka.olumlu { --c: #34c38f; }
.ik-puan-halka.belirsiz { --c: #f1b44c; }
.ik-puan-halka.olumsuz { --c: #f46a6a; }
.ik-ai-kutu { border-radius: 12px; padding: 14px 16px; height: 100%; border: 1px solid transparent; }
.ik-ai-kutu h6 { font-size: 13px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
.ik-ai-kutu ul { padding-left: 1.1rem; margin: 0; font-size: 13px; }
.ik-ai-kutu li + li { margin-top: 4px; }
.ik-ai-kutu.guclu { background: rgba(52,195,143,.08); border-color: rgba(52,195,143,.25); }
.ik-ai-kutu.guclu h6 { color: #1f8f68; }
.ik-ai-kutu.zayif { background: rgba(244,106,106,.07); border-color: rgba(244,106,106,.25); }
.ik-ai-kutu.zayif h6 { color: #c0392b; }
.ik-ai-kutu.risk { background: rgba(241,180,76,.1); border-color: rgba(241,180,76,.3); }
.ik-ai-kutu.risk h6 { color: #b07d1c; }
.ik-ai-kutu.soru { background: rgba(85,110,230,.06); border-color: rgba(85,110,230,.22); }
.ik-ai-kutu.soru h6 { color: #556ee6; }
.ik-ozet { background: rgba(116,120,141,.07); border-radius: 10px; padding: 10px 14px; font-size: 13.5px; }
.ik-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: rgba(116,120,141,.1); color: #495057; text-decoration: none; border: 1px solid rgba(116,120,141,.15); }
a.ik-chip:hover { background: rgba(85,110,230,.12); color: #556ee6; }
.ik-bilgi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
.ik-bilgi-grid .ik-tam { grid-column: 1 / -1; }
.ik-bilgi-oge .et { font-size: 11px; font-weight: 600; color: #74788d; text-transform: uppercase; letter-spacing: .03em; display: flex; align-items: center; gap: 4px; margin-bottom: 2px; }
.ik-bilgi-oge .dg { font-size: 14px; font-weight: 500; white-space: pre-line; word-break: break-word; }
.ik-zaman { position: relative; padding-left: 22px; }
.ik-zaman::before { content: ''; position: absolute; left: 6px; top: 6px; bottom: 6px; width: 2px; background: rgba(116,120,141,.2); }
.ik-zaman-oge { position: relative; padding-bottom: 14px; }
.ik-zaman-oge:last-child { padding-bottom: 0; }
.ik-zaman-oge::before { content: ''; position: absolute; left: -22px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: var(--bs-card-bg, #fff); border: 3px solid #556ee6; }
.ik-mulakat { border: 1px solid rgba(116,120,141,.2); border-radius: 12px; overflow: hidden; }
.ik-mulakat + .ik-mulakat { margin-top: 10px; }
.ik-mulakat-baslik { padding: 10px 14px; display: flex; align-items: center; gap: 10px; cursor: pointer; flex-wrap: wrap; }
.ik-mulakat-govde { padding: 4px 14px 14px; border-top: 1px dashed rgba(116,120,141,.25); }
.ik-qa { background: rgba(116,120,141,.06); border-radius: 10px; padding: 9px 12px; margin-top: 8px; }
.ik-qa .q { font-weight: 600; font-size: 13px; }
.ik-qa .a { font-size: 13px; white-space: pre-line; margin-top: 2px; }
.ik-soru-satir { border: 1px solid rgba(116,120,141,.2); border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
.ik-degisti { font-size: 11px; color: #b07d1c; }
.ik-karar-btn { flex: 1 1 0; min-width: 0; padding: 10px 6px; display: flex; flex-direction: column; align-items: center; gap: 2px; font-size: 12px; font-weight: 600; line-height: 1.2; }
.ik-karar-btn i { font-size: 20px; }
.ik-karar-btn.aktif { box-shadow: 0 0 0 2px currentColor inset; }
@media (min-width: 992px) { .ik-sticky { position: sticky; top: 90px; } }
@media (max-width: 575px) { .ik-bilgi-grid { grid-template-columns: 1fr; } }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid" id="ikDetayRoot">
    <div class="row align-items-center mb-3">
        <div class="col-md-7 col-12 d-flex align-items-center gap-3">
            <div id="dFoto" class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0 overflow-hidden" style="width: 44px; height: 44px;">
                <i class="bx bx-user fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16" id="dAd">Aday</h4>
                <p class="text-muted mb-0 font-size-12" id="dAlt">Başvuru detayı yükleniyor...</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-5 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <a href="index.php?p=ik-ise-alim/basvurular" class="btn btn-outline-secondary bg-white top-action-btn shadow-sm"><i class="bx bx-arrow-back font-size-16"></i> Listeye Dön</a>
            <?php if ($donusturYetki): ?>
            <button type="button" class="btn btn-success top-action-btn shadow-sm text-white d-none" id="btnPersoneleDonustur"><i class="bx bx-user-plus font-size-16"></i> Personel Olarak Ekle</button>
            <?php endif; ?>
            <a href="#" class="btn btn-primary top-action-btn shadow-sm text-white d-none" id="btnPersonelGit"><i class="bx bx-id-card font-size-16"></i> Personel Kartı</a>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3" id="dChipler"></div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card summary-kpi-card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="font-size-14 fw-bold mb-0"><i class="bx bx-cpu text-primary me-1"></i> Yapay Zekâ Ön Değerlendirmesi</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAiYeniden"><i class="bx bx-refresh"></i> Yeniden değerlendir</button>
                    </div>
                    <div id="aiAlani"></div>
                </div>
            </div>
            <?php if ($mulakatYetki): ?>
            <div class="card summary-kpi-card mb-3" id="mulakatKart">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="font-size-14 fw-bold mb-0"><i class="bx bx-conversation text-primary me-1"></i> Mülakatlar <span class="badge bg-primary-subtle text-primary rounded-pill ms-1" id="mulakatSayisi">0</span></h5>
                        <button type="button" class="btn btn-sm btn-primary text-white d-none" id="btnMulakatEkle"><i class="bx bx-plus"></i> Mülakat Kaydet</button>
                    </div>
                    <div id="mulakatListe" class="font-size-13"></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="card summary-kpi-card mb-3">
                <div class="card-body">
                    <h5 class="font-size-14 fw-bold mb-3"><i class="bx bx-history text-primary me-1"></i> Geçmiş</h5>
                    <div id="olaylar" class="font-size-13"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="ik-sticky">
                <div class="card summary-kpi-card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="font-size-14 fw-bold mb-0"><i class="bx bx-task text-primary me-1"></i> İK Kararı</h5>
                            <span id="durumRozet"></span>
                        </div>
                        <div class="d-flex gap-2 mb-3" id="durumButonlari">
                            <button type="button" class="btn btn-subtle-warning ik-durum ik-karar-btn" data-durum="gorusme"><i class="bx bx-phone-call"></i> Görüşmeye Al</button>
                            <button type="button" class="btn btn-subtle-success ik-durum ik-karar-btn" data-durum="olumlu"><i class="bx bx-like"></i> Olumlu</button>
                            <button type="button" class="btn btn-subtle-danger ik-durum ik-karar-btn" data-durum="olumsuz"><i class="bx bx-dislike"></i> Olumsuz</button>
                        </div>
                        <label class="form-label font-size-12 text-muted mb-1" for="ik_notu">İK Notu</label>
                        <textarea class="form-control mb-2" id="ik_notu" rows="4" maxlength="3000" placeholder="Görüşme notları, değerlendirme..."></textarea>
                        <button type="button" class="btn btn-sm btn-primary text-white" id="btnNotKaydet"><i class="bx bx-save"></i> Notu Kaydet</button>
                    </div>
                </div>
                <div class="card summary-kpi-card mb-3">
                    <div class="card-body">
                        <h5 class="font-size-14 fw-bold mb-3"><i class="bx bx-id-card text-primary me-1"></i> Aday Bilgileri</h5>
                        <div class="ik-bilgi-grid" id="adayBilgi"></div>
                        <div id="cvAlani" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($donusturYetki): ?>
<div class="modal fade" id="donusturModal" tabindex="-1" aria-labelledby="donusturModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="donusturModalLabel"><i class="bx bx-user-plus text-success me-1"></i> Personel Olarak Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="donusturForm">
                <div class="modal-body px-4">
                    <p class="text-muted font-size-13">Aday bilgileri personel kartına aktarılır, CV evrak olarak eklenir. T.C. kimlik no başvuruda alınmadığı için burada girilir; IBAN ve evraklar Karşılama bağlantısıyla toplanabilir.</p>
                    <div class="row g-3">
                        <div class="col-12"><?= Form::FormFloatInput("text", "tc_kimlik_no", "", "11 haneli T.C. kimlik no", "T.C. Kimlik No *", "credit-card", "form-control", true, 11, "off", false, 'inputmode="numeric" pattern="[0-9]{11}"') ?></div>
                        <div class="col-12"><?= Form::FormFloatInput("date", "ise_giris_tarihi", date('Y-m-d'), "İşe Giriş Tarihi", "İşe Giriş Tarihi *", "calendar", "form-control", true) ?></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-success text-white" id="btnDonusturKaydet"><i class="bx bx-check"></i> Personeli Oluştur</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($mulakatYetki): ?>
<div class="modal fade" id="mulakatModal" tabindex="-1" aria-labelledby="mulakatModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
                <h5 class="modal-title fw-bold" id="mulakatModalLabel"><i class="bx bx-conversation text-primary me-1"></i> Mülakat Kaydı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="mulakatForm">
                <input type="hidden" id="mulakat_id" value="">
                <div class="modal-body px-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><?= Form::FormFloatInput("datetime-local", "mulakat_tarihi", "", "Mülakat tarihi", "Mülakat Tarihi *", "calendar", "form-control", true) ?></div>
                        <div class="col-md-4"><?= Form::FormSelect2("mulakat_tur", $mulakatTurOptions, "yuz_yuze", "Mülakat Türü", "bx bx-video") ?></div>
                        <div class="col-md-4"><?= Form::FormSelect2("mulakat_sonuc", $mulakatSonucOptions, "beklemede", "Mülakat Sonucu", "bx bx-flag") ?></div>
                    </div>

                    <h6 class="fw-bold font-size-13 mb-2"><i class="bx bx-help-circle text-primary"></i> Sorular ve Cevaplar</h6>
                    <div id="mulakatSorular"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="btnSoruEkle"><i class="bx bx-plus"></i> Soru ekle</button>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><?= Form::FormSelect2("mulakat_puan", $mulakatPuanOptions, "", "Genel Puan (1-5)", "bx bx-star") ?></div>
                        <div class="col-md-8"><?= Form::FormFloatTextarea("mulakat_not", "", "Genel değerlendirme", "Genel Değerlendirme Notu", "bx bx-note", "form-control", false, "80px", 3, 'maxlength="3000"') ?></div>
                    </div>

                    <div class="border rounded-3 p-3" style="border-color: rgba(116,120,141,.2)!important;">
                        <h6 class="fw-bold font-size-13 mb-1"><i class="bx bx-edit text-warning"></i> Mülakatta Teyit Edilen / Düzeltilen Bilgiler</h6>
                        <p class="text-muted font-size-12 mb-2">Aday mülakatta farklı bir bilgi verdiyse yeni değeri yazın. Başvurudaki orijinal beyan korunur; değişiklik eski ve yeni değeriyle kayda alınır.</p>
                        <div class="row g-2" id="mulakatBilgi"></div>
                    </div>
                    <div class="alert alert-light border font-size-12 mt-3 mb-0"><i class="bx bx-shield-quarter"></i> Notlarda yaş, cinsiyet, medeni hal, sağlık, din gibi korunan kişisel bilgileri yazmayın; yalnızca işle ilgili gözlemleri girin.</div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-primary text-white" id="btnMulakatKaydet"><i class="bx bx-save"></i> Mülakatı Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>window.IK_MULAKAT_YETKI = <?= $mulakatYetki ? 'true' : 'false' ?>;</script>
<script src="views/ik-ise-alim/js/basvuru-detay.js?v=<?php echo time(); ?>"></script>
