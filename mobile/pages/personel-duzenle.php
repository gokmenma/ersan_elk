<?php
use App\Model\PersonelModel;
use App\Helper\Security;
use App\Helper\Helper;

// ID çöz veya 0 yap
$enc_id = $_GET['id'] ?? '';
// PHP $_GET otomatik olarak url decode yapar. Ancak base64 içindeki bazı + karakterleri boşluk olmuşsa düzeltelim:
$encoded_id = str_replace(' ', '+', $enc_id);
$personel_id = Security::decrypt($encoded_id) ?: 0;

$PersonelModel = new PersonelModel();
$personel = $personel_id > 0 ? $PersonelModel->find($personel_id) : null;

$isEdit = $personel ? true : false;
$pageTitle = $isEdit ? 'Personel Düzenle' : 'Yeni Personel Ekle';

$activeTab = $_GET['tab'] ?? 'genel';

// Tüm sekmeler için gerekli verileri tek seferde çek (Sekme mantığı için)
$gecmis = $PersonelModel->getEkipGecmisi($personel_id);
$bolgeler = $PersonelModel->getDb()->query("SELECT DISTINCT ekip_bolge FROM tanimlamalar WHERE grup = 'ekip_kodu' AND ekip_bolge IS NOT NULL AND ekip_bolge != '' ORDER BY ekip_bolge ASC")->fetchAll(PDO::FETCH_COLUMN);
$ekip_kodlari_all = $PersonelModel->getDb()->query("SELECT id, tur_adi, ekip_bolge FROM tanimlamalar WHERE grup = 'ekip_kodu' AND silinme_tarihi IS NULL")->fetchAll(PDO::FETCH_OBJ);

// İzinler (LIMIT sildik - mobil yönetim için hepsi lazım)
$stmt = $PersonelModel->getDb()->prepare("SELECT pi.*, t.tur_adi as izin_tipi_adi FROM personel_izinleri pi LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL ORDER BY pi.baslangic_tarihi DESC");
$stmt->execute([$personel_id]);
$izinler = $stmt->fetchAll(PDO::FETCH_OBJ);

// Mobil İzin Yönetimi Verileri
$PersonelIzinleriModel = new \App\Model\PersonelIzinleriModel();
$entitlement = $PersonelIzinleriModel->calculateLeaveEntitlement($personel_id);
$izin_turleri_query = $PersonelModel->getDb()->query("SELECT id, tur_adi, ucretli_mi FROM tanimlamalar WHERE grup = 'izin_turu' AND silinme_tarihi IS NULL AND kisa_kod NOT IN ('X', 'x') ORDER BY tur_adi ASC");
$izin_turleri = $izin_turleri_query->fetchAll(PDO::FETCH_OBJ);

// Zimmetler
$stmt = $PersonelModel->getDb()->prepare("SELECT z.*, d.demirbas_adi, k.tur_adi as kategori_adi FROM demirbas_zimmet z LEFT JOIN demirbas d ON z.demirbas_id = d.id LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi' WHERE z.personel_id = ? AND z.durum = 'teslim' AND z.silinme_tarihi IS NULL ORDER BY z.teslim_tarihi DESC LIMIT 10");
$stmt->execute([$personel_id]);
$zimmetler = $stmt->fetchAll(PDO::FETCH_OBJ);

// Evraklar
$EvrakModel = new \App\Model\PersonelEvrakModel();
$evraklar = $EvrakModel->getByPersonel($personel_id);

// İcralar
$IcraModel = new \App\Model\PersonelIcralariModel();
$icralar = $IcraModel->getPersonelIcralariWithKesintiler($personel_id);

$aktifIcra = 0;
$toplamBorc = 0;
$toplamKesilen = 0;
$toplamKalan = 0;
$nextIcraSira = 1;

if (!empty($icralar)) {
    foreach ($icralar as $i) {
        if (($i->durum ?? '') === 'devam_ediyor') {
            $aktifIcra++;
        }
        $toplamBorc += floatval($i->toplam_borc ?? 0);
        $toplamKesilen += floatval($i->toplam_kesilen ?? 0);
        $toplamKalan += floatval($i->kalan_tutar ?? 0);
        if (intval($i->sira ?? 0) >= $nextIcraSira) {
            $nextIcraSira = intval($i->sira) + 1;
        }
    }
}

function getMobileFileIcon($mimeType) {
    if (strpos($mimeType, 'pdf') !== false) return 'description';
    if (strpos($mimeType, 'image') !== false) return 'image';
    if (strpos($mimeType, 'word') !== false || strpos($mimeType, 'document') !== false) return 'article';
    if (strpos($mimeType, 'excel') !== false || strpos($mimeType, 'sheet') !== false) return 'table_chart';
    return 'insert_drive_file';
}

function formatMobileFileSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0) . ' KB';
    return $bytes . ' B';
}

?>

