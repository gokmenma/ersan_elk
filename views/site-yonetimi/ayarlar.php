<?php
require_once dirname(__DIR__, 1) . '/../Autoloader.php';
use App\Helper\Form;

$maintitle = 'Site Yönetimi';
$title = 'Site Ayarları';
?>
<style>
.sa-sec { font-size: 12px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #74788d; margin: 10px 0 -4px; }
.sa-hint { font-size: 12px; color: #74788d; margin: 6px 2px 0; }
.sa-tabs .nav-link { font-weight: 600; font-size: 13px; }
.sa-bar { position: sticky; bottom: 0; z-index: 5; }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-8 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-cog fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Site Ayarları</h4>
                <p class="text-muted mb-0 font-size-12">İletişim bilgileri, hakkımızda, çalışma süreci, slayt metinleri ve sosyal medya. Boş bıraktığınız alanda sitenin varsayılan metni kullanılır.</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-4 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <button type="submit" form="ayarForm" class="btn btn-primary top-action-btn shadow-sm text-white" id="btnAyarKaydet">
                <i class="bx bx-save font-size-16"></i> Kaydet
            </button>
        </div>
    </div>

    <div class="card summary-kpi-card mb-3">
        <div class="card-body">
            <ul class="nav nav-tabs sa-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sekmeGenel" type="button" role="tab">Genel ve İletişim</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sekmeHakkimizda" type="button" role="tab">Hakkımızda</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sekmeSurec" type="button" role="tab">Çalışma Süreci</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sekmeSlayt" type="button" role="tab">Slayt Metinleri</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sekmeSosyal" type="button" role="tab">Sosyal Medya</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sekmeSeo" type="button" role="tab">SEO ve Paylaşım</button></li>
            </ul>
            <form id="ayarForm" novalidate>
                <input type="hidden" name="action" value="ayar-kaydet">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="sekmeGenel" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "site_adi", "", "Örn: Ersan Ltd. Şti.", "Site / Firma Adı", "briefcase", "form-control", false, 100) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("tel", "telefon", "", "+90 5xx xxx xx xx", "Telefon", "phone", "form-control", false, 30) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("email", "eposta", "", "info@firma.com", "E-posta", "mail", "form-control", false, 190) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "calisma_saatleri", "", "Pazartesi - Cumartesi: 08:30 - 18:30", "Çalışma Saatleri", "clock", "form-control", false, 150) ?></div>
                            <div class="col-12"><?= Form::FormFloatInput("text", "adres", "", "Açık adres", "Adres", "map-pin", "form-control", false, 300) ?></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sekmeHakkimizda" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12"><?= Form::FormFloatInput("text", "hakkimizda_baslik", "", "Örn: Sahada 15 Yılı Aşan Tecrübe", "Hakkımızda Başlığı", "type", "form-control", false, 150) ?></div>
                            <div class="col-12"><?= Form::FormFloatTextarea("hakkimizda_metin", "", "Firmanızı tanıtan paragraf", "Hakkımızda Metni", "file-text", "form-control", false, "140px") ?></div>
                            <div class="col-12"><?= Form::FormFloatTextarea("hakkimizda_maddeler", "", "Her satıra bir madde (en fazla 8)", "Öne Çıkan Maddeler (satır satır)", "check-square", "form-control", false, "130px") ?></div>
                            <div class="col-md-6"><?= Form::FormFloatTextarea("misyon", "", "Misyon metni", "Misyon", "target", "form-control", false, "100px") ?></div>
                            <div class="col-md-6"><?= Form::FormFloatTextarea("vizyon", "", "Vizyon metni", "Vizyon", "eye", "form-control", false, "100px") ?></div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="sekmeSurec" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12"><?= Form::FormFloatTextarea("surec_adimlari", "", "Her satıra: Başlık | Açıklama (en fazla 6 adım)", "Çalışma Adımları (satır satır)", "list", "form-control", false, "200px") ?></div>
                        </div>
                        <p class="sa-hint">Örnek: <code>İhtiyaç Analizi | Bölge, abone sayısı ve takvimi birlikte belirliyoruz.</code> Başlık ile açıklamayı "|" karakteriyle ayırın.</p>
                    </div>
                    <div class="tab-pane fade" id="sekmeSlayt" role="tabpanel">
                        <div class="row g-3">
                        <div class="col-12"><div class="sa-sec">1. Slayt</div></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("text", "slayt1_kicker", "", "Örn: Sayaç Okuma", "Üst Etiket", "tag", "form-control", false, 60) ?></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("text", "slayt1_baslik", "", "Slayt başlığı", "Başlık", "type", "form-control", false, 120) ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("slayt1_metin", "", "Kısa açıklama", "Açıklama", "align-left", "form-control", false, "80px") ?></div>
                        <div class="col-12"><div class="sa-sec">2. Slayt</div></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("text", "slayt2_kicker", "", "Örn: Sayaç Okuma", "Üst Etiket", "tag", "form-control", false, 60) ?></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("text", "slayt2_baslik", "", "Slayt başlığı", "Başlık", "type", "form-control", false, 120) ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("slayt2_metin", "", "Kısa açıklama", "Açıklama", "align-left", "form-control", false, "80px") ?></div>
                        <div class="col-12"><div class="sa-sec">3. Slayt</div></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("text", "slayt3_kicker", "", "Örn: Sayaç Okuma", "Üst Etiket", "tag", "form-control", false, 60) ?></div>
                        <div class="col-md-6"><?= Form::FormFloatInput("text", "slayt3_baslik", "", "Slayt başlığı", "Başlık", "type", "form-control", false, 120) ?></div>
                        <div class="col-12"><?= Form::FormFloatTextarea("slayt3_metin", "", "Kısa açıklama", "Açıklama", "align-left", "form-control", false, "80px") ?></div>
                        </div>
                        <p class="sa-hint">Slayt görselleri şimdilik sitenin tasarım dosyalarından gelir; yalnızca metinler buradan değiştirilir.</p>
                    </div>
                    <div class="tab-pane fade" id="sekmeSeo" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-12"><?= Form::FormFloatInput("text", "seo_baslik", "", "Örn: Ersan | Sayaç Okuma, Kesme-Açma ve Saha Hizmetleri", "Ana Sayfa Başlığı (en fazla 70 karakter)", "search", "form-control", false, 70) ?></div>
                            <div class="col-12"><?= Form::FormFloatTextarea("seo_aciklama", "", "Arama sonuçlarında ve paylaşımlarda görünen kısa açıklama", "Ana Sayfa Açıklaması (en fazla 160 karakter)", "align-left", "form-control", false, "90px") ?></div>
                        </div>
                        <p class="sa-hint">Diğer sayfaların başlıkları "Sayfa Adı | Firma Adı" biçiminde otomatik oluşur. Boş bırakırsanız sitenin varsayılan başlığı ve açıklaması kullanılır.</p>
                    </div>
                    <div class="tab-pane fade" id="sekmeSosyal" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "sosyal_facebook", "", "https://facebook.com/...", "Facebook", "facebook", "form-control", false, 255) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "sosyal_instagram", "", "https://instagram.com/...", "Instagram", "instagram", "form-control", false, 255) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "sosyal_linkedin", "", "https://linkedin.com/company/...", "LinkedIn", "linkedin", "form-control", false, 255) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("text", "sosyal_youtube", "", "https://youtube.com/...", "YouTube", "youtube", "form-control", false, 255) ?></div>
                            <div class="col-md-6"><?= Form::FormFloatInput("tel", "sosyal_whatsapp", "", "905xxxxxxxxx", "WhatsApp Numarası", "message-circle", "form-control", false, 30) ?></div>
                        </div>
                        <p class="sa-hint">Bağlantılar https:// ile başlamalıdır. Dolu olanlar sitenin alt bölümünde görünür.</p>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="views/site-yonetimi/js/ayarlar.js?v=<?php echo time(); ?>"></script>
