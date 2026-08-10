<?php
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
        echo '<div class="p-4"><div class="bg-rose-50 text-rose-700 p-4 rounded-2xl text-sm font-bold">Giden evrak bulunamadı.</div></div>';
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
$dateValue = $record && !empty($record->tarih) ? date('Y-m-d', strtotime($record->tarih)) : date('Y-m-d');

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
$allSignersList = [];
foreach ($selectedSignerIds as $id) {
    $id = (int) $id;
    if (isset($signingUserMap[$id])) {
        $user = $signingUserMap[$id];
        $selectedSigners[] = $user->enc_id;
        $allSignersList[] = [
            'enc_id' => $user->enc_id,
            'name' => $user->adi_soyadi,
            'title' => $user->imza_unvani ?: '',
            'selected' => true,
        ];
        unset($signingUserMap[$id]);
    }
}
foreach ($signingUserMap as $user) {
    $allSignersList[] = [
        'enc_id' => $user->enc_id,
        'name' => $user->adi_soyadi,
        'title' => $user->imza_unvani ?: '',
        'selected' => false,
    ];
}

$selectedRelated = !empty($record->ilgili_evrak_id) ? Security::encrypt($record->ilgili_evrak_id) : '';
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

$savedKiminAdina = json_decode((string) ($record->imza_kimin_adina_json ?? '[]'), true) ?: [];
?>

<!-- Summernote Lite (Mobil Uyumlu Zengin Metin Editörü) -->
<link rel="stylesheet" href="../assets/libs/summernote/summernote-lite.min.css">
<script src="../assets/libs/summernote/summernote-lite.min.js"></script>
<script src="../assets/libs/summernote/lang/summernote-tr-TR.min.js"></script>

<style>
/* ===== Summernote Lite Custom Mobile Theme ===== */
.note-editor.note-frame {
    border-radius: 1.25rem !important;
    border: 1px solid #e2e8f0 !important;
    overflow: hidden !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
    background-color: #ffffff !important;
}

.dark .note-editor.note-frame {
    border-color: #334155 !important;
    background-color: #0f172a !important;
}

.note-editor.note-frame .note-toolbar {
    background-color: #f8fafc !important;
    border-bottom: 1px solid #e2e8f0 !important;
    padding: 6px 8px !important;
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 4px 6px !important;
}

.dark .note-editor.note-frame .note-toolbar {
    background-color: #1e293b !important;
    border-bottom-color: #334155 !important;
}

.note-editor.note-frame .note-btn-group {
    margin-right: 4px !important;
    margin-bottom: 4px !important;
    display: inline-flex !important;
    gap: 2px !important;
}

.note-editor.note-frame .note-btn {
    border-radius: 0.625rem !important;
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #334155 !important;
    font-size: 12px !important;
    font-weight: 700 !important;
    padding: 5px 8px !important;
    height: 33px !important;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.04) !important;
    transition: all 0.15s ease !important;
}

.dark .note-editor.note-frame .note-btn {
    background: #334155 !important;
    border-color: #475569 !important;
    color: #f8fafc !important;
}

.note-editor.note-frame .note-btn:hover {
    background: #f1f5f9 !important;
    border-color: #94a3b8 !important;
}

.note-editor.note-frame .note-btn.active {
    background: #e0f2fe !important;
    border-color: #0284c7 !important;
    color: #0284c7 !important;
}

.dark .note-editor.note-frame .note-btn.active {
    background: #0369a1 !important;
    border-color: #38bdf8 !important;
    color: #ffffff !important;
}

.note-editor.note-frame .note-statusbar {
    display: none !important;
}

.note-editor.note-frame .note-editable {
    padding: 14px !important;
    background-color: #ffffff !important;
    color: #0f172a !important;
    font-family: "Times New Roman", Times, serif !important;
    font-size: 12pt !important;
    font-weight: 400 !important;
    font-style: normal !important;
    min-height: 240px !important;
}

.dark .note-editor.note-frame .note-editable {
    background-color: #0f172a !important;
    color: #f8fafc !important;
}

.note-dropdown-menu {
    border-radius: 1rem !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1) !important;
    padding: 6px !important;
}