<div class="bg-white dark:bg-card-dark min-h-screen flex flex-col relative">
    
    <!-- Özel Üst Bilgi Başlığı -->
    <header class="px-4 py-3 border-b border-slate-100 dark:border-slate-700/50 flex items-center justify-between sticky top-0 bg-white/95 dark:bg-card-dark/95 backdrop-blur-md z-40 shrink-0 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="?p=personel" class="w-9 h-9 flex items-center justify-center text-slate-500 rounded-full bg-slate-100 dark:bg-slate-800 active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h3 class="font-bold text-slate-900 dark:text-white text-[15px]"><?= $pageTitle ?></h3>
                <?php if($isEdit): ?>
                <p class="text-[10px] text-slate-500 font-medium truncate w-40"><?= htmlspecialchars($personel->adi_soyadi ?? '') ?></p>
                <?php else: ?>
                <p class="text-[10px] text-slate-500 font-medium">Yeni kayıt oluşturuluyor</p>
                <?php endif; ?>
            </div>
        </div>
        <?php if($isEdit): ?>
        <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center border border-primary/20 shrink-0 font-bold text-sm overflow-hidden">
            <?php 
            $peResim = !empty($personel->personel_resim_yolu) ? $personel->personel_resim_yolu : ($personel->resim_yolu ?? '');
            if (!empty($peResim) && file_exists($peResim)): ?>
                <img src="../<?= htmlspecialchars($peResim) ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <?= mb_strtoupper(mb_substr($personel->adi_soyadi ?? 'P', 0, 1, 'UTF-8')) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>
    <?php if($isEdit): ?>
    <!-- Yatay Kaydırılabilir Sekmeler (Talep Sayfası Tasarımı) -->
    <div class="px-3 shrink-0 sticky top-[61px] z-30 bg-white/95 dark:bg-card-dark/95 backdrop-blur-md pb-2 pt-1 border-b border-slate-100 dark:border-slate-800/50">
        <div class="flex gap-2 p-1 bg-slate-50/50 dark:bg-slate-900/50 rounded-2xl border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar scroll-smooth">
            <?php
            $pTabs = [
                'genel' => ['icon' => 'badge', 'label' => 'Genel'],
                'calisma' => ['icon' => 'work', 'label' => 'Çalışma'],
                'izinler' => ['icon' => 'event', 'label' => 'İzinler'],
                'zimmetler' => ['icon' => 'inventory_2', 'label' => 'Zimmet'],
                'finansal' => ['icon' => 'payments', 'label' => 'Maaş'],
                'evraklar' => ['icon' => 'folder_open', 'label' => 'Evrak'],
                'icralar' => ['icon' => 'gavel', 'label' => 'İcralar'],
                'puantaj' => ['icon' => 'more_time', 'label' => 'İş Takip'],
            ];
            
            foreach($pTabs as $tKey => $tData):
                $isActive = $activeTab === $tKey;
                $btnClass = $isActive 
                    ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20 active:scale-95' 
                    : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 active:scale-95';
            ?>
            <button type="button" onclick="switchTab('<?= $tKey ?>')" id="tab-btn-<?= $tKey ?>" 
                    class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl whitespace-nowrap text-[12px] font-bold transition-all <?= $btnClass ?>">
                <span class="material-symbols-outlined text-[18px]"><?= $tData['icon'] ?></span>
                <?= $tData['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    
    /* Bottom Sheet Z-Index Fix */
    #icraFormArea, #gorevFormArea, #ekipFormArea, #izinFormArea, #evrakFormArea, #icraHistoryArea {
        z-index: 10000 !important;
    }
    #icraBottomSheetBackdrop, #gorevBottomSheetBackdrop, #ekipBottomSheetBackdrop, #izinBottomSheetBackdrop, #evrakBottomSheetBackdrop, #icraHistoryBottomSheetBackdrop {
        z-index: 9999 !important;
    }
    
    /* Select2 Mobile Small Style */
    .select2-container--default .select2-selection--single {
        background-color: rgba(248, 250, 252, 0.8) !important;
        border: 1px solid #e2e8f0 !important;
        height: 30px !important;
        font-size: 11px !important;
        font-weight: 800 !important;
        border-radius: 10px !important;
        display: flex !important;
        align-items: center !important;
        transition: all 0.2s;
    }
    .dark .select2-container--default .select2-selection--single {
        background-color: rgba(15, 23, 42, 0.5) !important;
        border-color: #334155 !important;
        color: #f1f5f9 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: inherit !important;
        padding-left: 10px !important;
        padding-right: 25px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 28px !important;
        right: 4px !important;
    }
    .select2-small-dropdown.select2-dropdown {
        border-radius: 14px !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important;
        margin-top: 4px;
        overflow: hidden;
    }
    .dark .select2-small-dropdown.select2-dropdown {
        background-color: #1e293b !important;
        border-color: #334155 !important;
    }
    .select2-results__option {
        font-size: 12px !important;
        padding: 8px 12px !important;
        font-weight: 600 !important;
    }
    </style>
    <?php endif; ?>

    <div class="flex-1 overflow-y-auto">
        <!-- TAB Content: Genel Bilgiler -->
        <div id="content-genel" class="tab-content <?= $activeTab === 'genel' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
        <form id="personelMobileForm" onsubmit="submitPersonelForm(event)" autocomplete="off">
            <input type="hidden" name="action" value="personel-kaydet">
            <input type="hidden" name="personel_id" value="<?= $personel_id ?>">

            <div class="space-y-5">
                
                <!-- Genel Bilgiler Bölümü -->
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="material-symbols-outlined text-[16px]">badge</span> Genel Bilgiler
                    </h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Ad Soyad*</label>
                            <input type="text" name="adi_soyadi" value="<?= htmlspecialchars($personel->adi_soyadi ?? '') ?>" required 
                                   class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">TC Kimlik</label>
                                <input type="text" name="tc_kimlik_no" value="<?= htmlspecialchars($personel->tc_kimlik_no ?? '') ?>" maxlength="11" 
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Doğum Tarihi</label>
                                <?php 
                                $dt = $personel->dogum_tarihi ?? '';
                                if ($dt && $dt !== '0000-00-00') {
                                    $dt = date('d.m.Y', strtotime($dt));
                                } else {
                                    $dt = '';
                                }
                                ?>
                                <input type="text" name="dogum_tarihi" value="<?= $dt ?>" placeholder="GG.AA.YYYY"
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Cinsiyet</label>
                                <select name="cinsiyet" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                    <option value="">Seçiniz</option>
                                    <option value="Erkek" <?= ($personel->cinsiyet ?? '') === 'Erkek' ? 'selected' : '' ?>>Erkek</option>
                                    <option value="Kadın" <?= ($personel->cinsiyet ?? '') === 'Kadın' ? 'selected' : '' ?>>Kadın</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Medeni Durum</label>
                                <select name="medeni_durum" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                    <option value="">Seçiniz</option>
                                    <option value="Bekar" <?= ($personel->medeni_durum ?? '') === 'Bekar' ? 'selected' : '' ?>>Bekar</option>
                                    <option value="Evli" <?= ($personel->medeni_durum ?? '') === 'Evli' ? 'selected' : '' ?>>Evli</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- İletişim Bilgileri Bölümü -->
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="material-symbols-outlined text-[16px]">contact_phone</span> İletişim & Adres
                    </h4>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Cep Telefonu</label>
                                <input type="tel" name="cep_telefonu" value="<?= htmlspecialchars($personel->cep_telefonu ?? '') ?>" 
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Cep Tel 2</label>
                                <input type="tel" name="cep_telefonu_2" value="<?= htmlspecialchars($personel->cep_telefonu_2 ?? '') ?>" 
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">E-Posta</label>
                            <input type="email" name="email_adresi" value="<?= htmlspecialchars($personel->email_adresi ?? '') ?>" 
                                   class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Adres</label>
                            <textarea name="adres" rows="2" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white"><?= htmlspecialchars($personel->adres ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Giriş Bilgileri Bölümü -->
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="material-symbols-outlined text-[16px]">key</span> Giriş Bilgileri
                    </h4>
                    
                    <div class="space-y-4">
                        <div class="bg-slate-50 dark:bg-slate-800/30 p-3 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1.5 ml-1">Program Şifresi</label>
                            <div class="relative">
                                <input type="password" name="sifre" placeholder="Değiştirmek için doldurun" autocomplete="new-password"
                                       class="w-full pl-10 pr-12 py-3 bg-white dark:bg-card-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">lock</span>
                                <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                                </button>
                            </div>
                            <p class="text-[9px] text-slate-400 mt-1.5 ml-1">Personelin programa giriş için kullandığı şifreyi buradan güncelleyebilirsiniz.</p>
                        </div>

                        <div class="bg-sky-50/50 dark:bg-sky-900/10 p-4 rounded-3xl border border-sky-100/50 dark:border-sky-800/20">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="material-symbols-outlined text-sky-600 text-[18px]">smartphone</span>
                                <h5 class="text-[11px] font-bold text-sky-800 dark:text-sky-400 uppercase tracking-wider">Kaski APK Giriş Bilgileri</h5>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-sky-700/60 dark:text-sky-400/60 uppercase mb-1 ml-1">Kullanıcı Adı</label>
                                    <input type="text" name="kaski_kullanici_adi" value="<?= htmlspecialchars($personel->kaski_kullanici_adi ?? '') ?>" 
                                           class="w-full px-4 py-3 bg-white dark:bg-card-dark border border-sky-200/50 dark:border-sky-800/50 rounded-xl focus:border-sky-500 focus:ring-1 focus:ring-sky-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-sky-700/60 dark:text-sky-400/60 uppercase mb-1 ml-1">Şifre</label>
                                    <div class="relative">
                                        <input type="password" name="kaski_sifre" value="<?= htmlspecialchars($personel->kaski_sifre ?? '') ?>" autocomplete="new-password"
                                               class="w-full pl-4 pr-12 py-3 bg-white dark:bg-card-dark border border-sky-200/50 dark:border-sky-800/50 rounded-xl focus:border-sky-500 focus:ring-1 focus:ring-sky-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                        <button type="button" onclick="togglePassword(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-sky-400">
                                            <span class="material-symbols-outlined text-[18px]">visibility</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Alt Buton (Genel Bilgiler) -->
            <div class="fixed bottom-[66px] left-0 right-0 px-4 py-3 bg-white/95 dark:bg-card-dark/95 backdrop-blur-md border-t border-slate-100 dark:border-slate-800 z-40 safe-area-bottom">
                <button type="submit" id="saveBtn" class="w-full py-3.5 bg-indigo-600 text-white rounded-2xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg shadow-indigo-600/30 text-[15px]">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span> <?= $isEdit ? 'Değişiklikleri Kaydet' : 'Personeli Ekle' ?>
                </button>
            </div>
            
        </form>
        </div>

        <!-- TAB Content: Çalışma Bilgileri -->
        <div id="content-calisma" class="tab-content <?= $activeTab === 'calisma' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
        <form id="personelCalismaForm" onsubmit="submitPersonelForm(event)" autocomplete="off">
            <input type="hidden" name="action" value="personel-kaydet">
            <input type="hidden" name="personel_id" value="<?= $personel_id ?>">

            <div class="space-y-6">
                <!-- Temel Çalışma Bilgileri Bölümü -->
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="material-symbols-outlined text-[16px]">work</span> Temel Bilgiler
                    </h4>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                           
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">İşe Giriş</label>
                                <?php 
                                $igt = $personel->ise_giris_tarihi ?? '';
                                if ($igt && $igt !== '0000-00-00') {
                                    $igt = date('d.m.Y', strtotime($igt));
                                } else {
                                    $igt = '';
                                }
                                ?>
                                <input type="text" name="ise_giris_tarihi" value="<?= $igt ?>" placeholder="GG.AA.YYYY"
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                            </div>
                             <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">İşten Çıkış</label>
                                <?php 
                                $ict = $personel->isten_cikis_tarihi ?? '';
                                if ($ict && $ict !== '0000-00-00') {
                                    $ict = date('d.m.Y', strtotime($ict));
                                } else {
                                    $ict = '';
                                }
                                ?>
                                <input type="text" name="isten_cikis_tarihi" value="<?= $ict ?>" placeholder="GG.AA.YYYY"
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Saha Takibi</label>
                                <select name="saha_takibi" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                    <option value="1" <?= ($personel->saha_takibi ?? 0) == 1 ? 'selected' : '' ?>>Evet</option>
                                    <option value="0" <?= ($personel->saha_takibi ?? 0) == 0 ? 'selected' : '' ?>>Hayır</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Araç Kullanım</label>
                                <select name="arac_kullanim" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                    <option value="Yok" <?= ($personel->arac_kullanim ?? '') == 'Yok' ? 'selected' : '' ?>>Yok</option>
                                    <option value="Kendi Aracı" <?= ($personel->arac_kullanim ?? '') == 'Kendi Aracı' ? 'selected' : '' ?>>Kendi Aracı</option>
                                    <option value="Şirket aracı" <?= ($personel->arac_kullanim ?? '') == 'Şirket aracı' ? 'selected' : '' ?>>Şirket aracı</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Görev / Rol</label>
                            <div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl" onclick="switchTab('finansal')">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px] text-indigo-500">award_star</span>
                                    <div class="flex flex-col">
                                        <span class="text-[13px] font-black text-slate-800 dark:text-white"><?= htmlspecialchars($personel->gorev ?? 'Belirtilmemiş') ?></span>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase"><?= htmlspecialchars($personel->departman ?? '-') ?></span>
                                    </div>
                                </div>
                                <span class="material-symbols-outlined text-[16px] text-slate-300">chevron_right</span>
                            </div>
                            <p class="text-[9px] text-slate-400 mt-1.5 ml-1">Maaş & Finansal sekmesinden yönetilir.</p>
                        </div>
                    </div>
                </div>

                <!-- Ekip Kodu İşlemleri Bölümü -->
                <div>
                    <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">group</span> Ekip Kodu Geçmişi
                        </h4>
                        <button type="button" onclick="openEkipForm()" class="flex items-center gap-1 px-2 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg text-[10px] font-bold border border-indigo-100 dark:border-indigo-800/50">
                            <span class="material-symbols-outlined text-[14px]">add</span> Yeni Ekle
                        </button>
                    </div>




                    <?php if(empty($gecmis)): ?>
                        <div class="text-center py-8 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <span class="material-symbols-outlined text-3xl mb-2 opacity-30">group_off</span>
                            <p class="text-xs font-semibold">Henüz bir ekip ataması bulunmuyor.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach($gecmis as $g): 
                                $bugun = date('Y-m-d');
                                $isAktif = ($g->baslangic_tarihi <= $bugun && ($g->bitis_tarihi === null || $g->bitis_tarihi >= $bugun));
                                $statusLabel = $isAktif ? 'Aktif' : 'Pasif';
                                $statusColor = $isAktif ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30' : 'text-slate-500 bg-slate-100 dark:bg-slate-800';
                            ?>
                            <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-2xl p-3 shadow-sm relative overflow-hidden">
                                <?php if($g->ekip_sefi_mi == 1): ?>
                                    <div class="absolute -top-1 right-10">
                                        <span class="bg-amber-100 dark:bg-amber-900/50 text-amber-700 dark:text-amber-400 text-[10px] px-2 py-0.5 rounded-b-md font-bold flex items-center gap-0.5">
                                            <span class="material-symbols-outlined text-[12px]">verified</span> Şef
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h5 class="font-bold text-sm text-slate-800 dark:text-white"><?= htmlspecialchars($g->ekip_adi ?? 'Ekip Kodu') ?></h5>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider <?= $statusColor ?>"><?= $statusLabel ?></span>
                                            <p class="text-[10px] text-slate-400 font-medium">Başlangıç: <?= date('d.m.Y', strtotime($g->baslangic_tarihi)) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex gap-1">
                                        <button type="button" onclick="editEkipGecmisi(<?= $g->id ?>)" class="w-8 h-8 flex items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </button>
                                        <button type="button" onclick="deleteEkipGecmisi(<?= $g->id ?>)" class="w-8 h-8 flex items-center justify-center rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 pt-2 border-t border-slate-50 dark:border-slate-800/50">
                                    <span class="material-symbols-outlined text-[14px] text-slate-400">event</span>
                                    <span class="text-[11px] text-slate-500 font-medium">Bitiş: <?= $g->bitis_tarihi ? date('d.m.Y', strtotime($g->bitis_tarihi)) : 'Devam ediyor' ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sticky Alt Buton (Çalışma Bilgileri) -->
            <div class="fixed bottom-[66px] left-0 right-0 px-4 py-3 bg-white/95 dark:bg-card-dark/95 backdrop-blur-md border-t border-slate-100 dark:border-slate-800 z-40 safe-area-bottom">
                <button type="submit" id="saveBtn_calisma" class="w-full py-3.5 bg-indigo-600 text-white rounded-2xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg shadow-indigo-600/30 text-[15px]">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span> Kaydedin
                </button>
            </div>
        </form>
        </div>

        <!-- TAB Content: İzinler -->
        <div id="content-izinler" class="tab-content <?= $activeTab === 'izinler' ? '' : 'hidden' ?> px-4 pt-4 pb-14">


            <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">event</span> İzin Geçmişi
                </h4>
                <div class="flex items-center gap-2">
                    <select id="izinYearFilter" onchange="filterIzinlerByYear()" class="select2 text-[11px] font-bold">
                        <option value="all">Tüm Yıllar</option>
                        <?php 
                        if (!empty($izinler)) {
                            $currentYear = date('Y');
                            $years = [];
                            foreach($izinler as $iz) {
                                if(!empty($iz->baslangic_tarihi)) {
                                    $year = date('Y', strtotime($iz->baslangic_tarihi));
                                    if(!in_array($year, $years)) $years[] = $year;
                                }
                            }
                            rsort($years);
                            $hasCurrentYear = in_array($currentYear, $years);

                            foreach($years as $y):
                                $selected = ($hasCurrentYear && $y == $currentYear) ? 'selected' : '';
                            ?>
                            <option value="<?= $y ?>" <?= $selected ?>><?= $y ?></option>
                            <?php endforeach; 
                        } ?>
                    </select>
                </div>
            </div>

            <button type="button" onclick="openIzinForm()" class="fixed bottom-28 right-6 w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg shadow-indigo-600/40 flex items-center justify-center z-40 active:scale-95 transition-transform border-0 focus:outline-none">
                <span class="material-symbols-outlined text-3xl">add</span>
            </button>
            
            <?php if(empty($izinler)): ?>
                <div id="izinEmptyMsg" class="text-center py-8 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <span class="material-symbols-outlined text-4xl mb-2 opacity-30">event_busy</span>
                    <p class="text-xs font-semibold">Henüz izin/rapor kaydı bulunmuyor.</p>
                </div>
            <?php else: ?>
                <div id="izinEmptyMsg" class="hidden text-center py-8 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                    <span class="material-symbols-outlined text-4xl mb-2 opacity-30">event_busy</span>
                    <p class="text-xs font-semibold">Seçilen yıla ait kayıt bulunmuyor.</p>
                </div>
                <div class="space-y-3 pb-20" id="izinList">
                    <?php foreach($izinler as $izin): 
                        $statusColor = 'bg-slate-100 text-slate-600 border-slate-200';
                        $statusIcon = 'schedule';
                        if ($izin->onay_durumu === 'Onaylandı') {
                            $statusColor = 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-900/20 dark:border-emerald-800';
                            $statusIcon = 'check_circle';
                        } elseif ($izin->onay_durumu === 'Reddedildi') {
                            $statusColor = 'bg-rose-50 text-rose-600 border-rose-100 dark:bg-rose-900/20 dark:border-rose-800';
                            $statusIcon = 'cancel';
                        }
                        
                        $izinJson = htmlspecialchars(json_encode($izin), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="relative izin-item-container overflow-hidden rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800">
                        <!-- Delete Action (revealed on swipe right) -->
                        <div class="absolute left-0 top-0 bottom-0 w-[70px] bg-rose-500 flex items-center justify-center text-white cursor-pointer swipe-action-right opacity-0 pointer-events-none transition-opacity duration-200" 
                             onclick="event.stopPropagation(); deleteIzin(<?= $izin->id ?>)">
                            <div class="flex flex-col items-center gap-1">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                                <span class="text-[9px] font-bold uppercase">Sil</span>
                            </div>
                        </div>

                        <!-- Card Content (Swipeable) -->
                        <div class="bg-white dark:bg-card-dark p-4 transition-transform duration-200 swipe-content cursor-pointer" data-izin="<?= $izinJson ?>" onclick="handleIzinClick(this)">
                            <div class="flex items-start justify-between mb-3 border-b border-slate-50 dark:border-slate-800/60 pb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-slate-100 dark:border-slate-700 bg-slate-50 flex items-center justify-center shrink-0">
                                        <?php if (!empty($peResim) && file_exists($peResim)): ?>
                                            <img src="../<?= htmlspecialchars($peResim) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-slate-400">person</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3 class="font-black text-slate-800 dark:text-white text-[13px] leading-tight"><?= htmlspecialchars($izin->izin_tipi_adi ?? 'İzin') ?></h3>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight"><?= date('d.m.Y', strtotime($izin->baslangic_tarihi)) ?></span>
                                            <span class="material-symbols-outlined text-[10px] text-slate-300">calendar_today</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-flex items-center gap-1 bg-indigo-50 text-indigo-600 font-bold px-2 py-1 rounded-lg text-sm dark:bg-indigo-900/20 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800">
                                        <?= floatval($izin->toplam_gun) ?> <small class="text-[10px] ml-0.5">GÜN</small>
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="flex items-center gap-2 text-slate-500 text-[11px] font-medium">
                                    <span class="material-symbols-outlined text-[14px]">event_repeat</span>
                                    <span>Bitiş: <span class="text-slate-800 dark:text-slate-200"><?= date('d.m.Y', strtotime($izin->bitis_tarihi)) ?></span></span>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 <?= $statusColor ?> rounded-full text-[9px] font-black uppercase tracking-wider border">
                                        <span class="material-symbols-outlined text-[12px]"><?= $statusIcon ?></span>
                                        <?= htmlspecialchars($izin->onay_durumu ?? 'Beklemede') ?>
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?= ($izin->ucretli_mi ?? 1) == 1 ? 'Ücretli' : 'Ücretsiz' ?></span>
                                </div>

                                <?php if($izin->aciklama): ?>
                                <div class="bg-slate-50 dark:bg-slate-800/40 rounded-xl p-3 mt-2 border border-slate-100/50 dark:border-slate-800/50">
                                    <p class="text-[11px] text-slate-600 dark:text-slate-400 italic">"<?= htmlspecialchars($izin->aciklama) ?>"</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB Content: Zimmetler -->
        <div id="content-zimmetler" class="tab-content <?= $activeTab === 'zimmetler' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                <span class="material-symbols-outlined text-[16px]">devices</span> Mevcut Zimmetler
            </h4>
            
            <?php if(empty($zimmetler)): ?>
                <div class="text-center py-6 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="material-symbols-outlined text-3xl mb-2 opacity-50">print_disabled</span>
                    <p class="text-xs font-semibold">Bu personele ait aktif zimmet kaydı bulunamadı.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach($zimmetler as $zimmet): ?>
                    <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl p-3 shadow-sm flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 text-indigo-500 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined">devices</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h5 class="font-bold text-sm text-slate-800 dark:text-white truncate"><?= htmlspecialchars($zimmet->demirbas_adi ?? 'Zimmet Eşyası') ?></h5>
                            <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5 truncate"><?= htmlspecialchars($zimmet->kategori_adi ?: 'Detay yok') ?> <?= htmlspecialchars($zimmet->aciklama ? ' - ' . $zimmet->aciklama : '') ?></p>
                            <p class="text-[10px] text-slate-400 mt-1 uppercase tracking-wide font-medium"><span class="font-bold">Veriliş:</span> <?= date('d.m.Y', strtotime($zimmet->teslim_tarihi)) ?></p>
                        </div>
                        <span class="bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-500 font-bold px-2 py-0.5 rounded text-[10px] self-start mt-1 shrink-0">Zimmetli</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB Content: Finansal (Maaş & Görev) -->
        <div id="content-finansal" class="tab-content <?= $activeTab === 'finansal' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
            <form id="personelFinansalForm" onsubmit="submitPersonelForm(event)" autocomplete="off">
                <input type="hidden" name="action" value="personel-kaydet">
                <input type="hidden" name="personel_id" value="<?= $personel_id ?>">

                <div class="space-y-6">
                    <!-- Banka Bilgileri -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                            <span class="material-symbols-outlined text-[16px]">account_balance</span> Banka Bilgileri
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Maaş IBAN</label>
                                <input type="text" name="iban_numarasi" value="<?= htmlspecialchars($personel->iban_numarasi ?? '') ?>" 
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Ek Ödeme IBAN</label>
                                <input type="text" name="ek_odeme_iban_numarasi" value="<?= htmlspecialchars($personel->ek_odeme_iban_numarasi ?? '') ?>" 
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Yan Haklar & Kesintiler -->
                    <div>
                        <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                            <span class="material-symbols-outlined text-[16px]">award_star</span> Yan Haklar & Kesintiler
                        </h4>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Bes Kesintisi Var mı?</label>
                                <select name="bes_kesintisi_varmi" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                    <option value="1" <?= ($personel->bes_kesintisi_varmi ?? 0) == 1 ? 'selected' : '' ?>>Evet</option>
                                    <option value="0" <?= ($personel->bes_kesintisi_varmi ?? 0) == 0 ? 'selected' : '' ?>>Hayır</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Sodexo Tutarı</label>
                                    <input type="text" name="sodexo" value="<?= \App\Helper\Helper::formattedMoney($personel->sodexo ?? 0) ?>" 
                                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white money">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Sodexo Kart No</label>
                                    <input type="text" name="sodexo_kart_no" value="<?= htmlspecialchars($personel->sodexo_kart_no ?? '') ?>" 
                                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maaş Tipi Geçmişi -->
                    <div>
                        <div class="flex items-center justify-between mb-3 border-b border-slate-100 dark:border-slate-800 pb-2">
                            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">history</span> Maaş Tipi Geçmişi
                            </h4>
                            <?php 
                            $aktifGorevCheck = $PersonelModel->getAktifGorevGecmisi($personel_id);
                            if (!$aktifGorevCheck): 
                            ?>
                            <button type="button" onclick="openGorevForm()" class="flex items-center gap-1 px-2 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-lg text-[10px] font-bold border border-indigo-100 dark:border-indigo-800/50">
                                <span class="material-symbols-outlined text-[14px]">add</span> Yeni Maaş Tipi
                            </button>
                            <?php else: ?>
                            <span class="text-[9px] text-amber-600 font-bold bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-md">Aktif kayıt var</span>
                            <?php endif; ?>
                        </div>

                        <?php 
                        $maasGecmisi = $PersonelModel->getGorevGecmisi($personel_id);
                        if(empty($maasGecmisi)): 
                        ?>
                            <div class="text-center py-6 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                                <span class="material-symbols-outlined text-3xl mb-2 opacity-30">payments</span>
                                <p class="text-xs font-semibold">Henüz maaş geçmişi bulunmuyor.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach($maasGecmisi as $m): 
                                    $bugun = date('Y-m-d');
                                    $isAktif = ($m->baslangic_tarihi <= $bugun && ($m->bitis_tarihi === null || $m->bitis_tarihi >= $bugun));
                                    $statusColor = $isAktif ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30' : 'text-slate-500 bg-slate-100 dark:bg-slate-800';
                                ?>
                                <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-2xl p-4 shadow-sm relative overflow-hidden">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <h5 class="font-black text-sm text-slate-800 dark:text-white"><?= htmlspecialchars($m->gorev ?? 'Belirtilmemiş') ?></h5>
                                            <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mb-2"><?= htmlspecialchars($m->departman ?? '-') ?></p>
                                            
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider <?= $statusColor ?>"><?= $isAktif ? 'AKTİF' : 'PASİF' ?></span>
                                                <span class="text-[12px] font-black text-indigo-600"><?= \App\Helper\Helper::formattedMoney($m->maas_tutari) ?></span>
                                                <span class="text-[10px] font-bold text-slate-400 ml-1">(<?= htmlspecialchars($m->maas_durumu) ?>)</span>
                                            </div>
                                        </div>
                                        <div class="flex gap-1">
                                            <button type="button" onclick="editGorevGecmisi(<?= $m->id ?>)" class="w-8 h-8 flex items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </button>
                                            <button type="button" onclick="deleteGorevGecmisi(<?= $m->id ?>)" class="w-8 h-8 flex items-center justify-center rounded-full bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400">
                                                <span class="material-symbols-outlined text-[18px]">delete</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-4 pt-3 border-t border-slate-50 dark:border-slate-800/50 mt-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[14px] text-slate-400">event</span>
                                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tight"><?= date('d.m.Y', strtotime($m->baslangic_tarihi)) ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[14px] text-slate-400">event_busy</span>
                                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tight"><?= $m->bitis_tarihi ? date('d.m.Y', strtotime($m->bitis_tarihi)) : 'DEVAM EDİYOR' ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sticky Alt Buton (Finansal Bilgiler) -->
                <div class="fixed bottom-[66px] left-0 right-0 px-4 py-3 bg-white/95 dark:bg-card-dark/95 backdrop-blur-md border-t border-slate-100 dark:border-slate-800 z-40 safe-area-bottom">
                    <button type="submit" id="saveBtn_finansal" class="w-full py-3.5 bg-indigo-600 text-white rounded-2xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg shadow-indigo-600/30 text-[15px]">
                        <span class="material-symbols-outlined text-[20px]">task_alt</span> Bilgileri Güncelle
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB Content: Evraklar -->
        <div id="content-evraklar" class="tab-content <?= $activeTab === 'evraklar' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
            <div class="flex items-center justify-between mb-4">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2 flex-1">
                    <span class="material-symbols-outlined text-[16px]">folder_open</span> Personel Evrakları
                </h4>
            </div>

            <!-- FAB: Yeni Evrak Yükle -->
            <button type="button" onclick="openEvrakForm()" class="fixed bottom-28 right-6 w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg shadow-indigo-600/40 flex items-center justify-center z-40 active:scale-95 transition-transform border-0 focus:outline-none">
                <span class="material-symbols-outlined text-3xl">upload_file</span>
            </button>
            
            <?php if(empty($evraklar)): ?>
                <div class="flex flex-col items-center justify-center p-8 text-center bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800 mt-4">
                    <div class="w-16 h-16 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-400 mb-3">
                        <span class="material-symbols-outlined text-3xl">folder</span>
                    </div>
                    <h4 class="font-bold text-slate-800 dark:text-white text-sm mb-1">Evrak Bulunamadı</h4>
                    <p class="text-[11px] text-slate-500 font-medium">Henüz bu personele ait bir evrak yüklenmemiş.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-3">
                    <?php foreach($evraklar as $evrak): 
                        $icon = getMobileFileIcon($evrak->dosya_tipi);
                        $size = formatMobileFileSize($evrak->dosya_boyutu);
                        $path = '../uploads/personel_evraklar/' . $personel_id . '/' . $evrak->dosya_adi;
                        $encId = Security::encrypt($evrak->id);
                    ?>
                    <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-2xl p-3 shadow-sm flex items-center gap-3 active:bg-slate-50 dark:active:bg-slate-800/50 transition-colors" onclick="viewEvrak('<?= $path ?>', '<?= htmlspecialchars($evrak->evrak_adi) ?>', '<?= $evrak->dosya_tipi ?>')">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-2xl"><?= $icon ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h5 class="font-bold text-sm text-slate-800 dark:text-white truncate"><?= htmlspecialchars($evrak->evrak_adi) ?></h5>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-tight"><?= \App\Helper\Helper::EVRAK_TURLERI[$evrak->evrak_turu] ?? $evrak->evrak_turu ?></span>
                                <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                <span class="text-[10px] text-slate-400 font-medium"><?= $size ?></span>
                            </div>
                        </div>
                        <div class="flex gap-1" onclick="event.stopPropagation()">
                            <button type="button" onclick="deleteEvrak('<?= $encId ?>')" class="w-9 h-9 flex items-center justify-center rounded-full text-rose-500 bg-rose-50 dark:bg-rose-900/20 active:scale-90 transition-transform">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB Content: İcralar -->
        <div id="content-icralar" class="tab-content <?= $activeTab === 'icralar' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
            <!-- Stats Section -->
            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1 opacity-70">Aktif Dosya</p>
                    <div class="flex items-end justify-between">
                        <h4 class="text-xl font-black text-slate-800 dark:text-white leading-none"><?= $aktifIcra ?></h4>
                        <span class="material-symbols-outlined text-indigo-500 text-[20px]">gavel</span>
                    </div>
                </div>
                <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1 opacity-70">Toplam Borç</p>
                    <div class="flex items-end justify-between">
                        <h4 class="text-lg font-black text-rose-600 dark:text-rose-400 leading-none"><?= \App\Helper\Helper::formattedMoney($toplamBorc) ?></h4>
                    </div>
                </div>
                <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1 opacity-70">Toplam Kesilen</p>
                    <div class="flex items-end justify-between">
                        <h4 class="text-lg font-black text-emerald-600 dark:text-emerald-400 leading-none"><?= \App\Helper\Helper::formattedMoney($toplamKesilen) ?></h4>
                    </div>
                </div>
                <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800">
                    <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-1 opacity-70">Kalan Tutarlar</p>
                    <div class="flex items-end justify-between">
                        <h4 class="text-lg font-black text-indigo-600 dark:text-indigo-400 leading-none"><?= \App\Helper\Helper::formattedMoney($toplamKalan) ?></h4>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mb-4">
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2 flex-1">
                    <span class="material-symbols-outlined text-[16px]">list_alt</span> İcra Dosyaları
                </h4>
            </div>

            <!-- FAB: Yeni İcra Ekle -->
            <button type="button" onclick="openIcraForm()" class="fixed bottom-28 right-6 w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg shadow-indigo-600/40 flex items-center justify-center z-40 active:scale-95 transition-transform border-0 focus:outline-none">
                <span class="material-symbols-outlined text-3xl">gavel</span>
            </button>
            
            <?php if(empty($icralar)): ?>
                <div class="flex flex-col items-center justify-center p-8 text-center bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800 mt-4">
                    <div class="w-16 h-16 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-400 mb-3">
                        <span class="material-symbols-outlined text-3xl">gavel</span>
                    </div>
                    <h4 class="font-bold text-slate-800 dark:text-white text-sm mb-1">Dosya Bulunamadı</h4>
                    <p class="text-[11px] text-slate-500 font-medium">Bu personele ait bir icra dosyası bulunmuyor.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-3">
                    <?php foreach($icralar as $i): 
                        $statusColors = [
                            'bekliyor' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400 border-amber-100 dark:border-amber-800/50',
                            'devam_ediyor' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-400 border-emerald-100 dark:border-emerald-800/50',
                            'fekki_geldi' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400 border-indigo-100 dark:border-indigo-800/50',
                            'kesinti_bitti' => 'bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-100 dark:border-slate-700',
                            'bitti' => 'bg-slate-50 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border-slate-100 dark:border-slate-700',
                            'durduruldu' => 'bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400 border-rose-100 dark:border-rose-800/50'
                        ];
                        $statusLabels = [
                            'bekliyor' => 'BEKLEMEDE',
                            'devam_ediyor' => 'KESİNTİ YAPILIYOR',
                            'fekki_geldi' => 'FEKKİ GELDİ',
                            'kesinti_bitti' => 'KESİNTİ BİTTİ',
                            'bitti' => 'KAPATILDI',
                            'durduruldu' => 'DURDURULDU'
                        ];
                        $statusColor = $statusColors[$i->durum] ?? 'bg-slate-50 text-slate-600 dark:bg-slate-800';
                        $statusLabel = $statusLabels[$i->durum] ?? 'BİLİNMİYOR';
                        
                        $kesintiDetay = '';
                        if ($i->kesinti_tipi == 'oran') {
                            $kesintiDetay = '%' . $i->kesinti_orani;
                        } else {
                            $kesintiDetay = \App\Helper\Helper::formattedMoney($i->aylik_kesinti_tutari);
                        }
                    ?>
                    <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-3xl p-4 shadow-sm active:scale-[0.98] transition-all" onclick="editIcra(<?= $i->id ?>)">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-10 h-10 rounded-2xl bg-slate-50 dark:bg-slate-800 border border-slate-100 dark:border-slate-700 flex flex-col items-center justify-center shrink-0">
                                    <span class="text-[10px] font-black text-slate-400 leading-none">NO</span>
                                    <span class="text-[14px] font-black text-indigo-600 leading-none mt-0.5"><?= $i->sira ?></span>
                                </div>
                                <div>
                                    <h5 class="font-black text-[14px] text-slate-800 dark:text-white leading-tight"><?= htmlspecialchars($i->icra_dairesi) ?></h5>
                                    <p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mt-0.5"><?= htmlspecialchars($i->dosya_no) ?></p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-[9px] font-black border tracking-wider <?= $statusColor ?>"><?= $statusLabel ?></span>
                        </div>

                        <div class="grid grid-cols-2 gap-4 py-3 border-y border-slate-50 dark:border-slate-800/50">
                            <div>
                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest mb-0.5">TOPLAM BORÇ</p>
                                <p class="text-[13px] font-black text-slate-800 dark:text-white leading-none"><?= \App\Helper\Helper::formattedMoney($i->toplam_borc) ?></p>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="text-[10px] text-emerald-600 font-bold tracking-tight">K: <?= \App\Helper\Helper::formattedMoney($i->toplam_kesilen) ?></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] text-slate-400 font-black uppercase tracking-widest mb-0.5">KALAN TUTAR</p>
                                <p class="text-[13px] font-black text-rose-600 dark:text-rose-400 leading-none"><?= \App\Helper\Helper::formattedMoney($i->kalan_tutar) ?></p>
                                <div class="flex items-center justify-end gap-1.5 mt-1">
                                    <span class="text-[10px] text-indigo-600 font-bold tracking-tight">Aylık: <?= $kesintiDetay ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-3">
                            <div class="flex items-center gap-3">
                                <div class="flex flex-col">
                                    <span class="text-[8px] text-slate-400 font-black uppercase tracking-widest leading-none mb-1">BAŞLANGIÇ</span>
                                    <span class="text-[10px] text-slate-600 dark:text-slate-300 font-bold leading-none"><?= $i->baslangic_tarihi ? date('d.m.Y', strtotime($i->baslangic_tarihi)) : '-' ?></span>
                                </div>
                                <div class="w-1.5 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                                <div class="flex flex-col">
                                    <span class="text-[8px] text-slate-400 font-black uppercase tracking-widest leading-none mb-1">BİTİŞ</span>
                                    <span class="text-[10px] text-slate-600 dark:text-slate-300 font-bold leading-none"><?= ($i->bitis_tarihi && $i->bitis_tarihi != '0000-00-00') ? date('d.m.Y', strtotime($i->bitis_tarihi)) : '-' ?></span>
                                </div>
                            </div>
                            <div class="flex gap-2" onclick="event.stopPropagation()">
                                <button type="button" onclick="viewIcraKesintileri(<?= $i->id ?>, '<?= htmlspecialchars($i->icra_dairesi) ?>', '<?= htmlspecialchars($i->dosya_no) ?>', <?= $i->toplam_borc ?>)" class="w-9 h-9 flex items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 active:scale-90 transition-all">
                                    <span class="material-symbols-outlined text-[18px]">history</span>
                                </button>
                                <button type="button" onclick="deleteIcra(<?= $i->id ?>)" class="w-9 h-9 flex items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 active:scale-90 transition-all">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB Content: İş Takip -->
        <div id="content-puantaj" class="tab-content <?= $activeTab === 'puantaj' ? '' : 'hidden' ?> px-4 pt-4 pb-28">
            <!-- Filter Section -->
            <div class="bg-white dark:bg-card-dark rounded-2xl p-4 shadow-sm border border-slate-100 dark:border-slate-800 mb-4">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary dark:bg-primary/20 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">find_in_page</span>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 dark:text-white leading-tight">İş Takip Filtre</h4>
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest opacity-70">Dökümleri İncele</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Yıl Seçimi</label>
                        <select id="isTakipYear" class="select2-small-dropdown w-full" onchange="loadPersonelIsTakip()">
                            <?php 
                            $currYear = (int)date('Y');
                            for($y = $currYear; $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $y === $currYear ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Ay Seçimi</label>
                        <select id="isTakipMonth" class="select2-small-dropdown w-full" onchange="loadPersonelIsTakip()">
                            <option value="all">TÜM YIL</option>
                            <?php 
                            $currMonth = (int)date('m');
                            foreach(\App\Helper\Date::MONTHS as $mIdx => $mName): ?>
                                <option value="<?= $mIdx ?>" <?= $mIdx === $currMonth ? 'selected' : '' ?>><?= mb_strtoupper($mName, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Sub Tabs (Hidden by default to prevent flicker) -->
            <div class="overflow-x-auto no-scrollbar -mx-4 px-4 mb-4 hidden" id="isTakipSubTabsContainer">
                <div class="flex gap-2 min-w-max pb-1">
                    <button class="is-takip-subtab bg-indigo-600 text-white px-4 py-2 rounded-xl text-[11px] font-black shadow-sm shadow-indigo-600/20 active:scale-95 transition-all" onclick="switchIsTakipSubTab('okuma', this)">ENDEKS OKUMA</button>
                    <button class="is-takip-subtab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-[11px] font-black shadow-sm active:scale-95 transition-all" onclick="switchIsTakipSubTab('kesme', this)">KESME / AÇMA</button>
                    <button class="is-takip-subtab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-[11px] font-black shadow-sm active:scale-95 transition-all" onclick="switchIsTakipSubTab('sokme_takma', this)">SAYAÇ SÖ/TA</button>
                    <button class="is-takip-subtab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-[11px] font-black shadow-sm active:scale-95 transition-all" onclick="switchIsTakipSubTab('muhurleme', this)">MÜHÜRLEME</button>
                    <button class="is-takip-subtab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-[11px] font-black shadow-sm active:scale-95 transition-all" onclick="switchIsTakipSubTab('kacakkontrol', this)">KAÇAK KONTROL</button>
                </div>
            </div>

            <!-- List Container -->
            <div id="isTakipContent" class="space-y-4">
                <div class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <div class="w-8 h-8 border-2 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
                    <p class="text-[11px] font-bold tracking-widest uppercase">Veriler Yükleniyor...</p>
                </div>
            </div>
    </div> <!-- flex-1 closure -->
</div> <!-- Main div closure for line 85 moved here -->

<!-- Bottom Sheets Area (Root Level) -->
    <div id="gorevBottomSheetBackdrop" class="fixed inset-0 bg-black/60 z-[9999] hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="closeGorevForm()"></div>

    <!-- Maaş Tipi Ekle/Düzenle Bottom Sheet -->
    <div id="gorevFormArea" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[10000] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom pb-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="px-6 pb-2 border-b border-slate-50 dark:border-slate-800/50 mb-4">
            <h5 id="gorevFormTitle" class="text-[17px] font-bold text-slate-800 dark:text-white">Yeni Maaş Tipi Tanımla</h5>
        </div>

        <div class="px-6 space-y-4 max-h-[75vh] overflow-y-auto no-scrollbar">
            <input type="hidden" id="gorev_gecmisi_id" value="">
            <input type="hidden" id="gorev_gecmisi_action" value="gorev-gecmisi-ekle">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Departman</label>
                    <select id="modal_departman" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" onchange="loadMobileGorevOptions()">
                        <option value="">Seçiniz</option>
                        <?php foreach(\App\Helper\Helper::DEPARTMAN as $key => $val): ?>
                            <option value="<?= $key ?>"><?= $val ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Görev / Unvan</label>
                    <select id="modal_gorev" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                        <option value="">Önce Departman Seçin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Maaş Tipi</label>
                    <select id="modal_maas_durumu" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                        <?php foreach(\App\Helper\Helper::MAAS_HESAPLAMA_TIPI as $key => $val): ?>
                            <option value="<?= $key ?>"><?= $val ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Maaş Tutarı</label>
                    <input type="text" id="modal_maas_tutari" placeholder="0,00 ₺" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-black text-indigo-600 money">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Başlangıç</label>
                        <input type="text" id="modal_gorev_baslangic" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Bitiş</label>
                        <input type="text" id="modal_gorev_bitis" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Açıklama</label>
                    <textarea id="modal_aciklama" rows="2" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" placeholder="Ekstra notlar..."></textarea>
                </div>

                <div class="flex gap-3 pt-2 mb-6">
                    <button type="button" onclick="closeGorevForm()" class="flex-1 py-4 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl text-[15px] font-bold active:scale-95 transition-transform">
                        İptal
                    </button>
                    <button type="button" onclick="saveGorevGecmisi()" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[15px] font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-transform flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">check_circle</span> Kaydet
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ekip Atama Bottom Sheet Backdrop -->
    <div id="ekipBottomSheetBackdrop" class="fixed inset-0 bg-black/60 z-[9999] hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="closeEkipForm()"></div>

    <!-- Ekip Atama Bottom Sheet -->
    <div id="ekipFormArea" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[10000] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom pb-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="px-6 pb-2 border-b border-slate-50 dark:border-slate-800/50 mb-4">
            <h5 id="ekipFormTitle" class="text-[17px] font-bold text-slate-800 dark:text-white">Yeni Ekip Ataması</h5>
        </div>

        <div class="px-6 space-y-4 max-h-[75vh] overflow-y-auto no-scrollbar">
            <input type="hidden" id="ekip_gecmisi_id" value="">
            <input type="hidden" id="ekip_gecmisi_action" value="ekip-gecmisi-ekle">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Bölge</label>
                    <select id="modal_ekip_bolge" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" onchange="filterMobileEkiplerByBolge()">
                        <option value="">Seçiniz</option>
                        <?php foreach($bolgeler as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Ekip Kodu</label>
                    <select id="modal_ekip_kodu_id" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                        <option value="">Önce Bölge Seçin</option>
                        <?php if(isset($ekip_kodlari_all)): foreach($ekip_kodlari_all as $e): ?>
                            <option value="<?= $e->id ?>" data-bolge="<?= htmlspecialchars($e->ekip_bolge) ?>"><?= htmlspecialchars($e->tur_adi) ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Başlangıç</label>
                        <input type="text" id="modal_ekip_baslangic" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Bitiş</label>
                        <input type="text" id="modal_ekip_bitis" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Sorumluluk</label>
                    <select id="modal_ekip_sefi_mi" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                        <option value="0">Ekip Üyesi</option>
                        <option value="1">Ekip Şefi</option>
                    </select>
                </div>

                <div class="flex gap-3 pt-2 mb-6">
                    <button type="button" onclick="closeEkipForm()" class="flex-1 py-4 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl text-[15px] font-bold active:scale-95 transition-transform">
                        İptal
                    </button>
                    <button type="button" onclick="saveEkipGecmisi()" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[15px] font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-transform flex items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">check_circle</span> Kaydet
                    </button>
                </div>
            </div>
        </div>

    <!-- İzin Ekle/Düzenle Bottom Sheet Backdrop -->
    <div id="izinBottomSheetBackdrop" class="fixed inset-0 bg-black/60 z-[9999] hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="closeIzinForm()"></div>

    <!-- İzin Ekle/Düzenle Bottom Sheet -->
    <div id="izinFormArea" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[10000] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom pb-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="px-6 pb-2 border-b border-slate-50 dark:border-slate-800/50 mb-4">
            <h5 id="izinFormTitle" class="text-[17px] font-bold text-slate-800 dark:text-white">Yeni İzin Kaydı</h5>
        </div>

        <div class="px-6 space-y-4 max-h-[70vh] overflow-y-auto no-scrollbar">
            <form id="izinMobileForm" autocomplete="off">
                <input type="hidden" name="id" id="modal_izin_id" value="0">
                <input type="hidden" name="personel_id" value="<?= $personel_id ?>">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">İzin Türü</label>
                        <select name="izin_tipi" id="modal_izin_tipi" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                            <option value="">Seçiniz</option>
                            <?php foreach($izin_turleri as $it): ?>
                                <option value="<?= $it->id ?>" data-ucretli="<?= $it->ucretli_mi ?>"><?= htmlspecialchars($it->tur_adi) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Başlangıç</label>
                            <input type="text" name="baslangic_tarihi" id="modal_izin_baslangic" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white transition-all flatpickr-date">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Bitiş</label>
                            <input type="text" name="bitis_tarihi" id="modal_izin_bitis" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white transition-all flatpickr-date">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Toplam Gün</label>
                            <input type="number" name="sure" id="modal_izin_sure" step="0.5" class="w-full px-4 py-3 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-black text-indigo-600 dark:text-indigo-400">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Ücret Durumu</label>
                            <select name="izin_ucret_durumu" id="modal_izin_ucret" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                                <option value="1">Ücretli</option>
                                <option value="0">Ücretsiz</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Açıklama</label>
                        <textarea name="aciklama" id="modal_izin_aciklama" rows="2" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" placeholder="İzin nedeni vb..."></textarea>
                    </div>

                    <div class="flex gap-3 pt-2 mb-6">
                        <button type="button" onclick="closeIzinForm()" class="flex-1 py-4 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl text-[15px] font-bold active:scale-95 transition-transform">
                            İptal
                        </button>
                        <button type="button" onclick="saveIzin()" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[15px] font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-transform flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">check_circle</span> Kaydet
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Evrak Yükle Bottom Sheet Backdrop -->
    <div id="evrakBottomSheetBackdrop" class="fixed inset-0 bg-black/60 z-[9999] hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="closeEvrakForm()"></div>

    <!-- Evrak Yükle Bottom Sheet -->
    <div id="evrakFormArea" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[10000] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom pb-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="px-6 pb-2 border-b border-slate-50 dark:border-slate-800/50 mb-4">
            <h5 class="text-[17px] font-bold text-slate-800 dark:text-white">Yeni Evrak Yükle</h5>
        </div>

        <div class="px-6 space-y-4 max-h-[70vh] overflow-y-auto no-scrollbar">
            <form id="evrakMobileForm" enctype="multipart/form-data" autocomplete="off">
                <input type="hidden" name="action" value="evrak_yukle">
                <input type="hidden" name="personel_id" value="<?= $personel_id ?>">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Evrak Adı*</label>
                        <input type="text" name="evrak_adi" required placeholder="Örn: İş Sözleşmesi" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all">
                    </div>
                    
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Evrak Türü*</label>
                        <select name="evrak_turu" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                            <?php foreach(\App\Helper\Helper::EVRAK_TURLERI as $key => $val): ?>
                                <option value="<?= $key ?>" <?= $key === 'diger' ? 'selected' : '' ?>><?= htmlspecialchars($val) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Dosya Seç*</label>
                        <div class="relative">
                            <input type="file" name="evrak_dosyasi" id="evrak_dosyasi" required class="hidden" onchange="updateFileName(this)">
                            <label for="evrak_dosyasi" class="flex items-center justify-between w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-600 rounded-2xl cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                                <span id="fileName" class="text-[13px] text-slate-500 font-medium">Dosya seçilmedi...</span>
                                <span class="material-symbols-outlined text-indigo-500">attach_file</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Açıklama</label>
                        <textarea name="aciklama" rows="2" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" placeholder="Evrak hakkında notlar..."></textarea>
                    </div>

                    <div class="flex gap-3 pt-2 mb-6">
                        <button type="button" onclick="closeEvrakForm()" class="flex-1 py-4 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl text-[15px] font-bold active:scale-95 transition-transform">
                            İptal
                        </button>
                        <button type="button" onclick="saveEvrak()" id="btnEvrakKaydet" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[15px] font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-transform flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">cloud_upload</span> Yükle
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- İcra Ekle/Düzenle Bottom Sheet Backdrop -->
    <div id="icraBottomSheetBackdrop" class="fixed inset-0 bg-black/60 z-[9999] hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="closeIcraForm()"></div>

    <!-- İcra Ekle/Düzenle Bottom Sheet -->
    <div id="icraFormArea" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[10000] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom pb-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="px-6 pb-2 border-b border-slate-50 dark:border-slate-800/50 mb-4">
            <h5 id="icraFormTitle" class="text-[17px] font-bold text-slate-800 dark:text-white">Yeni İcra Dosyası</h5>
        </div>

        <div class="px-6 space-y-4 max-h-[80vh] overflow-y-auto no-scrollbar pb-8">
            <form id="icraMobileForm" autocomplete="off">
                <input type="hidden" name="id" id="modal_icra_id" value="">
                <input type="hidden" name="personel_id" value="<?= $personel_id ?>">
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Sıra No</label>
                            <input type="number" name="icra_sira" id="modal_icra_sira" value="<?= $nextIcraSira ?>" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Durum</label>
                            <select name="icra_durum" id="modal_icra_durum" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                                <option value="bekliyor">Beklemede</option>
                                <option value="devam_ediyor" selected>Kesinti Yapılıyor</option>
                                <option value="fekki_geldi">Fekki Geldi</option>
                                <option value="kesinti_bitti">Kesinti Bitti</option>
                                <option value="bitti">Kapatıldı</option>
                                <option value="durduruldu">Durduruldu</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">İcra Dairesi</label>
                        <input type="text" name="icra_dairesi" id="modal_icra_dairesi" placeholder="Örn: Ankara 1. İcra Dairesi" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Dosya No</label>
                            <input type="text" name="icra_dosya_no" id="modal_icra_dosya_no" placeholder="2024/..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Toplam Borç</label>
                            <input type="text" name="icra_toplam_borc" id="modal_icra_toplam_borc" placeholder="0,00 ₺" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-black text-rose-600 money">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">İcra IBAN</label>
                        <input type="text" name="icra_iban" id="modal_icra_iban" placeholder="TR..." class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Hesap Bilgileri</label>
                        <textarea name="icra_hesap_bilgileri" id="modal_icra_hesap_bilgileri" rows="1" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" placeholder="Banka, Şube vb."></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Kesinti Tipi</label>
                            <select name="icra_kesinti_tipi" id="modal_icra_kesinti_tipi" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" onchange="toggleIcraKesintiFields()">
                                <option value="tutar">Sabit Tutar</option>
                                <option value="oran">Maaş Oranı (%)</option>
                                <option value="net_yuzde">Net Maaş %</option>
                                <option value="asgari_yuzde">Asgari Ücret %</option>
                            </select>
                        </div>
                        <div id="div_modal_icra_aylik_kesinti">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Aylık Tutar</label>
                            <input type="text" name="icra_aylik_kesinti" id="modal_icra_aylik_kesinti" placeholder="0,00 ₺" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-black text-indigo-600 money">
                        </div>
                        <div id="div_modal_icra_kesinti_orani" style="display:none;">
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Kesinti Oranı (%)</label>
                            <input type="number" name="icra_kesinti_orani" id="modal_icra_kesinti_orani" value="25" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-black text-indigo-600">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Başlangıç</label>
                            <input type="text" name="icra_baslangic" id="modal_icra_baslangic" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Bitiş</label>
                            <input type="text" name="icra_bitis" id="modal_icra_bitis" placeholder="GG.AA.YYYY" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white flatpickr-date">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5 ml-1">Açıklama</label>
                        <textarea name="icra_aciklama" id="modal_icra_aciklama" rows="2" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-2xl text-[13px] font-semibold text-slate-800 dark:text-white" placeholder="Dosya hakkında notlar..."></textarea>
                    </div>

                    <div class="flex gap-3 pt-2 mb-6">
                        <button type="button" onclick="closeIcraForm()" class="flex-1 py-4 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl text-[15px] font-bold active:scale-95 transition-transform">
                            İptal
                        </button>
                        <button type="button" onclick="saveIcra()" class="flex-[2] py-4 bg-indigo-600 text-white rounded-2xl text-[15px] font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-transform flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-[20px]">check_circle</span> Kaydet
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- İcra Kesinti Geçmişi Bottom Sheet Backdrop -->
    <div id="icraHistoryBottomSheetBackdrop" class="fixed inset-0 bg-black/60 z-[9999] hidden opacity-0 transition-opacity duration-300 pointer-events-none" onclick="closeIcraHistory()"></div>

    <!-- İcra Kesinti Geçmişi Bottom Sheet -->
    <div id="icraHistoryArea" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[10000] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom pb-4 border-t border-slate-100 dark:border-slate-800">
        <div class="flex justify-center pt-3 pb-2">
            <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="px-6 pb-2 border-b border-slate-50 dark:border-slate-800/50 mb-4">
            <h5 id="icraHistoryTitle" class="text-[17px] font-bold text-slate-800 dark:text-white leading-tight">Kesinti Geçmişi</h5>
            <p id="icraHistorySubtitle" class="text-[11px] text-slate-500 font-bold uppercase tracking-widest mt-1">DOSYA DETAYLARI</p>
        </div>

        <div class="px-6 space-y-4 max-h-[70vh] overflow-y-auto no-scrollbar pb-8">
            <div id="icraHistoryStats" class="grid grid-cols-2 gap-3 mb-2">
                <div class="bg-emerald-50 dark:bg-emerald-900/10 p-3 rounded-2xl border border-emerald-100 dark:border-emerald-800/30">
                    <p class="text-[8px] text-emerald-600 dark:text-emerald-400 font-black tracking-widest uppercase mb-1">KESİLEN</p>
                    <p id="stat_icra_kesilen" class="text-[14px] font-black text-emerald-700 dark:text-emerald-300">0,00 ₺</p>
                </div>
                <div class="bg-rose-50 dark:bg-rose-900/10 p-3 rounded-2xl border border-rose-100 dark:border-rose-800/30">
                    <p class="text-[8px] text-rose-600 dark:text-rose-400 font-black tracking-widest uppercase mb-1">KALAN</p>
                    <p id="stat_icra_kalan" class="text-[14px] font-black text-rose-700 dark:text-rose-300">0,00 ₺</p>
                </div>
            </div>

            <div id="icraHistoryList" class="space-y-3">
                <!-- AJAX ile dolacak -->
            </div>
            
            <button type="button" onclick="closeIcraHistory()" class="w-full py-4 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-2xl text-[15px] font-bold active:scale-95 transition-transform mt-4">
                Kapat
            </button>
        </div>
    </div>

<script>
function submitPersonelForm(e) {
    e.preventDefault();
    const form = e.target;
    // Find the submit button within the form, or fallback to general saveBtn
    let btn = form.querySelector('button[type="submit"]');
    if (!btn) btn = document.getElementById('saveBtn');
    
    const defaultBtnHtml = btn.innerHTML;
    const formData = new FormData(form);
    
    btn.disabled = true;
    const originalBtnContent = btn.innerHTML;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto"></div>';
    
    fetch('../views/personel/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success' || data.status === 'success_alert') {
            Toast.show("Kayıt başarıyla tamamlandı!", "success");
            // If it's the financial form, we might want to stay on the page to see the history update
            // But for consistency let's go back to list as the original code did
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            Toast.show(data.message || "Bir hata oluştu.", "error");
            btn.disabled = false;
            btn.innerHTML = defaultBtnHtml;
        }
    })
    .catch(err => {
        Toast.show("Sunucu ile bağlantı kurulamadı.", "error");
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;
    });
}

function switchTab(tab) {
    // Tüm içerikleri gizle
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    // Seçilen içeriği göster
    const target = document.getElementById('content-' + tab);
    if (target) {
        target.classList.remove('hidden');
        // Yukarı kaydır
        window.scrollTo({ top: 0, behavior: 'smooth' });
        
        // İş Takip sekmesi seçildiyse verileri yükle
        if (tab === 'puantaj') {
            loadPersonelIsTakip();
        }
    }
    
    // URL'yi güncelle (sayfa yenilenmeden)
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);

    // Buton stillerini güncelle
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.className = 'tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl whitespace-nowrap text-[12px] font-bold transition-all text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 active:scale-95';
    });
    
    const activeBtn = document.getElementById('tab-btn-' + tab);
    if (activeBtn) {
        activeBtn.className = 'tab-btn flex items-center gap-2 px-4 py-2.5 rounded-xl whitespace-nowrap text-[12px] font-bold transition-all bg-indigo-600 text-white shadow-lg shadow-indigo-600/20 active:scale-95';
    }
}

