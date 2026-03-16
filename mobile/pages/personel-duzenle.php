<?php
use App\Model\PersonelModel;
use App\Helper\Security;

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
?>

<div class="bg-white dark:bg-card-dark min-h-screen flex flex-col relative pb-28">
    
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
            <?php if (!empty($personel->resim_yolu) && file_exists($personel->resim_yolu)): ?>
                <img src="../<?= htmlspecialchars($personel->resim_yolu) ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <?= mb_strtoupper(mb_substr($personel->adi_soyadi ?? 'P', 0, 1, 'UTF-8')) ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </header>
    
    <?php if($isEdit): ?>
    <!-- Yatay Kaydırılabilir Sekmeler (Mobil Uyumlu) -->
    <div class="px-2 py-2 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 shrink-0 sticky top-[61px] z-30 shadow-sm overflow-x-auto select-none no-scrollbar flex items-center gap-2">
        <?php
        $pTabs = [
            'genel' => ['icon' => 'badge', 'label' => 'Genel Bilgiler'],
            'izinler' => ['icon' => 'event', 'label' => 'İzinler'],
            'zimmetler' => ['icon' => 'devices', 'label' => 'Zimmetler'],
            'finansal' => ['icon' => 'account_balance_wallet', 'label' => 'Maaş & Finansal'],
            'evraklar' => ['icon' => 'folder_open', 'label' => 'Evraklar'],
            'puantaj' => ['icon' => 'more_time', 'label' => 'İş Takip'],
        ];
        
        foreach($pTabs as $tKey => $tData):
            $isActive = $activeTab === $tKey;
            $btnClass = $isActive 
                ? 'bg-indigo-600 text-white shadow-md font-bold' 
                : 'bg-white dark:bg-card-dark text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-semibold';
        ?>
        <a href="?p=personel-duzenle&id=<?= urlencode($enc_id) ?>&tab=<?= $tKey ?>" class="flex items-center gap-1.5 px-3 py-1.5 rounded-full whitespace-nowrap text-[11px] transition-all <?= $btnClass ?>">
            <span class="material-symbols-outlined text-[14px]"><?= $tData['icon'] ?></span>
            <?= $tData['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>
    <style>
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    <?php endif; ?>

    <div class="p-4 flex-1 overflow-y-auto">
        <?php if (!isset($_GET['tab']) || $_GET['tab'] === 'genel'): ?>
        <form id="personelMobileForm" onsubmit="submitPersonelForm(event)">
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
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
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

                <!-- Çalışma Bilgileri Bölümü -->
                <div>
                    <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                        <span class="material-symbols-outlined text-[16px]">work</span> Çalışma Bilgileri
                    </h4>
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Durum</label>
                                <select name="aktif_mi" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                                    <option value="1" <?= ($personel->aktif_mi ?? 1) == 1 ? 'selected' : '' ?>>Aktif Çalışan</option>
                                    <option value="0" <?= ($personel->aktif_mi ?? 1) == 0 ? 'selected' : '' ?>>Ayrıldı (Pasif)</option>
                                </select>
                            </div>
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
                                       class="w-full px-3 py-2.5 bg-slate-50 dark:bg-background-dark border border-slate-200 dark:border-slate-700 rounded-xl focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/20 text-[13px] font-semibold text-slate-800 dark:text-white">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Görev / Rol</label>
                            <input type="text" name="gorev_gosterimi_mobile" value="<?= htmlspecialchars($personel->gorev ?? '') ?>" disabled
                                   class="w-full px-3 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-[13px] font-semibold text-slate-500 cursor-not-allowed">
                            <p class="text-[9px] text-slate-400 mt-1">Görev ve departman atamaları masaüstü sürümden yönetilmektedir.</p>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Sticky Alt Buton -->
            <div class="fixed bottom-[60px] left-0 right-0 px-4 py-3 bg-white/90 dark:bg-card-dark/90 backdrop-blur-md border-t border-slate-100 dark:border-slate-800 z-40 safe-area-bottom">
                <button type="submit" id="saveBtn" class="w-full py-3.5 bg-indigo-600 text-white rounded-xl font-bold flex items-center justify-center gap-2 active:scale-95 transition-transform shadow-lg shadow-indigo-600/30 text-[15px]">
                    <span class="material-symbols-outlined text-[20px]">task_alt</span> <?= $isEdit ? 'Değişiklikleri Kaydet' : 'Personeli Ekle' ?>
                </button>
            </div>
            
        </form>
        <?php elseif ($activeTab === 'izinler'): 
            // Fetch Izinler
            $stmt = $PersonelModel->getDb()->prepare("
                SELECT pi.*, t.tur_adi as izin_tipi_adi 
                FROM personel_izinleri pi
                LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
                WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL 
                ORDER BY pi.baslangic_tarihi DESC LIMIT 10
            ");
            $stmt->execute([$personel_id]);
            $izinler = $stmt->fetchAll(PDO::FETCH_OBJ);
        ?>
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                <span class="material-symbols-outlined text-[16px]">event</span> Son İzin Kayıtları
            </h4>
            
            <?php if(empty($izinler)): ?>
                <div class="text-center py-6 text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800">
                    <span class="material-symbols-outlined text-3xl mb-2 opacity-50">event_busy</span>
                    <p class="text-xs font-semibold">Bu personele ait izin/rapor kaydı bulunamadı.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach($izinler as $izin): 
                        $statusClass = $izin->onay_durumu === 'Onaylandı' ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30' : ($izin->onay_durumu === 'Reddedildi' ? 'text-rose-600 bg-rose-50 dark:bg-rose-900/30' : 'text-amber-600 bg-amber-50 dark:bg-amber-900/30');
                    ?>
                    <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl p-3 shadow-sm">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h5 class="font-bold text-sm text-slate-800 dark:text-white"><?= htmlspecialchars($izin->izin_tipi_adi ?? 'İzin') ?></h5>
                                <p class="text-[10px] text-slate-500"><?= date('d.m.Y', strtotime($izin->baslangic_tarihi)) ?> - <?= date('d.m.Y', strtotime($izin->bitis_tarihi)) ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $statusClass ?>"><?= htmlspecialchars($izin->onay_durumu ?? 'Beklemede') ?></span>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 border-t border-slate-100 dark:border-slate-800 pt-2"><span class="font-semibold text-slate-400 uppercase text-[9px]">Açıklama:</span> <?= htmlspecialchars($izin->aciklama ?: '-') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <a href="?p=izin" class="block mt-4 text-center py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold active:scale-95 transition-transform">
                Tüm İzinleri Yönet
            </a>

        <?php elseif ($activeTab === 'zimmetler'): 
            // Fetch Zimmetler
            $stmt = $PersonelModel->getDb()->prepare("
                SELECT z.*, d.demirbas_adi, k.tur_adi as kategori_adi 
                FROM demirbas_zimmet z
                LEFT JOIN demirbas d ON z.demirbas_id = d.id
                LEFT JOIN tanimlamalar k ON d.kategori_id = k.id AND k.grup = 'demirbas_kategorisi'
                WHERE z.personel_id = ? AND z.durum = 'teslim' AND z.silinme_tarihi IS NULL
                ORDER BY z.teslim_tarihi DESC LIMIT 10
            ");
            $stmt->execute([$personel_id]);
            $zimmetler = $stmt->fetchAll(PDO::FETCH_OBJ);
        ?>
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

        <?php elseif ($activeTab === 'finansal'): ?>
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-1.5 border-b border-slate-100 dark:border-slate-800 pb-2">
                <span class="material-symbols-outlined text-[16px]">account_balance_wallet</span> Finansal Özet
            </h4>
            
            <div class="bg-white dark:bg-card-dark border border-slate-100 dark:border-slate-800 rounded-xl p-4 shadow-sm space-y-3">
                <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-xs font-bold text-slate-500">Maaş Durumu</span>
                    <span class="text-sm font-black text-slate-800 dark:text-white"><?= htmlspecialchars($personel->maas_durumu ?? 'Belirtilmemiş') ?></span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-xs font-bold text-slate-500">Maaş Tutarı</span>
                    <span class="text-sm font-black text-emerald-600"><?= number_format($personel->maas_tutari ?? 0, 2, ',', '.') ?> TL</span>
                </div>
                <div class="flex justify-between items-center py-2">
                    <span class="text-xs font-bold text-slate-500">Günlük Ücret</span>
                    <span class="text-sm font-black text-indigo-600"><?= number_format($personel->gunluk_ucret ?? 0, 2, ',', '.') ?> TL</span>
                </div>
            </div>
            
            <p class="text-[10px] text-slate-400 mt-3 text-center">Detaylı finansal işlemler ve bordro için masaüstü sürümü ziyaret edin.</p>

        <?php else: ?>
        <div class="flex flex-col items-center justify-center p-8 text-center bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800 mt-4">
            <div class="w-16 h-16 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-slate-400 mb-3">
                <span class="material-symbols-outlined text-3xl">construction</span>
            </div>
            <h4 class="font-bold text-slate-800 dark:text-white text-sm mb-1">Yakında Eklenecek</h4>
            <p class="text-[11px] text-slate-500 font-medium">Bu sekmenin mobil görünümü henüz tam olarak tasarlanmadı. İhtiyaç halinde masaüstü sürümünü kullanın.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function submitPersonelForm(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('saveBtn');
    const defaultBtnHtml = btn.innerHTML;
    const formData = new FormData(form);
    
    btn.disabled = true;
    btn.innerHTML = '<div class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>';
    
    fetch('../views/personel/api.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success' || data.status === 'success_alert') {
            alert("Kayıt başarıyla tamamlandı!");
            window.location.href = '?p=personel';
        } else {
            alert("Hata: " + (data.message || "Bir hata oluştu."));
            btn.disabled = false;
            btn.innerHTML = defaultBtnHtml;
        }
    })
    .catch(err => {
        alert("Sunucu ile bağlantı kurulamadı.");
        btn.disabled = false;
        btn.innerHTML = defaultBtnHtml;
    });
}
</script>