.dark .note-dropdown-menu {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
</style>

<!-- Talepler Tarzı Gradient Header -->
<header class="bg-gradient-to-br from-sky-600 via-sky-500 to-blue-700 text-white px-4 pt-5 pb-12 rounded-b-3xl relative overflow-hidden shadow-lg z-30">
    <!-- Dekoratif Arka Plan Çemberleri -->
    <div class="absolute inset-0 opacity-10 pointer-events-none">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <a href="?p=evrak-takip" class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white active:scale-95 transition-transform border border-white/10">
                <span class="material-symbols-outlined text-xl">arrow_back</span>
            </a>
            <div>
                <h2 class="text-xl font-extrabold leading-tight tracking-tight">
                    <?= $record ? 'Giden Evrak Düzenle' : 'Yeni Giden Evrak' ?>
                </h2>
                <p class="text-white/80 text-xs mt-0.5 font-medium"><?= date('d.m.Y') ?> – Yönetim Paneli</p>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <button type="button" onclick="openIcraUstYaziModal()" class="px-2.5 py-1.5 rounded-xl bg-emerald-500/30 hover:bg-emerald-500/40 backdrop-blur-md text-emerald-100 flex items-center gap-1 text-[11px] font-bold active:scale-95 transition-all border border-emerald-400/20">
                <span class="material-symbols-outlined text-sm">description</span> İcra
            </button>
            <button type="button" onclick="openAiTaslakModal()" class="px-2.5 py-1.5 rounded-xl bg-amber-500/30 hover:bg-amber-500/40 backdrop-blur-md text-amber-100 flex items-center gap-1 text-[11px] font-bold active:scale-95 transition-all border border-amber-400/20">
                <span class="material-symbols-outlined text-sm">auto_awesome</span> AI
            </button>
        </div>
    </div>
</header>

<!-- Floating Card Tab Navigation (Talepler Sayfası Stili) -->
<div class="px-4 mt-[-28px] relative z-40">
    <div class="flex gap-1.5 p-1.5 bg-white dark:bg-card-dark rounded-2xl shadow-lg border border-slate-100 dark:border-slate-800">
        <button type="button" onclick="switchTab('evrak-bilgileri')" id="tab-btn-evrak-bilgileri" class="flex-1 py-2 px-2 rounded-xl text-xs font-extrabold flex items-center justify-center gap-1.5 transition-all bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 shadow-sm border border-sky-100 dark:border-sky-800/50">
            <span class="material-symbols-outlined text-[18px]">article</span>
            Evrak Bilgileri
        </button>
        <button type="button" onclick="switchTab('icerik')" id="tab-btn-icerik" class="flex-1 py-2 px-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 dark:text-slate-400">
            <span class="material-symbols-outlined text-[18px]">description</span>
            İçerik
        </button>
        <button type="button" onclick="switchTab('ekler-imza')" id="tab-btn-ekler-imza" class="flex-1 py-2 px-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 dark:text-slate-400">
            <span class="material-symbols-outlined text-[18px]">draw</span>
            Ekler & İmza
        </button>
    </div>
</div>

<div class="px-4 py-4 space-y-4 pb-28">

    <?php if ($kilitli): ?>
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-3.5 text-xs space-y-1 text-amber-800 dark:text-amber-300">
            <div class="font-extrabold flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">lock</span>
                <?php if ($onayDurumu === 'onaylandi'): ?>
                    Bu evrak elektronik imza ile onaylanmıştır.
                <?php else: ?>
                    Evrak elektronik imza onayındadır (<?= $onaylananSayisi . '/' . count($onayKayitlari) ?> imza).
                <?php endif; ?>
            </div>
            <p class="text-[11px] opacity-90">
                <?php if ($siradakiImzaci): ?>
                    Sırada: <strong><?= htmlspecialchars((string) $siradakiImzaci->adi_soyadi) ?></strong> <?= $siraBende ? '— (Sıra sizde)' : '' ?>.
                <?php endif; ?>
                Süreç tamamlanana kadar evrak içeriği değiştirilemez.
            </p>
        </div>
    <?php endif; ?>

    <?php if ($iadeGerekcesi !== ''): ?>
        <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-800 rounded-2xl p-3.5 text-xs space-y-1 text-rose-800 dark:text-rose-300">
            <div class="font-extrabold flex items-center gap-1.5">
                <span class="material-symbols-outlined text-base">error</span>
                Evrak İade Edildi <?= $iadeEden !== '' ? ' — ' . htmlspecialchars($iadeEden) : '' ?>
            </div>
            <p class="text-[11px]"><strong>Gerekçe:</strong> <?= nl2br(htmlspecialchars($iadeGerekcesi)) ?></p>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form id="gidenEvrakForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="evrak-kaydet">
        <input type="hidden" name="id" value="<?= htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="evrak_tipi" value="giden">
        <input type="hidden" name="yazi_tipi" value="times_new_roman">
        <input type="hidden" name="ek_duzen_json" id="ek_duzen_json" value="[]">
        <input type="hidden" name="silinen_ek_ids_json" id="silinen_ek_ids_json" value="[]">

        <!-- TAB 1: EVRAK BİLGİLERİ -->
        <div id="tab-content-evrak-bilgileri" class="tab-pane space-y-4">
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-sky-500">article</span>
                    Evrak ve Muhatap Bilgileri
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Tarih *</label>
                        <input type="date" name="tarih" id="tarih" value="<?= $dateValue ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" <?= $kilitli ? 'disabled' : 'required' ?>>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Evrak No / Sayı *</label>
                        <input type="text" name="evrak_no" id="evrak_no" value="<?= htmlspecialchars($defaultEvrakNo) ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Sayı" <?= $kilitli ? 'disabled' : 'required' ?>>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Konu *</label>
                    <input type="text" name="konu" id="konu" value="<?= htmlspecialchars((string) $value('konu')) ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Resmî Yazı Konusu" <?= $kilitli ? 'disabled' : 'required' ?>>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Muhatap Kurum / Kişi *</label>
                    <input type="text" name="kurum_adi" id="kurum_adi" value="<?= htmlspecialchars((string) $value('kurum_adi')) ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-bold focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Muhatap Kurum" <?= $kilitli ? 'disabled' : 'required' ?>>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Alt Birim / Bölüm</label>
                    <input type="text" name="muhatap_alt_birim" value="<?= htmlspecialchars((string) $value('muhatap_alt_birim')) ?>" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-medium focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Örn: İnsan Kaynakları Müd." <?= $kilitli ? 'disabled' : '' ?>>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Muhatap Adresi</label>
                    <textarea name="muhatap_adres" rows="2" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-medium focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Muhatap Adresi" <?= $kilitli ? 'disabled' : '' ?>><?= htmlspecialchars((string) $value('muhatap_adres')) ?></textarea>
                </div>

                <div class="pt-2 border-t border-slate-100 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Zimmetlenen Personel</label>
                        <select name="personel_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-medium outline-none dark:text-white" <?= $kilitli ? 'disabled' : '' ?>>
                            <?php foreach ($personelOptions as $pId => $pName): ?>
                                <option value="<?= $pId ?>" <?= $pId == $value('personel_id') ? 'selected' : '' ?>><?= htmlspecialchars($pName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İlgili Personel</label>
                        <select name="ilgili_personel_id" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-medium outline-none dark:text-white" <?= $kilitli ? 'disabled' : '' ?>>
                            <?php foreach ($personelOptions as $pId => $pName): ?>
                                <option value="<?= $pId ?>" <?= $pId == $value('ilgili_personel_id') ? 'selected' : '' ?>><?= htmlspecialchars($pName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: İÇERİK -->
        <div id="tab-content-icerik" class="tab-pane hidden space-y-4">
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-sky-500">description</span>
                        Resmî Yazı Metni
                    </div>
                    <button type="button" onclick="openAiSecimModal()" class="text-[11px] font-bold text-sky-600 dark:text-sky-400 flex items-center gap-1 bg-sky-50 dark:bg-sky-900/30 px-2.5 py-1 rounded-lg active:scale-95 transition-all">
                        <span class="material-symbols-outlined text-sm">auto_awesome</span> AI Düzenle
                    </button>
                </div>

                <div>
                    <textarea id="giden_evrak_icerik" name="aciklama" class="w-full min-h-[260px] bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-xs font-serif leading-relaxed focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Resmî yazı metnini giriniz..." <?= $kilitli ? 'disabled' : '' ?>><?= htmlspecialchars((string) $value('aciklama')) ?></textarea>
                    <div class="text-[10px] text-slate-400 mt-1">Varsayılan Times New Roman 12 puntodur. HTML biçimlendirmeleri PDF'e aktarılır.</div>
                </div>
            </div>
        </div>

        <!-- TAB 3: EKLER VE İMZA -->
        <div id="tab-content-ekler-imza" class="tab-pane hidden space-y-4">
            
            <!-- Düzenli Mobil İmza Kullanıcıları Seçim Kartı -->
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 space-y-4">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-base text-sky-500">draw</span>
                        İmza Sahibi Seçimi (En Fazla 3) *
                    </div>
                    <button type="button" onclick="openSignerSelectModal()" class="text-[11px] font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-900/30 px-2.5 py-1 rounded-lg active:scale-95 transition-all flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">person_add</span> Kişi Seç
                    </button>
                </div>

                <!-- Seçilen İmzacıların Sıralama Listesi -->
                <div id="imzaSiraContainer" class="space-y-2">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">İmza Sıralaması ve Unvanlar (Soldan Sağa)</div>
                    <div id="imzaSiraListesi" class="bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                        <div class="p-3 text-slate-400 text-xs text-center">Henüz imzacılar seçilmedi.</div>
                    </div>
                </div>
                <div id="hiddenSignersInputs"></div>
            </div>

            <!-- İlişkili Evrak & İlgi & Ekler Metni -->
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
                <div class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-sky-500">link</span>
                    İlişkili Evrak ve İlgi
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İlişkili Gelen Evrak</label>
                    <select name="ilgili_evrak_id" id="ilgili_evrak_id" onchange="onIlgiliEvrakChange(this.value)" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2.5 text-xs font-medium focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" <?= $kilitli ? 'disabled' : '' ?>>
                        <?php foreach ($gelenOptions as $gelenEncId => $gelenLabel): ?>
                            <option value="<?= htmlspecialchars($gelenEncId) ?>" <?= $gelenEncId === $selectedRelated ? 'selected' : '' ?>>
                                <?= htmlspecialchars($gelenLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İlgi (Her Satıra Bir Kayıt)</label>
                    <textarea name="ilgiler" id="ilgiler" rows="3" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-xs font-medium focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Örn: 09.08.2026 tarihli ve 1234 sayılı yazınız." <?= $kilitli ? 'disabled' : '' ?>><?= htmlspecialchars((string) $value('ilgiler')) ?></textarea>
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Ek Metni (PDF'te Görünecek)</label>
                    <textarea name="ekler" id="ekler" rows="2" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-xs font-medium focus:ring-2 focus:ring-sky-500 outline-none dark:text-white" placeholder="Örn: 1 Adet Sözleşme Örneği" <?= $kilitli ? 'disabled' : '' ?>><?= htmlspecialchars((string) $value('ekler')) ?></textarea>
                </div>
            </div>

            <!-- Ek Belgeler (Dosyalar) -->
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 space-y-3">
                <div class="text-xs font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-sky-500">attach_file</span>
                    Ek Belgeler (Fiziksel Dosyalar)
                </div>

                <?php if (!$kilitli): ?>
                    <div>
                        <input type="file" id="ek_dosyalari" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[11px] file:font-black file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-900/30 dark:file:text-sky-400" onchange="handleNewAttachmentFiles(this.files)">
                    </div>
                <?php endif; ?>

                <div id="ekDosyaListesi" class="bg-slate-50 dark:bg-slate-900/50 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden divide-y divide-slate-100 dark:divide-slate-800">
                    <!-- Javascript ile doldurulacak -->
                </div>
            </div>

        </div>

    </form>
</div>

<!-- Mobile Sub-page Sticky Action Bar (Global Nav Bar Üstünde) -->
<div class="fixed bottom-[4.5rem] left-0 right-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-b border-slate-200 dark:border-slate-800 px-3 py-2 z-40 flex items-center justify-between gap-1.5 shadow-xl">
    <a href="?p=evrak-takip" class="px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs flex items-center gap-1 active:scale-95 transition-all">
        <span class="material-symbols-outlined text-base">close</span>
        Vazgeç
    </a>

    <div class="flex items-center gap-1.5">
        <button type="button" onclick="previewGidenPdf()" class="px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center gap-1 active:scale-95 transition-all" title="Evrak Önizle">
            <span class="material-symbols-outlined text-base text-sky-500">visibility</span>
            Önizle
        </button>

        <?php if (!$kilitli): ?>
            <button type="button" onclick="eImzaOnayaSun('<?= htmlspecialchars($encryptedId ?? '', ENT_QUOTES, 'UTF-8') ?>')" class="px-2.5 py-2 rounded-xl bg-sky-50 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 font-bold text-xs flex items-center gap-1 active:scale-95 transition-all" title="E-İmza ile Onaya Sun">
                <span class="material-symbols-outlined text-base">send</span>
                Onaya Sun
            </button>
        <?php endif; ?>

        <?php if ($record && ($kilitli || $bekleyenImzam || $siraBende || $geriAlinabilir)): ?>
            <button type="button" onclick="openIslemlerSheet()" class="px-2.5 py-2 rounded-xl bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 font-bold text-xs flex items-center gap-1 active:scale-95 transition-all">
                <span class="material-symbols-outlined text-base">settings</span>
                İşlemler
            </button>
        <?php endif; ?>

        <?php if (!$kilitli): ?>
            <button type="button" onclick="submitGidenForm()" class="px-3.5 py-2 rounded-xl bg-sky-500 hover:bg-sky-600 text-white font-extrabold text-xs shadow-md shadow-sky-500/20 active:scale-95 transition-all flex items-center gap-1">
                <span class="material-symbols-outlined text-base">save</span>
                Kaydet
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL: Düzenli Mobil İmza Sahibi Seçimi -->
<div id="modal-signer-picker" class="fixed inset-0 bg-slate-900/60 z-[100] hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-md max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
            <h3 class="font-extrabold text-sm text-slate-800 dark:text-white flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sky-500 text-lg">group</span>
                İmza Sahibi Kullanıcılar
            </h3>
            <button onclick="closeModal('modal-signer-picker')" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-100">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
        <div class="p-4 space-y-2 overflow-y-auto max-h-[60vh]">
            <p class="text-[11px] text-slate-400 font-medium mb-2">Resmî yazıyı imzalayacak kişileri seçiniz (En fazla 3 kişi):</p>
            <div id="signerPickerList" class="space-y-2">
                <?php foreach ($allSignersList as $sItem): ?>
                    <label class="flex items-center justify-between p-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 cursor-pointer active:scale-98 transition-all">
                        <div class="min-w-0 flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-600 dark:text-sky-300 font-black text-xs flex items-center justify-center">
                                <?= mb_substr($sItem['name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <div class="min-w-0">
                                <div class="font-bold text-xs text-slate-800 dark:text-white truncate"><?= htmlspecialchars($sItem['name']) ?></div>
                                <?php if ($sItem['title']): ?>
                                    <div class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($sItem['title']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <input type="checkbox" value="<?= htmlspecialchars($sItem['enc_id']) ?>" <?= $sItem['selected'] ? 'checked' : '' ?> onchange="toggleSignerSelection(this)" class="w-5 h-5 rounded-md text-sky-500 border-slate-300 focus:ring-sky-500">
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
            <button onclick="closeModal('modal-signer-picker')" class="w-full py-3 rounded-xl bg-sky-500 text-white font-extrabold text-xs shadow-md">
                Tamam
            </button>
        </div>
    </div>
</div>

<!-- MODAL: İcra Üst Yazısı Modal -->
<div id="modal-icra" class="fixed inset-0 bg-slate-900/60 z-[100] hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-md max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
            <h3 class="font-extrabold text-sm text-slate-800 dark:text-white flex items-center gap-1.5">
                <span class="material-symbols-outlined text-emerald-500 text-lg">description</span>
                İcra Üst Yazısı Oluştur
            </h3>
            <button onclick="closeModal('modal-icra')" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-100">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
        <div class="p-4 space-y-4 overflow-y-auto">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Personel Seçin</label>
                <select id="icra_personel_select" onchange="onIcraPersonelChange(this.value)" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 dark:text-white">
                    <option value="">Personel Seçiniz...</option>
                    <?php foreach ($icraPersonelOptions as $pId => $pName): ?>
                        <option value="<?= $pId ?>"><?= htmlspecialchars($pName) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">İcra Dosyası Seçin</label>
                <div id="icraDosyaListesi" class="bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-xs text-slate-400 text-center">
                    Önce personel seçiniz.
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex gap-2">
            <button onclick="closeModal('modal-icra')" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs">Vazgeç</button>
            <button id="btnIcraUstYaziOlustur" onclick="createIcraUstYazi()" disabled class="flex-1 py-2.5 rounded-xl bg-emerald-500 text-white font-extrabold text-xs disabled:opacity-50">Metni Oluştur</button>
        </div>
    </div>
</div>

<!-- MODAL: AI Taslak Oluştur Modal -->
<div id="modal-ai-taslak" class="fixed inset-0 bg-slate-900/60 z-[100] hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-md max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
            <h3 class="font-extrabold text-sm text-slate-800 dark:text-white flex items-center gap-1.5">
                <span class="material-symbols-outlined text-amber-500 text-lg">auto_awesome</span>
                Yapay Zekâ ile Evrak Oluştur
            </h3>
            <button onclick="closeModal('modal-ai-taslak')" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
        <div class="p-4 space-y-4 overflow-y-auto">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Gelen Evrak Görseli/PDF (Opsiyonel)</label>
                <input type="file" id="aiGelenEvrakFile" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-amber-50 file:text-amber-700">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Talimat veya İstek</label>
                <textarea id="aiTalimatText" rows="4" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-xs font-medium dark:text-white" placeholder="Örn: Talebi uygun bulduğumuzu, işlemlerin 15 gün içinde tamamlanacağını bildiren cevap hazırla."></textarea>
            </div>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex gap-2">
            <button onclick="closeModal('modal-ai-taslak')" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs">Vazgeç</button>
            <button onclick="generateAiTaslak()" class="flex-1 py-2.5 rounded-xl bg-amber-500 text-white font-extrabold text-xs">Taslak Oluştur</button>
        </div>
    </div>
</div>

<!-- MODAL: AI Seçili Metni Düzenle Modal -->
<div id="modal-ai-secim" class="fixed inset-0 bg-slate-900/60 z-[100] hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-md max-h-[85vh] flex flex-col shadow-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-800/50">
            <h3 class="font-extrabold text-sm text-slate-800 dark:text-white flex items-center gap-1.5">
                <span class="material-symbols-outlined text-sky-500 text-lg">auto_awesome</span>
                Seçili Metni AI ile Düzenle
            </h3>
            <button onclick="closeModal('modal-ai-secim')" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400">
                <span class="material-symbols-outlined text-sm">close</span>
            </button>
        </div>
        <div class="p-4 space-y-4 overflow-y-auto">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Talimat</label>
                <textarea id="aiSecimTalimatText" rows="3" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 text-xs font-medium dark:text-white" placeholder="Nasıl düzenlemek istersiniz?"></textarea>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button type="button" onclick="setAiSecimPrompt('Resmîleştir')" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold rounded-lg">Resmîleştir</button>
                <button type="button" onclick="setAiSecimPrompt('Kısalt')" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold rounded-lg">Kısalt</button>
                <button type="button" onclick="setAiSecimPrompt('Dilbilgisini Düzelt')" class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-bold rounded-lg">Dilbilgisini Düzelt</button>
            </div>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-800 flex gap-2">
            <button onclick="closeModal('modal-ai-secim')" class="flex-1 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs">Vazgeç</button>
            <button onclick="applyAiSecimTransform()" class="flex-1 py-2.5 rounded-xl bg-sky-500 text-white font-extrabold text-xs">Uygula</button>
        </div>
    </div>
</div>

<!-- MODAL: PDF Önizleme Modal -->
<div id="modal-pdf-preview" class="fixed inset-0 bg-slate-900/80 z-[100] hidden items-center justify-center p-2 sm:p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl w-full max-w-2xl h-[85vh] flex flex-col shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-800">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-slate-900 text-white shrink-0">
            <h3 class="font-extrabold text-xs flex items-center gap-1.5">
                <span class="material-symbols-outlined text-amber-400 text-base">picture_as_pdf</span>
                Resmî Yazı Önizleme
            </h3>
            <div class="flex items-center gap-2">
                <a id="btnPdfOpenNewTab" href="#" target="_blank" class="px-2.5 py-1.5 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-[11px] font-bold flex items-center gap-1 active:scale-95 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-sm">open_in_new</span> Yeni Sekmede Aç
                </a>
                <button onclick="closeModal('modal-pdf-preview')" class="w-8 h-8 flex items-center justify-center rounded-full text-slate-400 hover:bg-slate-800 active:scale-95">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>
        </div>
        <div id="pdfCanvasContainer" class="flex-1 relative bg-slate-100 dark:bg-slate-950 p-3 overflow-y-auto">
            <iframe id="pdfPreviewFrame" class="w-full h-full border-0"></iframe>
        </div>
    </div>
</div>

<!-- BOTTOM SHEET: E-İmza İşlemleri -->
<div id="sheet-islemler" class="fixed inset-0 bg-slate-900/60 z-[100] hidden flex-col justify-end">
    <div class="absolute inset-0" onclick="closeSheetIslemler()"></div>
    <div class="relative w-full bg-white dark:bg-slate-900 rounded-t-3xl p-4 space-y-3 shadow-2xl border-t border-slate-200 dark:border-slate-800">
        <div class="w-10 h-1 bg-slate-300 dark:bg-slate-700 rounded-full mx-auto mb-2"></div>
        <h4 class="font-extrabold text-sm text-slate-800 dark:text-white">Evrak İşlemleri</h4>
        <div class="space-y-2">
            <?php if (!$kilitli): ?>
                <button onclick="eImzaOnayaSun('<?= htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8') ?>')" class="w-full p-3 rounded-2xl bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-300 font-bold text-xs flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl">send</span>
                    E-İmza ile Onaya Sun
                </button>
            <?php endif; ?>

            <?php if ($siraBende): ?>
                <button onclick="eImzaOnayla('<?= htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8') ?>')" class="w-full p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300 font-bold text-xs flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl">verified</span>
                    E-İmza ile Onayla
                </button>
                <button onclick="eImzaIade('<?= htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8') ?>')" class="w-full p-3 rounded-2xl bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-300 font-bold text-xs flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl">undo</span>
                    Düzeltilmek Üzere İade Et
                </button>
            <?php endif; ?>

            <?php if ($kilitli && $geriAlinabilir): ?>
                <button onclick="eImzaGeriAl('<?= htmlspecialchars($encryptedId, ENT_QUOTES, 'UTF-8') ?>')" class="w-full p-3 rounded-2xl bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 font-bold text-xs flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl">restart_alt</span>
                    Evrakı Üzerime Geri Al
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const MobileSwal = Swal.mixin({
    customClass: {
        container: 'z-[9999]',
        popup: 'rounded-[2rem] shadow-2xl bg-white dark:bg-slate-800 dark:text-white border border-slate-100 dark:border-slate-700',
        title: 'text-lg font-extrabold text-slate-800 dark:text-white tracking-tight',
        htmlContainer: 'text-sm text-slate-500 dark:text-slate-400',
        actions: 'flex gap-3 w-full px-6 mb-4',
        confirmButton: 'flex-1 py-3 bg-sky-500 hover:bg-sky-600 active:scale-95 text-white font-bold rounded-xl transition-all shadow-md focus:outline-none',
        cancelButton: 'flex-1 py-3 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 font-bold rounded-xl transition-all focus:outline-none'
    },
    buttonsStyling: false
});

window.gidenGelenEvraklarMap = <?= json_encode($gelenEvrakMap, JSON_UNESCAPED_UNICODE) ?>;
window.gidenExistingAttachments = <?= json_encode($existingAttachments, JSON_UNESCAPED_UNICODE) ?>;
window.gidenKilitli = <?= $kilitli ? 'true' : 'false' ?>;

let allSignersData = <?= json_encode($allSignersList, JSON_UNESCAPED_UNICODE) ?>;
let selectedSignersList = <?= json_encode($selectedSigners, JSON_UNESCAPED_UNICODE) ?>;
let savedKiminAdinaArr = <?= json_encode($savedKiminAdina, JSON_UNESCAPED_UNICODE) ?>;

let attachmentsList = window.gidenExistingAttachments.map(item => ({ ...item, type: 'existing' }));
let removedAttachmentIds = [];
let newFileKeyCounter = 1;

document.addEventListener('DOMContentLoaded', function() {
    renderSignerOrderList();
    renderAttachmentList();
    initRichTextEditor();
});

function initRichTextEditor() {
    if (!window.gidenKilitli && typeof $.fn.summernote !== 'undefined') {
        $('#giden_evrak_icerik').summernote({
            placeholder: 'Resmî yazı metnini yazınız...',
            tabsize: 2,
            height: 280,
            lang: 'tr-TR',
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['view', ['undo', 'redo']]
            ],
            callbacks: {
                onInit: function() {
                    $('.note-editable').css({ fontFamily: '"Times New Roman", Times, serif', fontSize: '12pt', fontWeight: '400', fontStyle: 'normal' });
                    $('.note-btn-bold').removeClass('active');
                }
            }
        });
    }
}

// --- Sekme Değiştirme (Switch Tab) ---
function switchTab(tabId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.add('hidden'));
    document.getElementById('tab-content-' + tabId).classList.remove('hidden');

    const tabs = ['evrak-bilgileri', 'icerik', 'ekler-imza'];
    tabs.forEach(t => {
        const btn = document.getElementById('tab-btn-' + t);
        if (!btn) return;
        if (t === tabId) {
            btn.className = 'flex-1 py-2 px-2 rounded-xl text-xs font-extrabold flex items-center justify-center gap-1.5 transition-all bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300 shadow-sm border border-sky-100 dark:border-sky-800/50';
        } else {
            btn.className = 'flex-1 py-2 px-2 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 transition-all text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-800 dark:text-slate-400';
        }
    });
}

// --- Düzenli İmza Kullanıcısı Seçimi ---
function openSignerSelectModal() {
    openModal('modal-signer-picker');
}

function toggleSignerSelection(checkbox) {
    const encId = checkbox.value;
    if (checkbox.checked) {
        if (selectedSignersList.length >= 3) {
            MobileSwal.fire('Uyarı', 'En fazla 3 imza sahibi seçebilirsiniz.', 'warning');
            checkbox.checked = false;
            return;
        }
        if (!selectedSignersList.includes(encId)) {
            selectedSignersList.push(encId);
        }
    } else {
        selectedSignersList = selectedSignersList.filter(id => id !== encId);
    }
    renderSignerOrderList();
}

function renderSignerOrderList() {
    const container = document.getElementById('imzaSiraListesi');
    const hiddenContainer = document.getElementById('hiddenSignersInputs');
    if (!container) return;

    container.innerHTML = '';
    if (hiddenContainer) hiddenContainer.innerHTML = '';

    if (selectedSignersList.length === 0) {
        container.innerHTML = '<div class="p-3 text-slate-400 text-xs text-center">Henüz imzacılar seçilmedi.</div>';
        return;
    }

    selectedSignersList.forEach((encId, index) => {
        const sInfo = allSignersData.find(item => item.enc_id === encId) || { name: 'Kullanıcı', title: '' };

        if (hiddenContainer) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'imza_kullanici_ids[]';
            input.value = encId;
            hiddenContainer.appendChild(input);
        }

        const kiminAdinaVal = savedKiminAdinaArr[index] || '';

        const div = document.createElement('div');
        div.className = 'p-3 bg-white dark:bg-slate-800 text-xs space-y-2';
        div.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="px-2 py-0.5 rounded-full bg-sky-100 dark:bg-sky-900/40 text-sky-700 dark:text-sky-300 font-extrabold text-[10px] shrink-0">
                        ${index + 1}. İmza
                    </span>
                    <div class="min-w-0">
                        <div class="font-bold text-slate-800 dark:text-white truncate">${escapeHtml(sInfo.name)}</div>
                        ${sInfo.title ? `<div class="text-[10px] text-slate-400 truncate">${escapeHtml(sInfo.title)}</div>` : ''}
                    </div>
                </div>
                ${!window.gidenKilitli ? `
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" onclick="moveSigner(${index}, -1)" ${index === 0 ? 'disabled class="opacity-30 cursor-not-allowed"' : ''} class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center active:scale-95">
                            <span class="material-symbols-outlined text-sm">arrow_upward</span>
                        </button>
                        <button type="button" onclick="moveSigner(${index}, 1)" ${index === selectedSignersList.length - 1 ? 'disabled class="opacity-30 cursor-not-allowed"' : ''} class="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center active:scale-95">
                            <span class="material-symbols-outlined text-sm">arrow_downward</span>
                        </button>
                        <button type="button" onclick="removeSigner(${index})" class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center active:scale-95">
                            <span class="material-symbols-outlined text-sm">close</span>
                        </button>
                    </div>
                ` : ''}
            </div>
            <div>
                <input type="text" name="kimin_adina_${index + 1}" value="${escapeHtml(kiminAdinaVal)}" onchange="savedKiminAdinaArr[${index}] = this.value" class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2.5 py-1.5 text-[11px] dark:text-white" placeholder="${index + 1}. İmza Kimin Adına (Örn: Firma Yetkilisi a.)" ${window.gidenKilitli ? 'disabled' : ''}>
            </div>
        `;
        container.appendChild(div);
    });
}

function moveSigner(index, dir) {
    const target = index + dir;
    if (target < 0 || target >= selectedSignersList.length) return;
    const temp = selectedSignersList[index];
    selectedSignersList[index] = selectedSignersList[target];
    selectedSignersList[target] = temp;

    const tempKa = savedKiminAdinaArr[index];
    savedKiminAdinaArr[index] = savedKiminAdinaArr[target];
    savedKiminAdinaArr[target] = tempKa;

    renderSignerOrderList();
}

function removeSigner(index) {
    const encId = selectedSignersList[index];
    selectedSignersList.splice(index, 1);
    savedKiminAdinaArr.splice(index, 1);

    const checkbox = document.querySelector(`#signerPickerList input[value="${encId}"]`);
    if (checkbox) checkbox.checked = false;

    renderSignerOrderList();
}

// --- İlişkili Gelen Evrak Otomatik İlgi Ekleme ---
function onIlgiliEvrakChange(val) {
    if (!val || !window.gidenGelenEvraklarMap || !window.gidenGelenEvraklarMap[val]) return;
    const item = window.gidenGelenEvraklarMap[val];
    const dateStr = (item.tarih || '').trim();
    const noStr = (item.evrak_no || '').trim();

    let text = '';
    if (dateStr && noStr) text = dateStr + ' tarih ve ' + noStr + ' sayılı yazınız.';
    else if (dateStr) text = dateStr + ' tarihli yazınız.';
    else if (noStr) text = noStr + ' sayılı yazınız.';

    if (!text) return;
    const ilgilerEl = document.getElementById('ilgiler');
    const current = ilgilerEl.value.trim();
    if (!current) {
        ilgilerEl.value = text;
    } else {
        const lines = current.split('\n').map(l => l.trim()).filter(l => l !== '');
        if (!lines.includes(text)) {
            lines.push(text);
            ilgilerEl.value = lines.join('\n');
        }
    }
}

// --- Ek Belgeler Yönetimi ---
function handleNewAttachmentFiles(files) {
    if (!files || files.length === 0) return;
    Array.from(files).forEach(file => {
        const fileKey = 'new_' + newFileKeyCounter++;
        attachmentsList.push({
            type: 'new',
            key: fileKey,
            name: file.name,
            size: file.size,
            fileObj: file
        });
    });
    renderAttachmentList();
}

function renderAttachmentList() {
    const container = document.getElementById('ekDosyaListesi');
    if (!container) return;
    container.innerHTML = '';

    if (attachmentsList.length === 0) {
        container.innerHTML = '<div class="p-3 text-slate-400 text-xs text-center">Henüz eklenmiş belge bulunmuyor.</div>';
        return;
    }

    attachmentsList.forEach((att, index) => {
        const div = document.createElement('div');
        div.className = 'flex items-center justify-between p-2.5 bg-white dark:bg-slate-800 text-xs';
        div.innerHTML = `
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-6 h-6 rounded bg-slate-100 dark:bg-slate-700 text-[10px] font-bold flex items-center justify-center text-slate-500">${index + 1}</span>
                <span class="font-semibold text-slate-700 dark:text-slate-200 truncate">${escapeHtml(att.name)}</span>
            </div>
            ${!window.gidenKilitli ? `
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" onclick="moveAttachment(${index}, -1)" ${index === 0 ? 'disabled class="opacity-30 cursor-not-allowed"' : ''} class="w-6 h-6 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">arrow_upward</span>
                    </button>
                    <button type="button" onclick="moveAttachment(${index}, 1)" ${index === attachmentsList.length - 1 ? 'disabled class="opacity-30 cursor-not-allowed"' : ''} class="w-6 h-6 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">arrow_downward</span>
                    </button>
                    <button type="button" onclick="removeAttachment(${index})" class="w-6 h-6 rounded bg-rose-50 text-rose-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">delete</span>
                    </button>
                </div>
            ` : ''}
        `;
        container.appendChild(div);
    });

    document.getElementById('ek_duzen_json').value = JSON.stringify(attachmentsList.map(a => a.type === 'existing' ? { type: 'existing', id: a.id } : { type: 'new', key: a.key }));
    document.getElementById('silinen_ek_ids_json').value = JSON.stringify(removedAttachmentIds);
}

function moveAttachment(index, dir) {
    const target = index + dir;
    if (target < 0 || target >= attachmentsList.length) return;
    const temp = attachmentsList[index];
    attachmentsList[index] = attachmentsList[target];
    attachmentsList[target] = temp;
    renderAttachmentList();
}

function removeAttachment(index) {
    const item = attachmentsList[index];
    if (item.type === 'existing') {
        removedAttachmentIds.push(item.id);
    }
    attachmentsList.splice(index, 1);
    renderAttachmentList();
}

// --- Form Zorunlu Alan Doğrulama ---
function validateGidenForm() {
    if (typeof $.fn.summernote !== 'undefined' && $('#giden_evrak_icerik').data('summernote')) {
        $('#giden_evrak_icerik').val($('#giden_evrak_icerik').summernote('code'));
    }

    const form = document.getElementById('gidenEvrakForm');
    const konu = form.konu ? form.konu.value.trim() : '';
    const kurumAdi = form.kurum_adi ? form.kurum_adi.value.trim() : '';
    const icerikRaw = form.aciklama ? form.aciklama.value.trim() : '';
    const icerikText = icerikRaw.replace(/<[^>]*>/g, '').trim();

    if (!konu) {
        switchTab('evrak-bilgileri');
        MobileSwal.fire('Eksik Bilgi', 'Lütfen Evrak Konusu alanını doldurunuz.', 'warning');
        if (form.konu) form.konu.focus();
        return false;
    }

    if (!kurumAdi) {
        switchTab('evrak-bilgileri');
        MobileSwal.fire('Eksik Bilgi', 'Lütfen Muhatap Kurum / Kişi alanını doldurunuz.', 'warning');
        if (form.kurum_adi) form.kurum_adi.focus();
        return false;
    }

    if (!icerikText) {
        switchTab('icerik');
        MobileSwal.fire('Eksik Bilgi', 'Lütfen Resmî Yazı Metni (İçerik) alanını doldurunuz.', 'warning');
        return false;
    }

    if (selectedSignersList.length === 0) {
        switchTab('ekler-imza');
        MobileSwal.fire('Eksik Bilgi', 'Lütfen Ekler & İmza sekmesinden en az 1 imza sahibi seçiniz.', 'warning');
        return false;
    }

    return true;
}

// --- Form Gönderimi (Kaydet) ---
function submitGidenForm() {
    if (!validateGidenForm()) return;

    const formData = new FormData(form);

    const newAttachments = attachmentsList.filter(a => a.type === 'new');
    newAttachments.forEach((att) => {
        if (att.fileObj) {
            formData.append('ek_dosyalari[]', att.fileObj);
        }
    });

    MobileSwal.fire({
        title: 'Kaydediliyor...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('../views/evrak-takip/api.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            MobileSwal.fire({ icon: 'success', title: 'Başarılı', text: res.message, timer: 1500 })
            .then(() => window.location.href = '?p=evrak-takip');
        } else {
            MobileSwal.fire('Hata', res.message || 'Kaydedilemedi.', 'error');
        }
    })
    .catch(() => MobileSwal.fire('Hata', 'Sunucu hatası.', 'error'));
}

// --- Modallar ve Önizleme ---
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
    }
}

function openIcraUstYaziModal() { openModal('modal-icra'); }
function openAiTaslakModal() { openModal('modal-ai-taslak'); }
function openAiSecimModal() { openModal('modal-ai-secim'); }
function openIslemlerSheet() {
    const el = document.getElementById('sheet-islemler');
    if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
}
function closeSheetIslemler() {
    const el = document.getElementById('sheet-islemler');
    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
}

// Universal PDF Canvas Renderer (Mobile Phone & Tablet Compatible)
function renderPdfOnCanvasContainer(containerId, pdfUrl) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs flex flex-col items-center justify-center gap-2">
            <span class="material-symbols-outlined animate-spin text-3xl text-sky-500">sync</span>
            <span class="font-bold">PDF Sayfaları Yükleniyor...</span>
        </div>
    `;

    if (typeof pdfjsLib === 'undefined') {
        container.innerHTML = `
            <div class="w-full h-full flex flex-col items-center justify-center p-4">
                <iframe src="${pdfUrl}" class="w-full h-[65vh] rounded-xl shadow-sm border-0 mb-3"></iframe>
                <a href="${pdfUrl}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md">
                    <span class="material-symbols-outlined text-base">open_in_new</span> Yeni Sekmede Aç / İndir
                </a>
            </div>
        `;
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const loadingTask = pdfjsLib.getDocument(pdfUrl);
    loadingTask.promise.then(function(pdf) {
        container.innerHTML = '';
        const numPages = pdf.numPages;

        for (let pageNum = 1; pageNum <= numPages; pageNum++) {
            pdf.getPage(pageNum).then(function(page) {
                const screenWidth = container.clientWidth || window.innerWidth || 360;
                const unscaledViewport = page.getViewport({ scale: 1.0 });
                const desiredScale = (screenWidth - 32) / unscaledViewport.width;
                const viewport = page.getViewport({ scale: Math.max(desiredScale, 0.85) });

                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.className = 'max-w-full h-auto mx-auto shadow-md rounded-xl mb-4 bg-white border border-slate-200 dark:border-slate-800';

                container.appendChild(canvas);

                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                page.render(renderContext);
            });
        }
    }).catch(function(err) {
        console.warn('PDF.js render fallback:', err);
        container.innerHTML = `
            <div class="p-6 text-center space-y-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 my-auto">
                <span class="material-symbols-outlined text-4xl text-sky-500 mb-1">picture_as_pdf</span>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-200">PDF Belgesi Hazır</p>
                <a href="${pdfUrl}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-sky-500 hover:bg-sky-600 text-white rounded-xl text-xs font-bold shadow-md active:scale-95 transition-all">
                    <span class="material-symbols-outlined text-base">open_in_new</span> Belgeyi Yeni Sekmede Aç / İndir
                </a>
            </div>
        `;
    });
}

// PDF Önizleme
function previewGidenPdf() {
    if (typeof $.fn.summernote !== 'undefined' && $('#giden_evrak_icerik').data('summernote')) {
        $('#giden_evrak_icerik').val($('#giden_evrak_icerik').summernote('code'));
    }
    const form = document.getElementById('gidenEvrakForm');
    const formData = new FormData(form);

    if (!formData.get('evrak_no')) formData.set('evrak_no', 'TASLAK');
    if (!formData.get('konu')) formData.set('konu', 'Resmî Yazı Taslağı');
    if (!formData.get('kurum_adi')) formData.set('kurum_adi', 'İlgili Makama');

    MobileSwal.fire({ title: 'PDF Önizleme Hazırlanıyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('../views/evrak-takip/pdf.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.blob())
    .then(blob => {
        MobileSwal.close();
        const url = URL.createObjectURL(blob);
        document.getElementById('btnPdfOpenNewTab').href = url;
        openModal('modal-pdf-preview');
        renderPdfOnCanvasContainer('pdfCanvasContainer', url);
    })
    .catch(() => {
        MobileSwal.close();
        MobileSwal.fire('Hata', 'PDF önizleme oluşturulamadı.', 'error');
    });
}

// İcra Üst Yazısı Metni Oluştur
function onIcraPersonelChange(pId) {
    const btn = document.getElementById('btnIcraUstYaziOlustur');
    const container = document.getElementById('icraDosyaListesi');
    if (!pId) {
        container.innerHTML = 'Önce personel seçiniz.';
        btn.disabled = true;
        return;
    }
    container.innerHTML = '<span class="text-slate-400">Yükleniyor...</span>';
    $.post('../views/evrak-takip/api.php', { action: 'icra-ust-yazi-listesi', personel_id: pId }, function(res) {
        const data = (typeof res === 'object') ? res : JSON.parse(res);
        if (data.status === 'success' && data.data && data.data.length > 0) {
            let html = '<div class="space-y-1.5 text-left">';
            data.data.forEach(item => {
                html += `
                    <label class="flex items-center gap-2 p-2 bg-white dark:bg-slate-900 rounded-lg border border-slate-100 cursor-pointer">
                        <input type="radio" name="icra_seçilen_id" value="${item.id}" checked>
                        <div>
                            <div class="font-bold text-xs">${escapeHtml(item.icra_dairesi || '')}</div>
                            <div class="text-[10px] text-slate-500">Esas No: ${escapeHtml(item.dosya_no || '')}</div>
                        </div>
                    </label>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
            btn.disabled = false;
        } else {
            container.innerHTML = '<span class="text-amber-600">Bu personelin icra dosyası bulunamadı.</span>';
            btn.disabled = true;
        }
    });
}

