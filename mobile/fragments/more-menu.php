    <!-- Daha Fazla Bottom Sheet -->
    <div id="more-menu-sheet" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-[32px] z-[100] transform translate-y-full transition-transform duration-500 shadow-2xl safe-area-bottom max-h-[70vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0 cursor-pointer" onclick="closeMoreMenu()">
            <div class="w-12 h-1 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
        </div>
        
        <div class="flex-1 overflow-y-auto px-5 py-4 no-scrollbar">
            <!-- Profil Bilgileri (Üstte Sabit gibi) -->
            <a href="?p=profil" class="flex items-center gap-3 p-3 rounded-2xl bg-primary/5 border border-primary/10 mb-4 active:bg-primary/10 transition-colors">
                <div class="w-11 h-11 rounded-full bg-primary/20 flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-2xl">person</span>
                </div>
                <div class="flex-1">
                    <p class="text-[10px] text-primary/70 font-black uppercase tracking-widest">KULLANICI PROFİLİ</p>
                    <p class="text-sm font-black text-slate-800 dark:text-white"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı') ?></p>
                </div>
                <span class="material-symbols-outlined text-primary/40">chevron_right</span>
            </a>

            <!-- Menü Listesi -->
            <div class="space-y-1.5">
                <?php
                $more_menu_items = [
                    ['id' => 'kasa',     'label' => 'Kasa Yönetimi',    'icon' => 'account_balance', 'color' => 'amber',  'link' => '?p=kasa'],
                    ['id' => 'raporlar', 'label' => 'İstatistikler',    'icon' => 'bar_chart',       'color' => 'purple', 'link' => '?p=raporlar'],
                    ['id' => 'gorevler', 'label' => 'Görev Takibi',     'icon' => 'check_circle',    'color' => 'green',  'link' => '?p=gorevler'],
                    ['id' => 'talepler', 'label' => 'Talep Yönetimi',   'icon' => 'assignment',      'color' => 'rose',   'link' => '?p=talepler'],
                    ['id' => 'evrak',    'label' => 'Evrak & Belgeler', 'icon' => 'mail',            'color' => 'sky',    'link' => '?p=evrak-takip'],
                    ['id' => 'nobet',    'label' => 'Nöbet İşlemleri',  'icon' => 'calendar_month',  'color' => 'pink',   'link' => '?p=nobet'],
                    ['id' => 'personel', 'label' => 'Personel Listesi', 'icon' => 'badge',           'color' => 'blue',   'link' => '?p=personeller'],
                    ['id' => 'ayarlar',  'label' => 'Sistem Ayarları',  'icon' => 'settings',        'color' => 'slate',  'link' => '?p=ayarlar'],
                ];

                foreach ($more_menu_items as $item): ?>
                    <a href="<?= $item['link'] ?>" class="flex items-center gap-4 p-3 rounded-2xl hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-all border border-transparent hover:border-slate-100">
                        <div class="w-10 h-10 rounded-xl bg-<?= $item['color'] ?>-100 dark:bg-<?= $item['color'] ?>-900/30 flex items-center justify-center text-<?= $item['color'] ?>-600">
                            <span class="material-symbols-outlined text-[20px]"><?= $item['icon'] ?></span>
                        </div>
                        <span class="text-[13px] font-bold text-slate-700 dark:text-slate-300"><?= $item['label'] ?></span>
                        <span class="material-symbols-outlined ml-auto text-slate-300 text-[18px]">chevron_right</span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Alt Menü -->
            <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2 pb-6">
                <a href="?force_desktop=1&p=home" class="flex items-center gap-4 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 text-slate-500">
                    <span class="material-symbols-outlined text-[20px]">desktop_windows</span>
                    <span class="text-[12px] font-bold">Masaüstü Görünümü</span>
                </a>
                <a href="../logout.php" class="flex items-center gap-4 p-3 rounded-2xl bg-red-50 text-red-600 dark:bg-red-900/20">
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                    <span class="text-[12px] font-bold">Güvenli Çıkış</span>
                </a>
            </div>
        </div>
    </div>
