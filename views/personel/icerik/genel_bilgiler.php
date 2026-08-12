<?php
use App\Helper\Form;
use App\Helper\Date;
?>

<div class="row">
    <div class="col-12">
        <div class="personel-ai-panel mb-3">
            <div class="personel-ai-glow personel-ai-glow-one"></div>
            <div class="personel-ai-glow personel-ai-glow-two"></div>
            <button type="button" class="personel-ai-toggle <?= $id > 0 ? 'collapsed' : '' ?>" data-bs-toggle="collapse"
                data-bs-target="#personelAiAccordionBody" aria-expanded="<?= $id > 0 ? 'false' : 'true' ?>"
                aria-controls="personelAiAccordionBody">
                <div class="d-flex align-items-center gap-2">
                    <span class="personel-ai-icon flex-shrink-0">
                        <i class="bx bx-bot" aria-hidden="true"></i>
                    </span>
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h6 class="personel-ai-title mb-0">Personel belgelerinden otomatik doldur</h6>
                            <span class="personel-ai-badge"><i class="bx bx-bot me-1"></i>AI Destekli</span>
                        </div>
                    </div>
                </div>
                <span class="personel-ai-chevron"><i class="bx bx-chevron-up"></i></span>
            </button>
            <div class="collapse <?= $id > 0 ? '' : 'show' ?>" id="personelAiAccordionBody">
                <div class="personel-ai-content d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
                    <p class="personel-ai-description mb-0">Kimlik, adres belgesi ve ehliyeti birlikte analiz eder; bulunan bilgileri kontrolünüz için forma önerir.</p>
                    <button type="button" class="btn personel-ai-button text-nowrap" id="btnPersonelBelgeAnalizAc">
                        <i class="bx bx-magic-wand me-1"></i> Belgelerden Doldur
                        <i class="bx bx-right-arrow-alt ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Sol Kolon: Kimlik ve Kişisel Bilgiler -->
    <div class="col-md-6">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-id-card me-2"></i>Kimlik Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "tc_kimlik_no", $personel->tc_kimlik_no ?? "", "11 Haneli TC", "TC Kimlik No", "user", "form-control", true, 11, "off"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "dogum_tarihi", Date::dmy($personel->dogum_tarihi ?? null) ?? "", "Doğum Tarihi", "Doğum Tarihi", "calendar", "form-control flatpickr", '', '', "off"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "adi_soyadi", $personel->adi_soyadi ?? "", "Ad Soyad", "Adı Soyadı", "user", "form-control", true); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormSelect2("cinsiyet", ['Erkek' => 'Erkek', 'Kadın' => 'Kadın'], $personel->cinsiyet ?? '', "Cinsiyet", "users"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormSelect2("medeni_durum", ['Evli' => 'Evli', 'Bekar' => 'Bekar'], $personel->medeni_durum ?? '', "Medeni Durum", "heart"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "kan_grubu", $personel->kan_grubu ?? "", "Kan Gr.", "Kan Grubu", "activity"); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border mt-3">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-user-circle me-2"></i>Kişisel Detaylar</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "anne_adi", $personel->anne_adi ?? "", "Anne Adı", "Anne Adı", "user"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "baba_adi", $personel->baba_adi ?? "", "Baba Adı", "Baba Adı", "user"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "dogum_yeri_il", $personel->dogum_yeri_il ?? "", "Doğum Yeri İl", "Doğum Yeri İl", "map-pin"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "dogum_yeri_ilce", $personel->dogum_yeri_ilce ?? "", "Doğum Yeri İlçe", "Doğum Yeri İlçe", "map-pin"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "ehliyet_sinifi", $personel->ehliyet_sinifi ?? "", "Ehliyet Sınıfı", "Ehliyet Sınıfı", "credit-card"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormSelect2("seyahat_engeli", ['Var' => 'Var', 'Yok' => 'Yok'], $personel->seyahat_engeli ?? 'Yok', "Seyahat Engeli", "truck"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: İletişim ve Diğer -->
    <div class="col-md-6">
        <div class="card border">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-phone me-2"></i>İletişim Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "cep_telefonu", $personel->cep_telefonu ?? "", "Cep Telefonu", "Cep Telefonu", "phone"); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormFloatInput("text", "cep_telefonu_2", $personel->cep_telefonu_2 ?? "", "2. Cep Telefonu", "2. Cep Telefonu", "phone"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatInput("email", "email_adresi", $personel->email_adresi ?? "", "Email", "Email", "mail", autocomplete: "new-password"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-12 mb-2">
                        <?php echo Form::FormFloatTextarea("adres", $personel->adres ?? "", "Adres", "Adres", "map", "form-control", false, "100px", 3); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border mt-3">
            <div class="card-header bg-transparent border-bottom">
                <h5 class="card-title mb-0 text-primary"><i class="bx bx-body me-2"></i>Fiziksel & Diğer</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "ayakkabi_numarasi", $personel->ayakkabi_numarasi ?? "", "Ayakkabı", "Ayakkabı No", "target"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "ust_beden_no", $personel->ust_beden_no ?? "", "Üst", "Üst Beden", "target"); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <?php echo Form::FormFloatInput("text", "alt_beden_no", $personel->alt_beden_no ?? "", "Alt", "Alt Beden", "target"); ?>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <?php echo Form::FormSelect2("esi_calisiyor_mu", ['Evet' => 'Evet', 'Hayır' => 'Hayır'], $personel->esi_calisiyor_mu ?? 'Hayır', "Eşi Çalışıyor Mu?", "briefcase"); ?>
                    </div>
                    <div class="col-md-6 d-none">
                        <?php echo Form::FormFloatInput("text", "resim_yolu", $personel->resim_yolu ?? "", "Resim Yolu / URL", "Resim Yolu", "image"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .personel-ai-panel {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        padding: 9px 12px;
        border: 1px solid rgba(124, 92, 255, .28);
        border-radius: 11px;
        background:
            linear-gradient(112deg, rgba(244, 241, 255, .98) 0%, rgba(238, 247, 255, .98) 48%, rgba(240, 253, 251, .98) 100%);
        box-shadow: 0 8px 25px rgba(79, 70, 229, .09), inset 0 1px 0 rgba(255, 255, 255, .8);
    }

    .personel-ai-panel::before {
        content: "";
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(90deg, rgba(124, 92, 255, .07), transparent 35%, rgba(30, 191, 185, .07));
    }

    .personel-ai-content {
        position: relative;
        z-index: 2;
        padding-top: 7px;
        margin-top: 6px;
        border-top: 1px solid rgba(111, 82, 224, .12);
    }

    .personel-ai-toggle {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 0;
        border: 0;
        color: inherit;
        background: transparent;
        text-align: left;
    }

    .personel-ai-chevron {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 25px;
        height: 25px;
        border-radius: 50%;
        color: #6650c7;
        background: rgba(255, 255, 255, .7);
        font-size: 18px;
        transition: transform .2s ease, background .2s ease;
    }

    .personel-ai-toggle.collapsed .personel-ai-chevron {
        transform: rotate(180deg);
    }

    .personel-ai-toggle:hover .personel-ai-chevron {
        background: #fff;
    }

    .personel-ai-glow {
        position: absolute;
        z-index: 0;
        width: 150px;
        height: 150px;
        border-radius: 50%;
        filter: blur(48px);
        opacity: .35;
        pointer-events: none;
    }

    .personel-ai-glow-one {
        top: -95px;
        left: 14%;
        background: #8b5cf6;
    }

    .personel-ai-glow-two {
        right: 8%;
        bottom: -110px;
        background: #22d3ee;
    }

    .personel-ai-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border: 1px solid rgba(255, 255, 255, .8);
        border-radius: 10px;
        color: #fff;
        font-size: 19px;
        background: linear-gradient(135deg, #7655e9 0%, #397be8 55%, #18b9b0 100%);
        box-shadow: 0 7px 18px rgba(93, 78, 218, .28);
    }

    .personel-ai-title {
        color: #28234a;
        font-weight: 700;
        letter-spacing: -.01em;
        font-size: 13px;
    }

    .personel-ai-description {
        color: #667085;
        font-size: 12px;
    }

    .personel-ai-badge {
        display: inline-flex;
        align-items: center;
        padding: 3px 8px;
        border: 1px solid rgba(111, 82, 224, .18);
        border-radius: 20px;
        color: #6650c7;
        background: rgba(255, 255, 255, .72);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    .personel-ai-button {
        padding: 6px 12px;
        border: 0;
        border-radius: 8px;
        color: #fff;
        background: linear-gradient(110deg, #6f52df 0%, #3d78e7 55%, #1caea8 100%);
        box-shadow: 0 7px 16px rgba(75, 92, 210, .25);
        font-weight: 600;
        font-size: 12px;
        transition: transform .18s ease, box-shadow .18s ease, color .18s ease;
    }

    .personel-ai-button:hover,
    .personel-ai-button:focus {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(75, 92, 210, .34);
    }

    @media (max-width: 575.98px) {
        .personel-ai-panel {
            padding: 9px 10px;
        }

        .personel-ai-badge {
            display: none;
        }

        .personel-ai-button {
            width: 100%;
        }
    }
</style>

<div class="modal fade" id="modalPersonelBelgeAnaliz" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title"><i class="bx bx-scan text-primary me-2"></i>Belgelerden Personel Bilgisi Çıkar</h5>
                    <small class="text-muted">Sonuçlar kontrolünüzden sonra forma aktarılır.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="personelBelgeSecimAlani">
                    <label for="personelBelgeDosyalari" class="form-label fw-semibold">Personel belgeleri</label>
                    <input type="file" class="form-control" id="personelBelgeDosyalari" multiple accept="application/pdf,image/jpeg,image/png,image/webp">
                    <div class="form-text">PDF, JPG, PNG veya WEBP; en fazla 6 belge, belge başına 12 MB ve toplam 30 MB.</div>
                    <div id="personelBelgeDosyaListesi" class="mt-3"></div>
                    <div class="alert alert-warning d-flex gap-2 mt-3 mb-0 py-2">
                        <i class="bx bx-shield-quarter fs-5"></i>
                        <small>Belgeler analiz için OpenAI servisine gönderilir ve bu adımda evrak arşivine kaydedilmez. Yalnızca gerekli belgeleri yükleyin.</small>
                    </div>
                </div>
                <div id="personelBelgeAnalizYukleniyor" class="text-center py-5 d-none">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h6>Belgeler analiz ediliyor</h6>
                    <p class="text-muted mb-0">Belge sayısına göre bu işlem biraz sürebilir.</p>
                </div>
                <div id="personelBelgeAnalizSonuc" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Bulunan bilgiler</h6>
                        <button type="button" class="btn btn-sm btn-link" id="btnPersonelBelgeYeniAnaliz">Yeni analiz</button>
                    </div>
                    <div id="personelBelgeUyarilar"></div>
                    <div class="table-responsive border rounded">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th style="width:42px"></th><th>Alan</th><th>Bulunan Değer</th><th>Kaynak</th><th>Güven</th></tr></thead>
                            <tbody id="personelBelgeAlanlar"></tbody>
                        </table>
                    </div>
                    <div id="personelBelgeArsivAlani" class="mt-3 d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div>
                                <h6 class="mb-0"><i class="bx bx-folder-plus text-primary me-1"></i>İşe giriş evraklarına kaydet</h6>
                                <small class="text-muted">Eşleşen belgeler personel kaydedilirken Evraklar sekmesine eklenir.</small>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="personelBelgeTumunuArsivle" checked>
                                <label class="form-check-label" for="personelBelgeTumunuArsivle">Tümü</label>
                            </div>
                        </div>
                        <div id="personelBelgeArsivListesi" class="list-group"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" id="btnPersonelBelgeleriAnalizEt"><i class="bx bx-scan me-1"></i> Analiz Et</button>
                <button type="button" class="btn btn-success d-none" id="btnPersonelBelgeAlanlariniUygula"><i class="bx bx-check me-1"></i> Seçimleri Uygula</button>
            </div>
        </div>
    </div>
</div>