function createIcraUstYazi() {
    const pId = document.getElementById('icra_personel_select').value;
    const selectedRadio = document.querySelector('input[name="icra_seçilen_id"]:checked');
    if (!pId || !selectedRadio) return;

    closeModal('modal-icra');
    MobileSwal.fire({ title: 'Metin Hazırlanıyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.post('../views/evrak-takip/api.php', { action: 'icra-ust-yazi-olustur', icra_id: selectedRadio.value }, function(res) {
        MobileSwal.close();
        const data = (typeof res === 'object') ? res : JSON.parse(res);
        if (data.status === 'success' && data.data) {
            const d = data.data;
            if (d.konu) document.getElementById('konu').value = d.konu;
            if (d.kurum_adi) document.getElementById('kurum_adi').value = d.kurum_adi;
            if (d.ilgiler) document.getElementById('ilgiler').value = d.ilgiler;
            const textVal = d.aciklama_html || d.aciklama || '';
            if (typeof $.fn.summernote !== 'undefined' && $('#giden_evrak_icerik').data('summernote')) {
                $('#giden_evrak_icerik').summernote('code', textVal);
            } else {
                document.getElementById('giden_evrak_icerik').value = textVal;
            }
            switchTab('icerik');
            MobileSwal.fire('Başarılı', 'İcra üst yazısı alanlara aktarıldı.', 'success');
        } else {
            MobileSwal.fire('Hata', data.message || 'Üst yazı oluşturulamadı.', 'error');
        }
    });
}

