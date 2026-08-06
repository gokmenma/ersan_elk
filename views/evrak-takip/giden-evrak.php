<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Helper\Form;
use App\Helper\Security;
use App\Model\EvrakTakipModel;
use App\Model\FirmaModel;
use App\Model\PersonelModel;
use App\Model\SettingsModel;
use App\Service\IcraUstYaziService;

$Evrak = new EvrakTakipModel();
$Personel = new PersonelModel();
$Settings = new SettingsModel();
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
$firma = (new FirmaModel())->find($firmaId);
$settings = (object) $Settings->getAllSettingsAsKeyValue($firmaId);
$personeller = $Personel->all(false, 'all_with_external');
$gelenEvraklar = $Evrak->getGelenEvraklar();
$signingUsers = $Evrak->getSigningUsers();

$encryptedId = (string) ($_GET['id'] ?? '');
$record = null;
if ($encryptedId !== '') {
    $recordId = (int) Security::decrypt($encryptedId);
    $record = $recordId > 0 ? $Evrak->getById($recordId) : null;
    if (!$record || $record->evrak_tipi !== 'giden') {
        echo '<div class="alert alert-danger">Giden evrak bulunamadı.</div>';
        return;
    }
}

$icraUstYazi = new IcraUstYaziService();
$icraPersonelOptions = $icraUstYazi->icrasiOlanPersoneller();

$prefill = [];
$icraParam = (string) ($_GET['icra_id'] ?? '');
if ($icraParam !== '' && !$record) {
    try {
        $taslak = $icraUstYazi->build((int) Security::decrypt($icraParam));
        $prefill = [
            'konu' => $taslak['konu'],
            'kurum_adi' => $taslak['kurum_adi'],
            'ilgiler' => $taslak['ilgiler'],
            'aciklama' => $taslak['aciklama_html'],
            'ilgili_personel_id' => $taslak['personel_id'],
        ];
    } catch (\Throwable $e) {
        error_log('Giden evrak icra ön doldurma hatası: ' . $e->getMessage());
    }
}

$value = static fn(string $field, mixed $default = '') => $record->$field ?? ($prefill[$field] ?? $default);
$defaultEvrakNo = $record ? ($record->evrak_no ?? '') : $Evrak->getMaxEvrakNo('giden');
$dateValue = $record && !empty($record->tarih) ? date('d.m.Y', strtotime($record->tarih)) : date('d.m.Y');

$personelOptions = ['' => 'Seçiniz...'];
foreach ($personeller as $personel) {
    $personelOptions[$personel->id] = $personel->adi_soyadi;
}
$gelenOptions = ['' => 'İlişkili gelen evrak seçiniz...'];
$gelenEvrakMap = [];
foreach ($gelenEvraklar as $gelen) {
    $encId = Security::encrypt($gelen->id);
    $gelenOptions[$encId] = ($gelen->evrak_no ?: '-') . ' — ' . $gelen->konu . ' (' . date('d.m.Y', strtotime($gelen->tarih)) . ')';
    $gelenEvrakMap[$encId] = [
        'id' => $encId,
        'evrak_no' => (string) ($gelen->evrak_no ?: ''),
        'tarih' => !empty($gelen->tarih) ? date('d.m.Y', strtotime($gelen->tarih)) : '',
        'konu' => (string) ($gelen->konu ?: ''),
        'kurum_adi' => (string) ($gelen->kurum_adi ?: ''),
    ];
}
$selectedSignerIds = json_decode((string) ($record->imza_kullanici_ids ?? '[]'), true) ?: [];
if ($record && $selectedSignerIds !== []) {
    $existingSigners = $Evrak->getSigningUsersByIds($selectedSignerIds);
    $signingUserIds = array_map(fn($u) => (int) $u->id, $signingUsers);
    foreach ($existingSigners as $exUser) {
        if (!in_array((int) $exUser->id, $signingUserIds, true)) {
            $exUser->adi_soyadi .= ' (Pasif)';
            $signingUsers[] = $exUser;
        }
    }
}

$signingUserMap = [];
foreach ($signingUsers as $user) {
    $user->enc_id = Security::encrypt((int) $user->id);
    $signingUserMap[(int) $user->id] = $user;
}