let currentIsTakipSubTab = 'okuma';

function switchIsTakipSubTab(tab, btn) {
    currentIsTakipSubTab = tab;
    
    // Update button styles
    document.querySelectorAll('.is-takip-subtab').forEach(b => {
        b.className = 'is-takip-subtab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-[11px] font-black shadow-sm active:scale-95 transition-all';
    });
    
    btn.className = 'is-takip-subtab bg-indigo-600 text-white px-4 py-2 rounded-xl text-[11px] font-black shadow-sm shadow-indigo-600/20 active:scale-95 transition-all';
    
    // Scroll to active tab
    const container = document.getElementById('isTakipSubTabsContainer');
    const scrollLeft = btn.offsetLeft - (container.offsetWidth / 2) + (btn.offsetWidth / 2);
    container.scrollTo({ left: scrollLeft, behavior: 'smooth' });
    
    loadPersonelIsTakip();
}

function loadPersonelIsTakip() {
    const container = document.getElementById('isTakipContent');
    const year = document.getElementById('isTakipYear').value;
    const month = document.getElementById('isTakipMonth').value;
    const personelId = '<?= $personel_id ?>';
    const subTabsContainer = document.getElementById('isTakipSubTabsContainer');
    
    subTabsContainer.classList.add('hidden');
    container.innerHTML = `
        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
            <div class="w-8 h-8 border-2 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
            <p class="text-[11px] font-bold tracking-widest uppercase">Kategoriler Kontrol Ediliyor...</p>
        </div>
    `;
    
    // First, check which tabs have data
    const summaryParams = new URLSearchParams({
        action: 'get-mobile-personel-is-takip-summary',
        pId: personelId,
        year: year
    });
    
    if (month !== 'all') {
        summaryParams.append('month', month);
    } else {
        summaryParams.append('start_date', year + '-01-01');
        summaryParams.append('end_date', year + '-12-31');
    }
    
    fetch('../views/puantaj/api.php?' + summaryParams.toString())
    .then(res => res.json())
    .then(summary => {
        let firstAvailableTab = null;
        let anyData = false;
        
        // Loop through all subtabs
        document.querySelectorAll('.is-takip-subtab').forEach(btn => {
            const tabKey = btn.getAttribute('onclick').match(/'([^']+)'/)[1];
            if (summary[tabKey] > 0) {
                btn.style.display = 'block';
                anyData = true;
                if (!firstAvailableTab) firstAvailableTab = tabKey;
            } else {
                btn.style.display = 'none';
            }
        });
        
        const subTabsContainer = document.getElementById('isTakipSubTabsContainer');
        
        if (anyData) {
            subTabsContainer.classList.remove('hidden');
            // Find the current active tab. If it's hidden now, switch to the first available one.
            const currentActiveBtn = document.querySelector('.is-takip-subtab.bg-indigo-600');
            const currentActiveTab = currentActiveBtn ? currentActiveBtn.getAttribute('onclick').match(/'([^']+)'/)[1] : null;
            
            if (!currentActiveTab || summary[currentActiveTab] === 0) {
                // Switch to first available
                const targetBtn = Array.from(document.querySelectorAll('.is-takip-subtab')).find(b => b.style.display === 'block');
                if (targetBtn) {
                    currentIsTakipSubTab = firstAvailableTab;
                    // Reset all button styles
                    document.querySelectorAll('.is-takip-subtab').forEach(b => {
                        b.className = 'is-takip-subtab bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-100 dark:border-slate-800 px-4 py-2 rounded-xl text-[11px] font-black shadow-sm active:scale-95 transition-all';
                    });
                    targetBtn.className = 'is-takip-subtab bg-indigo-600 text-white px-4 py-2 rounded-xl text-[11px] font-black shadow-sm shadow-indigo-600/20 active:scale-95 transition-all';
                }
            }
            
            // Re-fetch content for the active tab (or first available)
            fetchIsTakipContent(year, month, personelId);
        } else {
            subTabsContainer.classList.add('hidden');
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-12 px-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-700">
                    <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">history</span>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Kayıt Bulunamadı</p>
                    <p class="text-[10px] text-slate-400 mt-1">Seçilen dönemde yapılan herhangi bir iş kaydı bulunmuyor.</p>
                </div>
            `;
        }
    });
}

function fetchIsTakipContent(year, month, personelId) {
    const container = document.getElementById('isTakipContent');
    container.innerHTML = `
        <div class="flex flex-col items-center justify-center py-12 text-slate-400">
            <div class="w-8 h-8 border-2 border-primary/30 border-t-primary rounded-full animate-spin mb-3"></div>
            <p class="text-[11px] font-bold tracking-widest uppercase">Veriler Yükleniyor...</p>
        </div>
    `;

    const params = new URLSearchParams({
        action: 'get-mobile-personel-is-takip',
        pId: personelId,
        year: year,
        tab: currentIsTakipSubTab
    });
    
    if (month !== 'all') {
        params.append('month', month);
    } else {
        params.append('start_date', year + '-01-01');
        params.append('end_date', year + '-12-31');
    }
    
    fetch('../views/puantaj/api.php?' + params.toString())
    .then(res => res.text())
    .then(html => {
        container.innerHTML = html;
    })
    .catch(err => {
        container.innerHTML = `<div class="p-5 text-center text-rose-500 font-bold bg-rose-50 dark:bg-rose-900/20 rounded-2xl border border-rose-100 dark:border-rose-800/50">Veriler yüklenirken bir hata oluştu.</div>`;
    });
}

function openEkipForm() {
    document.getElementById('ekip_gecmisi_id').value = '';
    document.getElementById('ekip_gecmisi_action').value = 'ekip-gecmisi-ekle';
    document.getElementById('ekipFormTitle').innerText = 'Yeni Ekip Ataması';
    document.getElementById('modal_ekip_bolge').value = '';
    document.getElementById('modal_ekip_kodu_id').value = '';
    document.getElementById('modal_ekip_baslangic').value = '<?= date('d.m.Y') ?>';
    document.getElementById('modal_ekip_bitis').value = '';
    document.getElementById('modal_ekip_sefi_mi').value = '0';
    
    // Bottom Sheet Açılışı
    const backdrop = document.getElementById('ekipBottomSheetBackdrop');
    const sheet = document.getElementById('ekipFormArea');
    
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
    }, 10);
    
    filterMobileEkiplerByBolge();
}

function closeEkipForm() {
    const backdrop = document.getElementById('ekipBottomSheetBackdrop');
    const sheet = document.getElementById('ekipFormArea');
    
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    
    setTimeout(() => {
        backdrop.classList.add('hidden');
    }, 300);
}

function filterMobileEkiplerByBolge() {
    const bolge = document.getElementById('modal_ekip_bolge').value;
    const select = document.getElementById('modal_ekip_kodu_id');
    const options = select.querySelectorAll('option');
    
    options.forEach(opt => {
        if (opt.value === '') return;
        const optBolge = opt.getAttribute('data-bolge');
        if (bolge === '' || optBolge === bolge) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
        }
    });
    
    // Eğer seçili olan artık görünmüyorsa temizle
    if (select.selectedOptions[0] && select.selectedOptions[0].style.display === 'none') {
        select.value = '';
    }
}

// Maaş Geçmişi Fonksiyonları
function openGorevForm() {
    document.getElementById('gorev_gecmisi_id').value = '';
    document.getElementById('gorev_gecmisi_action').value = 'gorev-gecmisi-ekle';
    document.getElementById('gorevFormTitle').innerText = 'Yeni Maaş Tipi Tanımla';
    document.getElementById('modal_departman').value = '';
    document.getElementById('modal_gorev').innerHTML = '<option value="">Önce Departman Seçin</option>';
    document.getElementById('modal_maas_durumu').value = 'Brüt';
    document.getElementById('modal_maas_tutari').value = '';
    document.getElementById('modal_gorev_baslangic').value = '<?= date('d.m.Y') ?>';
    document.getElementById('modal_gorev_bitis').value = '';
    document.getElementById('modal_aciklama').value = '';
    
    const backdrop = document.getElementById('gorevBottomSheetBackdrop');
    const sheet = document.getElementById('gorevFormArea');
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeGorevForm() {
    const backdrop = document.getElementById('gorevBottomSheetBackdrop');
    const sheet = document.getElementById('gorevFormArea');
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    setTimeout(() => backdrop.classList.add('hidden'), 300);
}

function loadMobileGorevOptions(selectedGorev = null) {
    const dep = document.getElementById('modal_departman').value;
    const select = document.getElementById('modal_gorev');
    
    if (!dep) {
        select.innerHTML = '<option value="">Önce Departman Seçin</option>';
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'unvan-ucretleri-getir');
    formData.append('departman', dep);
    
    fetch('../views/tanimlamalar/api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            let html = '<option value="">Görev Seçin</option>';
            data.data.forEach(item => {
                html += `<option value="${item.tur_adi}">${item.tur_adi}</option>`;
            });
            select.innerHTML = html;
            if (selectedGorev) select.value = selectedGorev;
        }
    });
}

function saveGorevGecmisi() {
    const id = document.getElementById('gorev_gecmisi_id').value;
    const action = document.getElementById('gorev_gecmisi_action').value;
    const personel_id = '<?= $personel_id ?>';
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', action);
    formData.append('personel_id', personel_id);
    formData.append('departman', document.getElementById('modal_departman').value);
    formData.append('gorev', document.getElementById('modal_gorev').value);
    formData.append('maas_durumu', document.getElementById('modal_maas_durumu').value);
    formData.append('maas_tutari', document.getElementById('modal_maas_tutari').value);
    formData.append('gorev_baslangic', document.getElementById('modal_gorev_baslangic').value);
    formData.append('gorev_bitis', document.getElementById('modal_gorev_bitis').value);
    formData.append('aciklama', document.getElementById('modal_aciklama').value);
    
    fetch('../views/personel/api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message || "İşlem başarılı", "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Bir hata oluştu.", "error");
        }
    })
    .catch(err => Toast.show("Sunucu hatası.", "error"));
}

function editGorevGecmisi(id) {
    fetch('../views/personel/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=gorev-gecmisi-get&id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            const item = data.data;
            document.getElementById('gorev_gecmisi_id').value = item.id;
            document.getElementById('gorev_gecmisi_action').value = 'gorev-gecmisi-guncelle';
            document.getElementById('gorevFormTitle').innerText = 'Maaş Tipini Düzenle';
            
            document.getElementById('modal_departman').value = item.departman || '';
            loadMobileGorevOptions(item.gorev);
            
            document.getElementById('modal_maas_durumu').value = item.maas_durumu;
            document.getElementById('modal_maas_tutari').value = item.maas_tutari;
            document.getElementById('modal_gorev_baslangic').value = item.baslangic_tarihi;
            document.getElementById('modal_gorev_bitis').value = item.bitis_tarihi || '';
            document.getElementById('modal_aciklama').value = item.aciklama || '';
            
            const backdrop = document.getElementById('gorevBottomSheetBackdrop');
            const sheet = document.getElementById('gorevFormArea');
            backdrop.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0', 'pointer-events-none');
                backdrop.classList.add('opacity-100');
                sheet.classList.remove('translate-y-full');
            }, 10);
        }
    });
}

async function deleteGorevGecmisi(id) {
    const isConfirmed = await Alert.confirmDelete("Sil", "Bu maaş geçmişi kaydını silmek istediğinize emin misiniz?");
    if(!isConfirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'gorev-gecmisi-sil');
    formData.append('id', id);
    fetch('../views/personel/api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message || "İşlem başarılı", "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Hata oluştu.", "error");
        }
    });
}

function saveEkipGecmisi() {
    const id = document.getElementById('ekip_gecmisi_id').value;
    const action = document.getElementById('ekip_gecmisi_action').value;
    const personel_id = '<?= $personel_id ?>';
    const ekip_kodu_id = document.getElementById('modal_ekip_kodu_id').value;
    const baslangic_tarihi = document.getElementById('modal_ekip_baslangic').value;
    const bitis_tarihi = document.getElementById('modal_ekip_bitis').value;
    const ekip_sefi_mi = document.getElementById('modal_ekip_sefi_mi').value;
    
    if (!ekip_kodu_id || !baslangic_tarihi) {
        Toast.show("Lütfen tüm alanları doldurun.", "warning");
        return;
    }
    
    const formData = new FormData();
    formData.append('id', id);
    formData.append('action', action);
    formData.append('personel_id', personel_id);
    formData.append('ekip_kodu_id', ekip_kodu_id);
    formData.append('baslangic_tarihi', baslangic_tarihi);
    formData.append('bitis_tarihi', bitis_tarihi);
    formData.append('ekip_sefi_mi', ekip_sefi_mi);
    
    fetch('../views/personel/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message, "success");
            setTimeout(() => location.reload(), 1000); 
        } else {
            Toast.show(data.message || "Bir hata oluştu.", "error");
        }
    })
    .catch(err => Toast.show("Sunucu hatası.", "error"));
}

function editEkipGecmisi(id) {
    fetch('../views/personel/api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=ekip-gecmisi-get&id=' + id
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            const item = data.data;
            document.getElementById('ekip_gecmisi_id').value = item.id;
            document.getElementById('ekip_gecmisi_action').value = 'ekip-gecmisi-guncelle';
            document.getElementById('ekipFormTitle').innerText = 'Ekip Atamasını Düzenle';
            
            // Bölgeyi bul
            const opt = document.querySelector(`#modal_ekip_kodu_id option[value="${item.ekip_kodu_id}"]`);
            if (opt) {
                document.getElementById('modal_ekip_bolge').value = opt.getAttribute('data-bolge') || '';
                filterMobileEkiplerByBolge();
                document.getElementById('modal_ekip_kodu_id').value = item.ekip_kodu_id;
            }
            
            document.getElementById('modal_ekip_baslangic').value = item.baslangic_tarihi;
            document.getElementById('modal_ekip_bitis').value = item.bitis_tarihi || '';
            document.getElementById('modal_ekip_sefi_mi').value = item.ekip_sefi_mi || '0';
            
            // Bottom Sheet Açılışı
            const backdrop = document.getElementById('ekipBottomSheetBackdrop');
            const sheet = document.getElementById('ekipFormArea');
            
            backdrop.classList.remove('hidden');
            setTimeout(() => {
                backdrop.classList.remove('opacity-0', 'pointer-events-none');
                backdrop.classList.add('opacity-100');
                sheet.classList.remove('translate-y-full');
            }, 10);
        } else {
            Toast.show(data.message || "Veri alınamadı.", "error");
        }
    });
}