// AI Taslak Oluştur
function generateAiTaslak() {
    const file = document.getElementById('aiGelenEvrakFile').files[0];
    const prompt = document.getElementById('aiTalimatText').value;

    const fd = new FormData();
    fd.append('action', 'evrak-ai-taslak-olustur');
    if (file) fd.append('dosya', file);
    fd.append('talimat', prompt);

    closeModal('modal-ai-taslak');
    MobileSwal.fire({ title: 'AI Taslak Hazırlıyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    fetch('../views/evrak-takip/api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        MobileSwal.close();
        if (data.status === 'success' && data.data) {
            const d = data.data;
            if (d.konu) document.getElementById('konu').value = d.konu;
            if (d.kurum_adi) document.getElementById('kurum_adi').value = d.kurum_adi;
            const textVal = d.aciklama || '';
            if (typeof $.fn.summernote !== 'undefined' && $('#giden_evrak_icerik').data('summernote')) {
                $('#giden_evrak_icerik').summernote('code', textVal);
            } else {
                document.getElementById('giden_evrak_icerik').value = textVal;
            }
            switchTab('icerik');
            MobileSwal.fire('Başarılı', 'Yapay zeka taslağı aktarıldı.', 'success');
        } else {
            MobileSwal.fire('Hata', data.message || 'Taslak hazırlanamadı.', 'error');
        }
    })
    .catch(() => { MobileSwal.close(); MobileSwal.fire('Hata', 'AI Servis Hatası', 'error'); });
}