$selectedSigners = [];
$signerOptions = [];
foreach ($selectedSignerIds as $id) {
    $id = (int) $id;
    if (isset($signingUserMap[$id])) {
        $user = $signingUserMap[$id];
        $selectedSigners[] = $user->enc_id;
        $signerOptions[$user->enc_id] = trim($user->adi_soyadi . ($user->imza_unvani ? ' — ' . $user->imza_unvani : ''));
        unset($signingUserMap[$id]);
    }
}
foreach ($signingUserMap as $user) {
    $signerOptions[$user->enc_id] = trim($user->adi_soyadi . ($user->imza_unvani ? ' — ' . $user->imza_unvani : ''));
}
$selectedRelated = !empty($record->ilgili_evrak_id) ? Security::encrypt($record->ilgili_evrak_id) : '';
$solLogoPath = (string) ($settings->sol_logo_yolu ?? $firma->logo_yolu ?? '');
$sagLogoPath = (string) ($settings->sag_logo_yolu ?? '');
$currentUserId = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
$onayDurumu = $record->onay_durumu ?? 'taslak';
$kilitli = $record && $onayDurumu !== 'taslak';
if ($record && $onayDurumu === 'taslak') {
    $Evrak->ensureApprovalRowsForDrafts();
}
$onayKayitlari = $record ? $Evrak->getApprovalState((int) $record->id) : [];
$bekleyenImzam = false;
$onaylananSayisi = 0;
foreach ($onayKayitlari as $onayKaydi) {
    if ($onayKaydi['durum'] === 'onaylandi') {
        $onaylananSayisi++;
    } elseif ((int) $onayKaydi['kullanici_id'] === $currentUserId) {
        $bekleyenImzam = true;
    }
}
$siradakiImzaci = $record ? $Evrak->getNextPendingSigner((int) $record->id) : null;
$siraBende = $siradakiImzaci && (int) $siradakiImzaci->kullanici_id === $currentUserId;
$geriAlinabilir = $record ? $Evrak->canRevokeApproval($record, $currentUserId) : false;
$iadeGerekcesi = trim((string) ($record->e_imza_iade_gerekcesi ?? ''));
$iadeEden = '';
if ($iadeGerekcesi !== '' && !empty($record->e_imza_iade_kullanici_id)) {
    $iadeEdenUser = (new \App\Model\UserModel())->find((int) $record->e_imza_iade_kullanici_id);
    $iadeEden = (string) ($iadeEdenUser->adi_soyadi ?? '');
}

$existingAttachments = [];
if ($record) {
    foreach ($Evrak->getAttachments((int) $record->id) as $attachment) {
        $existingAttachments[] = [
            'id' => Security::encrypt($attachment->id),
            'name' => $attachment->dosya_adi,
            'path' => $attachment->dosya_yolu,
            'size' => (int) $attachment->dosya_boyutu,
            'date' => $attachment->olusturma_tarihi,
        ];
    }
}
?>