async function deleteEkipGecmisi(id) {
    const isConfirmed = await Alert.confirmDelete("Sil", "Bu ekip geçmişi kaydını silmek istediğinize emin misiniz?");
    if(!isConfirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'ekip-gecmisi-sil');
    formData.append('id', id);
    
    fetch('../views/personel/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message, "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Hata oluştu.", "error");
        }
    });
}

function togglePassword(btn) {
    const input = btn.parentElement.querySelector('input');
    const icon = btn.querySelector('span');
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerText = 'visibility_off';
    } else {
        input.type = 'password';
        icon.innerText = 'visibility';
    }
}
// İzin Yönetimi Fonksiyonları
function openIzinForm() {
    document.getElementById('modal_izin_id').value = '0';
    document.getElementById('izinFormTitle').innerText = 'Yeni İzin Kaydı';
    document.getElementById('izinMobileForm').reset();
    
    const backdrop = document.getElementById('izinBottomSheetBackdrop');
    const sheet = document.getElementById('izinFormArea');
    
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function closeIzinForm() {
    const backdrop = document.getElementById('izinBottomSheetBackdrop');
    const sheet = document.getElementById('izinFormArea');
    
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    
    setTimeout(() => {
        backdrop.classList.add('hidden');
    }, 300);
}

function editIzin(data) {
    document.getElementById('modal_izin_id').value = data.id;
    document.getElementById('izinFormTitle').innerText = 'İzin Kaydını Düzenle';
    
    document.getElementById('modal_izin_tipi').value = data.izin_tipi_id;
    document.getElementById('modal_izin_baslangic').value = formatDateTR(data.baslangic_tarihi);
    document.getElementById('modal_izin_bitis').value = formatDateTR(data.bitis_tarihi);
    document.getElementById('modal_izin_sure').value = data.toplam_gun;
    document.getElementById('modal_izin_ucret').value = data.ucretli_mi ?? 1;
    document.getElementById('modal_izin_aciklama').value = data.aciklama || '';
    
    // Bottom Sheet Aç
    const backdrop = document.getElementById('izinBottomSheetBackdrop');
    const sheet = document.getElementById('izinFormArea');
    
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
    }, 10);
}