// AI Seçilen Metni Düzenle
function setAiSecimPrompt(val) {
    document.getElementById('aiSecimTalimatText').value = val;
}

function applyAiSecimTransform() {
    const prompt = document.getElementById('aiSecimTalimatText').value;
    const content = (typeof $.fn.summernote !== 'undefined' && $('#giden_evrak_icerik').data('summernote')) ? $('#giden_evrak_icerik').summernote('code') : document.getElementById('giden_evrak_icerik').value;
    if (!prompt || !content) return;

    closeModal('modal-ai-secim');
    MobileSwal.fire({ title: 'AI Metni Düzenliyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    $.post('../views/evrak-takip/api.php', { action: 'evrak-ai-metin-duzenle', talimat: prompt, metin: content }, function(res) {
        MobileSwal.close();
        const data = (typeof res === 'object') ? res : JSON.parse(res);
        if (data.status === 'success' && data.duzenlenmis_metin) {
            if (typeof $.fn.summernote !== 'undefined' && $('#giden_evrak_icerik').data('summernote')) {
                $('#giden_evrak_icerik').summernote('code', data.duzenlenmis_metin);
            } else {
                document.getElementById('giden_evrak_icerik').value = data.duzenlenmis_metin;
            }
            MobileSwal.fire('Başarılı', 'Metin düzenlendi.', 'success');
        } else {
            MobileSwal.fire('Hata', data.message || 'Düzenlenemedi.', 'error');
        }
    });
}