<div class="container-fluid">
    <?php $maintitle = 'Evrak Takip'; $title = $record ? 'Giden Evrak Düzenle' : 'Yeni Giden Evrak'; include 'layouts/breadcrumb.php'; ?>

    <?php if ($kilitli): ?>
        <div class="alert <?php echo $onayDurumu === 'onaylandi' ? 'alert-success' : 'alert-warning'; ?> d-flex align-items-start border-0 shadow-sm mb-3">
            <i data-feather="lock" class="icon-sm me-2 mt-1"></i>
            <div>
                <?php if ($onayDurumu === 'onaylandi'): ?>
                    <div class="fw-bold">Bu evrak elektronik imza ile onaylanmıştır.</div>
                    <div class="small">
                        Onay tarihi: <?php echo $record->e_imza_onay_tarihi ? htmlspecialchars(date('d.m.Y H:i', strtotime($record->e_imza_onay_tarihi)), ENT_QUOTES, 'UTF-8') : '-'; ?>
                        &nbsp;•&nbsp; İmza süreci tamamlandığı için evrak kalıcı olarak kilitlendi; içeriği değiştirilemez ve geri alınamaz. Düzeltme gerekiyorsa yeni bir evrak düzenlenmelidir.
                    </div>
                <?php else: ?>
                    <div class="fw-bold">Evrak elektronik imza onayına sunuldu (<?php echo $onaylananSayisi . '/' . count($onayKayitlari); ?> imza tamamlandı).</div>
                    <div class="small">
                        <?php if ($siradakiImzaci): ?>
                            Sırada <strong><?php echo htmlspecialchars((string) $siradakiImzaci->adi_soyadi, ENT_QUOTES, 'UTF-8'); ?></strong> bulunuyor<?php echo $siraBende ? ' — <strong>imza sırası sizde</strong>' : ''; ?>.
                        <?php endif; ?>
                        Süreç tamamlanana kadar evrak içeriği değiştirilemez.
                        <?php if ($geriAlinabilir): ?>
                            Düzeltme gerekiyorsa <strong>İşlemler &rsaquo; Evrakı Üzerime Geri Al</strong> ile taslağa döndürebilirsiniz.
                        <?php else: ?>
                            Düzeltme gerekiyorsa evrakı oluşturan kullanıcı süreci geri alabilir.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($iadeGerekcesi !== ''): ?>
        <div class="alert alert-danger d-flex align-items-start border-0 shadow-sm mb-3">
            <i data-feather="corner-up-left" class="icon-sm me-2 mt-1"></i>
            <div>
                <div class="fw-bold">
                    Evrak imzalanmadan iade edildi<?php echo $iadeEden !== '' ? ' — ' . htmlspecialchars($iadeEden, ENT_QUOTES, 'UTF-8') : ''; ?>
                    <?php if (!empty($record->e_imza_iade_tarihi)): ?>
                        <span class="fw-normal small">(<?php echo htmlspecialchars(date('d.m.Y H:i', strtotime((string) $record->e_imza_iade_tarihi)), ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="small"><strong>Gerekçe:</strong> <?php echo nl2br(htmlspecialchars($iadeGerekcesi, ENT_QUOTES, 'UTF-8')); ?></div>
                <div class="small text-muted mt-1">Gerekli düzeltmeleri yaptıktan sonra evrakı yeniden onaya sunabilirsiniz.</div>
            </div>
        </div>
    <?php endif; ?>

    <form id="gidenEvrakForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="evrak-kaydet">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="evrak_tipi" value="giden">
        <input type="hidden" name="yazi_tipi" value="times_new_roman">
        <input type="hidden" name="ek_duzen_json" id="ek_duzen_json" value="[]">
        <input type="hidden" name="silinen_ek_ids_json" id="silinen_ek_ids_json" value="[]">

        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                <div><h5 class="mb-1 fw-bold"><?php echo $record ? 'Giden Evrak Düzenle' : 'Giden Evrak Ekle'; ?></h5><div class="text-muted small">Resmî yazı içeriğini ve evrak bilgilerini düzenleyin</div></div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" id="btnIcraUstYaziAc" class="btn btn-outline-success me-2"><i data-feather="file-plus" class="icon-xs me-1"></i> İcra Üst Yazısı</button>
                    <button type="button" id="btnAiTaslakAc" class="btn btn-outline-primary me-2"><i data-feather="zap" class="icon-xs me-1"></i> Yapay Zekâ ile Oluştur</button>
                    <?php if ($solLogoPath): ?><img src="<?php echo htmlspecialchars($solLogoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Sol logo" style="max-width:52px;max-height:45px;object-fit:contain" title="Sol Logo"><?php endif; ?>
                    <?php if ($sagLogoPath): ?><img src="<?php echo htmlspecialchars($sagLogoPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Sağ logo" style="max-width:52px;max-height:45px;object-fit:contain" title="Sağ Logo"><?php endif; ?>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-xl-8 border-end">
                        <ul class="nav nav-tabs px-3 pt-3" id="gidenEvrakTabs" role="tablist">
                            <li class="nav-item"><button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#gidenIcerikTab" type="button"><i data-feather="file-text" class="icon-xs me-1"></i> İçerik</button></li>
                            <li class="nav-item"><button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#gidenEklerTab" type="button"><i data-feather="paperclip" class="icon-xs me-1"></i> Ekler</button></li>
                        </ul>
                        <div class="tab-content p-3">
                            <div class="tab-pane fade show active" id="gidenIcerikTab">
                                <textarea id="giden_evrak_icerik" name="aciklama" class="form-control"><?php echo htmlspecialchars((string) $value('aciklama'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                                <div class="form-text mt-2">Varsayılan Times New Roman 12 puntodur. Summernote içindeki yazı tipi değişiklikleri PDF'e aktarılır.</div>
                            </div>
                            <div class="tab-pane fade" id="gidenEklerTab">
                                <div class="border rounded-3 p-3">
                                    <div class="d-flex align-items-center justify-content-between mb-3"><span class="fw-bold">Seç ve Ekle</span><i data-feather="plus" class="icon-sm"></i></div>
                                    <input type="file" id="ek_dosyalari" class="form-control" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt">
                                </div>
                                <div class="d-flex align-items-center justify-content-between mt-3 mb-2">
                                    <span class="small fw-bold text-muted">Eklenen Belgeler:</span>
                                    <span class="small text-muted">Belgeler aşağıdaki sırayla PDF ek listesinde gösterilir.</span>
                                </div>
                                <div id="ekBosUyari" class="alert alert-warning small">Henüz eklenmiş belge bulunmuyor.</div>
                                <div id="ekDosyaListesi" class="border rounded-3 overflow-hidden"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 bg-light-subtle">
                        <div class="p-3 d-grid gap-3 giden-meta-panel" style="overflow-y:auto">
                            <?php if ($onayKayitlari !== []): ?>
                                <div>
                                    <div class="small fw-bold text-uppercase text-muted mb-2">E-İmza Durumu</div>
                                    <div id="imzaDurumListesi" class="border rounded-3 overflow-hidden bg-white">
                                        <?php foreach ($onayKayitlari as $onayKaydi): ?>
                                            <?php $buSirada = $siradakiImzaci && (int) $siradakiImzaci->kullanici_id === (int) $onayKaydi['kullanici_id']; ?>
                                            <div class="d-flex align-items-center justify-content-between px-2 py-2 border-bottom <?php echo $buSirada && $kilitli ? 'bg-warning-subtle' : ''; ?>">
                                                <span class="small fw-semibold text-dark">
                                                    <span class="badge bg-light text-muted me-1" style="font-size:9px"><?php echo (int) $onayKaydi['sira']; ?></span>
                                                    <?php echo htmlspecialchars((string) $onayKaydi['adi_soyadi'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                                <?php if ($onayKaydi['durum'] === 'onaylandi'): ?>
                                                    <span class="badge bg-success-subtle text-success fw-bold" style="font-size:9px" title="<?php echo $onayKaydi['onay_tarihi'] ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $onayKaydi['onay_tarihi'])), ENT_QUOTES, 'UTF-8') : ''; ?>">İMZALADI</span>
                                                <?php elseif ($buSirada && $kilitli): ?>
                                                    <span class="badge bg-warning text-dark fw-bold" style="font-size:9px">SIRADA</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary fw-bold" style="font-size:9px">BEKLİYOR</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="form-text mt-1">İmzalar yukarıdaki sırayla atılır; sırası gelmeyen kullanıcı imzalayamaz.</div>
                                </div>
                            <?php endif; ?>
                            <div class="small fw-bold text-uppercase text-muted">Evrak Bilgileri</div>
                            <?php echo Form::FormFloatInput('text', 'tarih', $dateValue, 'Evrak Tarihi', 'Evrak Tarihi', 'calendar', 'form-control flatpickr', true); ?>
                            <?php echo Form::FormFloatInput('text', 'evrak_no', $defaultEvrakNo, 'Sayı', 'Sayı / Evrak No', 'hash', 'form-control', true); ?>
                            <?php echo Form::FormFloatInput('text', 'konu', $value('konu'), 'Konu', 'Konu', 'type', 'form-control', true); ?>
                            <?php echo Form::FormFloatInput('text', 'kurum_adi', $value('kurum_adi'), 'Muhatap', 'Muhatap Kurum / Kişi', 'home', 'form-control', true); ?>
                            <?php echo Form::FormFloatInput('text', 'muhatap_alt_birim', $value('muhatap_alt_birim'), 'Muhatap Alt Birimi', 'Alt Birim / Bölüm', 'layers'); ?>
                            <?php echo Form::FormFloatTextarea('muhatap_adres', $value('muhatap_adres'), 'Muhatap Adresi', 'Muhatap Adresi', 'map-pin', 'form-control', false, '90px'); ?>
                            <hr class="my-1">
                            <div class="small fw-bold text-uppercase text-muted">İmza ve İlişkilendirme</div>
                            <?php echo Form::FormMultipleSelect2('imza_kullanici_ids', $signerOptions, $selectedSigners, 'İmza Atacak Kişiler (En fazla 3)', 'edit-2', 'key', '', 'form-select giden-select2-multiple', true, 'imza_kullanici_ids', 'data-maximum-selection-length="3"'); ?>
                            <div class="mt-1 mb-2" id="imzaSiraContainer">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="small fw-bold text-muted text-uppercase" style="font-size:0.72rem;letter-spacing:0.5px">İmza Sıralaması (Soldan Sağa)</span>
                                    <span class="small text-muted" style="font-size:0.7rem"><i class="bx bx-info-circle me-1"></i>Sırayı ⬆ ⬇ ile değiştirebilirsiniz</span>
                                </div>
                                <div id="imzaSiraListesi" class="border rounded-3 overflow-hidden bg-white shadow-sm"></div>
                            </div>
                            <?php 
                            $savedKiminAdina = json_decode((string) ($record->imza_kimin_adina_json ?? '[]'), true) ?: []; 
                            $baseKiminAdinaOptions = [
                                '' => 'Seçiniz (İsteğe Bağlı)...',
                                'Firma Yetkilisi a.' => 'Firma Yetkilisi a.',
                            ];
                            $getKiminAdinaOptions = function($selectedVal) use ($baseKiminAdinaOptions) {
                                $opts = $baseKiminAdinaOptions;
                                if (!empty($selectedVal) && !isset($opts[$selectedVal])) {
                                    $opts[$selectedVal] = $selectedVal;
                                }
                                return $opts;
                            };
                            ?>
                            <?php echo Form::FormSelect2('kimin_adina_1', $getKiminAdinaOptions($savedKiminAdina[0] ?? ''), $savedKiminAdina[0] ?? '', '1. İmza Kimin Adına', 'user-check', 'key', '', 'form-select giden-select2-tags'); ?>
                            <?php echo Form::FormSelect2('kimin_adina_2', $getKiminAdinaOptions($savedKiminAdina[1] ?? ''), $savedKiminAdina[1] ?? '', '2. İmza Kimin Adına', 'user-check', 'key', '', 'form-select giden-select2-tags'); ?>
                            <?php echo Form::FormSelect2('kimin_adina_3', $getKiminAdinaOptions($savedKiminAdina[2] ?? ''), $savedKiminAdina[2] ?? '', '3. İmza Kimin Adına', 'user-check', 'key', '', 'form-select giden-select2-tags'); ?>
                            <?php echo Form::FormSelect2('ilgili_evrak_id', $gelenOptions, $selectedRelated, 'İlişkili Gelen Evrak', 'link', 'key', '', 'form-select giden-select2'); ?>
                            <?php echo Form::FormSelect2('personel_id', $personelOptions, $value('personel_id'), 'Zimmetlenen Personel', 'user-check', 'key', '', 'form-select giden-select2'); ?>
                            <?php echo Form::FormSelect2('ilgili_personel_id', $personelOptions, $value('ilgili_personel_id'), 'İlgili Personel', 'user', 'key', '', 'form-select giden-select2'); ?>
                            <hr class="my-1">
                            <div class="small fw-bold text-uppercase text-muted">İlgi ve Ekler</div>
                            <?php echo Form::FormFloatTextarea('ilgiler', $value('ilgiler'), 'İlgi — Her satıra bir kayıt', 'İlgi Belgeleri', 'link', 'form-control', false, '110px'); ?>
                            <div class="form-text mt-n2 mb-2">PDF'de a), b), c) şeklinde sıralanır.</div>
                            <?php echo Form::FormFloatTextarea('ekler', $value('ekler'), 'Ekler — Her satıra bir kayıt', 'Ek Metni (İsteğe Bağlı)', 'paperclip', 'form-control', false, '90px'); ?>
                            <div class="form-text mt-n2">Resmî yazıda Ek alanının görünmesini istiyorsanız doldurunuz.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="giden-action-bar d-flex align-items-center justify-content-between py-3 px-4 bg-white border-top shadow-lg">
            <div>
                <?php if (!$kilitli): ?>
                    <button type="button" id="btnFormuTemizle" class="btn btn-outline-danger px-3" title="Tüm evrak bilgilerini sıfırla"><i data-feather="trash-2" class="icon-xs me-1"></i> Formu Temizle</button>
                <?php else: ?>
                    <span class="small text-muted d-flex align-items-center"><i data-feather="lock" class="icon-xs me-1"></i> Evrak elektronik imza sürecinde kilitli</span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="index?p=evrak-takip/list" class="btn btn-light px-4"><?php echo $kilitli ? 'Listeye Dön' : 'Vazgeç'; ?></a>
                <button type="button" id="btnGidenPdfOnizle" class="btn btn-outline-primary px-4"><i data-feather="eye" class="icon-xs me-1"></i> Önizle</button>
                <?php
                $imzalayabilir = $onayDurumu === 'onay_bekliyor' && $siraBende;
                $islemVar = !$kilitli || $imzalayabilir || ($kilitli && $geriAlinabilir);
                ?>
                <?php if ($islemVar): ?>
                    <div class="dropup">
                        <button type="button" id="btnGidenIslemler" class="btn btn-outline-secondary px-4" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                            <i data-feather="settings" class="icon-xs me-1"></i> İşlemler <i class="bx bx-chevron-up ms-1"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 py-2" style="min-width:265px">
                            <?php if (!$kilitli): ?>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-start gap-2 py-2" id="btnEImzaOnayaSun">
                                        <i data-feather="send" class="icon-xs text-primary mt-1"></i>
                                        <span>
                                            <span class="d-block fw-semibold">E-İmza ile Onaya Sun</span>
                                            <span class="d-block small text-muted">Evrak kaydedilir ve imzacılara gönderilir</span>
                                        </span>
                                    </button>
                                </li>
                            <?php endif; ?>
                            <?php if ($imzalayabilir): ?>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-start gap-2 py-2" id="btnEImzaOnayla" data-id="<?php echo htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i data-feather="check-circle" class="icon-xs text-success mt-1"></i>
                                        <span>
                                            <span class="d-block fw-semibold">E-İmza ile Onayla</span>
                                            <span class="d-block small text-muted">Kendi imzanızı bu evraka ekler</span>
                                        </span>
                                    </button>
                                </li>
                            <?php endif; ?>
                            <?php if ($imzalayabilir): ?>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-start gap-2 py-2" id="btnEImzaIade" data-id="<?php echo htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i data-feather="corner-up-left" class="icon-xs text-danger mt-1"></i>
                                        <span>
                                            <span class="d-block fw-semibold">Düzeltilmek Üzere İade Et</span>
                                            <span class="d-block small text-muted">İmzalamadan gerekçeyle hazırlayana geri gönderir</span>
                                        </span>
                                    </button>
                                </li>
                            <?php endif; ?>
                            <?php if ($kilitli && $geriAlinabilir): ?>
                                <li>
                                    <button type="button" class="dropdown-item d-flex align-items-start gap-2 py-2" id="btnEImzaGeriAl" data-id="<?php echo htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i data-feather="rotate-ccw" class="icon-xs text-info mt-1"></i>
                                        <span>
                                            <span class="d-block fw-semibold">Evrakı Üzerime Geri Al</span>
                                            <span class="d-block small text-muted">İmza süreci iptal edilir, evrak taslağa döner</span>
                                        </span>
                                    </button>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!$kilitli): ?>
                    <button type="submit" id="btnGidenKaydet" class="btn btn-primary px-5"><i data-feather="save" class="icon-xs me-1"></i> Kaydet</button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<style>
.giden-action-bar{position:fixed;left:250px;right:0;bottom:0;z-index:1020;margin:0}
#gidenEvrakForm>.card{margin-bottom:0}
#imzaDurumListesi>div:last-child{border-bottom:0!important}
#gidenEvrakForm.evrak-kilitli .note-editable{background:#f8f9fa}
#gidenEvrakForm.evrak-kilitli .select2-container{pointer-events:none;opacity:.85}
#ekDosyaListesi:empty{display:none}.ek-dosya-satiri{background:#fff;border-bottom:1px solid var(--bs-border-color);padding:14px 16px}.ek-dosya-satiri:last-child{border-bottom:0}
#gidenPdfLoader.giden-pdf-hidden,#gidenPdfFrame.giden-pdf-hidden{display:none!important}
#evrakAiContextMenu{position:fixed;z-index:1090;min-width:220px;display:none}
#evrakAiContextMenu .dropdown-item{cursor:pointer;padding:.65rem .9rem}
.ai-secim-onizleme{max-height:130px;overflow:auto;white-space:pre-wrap}
.giden-meta-panel::-webkit-scrollbar,#gidenEklerTab::-webkit-scrollbar,#gidenEvrakForm .note-editable::-webkit-scrollbar{width:6px}
.giden-meta-panel::-webkit-scrollbar-thumb,#gidenEklerTab::-webkit-scrollbar-thumb,#gidenEvrakForm .note-editable::-webkit-scrollbar-thumb{background-color:rgba(0,0,0,0.15);border-radius:4px}
.giden-meta-panel::-webkit-scrollbar-thumb:hover,#gidenEklerTab::-webkit-scrollbar-thumb:hover,#gidenEvrakForm .note-editable::-webkit-scrollbar-thumb:hover{background-color:rgba(0,0,0,0.3)}
@media(min-width:992px){html,body{overflow:hidden!important}.page-content{padding-bottom:70px!important}}
@media(max-width:991.98px){.giden-action-bar{left:0}html,body{overflow:auto!important}}
</style>

<script>
window.gidenEvrakKilitli = <?php echo $kilitli ? 'true' : 'false'; ?>;
window.gidenExistingAttachments = <?php echo json_encode($existingAttachments, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.gidenGelenEvraklarMap = <?php echo json_encode($gelenEvrakMap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>

<div id="evrakAiContextMenu" class="dropdown-menu shadow border-0 p-1">
    <button type="button" id="btnAiSecimDuzenleAc" class="dropdown-item rounded"><i class="bx bx-magic-wand text-primary me-2"></i>Yapay Zekâ ile Düzenle</button>
</div>

<div class="modal fade" id="evrakAiSecimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
        <div class="modal-header"><div><h5 class="modal-title"><i class="bx bx-magic-wand text-primary me-1"></i> Seçili Metni Düzenle</h5><div class="small text-muted mt-1">Yalnızca seçtiğiniz bölüm değiştirilecektir.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="form-label small fw-semibold text-muted">Seçili metin</label>
            <div id="aiSeciliMetinOnizleme" class="ai-secim-onizleme border rounded bg-light p-2 small mb-3"></div>
            <label for="aiSecimTalimat" class="form-label fw-semibold">Nasıl düzenlensin?</label>
            <textarea id="aiSecimTalimat" class="form-control" rows="4" maxlength="2000" placeholder="Örnek: Daha resmî ve hukuki bir dille, anlamı ve rakamları değiştirmeden düzenle."></textarea>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-light ai-hizli-talimat" data-talimat="Anlamı ve tüm somut bilgileri koruyarak daha resmî, akademik ve hukuki bir dille düzenle.">Resmîleştir</button>
                <button type="button" class="btn btn-sm btn-light ai-hizli-talimat" data-talimat="Anlamı ve tüm somut bilgileri koruyarak daha kısa ve açık hâle getir.">Kısalt</button>
                <button type="button" class="btn btn-sm btn-light ai-hizli-talimat" data-talimat="Anlamı değiştirmeden anlatım ve dil bilgisi hatalarını düzelt.">Dilbilgisini Düzelt</button>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Vazgeç</button><button type="button" id="btnAiSecimUygula" class="btn btn-primary px-4"><i class="bx bx-magic-wand me-1"></i>Düzenle ve Uygula</button></div>
    </div></div>
</div>

<div class="modal fade" id="icraUstYaziModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 shadow">
        <div class="modal-header"><div><h5 class="modal-title"><i data-feather="file-plus" class="icon-sm text-success me-1"></i> İcra Üst Yazısı Oluştur</h5><div class="small text-muted mt-1">Personelin kayıtlı icra dosyalarından resmî yazı metnini hazırlar.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info small"><strong>Bilgi:</strong> Metin, sistemdeki icra kayıtlarından üretilir. Yeni gelen icra dosyasının önce personel kartına eklenmiş olması gerekir.</div>
            <div class="mb-3">
                <?php echo Form::FormSelect2('icraUstYaziPersonel', $icraPersonelOptions, '', 'Personel', 'user', 'key', '', 'form-select icra-ust-yazi-select2'); ?>
            </div>
            <label class="form-label fw-semibold">Yeni Gelen İcra Dosyası</label>
            <div id="icraUstYaziListe" class="border rounded-3 overflow-hidden">
                <div class="p-3 text-muted small text-center bg-light">Önce personel seçiniz.</div>
            </div>
        </div>
        <div class="modal-footer justify-content-end"><button type="button" class="btn btn-light me-1" data-bs-dismiss="modal">Vazgeç</button><button type="button" id="btnIcraUstYaziOlustur" class="btn btn-success px-4" disabled><i data-feather="file-plus" class="icon-xs me-1"></i> Metni Oluştur</button></div>
    </div></div>
</div>

<div class="modal fade" id="evrakAiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 shadow">
        <div class="modal-header"><div><h5 class="modal-title"><i data-feather="zap" class="icon-sm text-primary me-1"></i> Yapay Zekâ ile Evrak Oluştur</h5><div class="small text-muted mt-1">Gelen evrakı inceleyerek resmî cevap taslağı hazırlar.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info small"><strong>Bilgi:</strong> Oluşturulan taslak doğrudan kaydedilmez. Alanlara aktarıldıktan sonra kontrol edip düzenleyebilirsiniz.</div>
            <div class="mb-3"><label for="aiGelenEvrak" class="form-label fw-semibold">Gelen Evrak</label><input type="file" id="aiGelenEvrak" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp"><div class="form-text">PDF, JPG, PNG veya WEBP — en fazla 12 MB</div></div>
            <div><label for="aiTalimat" class="form-label fw-semibold">Ne yapmak istiyorsunuz?</label><textarea id="aiTalimat" class="form-control" rows="6" maxlength="4000" placeholder="Örnek: Talebi uygun bulduğumuzu, işlemlerin 15 gün içinde tamamlanacağını belirten resmî bir cevap hazırla."></textarea></div>
        </div>
        <div class="modal-footer justify-content-between"><button type="button" id="btnAiTemizle" class="btn btn-outline-secondary"><i class="bx bx-refresh me-1"></i> Temizle</button><div><button type="button" class="btn btn-light me-1" data-bs-dismiss="modal">Vazgeç</button><button type="button" id="btnAiTaslakOlustur" class="btn btn-primary px-4"><i data-feather="zap" class="icon-xs me-1"></i> Taslağı Oluştur</button></div></div>
    </div></div>
</div>

<div class="modal fade no-upgrade" id="gidenPdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content border-0 shadow">
        <div class="modal-header bg-dark text-white py-2.5 px-3">
            <h5 class="modal-title text-white fs-6 mb-0 d-flex align-items-center"><i class="bx bx-file-find me-2 text-warning fs-5"></i> Resmî Yazı Önizleme</h5>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button type="button" id="btnGidenPdfTamEkran" class="btn btn-sm btn-outline-light"><i class="bx bx-fullscreen me-1"></i>Tam Ekran</button>
                <a id="gidenPdfYeniSekme" href="#" target="_blank" class="btn btn-sm btn-outline-light d-none"><i class="bx bx-open-external me-1"></i>Yeni Sekmede Aç</a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
        </div>
        <div class="modal-body p-0 position-relative" style="min-height:75vh">
            <div id="gidenPdfLoader" class="position-absolute w-100 h-100 bg-white d-flex align-items-center justify-content-center" style="z-index:10"><div class="spinner-border text-primary"></div></div>
            <iframe id="gidenPdfFrame" class="w-100 border-0 giden-pdf-hidden" style="height:75vh" title="Resmi yazı PDF önizlemesi"></iframe>
        </div>
        <div class="modal-footer bg-light py-2 px-3 justify-content-between">
            <span class="small text-muted" id="gidenPdfFooterInfo">Resmî Yazı Önizleme</span>
            <button type="button" class="btn btn-secondary px-4 d-flex align-items-center" data-bs-dismiss="modal"><i class="bx bx-x me-1 fs-5"></i> Kapat</button>
        </div>
    </div></div>
</div>

<script src="<?php echo \App\Helper\Helper::assetVersion('views/evrak-takip/js/giden-evrak.js'); ?>"></script>