function formatDateTR(dateStr) {
    if(!dateStr || dateStr.startsWith('0000')) return '';
    const d = new Date(dateStr);
    return (d.getDate().toString().padStart(2, '0')) + '.' + 
           ((d.getMonth() + 1).toString().padStart(2, '0')) + '.' + 
           d.getFullYear();
}

function saveIzin() {
    const form = document.getElementById('izinMobileForm');
    const formData = new FormData(form);
    formData.append('action', 'izin_kaydet');
    
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch('../views/personel/api/APIizinler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message, "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Hata oluştu.", "error");
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        Toast.show("Sunucu hatası.", "error");
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

async function deleteIzin(id) {
    const isConfirmed = await Alert.confirmDelete("Sil", "Bu izin kaydını silmek istediğinize emin misiniz?");
    if(!isConfirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'izin_sil');
    formData.append('id', id);
    
    fetch('../views/personel/api/APIizinler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message, "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Hata oluştu.", "error");
        }
    })
    .catch(err => Toast.show("Sunucu hatası.", "error"));
}

// Tarih Değiştiğinde Süre Hesapla
if(document.getElementById('modal_izin_baslangic')) {
    document.getElementById('modal_izin_baslangic').addEventListener('change', calculateIzinDuration);
}
if(document.getElementById('modal_izin_bitis')) {
    document.getElementById('modal_izin_bitis').addEventListener('change', calculateIzinDuration);
}