// E-İmza İşlemleri Helper
function eImzaIstek(action, id, extra, basarilarMesaji) {
    closeSheetIslemler();
    MobileSwal.fire({ title: 'İşleniyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    $.post('../views/evrak-takip/api.php', Object.assign({ action: action, id: id }, extra || {}), function(response) {
        const res = (typeof response === 'object') ? response : JSON.parse(response);
        MobileSwal.close();
        if (res.status === 'success') {
            MobileSwal.fire(basarilarMesaji, res.message, 'success').then(() => window.location.reload());
        } else {
            MobileSwal.fire('Hata', res.message || 'İşlem tamamlanamadı.', 'error');
        }
    }).fail(() => {
        MobileSwal.close();
        MobileSwal.fire('Hata', 'Sunucu ile iletişim kurulamadı.', 'error');
    });
}

function eImzaOnayaSun(id) {
    if (!validateGidenForm()) return;

    MobileSwal.fire({
        title: 'E-İmza ile Onaya Sun',
        html: id ? 'Evrak imzacıların onayına sunulacak.<br><b>Süreç tamamlanana kadar içeriği değiştirilemez.</b>' : 'Evrak kaydedilecek ve imza sahiplerinin onayına sunulacaktır.<br><b>Devam edilsin mi?</b>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: id ? 'Onaya Sun' : 'Kaydet ve Onaya Sun',
        cancelButtonText: 'Vazgeç'
    }).then(res => {
        if (!res.isConfirmed) return;

        if (id) {
            eImzaIstek('evrak-e-imza-onaya-sun', id, null, 'Onaya Sunuldu');
        } else {
            const form = document.getElementById('gidenEvrakForm');
            const formData = new FormData(form);
            const newAttachments = attachmentsList.filter(a => a.type === 'new');
            newAttachments.forEach((att) => {
                if (att.fileObj) {
                    formData.append('ek_dosyalari[]', att.fileObj);
                }
            });

            MobileSwal.fire({ title: 'Kaydediliyor ve Onaya Sunuluyor...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch('../views/evrak-takip/api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(kayit => {
                if (kayit.status !== 'success' || !kayit.id) {
                    MobileSwal.fire('Hata', kayit.message || 'Evrak kaydedilemedi.', 'error');
                    return;
                }
                $.post('../views/evrak-takip/api.php', { action: 'evrak-e-imza-onaya-sun', id: kayit.id }, function(response) {
                    const resJson = (typeof response === 'object') ? response : JSON.parse(response);
                    if (resJson.status === 'success') {
                        MobileSwal.fire('Onaya Sunuldu', resJson.message || 'Evrak e-imza onayına sunuldu.', 'success')
                            .then(() => location.href = '?p=evrak-takip');
                    } else {
                        MobileSwal.fire('Uyarı', 'Evrak kaydedildi ancak onaya sunulamadı: ' + (resJson.message || ''), 'warning')
                            .then(() => location.href = '?p=giden-evrak&id=' + encodeURIComponent(kayit.id));
                    }
                }).fail(function() {
                    MobileSwal.fire('Hata', 'Evrak kaydedildi ancak onaya sunulamadı.', 'error');
                });
            })
            .catch(() => MobileSwal.fire('Hata', 'Sunucu hatası oluştu.', 'error'));
        }
    });
}

function eImzaOnayla(id) {
    MobileSwal.fire({
        title: 'E-İmza ile Onayla',
        html: 'Evrakı elektronik imza ile onaylıyorsunuz.<br><b>Tüm imzacılar onayladığında evrak kilitlenir.</b>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Evet, Onayla',
        cancelButtonText: 'Vazgeç'
    }).then(res => {
        if (res.isConfirmed) eImzaIstek('evrak-e-imza-onayla', id, null, 'Onaylandı');
    });
}

function eImzaIade(id) {
    MobileSwal.fire({
        title: 'Düzeltilmek Üzere İade Et',
        html: 'Evrak imzalanmadan taslağa döndürülecek.',
        input: 'textarea',
        inputLabel: 'İade gerekçesi',
        inputPlaceholder: 'Gerekçeyi yazınız...',
        inputAttributes: { maxlength: 2000, rows: 4 },
        showCancelButton: true,
        confirmButtonText: 'İade Et',
        cancelButtonText: 'Vazgeç',
        inputValidator: value => (!value || value.trim().length < 5) ? 'Gerekçeyi en az 5 karakter yazınız.' : undefined
    }).then(res => {
        if (res.isConfirmed) eImzaIstek('evrak-e-imza-iade', id, { gerekce: res.value }, 'İade Edildi');
    });
}

function eImzaGeriAl(id) {
    MobileSwal.fire({
        title: 'Evrakı Üzerime Geri Al',
        html: 'İmza süreci iptal edilecek ve evrak <b>taslak</b> durumuna dönecek.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Evet, Geri Al',
        cancelButtonText: 'Vazgeç'
    }).then(res => {
        if (res.isConfirmed) eImzaIstek('evrak-e-imza-geri-al', id, null, 'Geri Alındı');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
