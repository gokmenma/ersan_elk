<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;
use App\Service\Gate;

$maintitle = 'İşe Alım';
$title = 'Başvuru Detayı';
$donusturYetki = Gate::allows('ik_basvuru_personele_donustur');
?>
<style>
.ik-puan-halka { width: 96px; height: 96px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; border: 8px solid #e2e8f0; flex-shrink: 0; }
.ik-puan-halka.olumlu { border-color: #34c38f; color: #1f8f68; }
.ik-puan-halka.belirsiz { border-color: #f1b44c; color: #b07d1c; }
.ik-puan-halka.olumsuz { border-color: #f46a6a; color: #c0392b; }
.ik-liste { padding-left: 1.1rem; margin-bottom: 0; }
.ik-bilgi dt { font-size: 12px; font-weight: 600; color: #74788d; margin-bottom: 0; }
.ik-bilgi dd { margin-bottom: .6rem; white-space: pre-line; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid" id="ikDetayRoot">
    <div class="row align-items-center mb-3">
        <div class="col-md-7 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
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

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card summary-kpi-card mb-3">
                <div class="card-body">
                    <h5 class="font-size-14 fw-bold mb-3"><i class="bx bx-cpu text-primary me-1"></i> Yapay Zekâ Ön Değerlendirmesi</h5>
                    <div id="aiAlani"></div>
                </div>
            </div>
            <div class="card summary-kpi-card mb-3">
                <div class="card-body">
                    <h5 class="font-size-14 fw-bold mb-3"><i class="bx bx-history text-primary me-1"></i> Geçmiş</h5>
                    <div id="olaylar" class="font-size-13"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card summary-kpi-card mb-3">
                <div class="card-body">
                    <h5 class="font-size-14 fw-bold mb-3"><i class="bx bx-id-card text-primary me-1"></i> Aday Bilgileri</h5>
                    <dl class="ik-bilgi mb-0" id="adayBilgi"></dl>
                    <div id="cvAlani" class="mt-2"></div>
                </div>
            </div>
            <div class="card summary-kpi-card mb-3">
                <div class="card-body">
                    <h5 class="font-size-14 fw-bold mb-3"><i class="bx bx-task text-primary me-1"></i> İK Kararı</h5>
                    <div class="d-flex flex-wrap gap-2 mb-3" id="durumButonlari">
                        <button type="button" class="btn btn-sm btn-subtle-warning ik-durum" data-durum="gorusme"><i class="bx bx-phone-call"></i> Görüşmeye Al</button>
                        <button type="button" class="btn btn-sm btn-subtle-success ik-durum" data-durum="olumlu"><i class="bx bx-like"></i> Olumlu</button>
                        <button type="button" class="btn btn-sm btn-subtle-danger ik-durum" data-durum="olumsuz"><i class="bx bx-dislike"></i> Olumsuz</button>
                    </div>
                    <label class="form-label font-size-12 text-muted mb-1" for="ik_notu">İK Notu</label>
                    <textarea class="form-control mb-2" id="ik_notu" rows="4" maxlength="3000"></textarea>
                    <button type="button" class="btn btn-sm btn-primary text-white" id="btnNotKaydet"><i class="bx bx-save"></i> Notu Kaydet</button>
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

<script src="views/ik-ise-alim/js/basvuru-detay.js?v=<?php echo time(); ?>"></script>