function calculateIzinDuration() {
    const startStr = document.getElementById('modal_izin_baslangic').value;
    const endStr = document.getElementById('modal_izin_bitis').value;
    
    if(!startStr || !endStr) return;
    
    const parseDate = (str) => {
        const parts = str.split('.');
        if(parts.length === 3) return new Date(parts[2], parts[1]-1, parts[0]);
        return new Date(str);
    };
    
    const start = parseDate(startStr);
    const end = parseDate(endStr);
    
    if(start && end && !isNaN(start) && !isNaN(end)) {
        const diffTime = Math.abs(end - start);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('modal_izin_sure').value = diffDays > 0 ? diffDays : 0;
    }
}

// İcra Yönetimi
function openIcraForm() {
    document.getElementById('modal_icra_id').value = '';
    document.getElementById('icraFormTitle').innerText = 'Yeni İcra Dosyası';
    document.getElementById('icraMobileForm').reset();
    document.getElementById('modal_icra_sira').value = '<?= $nextIcraSira ?>';
    document.getElementById('modal_icra_durum').value = 'devam_ediyor';
    document.getElementById('modal_icra_kesinti_tipi').value = 'tutar';
    toggleIcraKesintiFields();
    
    const backdrop = document.getElementById('icraBottomSheetBackdrop');
    const sheet = document.getElementById('icraFormArea');
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
        sheet.classList.add('translate-y-0');
    }, 10);
}

