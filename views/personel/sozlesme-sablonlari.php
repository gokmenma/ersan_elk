<?php

require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;
use App\Helper\Security;
use App\Model\SozlesmeSablonModel;
use App\Service\Gate;
use App\Service\SozlesmeSablonService;

$maintitle = "Personel";
$title = "Sözleşme Şablonları";

$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$Model = new SozlesmeSablonModel();
$hazir = $Model->hazirMi();
$surumler = $hazir ? $Model->listele($firmaId) : [];
$kullanimdaOlan = null;
foreach ($surumler as $s) {
    if ((int) $s->kullanimda === 1) {
        $kullanimdaOlan = $s;
        break;
    }
}
$duzenleyebilir = Gate::allows('personel/sozlesme-sablonlari-duzenle');
$alanlar = SozlesmeSablonService::alanTanimlari();
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$apiUrl = 'views/personel/api/APIsozlesme-sablonlari.php';
?>
<style>
.sablon-kod { cursor: pointer; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.sablon-kod:hover { background: var(--bs-primary-bg-subtle); }
</style>

<?php include 'layouts/breadcrumb.php'; ?>

<div class="container-fluid">
    <div class="row align-items-center mb-3">
        <div class="col-md-6 col-12 d-flex align-items-center gap-3">
            <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center shadow-sm flex-shrink-0" style="width: 44px; height: 44px;">
                <i class="bx bx-file fs-4 text-primary"></i>
            </div>
            <div>
                <h4 class="mb-0 fw-bold text-dark font-size-16">Sözleşme Şablonları</h4>
                <p class="text-muted mb-0 font-size-12">Belirli süreli hizmet sözleşmesi şablonunu Word'de düzenleyip yeni sürüm olarak yükleyin</p>
            </div>
        </div>
        <div class="personel-action-toolbar col-md-6 col-12 d-flex align-items-center justify-content-md-end gap-2 mt-2 mt-md-0">
            <a class="btn btn-outline-secondary bg-white top-action-btn shadow-sm" href="<?= $e($apiUrl) ?>?action=sablon-indir&id=<?= $kullanimdaOlan ? $e(rawurlencode(Security::encrypt($kullanimdaOlan->id))) : 'varsayilan' ?>">
                <i class="bx bx-download font-size-16"></i> Kullanımdaki Şablonu İndir
            </a>
            <?php if ($duzenleyebilir && $hazir): ?>
            <button type="button" class="btn btn-primary top-action-btn shadow-sm text-white" data-bs-toggle="modal" data-bs-target="#sablonYukleModal">
                <i class="bx bx-upload font-size-16"></i> Yeni Sürüm Yükle
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$hazir): ?>
    <div class="alert alert-warning">Şablon yönetimi için veritabanı betiği (sql/2026_11_11_hizmet_sozlesmesi_sablon_yonetimi.sql) çalıştırılmalıdır. O zamana kadar sistem varsayılan şablonu kullanılır.</div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card mb-0" id="sablonListCard">
                <div class="card-header bg-transparent d-flex align-items-center gap-2">
                    <div class="bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;"><i class="bx bx-list-ul fs-5"></i></div>
                    <div>
                        <h5 class="font-size-14 fw-bold mb-0">Şablon Sürümleri</h5>
                        <p class="text-muted font-size-12 mb-0">Her yükleme yeni sürüm olarak saklanır; istediğiniz sürüme geri dönebilirsiniz</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle w-100 mb-0" id="sablonTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Sürüm</th>
                                    <th>Açıklama / Dosya</th>
                                    <th>Yükleyen</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th class="text-center" style="width: 130px;">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Sistem</strong></td>
                                    <td>Varsayılan şablon <div class="text-muted font-size-11">Hiç sürüm yüklenmediğinde veya varsayılana dönüldüğünde kullanılır</div></td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>
                                        <?php if (!$kullanimdaOlan): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kullanımda</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-btn-group d-inline-flex gap-1">
                                            <a class="btn btn-subtle-primary table-action-btn" title="İndir" href="<?= $e($apiUrl) ?>?action=sablon-indir&id=varsayilan"><i class="bx bx-download"></i></a>
                                            <?php if ($duzenleyebilir && $kullanimdaOlan): ?>
                                            <button type="button" class="btn btn-subtle-success table-action-btn sablon-varsayilana-don" title="Bu sürümü kullan"><i class="bx bx-check-circle"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php foreach ($surumler as $s): $sifreli = Security::encrypt($s->id); ?>
                                <tr>
                                    <td><strong>v<?= (int) $s->versiyon ?></strong></td>
                                    <td>
                                        <?= $e($s->aciklama ?: '-') ?>
                                        <div class="text-muted font-size-11"><?= $e($s->orijinal_dosya_adi) ?> · <?= $e(number_format(((int) $s->dosya_boyutu) / 1024, 0, ',', '.')) ?> KB</div>
                                    </td>
                                    <td><?= $e($s->yukleyen_adi ?: '-') ?></td>
                                    <td><?= $e(date('d.m.Y H:i', strtotime((string) $s->olusturma_tarihi))) ?></td>
                                    <td>
                                        <?php if ((int) $s->kullanimda === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Kullanımda</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-btn-group d-inline-flex gap-1">
                                            <a class="btn btn-subtle-primary table-action-btn" title="İndir" href="<?= $e($apiUrl) ?>?action=sablon-indir&id=<?= $e(rawurlencode($sifreli)) ?>"><i class="bx bx-download"></i></a>
                                            <?php if ($duzenleyebilir && (int) $s->kullanimda !== 1): ?>
                                            <button type="button" class="btn btn-subtle-success table-action-btn sablon-aktif-et" title="Bu sürümü kullan" data-id="<?= $e($sifreli) ?>"><i class="bx bx-check-circle"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card mb-0">
                <div class="card-header bg-transparent">
                    <h5 class="font-size-14 fw-bold mb-0"><i class="bx bx-code-curly me-1"></i> Yer Tutucular</h5>
                    <p class="text-muted font-size-12 mb-0">Word belgesinde ilgili yere yazın; sözleşme indirilirken personelin bilgisiyle değişir. Kopyalamak için tıklayın.</p>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($alanlar as $kod => $aciklama): ?>
                            <tr>
                                <td><code class="sablon-kod px-1 rounded" data-kod="{{<?= $e($kod) ?>}}">{{<?= $e($kod) ?>}}</code></td>
                                <td class="font-size-12 text-muted"><?= $e($aciklama) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="p-3 font-size-12 text-muted border-top">
                        <strong>Nasıl düzenlenir?</strong> Şablonu indirin, Word'de madde ekleyip değiştirin, yer tutucuları (<code>{{AD_SOYAD}}</code> gibi) kendi yerlerinde bırakın ve "Yeni Sürüm Yükle" ile yükleyin. Tanımsız bir yer tutucu yazarsanız yükleme reddedilir.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($duzenleyebilir && $hazir): ?>
<div class="modal fade" id="sablonYukleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bx bx-upload me-2"></i>Yeni Şablon Sürümü Yükle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <form id="sablonYukleForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="sablon-yukle">
                    <div class="mb-3"><?= Form::FormFileInput('sablon_dosyasi', 'Word Dosyası (.docx)', 'bx bx-file', 'form-control', true, 'accept=".docx"') ?></div>
                    <div class="mb-2"><?= Form::FormFloatTextarea('aciklama', '', 'Örn: Madde 16 eklendi', 'Değişiklik Açıklaması', 'bx bx-note', 'form-control', false, '80px', 3, 'maxlength="255"') ?></div>
                    <p class="font-size-12 text-muted mb-0">Yüklenen sürüm hemen kullanıma alınır. Önceki sürümler saklanır.</p>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Kapat</button>
                <button type="button" class="btn btn-primary" id="btnSablonYukle"><i class="bx bx-upload me-1"></i>Yükle</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script>window.SOZLESME_SABLON_API = <?= json_encode($apiUrl) ?>;</script>
<script src="views/personel/js/sozlesme-sablonlari.js?v=<?php echo time(); ?>"></script>