function closeIcraForm() {
    const backdrop = document.getElementById('icraBottomSheetBackdrop');
    const sheet = document.getElementById('icraFormArea');
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    sheet.classList.remove('translate-y-0');
    setTimeout(() => backdrop.classList.add('hidden'), 300);
}

function toggleIcraKesintiFields() {
    const tip = document.getElementById('modal_icra_kesinti_tipi').value;
    const divTutar = document.getElementById('div_modal_icra_aylik_kesinti');
    const divOran = document.getElementById('div_modal_icra_kesinti_orani');
    
    if (tip === 'tutar') {
        divTutar.style.display = 'block';
        divOran.style.display = 'none';
    } else {
        divTutar.style.display = 'none';
        divOran.style.display = 'block';
    }
}

function saveIcra() {
    const form = document.getElementById('icraMobileForm');
    const icraId = document.getElementById('modal_icra_id').value;
    const action = icraId ? 'update_icra' : 'save_icra';
    
    const formData = new FormData(form);
    formData.append('action', action);
    
    const btn = event.currentTarget;
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch('../views/personel/ajax/kesinti-islemleri.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            Toast.show(icraId ? "Güncellendi" : "Kaydedildi", "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.error || "Hata oluştu", "error");
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        Toast.show("Bağlantı hatası", "error");
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

function editIcra(id) {
    fetch('../views/personel/ajax/kesinti-islemleri.php?action=get_icra&id=' + id + '&personel_id=<?= $personel_id ?>')
    .then(res => res.json())
    .then(data => {
        if(data && !data.error) {
            const idField = document.getElementById('modal_icra_id');
            if(!idField) {
                console.error("modal_icra_id bulunamadı!");
                Toast.show("Form yüklenemedi. Lütfen sayfayı yenileyiniz.", "error");
                return;
            }
            
            idField.value = data.id;
            document.getElementById('icraFormTitle').innerText = 'İcra Dosyası Düzenle';
            document.getElementById('modal_icra_sira').value = data.sira || '';
            document.getElementById('modal_icra_durum').value = data.durum || 'devam_ediyor';
            document.getElementById('modal_icra_dairesi').value = data.icra_dairesi || '';
            document.getElementById('modal_icra_dosya_no').value = data.dosya_no || '';
            document.getElementById('modal_icra_toplam_borc').value = data.toplam_borc || '0,00';
            document.getElementById('modal_icra_iban').value = data.iban || '';
            document.getElementById('modal_icra_hesap_bilgileri').value = data.hesap_bilgileri || '';
            document.getElementById('modal_icra_kesinti_tipi').value = data.kesinti_tipi || 'tutar';
            document.getElementById('modal_icra_aylik_kesinti').value = data.aylik_kesinti_tutari || '0,00';
            document.getElementById('modal_icra_kesinti_orani').value = data.kesinti_orani || 25;
            document.getElementById('modal_icra_baslangic').value = data.baslangic_tarihi || '';
            document.getElementById('modal_icra_bitis').value = (data.bitis_tarihi && data.bitis_tarihi != '00.00.0000' && data.bitis_tarihi != '0000-00-00') ? data.bitis_tarihi : '';
            document.getElementById('modal_icra_aciklama').value = data.aciklama || '';
            
            toggleIcraKesintiFields();
            
            const backdrop = document.getElementById('icraBottomSheetBackdrop');
            const sheet = document.getElementById('icraFormArea');
            if (backdrop && sheet) {
                backdrop.classList.remove('hidden');
                setTimeout(() => {
                    backdrop.classList.remove('opacity-0', 'pointer-events-none');
                    backdrop.classList.add('opacity-100');
                    sheet.classList.remove('translate-y-full');
                    sheet.classList.add('translate-y-0');
                }, 10);
            }
        }
    });
}

async function deleteIcra(id) {
    const isConfirmed = await Alert.confirmDelete("Sil", "Bu icra dosyası ve tüm geçmişi silinecektir!");
    if(!isConfirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_icra');
    formData.append('id', id);
    formData.append('personel_id', '<?= $personel_id ?>');
    
    fetch('../views/personel/ajax/kesinti-islemleri.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            Toast.show("Dosya silindi", "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.error || "Silme başarısız", "error");
        }
    });
}

function viewIcraKesintileri(id, dairesi, dosyaNo, toplamBorc) {
    document.getElementById('icraHistoryTitle').innerText = dairesi;
    document.getElementById('icraHistorySubtitle').innerText = dosyaNo;
    const listContainer = document.getElementById('icraHistoryList');
    listContainer.innerHTML = '<div class="flex justify-center p-8"><div class="w-8 h-8 border-2 border-indigo-600/30 border-t-indigo-600 rounded-full animate-spin"></div></div>';
    
    const backdrop = document.getElementById('icraHistoryBottomSheetBackdrop');
    const sheet = document.getElementById('icraHistoryArea');
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
        sheet.classList.add('translate-y-0');
    }, 10);

    fetch('../views/personel/ajax/kesinti-islemleri.php?action=get_icra_kesintileri&icra_id=' + id + '&personel_id=<?= $personel_id ?>')
    .then(res => res.json())
    .then(data => {
        const kesintiler = data.kesintiler || [];
        let html = '';
        let totalKesilen = 0;
        
        if (kesintiler.length === 0) {
            html = '<div class="text-center p-8 text-slate-400 font-bold">Henüz kesinti kaydı bulunamadı.</div>';
        } else {
            kesintiler.forEach(k => {
                const tutar = parseFloat(k.tutar);
                totalKesilen += tutar;
                const date = new Date(k.olusturma_tarihi).toLocaleDateString('tr-TR');
                const badgeClass = k.durum === 'onaylandi' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20' : 'bg-amber-50 text-amber-600 dark:bg-amber-900/20';
                
                html += `
                    <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-3 border border-slate-100 dark:border-slate-800">
                        <div class="flex justify-between items-start mb-1">
                            <span class="text-[11px] font-black text-slate-800 dark:text-white uppercase leading-tight">${k.donem_adi || '-'}</span>
                            <span class="text-[12px] font-black text-indigo-600">${new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2 }).format(tutar)} ₺</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] text-slate-500 font-medium">${k.aciklama || 'Kesinti'}</span>
                            <span class="px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider ${badgeClass}">${k.durum === 'onaylandi' ? 'ONAYLI' : 'BEKLEMEDE'}</span>
                        </div>
                        <div class="mt-1 pt-1 border-t border-slate-200/50 dark:border-slate-700/50">
                            <span class="text-[8px] text-slate-400 font-bold uppercase tracking-widest italic">${date}</span>
                        </div>
                    </div>
                `;
            });
        }
        
        listContainer.innerHTML = html;
        document.getElementById('stat_icra_kesilen').innerText = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2 }).format(totalKesilen) + ' ₺';
        document.getElementById('stat_icra_kalan').innerText = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2 }).format(toplamBorc - totalKesilen) + ' ₺';
    });
}

function closeIcraHistory() {
    const backdrop = document.getElementById('icraHistoryBottomSheetBackdrop');
    const sheet = document.getElementById('icraHistoryArea');
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    setTimeout(() => backdrop.classList.add('hidden'), 300);
}

// Swipe Mantığı (İzin Kartları İçin)
(function() {
    let touchStartX = 0;
    let touchStartY = 0;
    let isMoving = false;

    window.closeAllIzinSwipes = function() {
        document.querySelectorAll('.swipe-content').forEach(el => {
            el.style.transform = 'translateX(0)';
        });
        document.querySelectorAll('.swipe-action-right').forEach(el => {
            el.style.opacity = '0';
            el.classList.add('pointer-events-none');
            el.classList.remove('pointer-events-auto');
        });
    };

    const list = document.getElementById('izinList');
    if (!list) return;

    list.addEventListener('touchstart', e => {
        const container = e.target.closest('.izin-item-container');
        if (!container) {
            window.closeAllIzinSwipes();
            return;
        }
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        isMoving = false;
    }, { passive: true });

    list.addEventListener('touchmove', e => {
        const container = e.target.closest('.izin-item-container');
        if (!container) return;
        const currentX = e.touches[0].clientX;
        const currentY = e.touches[0].clientY;
        const diffX = currentX - touchStartX;
        const diffY = currentY - touchStartY;
        
        if (Math.abs(diffX) > Math.abs(diffY)) {
            isMoving = true;
        }
    }, { passive: true });

    list.addEventListener('touchend', e => {
        const container = e.target.closest('.izin-item-container');
        if (!container) return;
        const touchEndX = e.changedTouches[0].clientX;
        const diffX = touchEndX - touchStartX;
        
        const swipeContent = container.querySelector('.swipe-content');
        const actionRight = container.querySelector('.swipe-action-right');

        if (isMoving && diffX > 60) {
            // Sağa kaydırma (Silme butonunu solda aç)
            window.closeAllIzinSwipes();
            swipeContent.style.transform = 'translateX(70px)';
            if (actionRight) {
                actionRight.style.opacity = '1';
                actionRight.classList.remove('pointer-events-none');
                actionRight.classList.add('pointer-events-auto');
            }
        } else {
            // İptal veya yetersiz kaydırma
            swipeContent.style.transform = 'translateX(0)';
            if (actionRight) {
                actionRight.style.opacity = '0';
                actionRight.classList.add('pointer-events-none');
                actionRight.classList.remove('pointer-events-auto');
            }
        }
    }, { passive: true });
})();

function handleIzinClick(el) {
    // Kaydırma sırasında tıklamayı engelle
    const currentTransform = el.style.transform;
    if (currentTransform && currentTransform !== 'translateX(0px)' && currentTransform !== 'translateX(0)') {
        window.closeAllIzinSwipes();
        return;
    }
    
    try {
        const jsonStr = el.getAttribute('data-izin');
        if (!jsonStr) {
            console.error("Veri bulunamadı (data-izin bos)");
            return;
        }
        
        // Veriyi çözmeden önce bir div içinde HTML decode edelim (entity'lerden kurtulmak için en güvenli yol)
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = jsonStr;
        const decodedJson = tempDiv.textContent || tempDiv.innerText;
        
        const data = JSON.parse(decodedJson);
        console.log("İzin verisi:", data);
        editIzin(data);
    } catch(e) {
        console.error("JSON Parse Hatası:", e, el.getAttribute('data-izin'));
        Toast.show("Bağlantı verisi okunamadı. Lütfen sayfayı yenileyip tekrar deneyiniz.", "error");
    }
}

// Evrak Yönetimi Fonksiyonları
function openEvrakForm() {
    document.getElementById('evrakMobileForm').reset();
    document.getElementById('fileName').innerText = 'Dosya seçilmedi...';
    
    const backdrop = document.getElementById('evrakBottomSheetBackdrop');
    const sheet = document.getElementById('evrakFormArea');
    backdrop.classList.remove('hidden');
    setTimeout(() => {
        backdrop.classList.remove('opacity-0', 'pointer-events-none');
        backdrop.classList.add('opacity-100');
        sheet.classList.remove('translate-y-full');
        sheet.classList.add('translate-y-0');
    }, 10);
}

function closeEvrakForm() {
    const backdrop = document.getElementById('evrakBottomSheetBackdrop');
    const sheet = document.getElementById('evrakFormArea');
    backdrop.classList.add('opacity-0', 'pointer-events-none');
    backdrop.classList.remove('opacity-100');
    sheet.classList.add('translate-y-full');
    setTimeout(() => backdrop.classList.add('hidden'), 300);
}

function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : 'Dosya seçilmedi...';
    document.getElementById('fileName').innerText = fileName;
}

function saveEvrak() {
    const form = document.getElementById('evrakMobileForm');
    const btn = document.getElementById('btnEvrakKaydet');
    const originalContent = btn.innerHTML;
    
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin mx-auto"></div>';
    
    const formData = new FormData(form);
    
    fetch('../views/personel/api/APIevraklar.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message, "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Hata oluştu.", "error");
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    })
    .catch(err => {
        Toast.show("Sunucu hatası.", "error");
        btn.disabled = false;
        btn.innerHTML = originalContent;
    });
}

async function deleteEvrak(id) {
    const isConfirmed = await Alert.confirmDelete("Sil", "Bu evrakı silmek istediğinize emin misiniz?");
    if(!isConfirmed) return;
    
    const formData = new FormData();
    formData.append('action', 'evrak_sil');
    formData.append('id', id);
    
    fetch('../views/personel/api/APIevraklar.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            Toast.show(data.message, "success");
            setTimeout(() => location.reload(), 1000);
        } else {
            Toast.show(data.message || "Hata oluştu.", "error");
        }
    })
    .catch(err => Toast.show("Sunucu hatası.", "error"));
}

function filterIzinlerByYear() {
    const selectedYear = document.getElementById('izinYearFilter').value;
    const containers = document.querySelectorAll('.izin-item-container');
    let visibleCount = 0;

    containers.forEach(container => {
        const content = container.querySelector('.swipe-content');
        const jsonStr = content.getAttribute('data-izin');
        if (jsonStr) {
            try {
                // Decode HTML entities (en güvenli yol)
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = jsonStr;
                const decodedJson = tempDiv.textContent || tempDiv.innerText;
                const data = JSON.parse(decodedJson);
                
                // Başlangıç tarihine göre yıl kontrolü
                const startYear = data.baslangic_tarihi.split('-')[0]; // YYYY-MM-DD varsayımı
                
                if (selectedYear === 'all' || startYear === selectedYear) {
                    container.classList.remove('hidden');
                    visibleCount++;
                } else {
                    container.classList.add('hidden');
                }
            } catch (e) {
                console.error("Year filter error:", e);
            }
        }
    });

    const emptyMsg = document.getElementById('izinEmptyMsg');
    if (emptyMsg) {
        if (visibleCount === 0) {
            emptyMsg.classList.remove('hidden');
        } else {
            emptyMsg.classList.add('hidden');
        }
    }
}

// Select2 Initialization and Filter Trigger
$(document).ready(function() {
    // Flatpickr Initialization
    if($('.flatpickr-date').length > 0) {
        $('.flatpickr-date').flatpickr({
            locale: 'tr',
            dateFormat: "d.m.Y",
            allowInput: true,
            disableMobile: true // Native picker yerine flatpickr kullanılsın
        });
    }

    if($('#izinYearFilter').length > 0) {
        $('#izinYearFilter').select2({
            minimumResultsForSearch: Infinity,
            width: '100px',
            dropdownCssClass: 'select2-small-dropdown'
        });
        
        // İlk açılışta filtrelemeyi çalıştır (seçili gelen yıla göre)
        setTimeout(() => {
            filterIzinlerByYear();
        }, 300);
    }

    // İş Takip Filtreleri
    if ($('#isTakipYear').length > 0) {
        $('#isTakipYear, #isTakipMonth').select2({
            minimumResultsForSearch: Infinity,
            width: '100%',
            dropdownCssClass: 'select2-small-dropdown'
        }).on('change', function() {
            loadPersonelIsTakip();
        });
    }

    // Eğer sayfa yüklendiğinde Puantaj sekmesi aktifse verileri çek
    const activeTab = '<?= $activeTab ?>';
    if (activeTab === 'puantaj') {
        setTimeout(loadPersonelIsTakip, 500);
    }
});

function viewEvrak(path, title, type) {
    // Mobil için en iyi yöntem dosyayı yeni sekmede açmak veya indirmektir
    // Eğer resimse bir modalda gösterilebilir ama şimdilik yeni sekme/doğrudan link daha güvenli
    window.open(path, '_blank');
}
</script>
