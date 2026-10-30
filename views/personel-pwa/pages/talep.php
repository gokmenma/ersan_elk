<?php
/**
 * Personel PWA - Talep Bildirimi Sayfası
 * Talep listesi ve yeni talep bildirimi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <?php pwaPageHeader([
        'eyebrow' => 'TALEP YÖNETİMİ',
        'title' => 'Talepler',
        'subtitle' => 'Taleplerinizi takip edin',
        'actions' => '<button type="button" onclick="openNewTalepModal()" class="pwa-circle-btn pwa-circle-btn--primary" aria-label="Talep Bildir" title="Talep Bildir"><span class="material-symbols-outlined">add_circle</span></button>',
    ]); ?>

    <section class="px-4 pb-3">
        <div class="pwa-hero-dark">
            <div class="pwa-hero-dark__top">
                <div>
                    <span class="pwa-hero-dark__eyebrow">TALEP MERKEZİ</span>
                    <h2 class="pwa-hero-dark__value"><span id="acik-talepler">0</span> açık talep</h2>
                    <p class="pwa-hero-dark__sub">Bildirdiğiniz talepler burada takip edilir</p>
                </div>
                <span class="pwa-hero-dark__icon"><span class="material-symbols-outlined">assignment</span></span>
            </div>
            <div class="pwa-hero-dark__stats">
                <div class="pwa-hero-dark__stat">
                    <span class="material-symbols-outlined">check_circle</span>
                    <strong id="cozulen-talepler">0</strong>
                    <small>Bu Ay Çözülen</small>
                </div>
                <div class="pwa-hero-dark__stat">
                    <span class="material-symbols-outlined">timer</span>
                    <strong id="ort-sure">0</strong>
                    <small>Ort. Süre (Saat)</small>
                </div>
            </div>
        </div>
    </section>

    <div class="px-4 pb-3">
        <div class="pwa-pill-row" role="tablist" aria-label="Talep filtresi">
            <button type="button" role="tab" onclick="changeTalepTab('tum')" class="talep-tab-btn pwa-filter-pill is-active" data-tab="tum">
                <span class="material-symbols-outlined">apps</span>Tümü <em data-talep-sayi="tum">0</em>
            </button>
            <button type="button" role="tab" onclick="changeTalepTab('devam')" class="talep-tab-btn pwa-filter-pill" data-tab="devam">
                <span class="material-symbols-outlined">schedule</span>Devam Eden <em data-talep-sayi="devam">0</em>
            </button>
            <button type="button" role="tab" onclick="changeTalepTab('cozuldu')" class="talep-tab-btn pwa-filter-pill" data-tab="cozuldu">
                <span class="material-symbols-outlined">task_alt</span>Çözüldü <em data-talep-sayi="cozuldu">0</em>
            </button>
        </div>
        <label class="pwa-search-bar">
            <span class="material-symbols-outlined">search</span>
            <input type="search" id="talep-search" placeholder="Referans, başlık veya konum ara…" aria-label="Taleplerde ara" autocomplete="off">
        </label>
    </div>

    <!-- Talep List -->
    <div class="flex-1 px-4 pb-4">
        <div class="flex flex-col gap-3" id="talep-list">
            <!-- Talep items will be loaded here -->
            <div class="shimmer h-20 rounded-xl"></div>
            <div class="shimmer h-20 rounded-xl"></div>
            <div class="shimmer h-20 rounded-xl"></div>
        </div>
    </div>
</div>

<!-- Yeni Talep Bildirimi Modal -->
<div id="talep-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content pwa-sheet" style="display: flex !important; flex-direction: column !important; max-height: min(92dvh, 780px) !important; overflow: hidden !important; padding: 0 !important;">
        <div class="pwa-sheet__handle"></div>
        <div class="pwa-sheet__head">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">edit_note</span></span>
            <div class="flex-1 min-w-0"><h3>Talep Bildirimi</h3></div>
            <button type="button" onclick="closeTalepModal()" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <form id="talep-form" class="flex flex-col flex-1 min-h-0">
            <input type="hidden" name="action" value="createTalepBildirimi">
            <div class="flex-1 overflow-y-auto pwa-sheet__body" style="overscroll-behavior-y: contain;">
                <section class="pwa-sheet-card">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">location_on</span>Konum</h2>
                    <div class="flex flex-col gap-2">
                        <div class="flex gap-2">
                            <input type="text" name="konum" id="konum-input" class="pwa-input flex-1"
                                placeholder="Konum girin veya GPS kullanın" required>
                            <button type="button" onclick="getLocation()" id="location-btn" class="pwa-round-btn" aria-label="GPS ile konum al">
                                <span class="material-symbols-outlined">my_location</span>
                            </button>
                        </div>
                        <div id="location-status" class="hidden text-xs text-center"></div>
                        <input type="hidden" name="latitude" id="lat-input">
                        <input type="hidden" name="longitude" id="lng-input">
                    </div>
                </section>

                <section class="pwa-sheet-card">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">category</span>Talep Türü</h2>
                    <div class="flex flex-wrap gap-2" id="kategori-chips">
                        <button type="button" onclick="selectKategori(this, 'ariza')" class="kategori-chip">🔧 Arıza</button>
                        <button type="button" onclick="selectKategori(this, 'oneri')" class="kategori-chip">💡 Öneri</button>
                        <button type="button" onclick="selectKategori(this, 'sikayet')" class="kategori-chip">⚠️ Şikayet</button>
                        <button type="button" onclick="selectKategori(this, 'istek')" class="kategori-chip">📦 İstek</button>
                        <button type="button" onclick="selectKategori(this, 'diger')" class="kategori-chip">📝 Diğer</button>
                    </div>
                    <input type="hidden" name="kategori" id="kategori-input" required>

                    <h2 class="pwa-card-title" style="margin-top:4px"><span class="material-symbols-outlined">priority_high</span>Öncelik</h2>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="selectOncelik(this, 'dusuk')" class="oncelik-btn">
                            <span class="text-green-500 text-lg">●</span>
                            <span class="block text-xs mt-1">Düşük</span>
                        </button>
                        <button type="button" onclick="selectOncelik(this, 'orta')" class="oncelik-btn">
                            <span class="text-amber-500 text-lg">●</span>
                            <span class="block text-xs mt-1">Orta</span>
                        </button>
                        <button type="button" onclick="selectOncelik(this, 'yuksek')" class="oncelik-btn">
                            <span class="text-red-500 text-lg">●</span>
                            <span class="block text-xs mt-1">Yüksek</span>
                        </button>
                    </div>
                    <input type="hidden" name="oncelik" id="oncelik-input" value="orta">
                </section>

                <section class="pwa-sheet-card">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">notes</span>Açıklama</h2>
                    <textarea name="aciklama" class="pwa-input" placeholder="Talebinizi detaylıca açıklayın..." required></textarea>
                </section>

                <section class="pwa-sheet-card">
                    <h2 class="pwa-card-title"><span class="material-symbols-outlined">add_a_photo</span>Fotoğraf Ekle <small style="font-weight:600;text-transform:none;letter-spacing:0">(Opsiyonel)</small></h2>
                    <div class="pwa-drop" onclick="document.getElementById('foto-input').click()">
                        <span class="material-symbols-outlined">add_a_photo</span>
                        <span>Yüklemek için dokunun</span>
                    </div>
                    <input type="file" id="foto-input" name="foto" accept="image/*" class="hidden" onchange="previewFoto(this)">
                    <div id="foto-preview" class="hidden"></div>
                </section>
            </div>

            <div class="pwa-sheet__footer">
                <button type="button" onclick="closeTalepModal()" class="pwa-btn pwa-btn--ghost">İptal</button>
                <button type="submit" class="pwa-btn pwa-btn--primary" id="talebiGonder">Talebi Gönder</button>
            </div>
        </form>
    </div>
</div>

<!-- Talep Detay Modal -->
<div id="talep-detay-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content pwa-sheet" style="display: flex !important; flex-direction: column !important; max-height: min(92dvh, 780px) !important; overflow: hidden !important; padding: 0 !important;">
        <div class="pwa-sheet__handle"></div>
        <div class="pwa-sheet__head">
            <span class="pwa-sheet__icon"><span class="material-symbols-outlined">assignment</span></span>
            <div class="flex-1 min-w-0"><h3 id="talep-modal-title">Talep Detayı</h3></div>
            <button type="button" onclick="Modal.close('talep-detay-modal')" class="pwa-sheet__close" aria-label="Kapat">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto pwa-sheet__body" style="overscroll-behavior-y: contain;">
            <div id="talep-detay-content">
            </div>
        </div>

        <div class="pwa-sheet__footer" style="grid-template-columns: 1fr;">
            <button type="button" onclick="Modal.close('talep-detay-modal')" class="pwa-btn pwa-btn--ghost">Kapat</button>
        </div>
    </div>
</div>

<style>
    .kategori-chip { padding: 10px 16px; border-radius: 999px; border: 2px solid transparent; background: #f1f4f8; font-size: 13px; font-weight: 700; color: #475569; transition: all .2s ease; }
    .oncelik-btn { padding: 12px 0; border-radius: 18px; border: 2px solid transparent; background: #f1f4f8; font-size: 13px; font-weight: 700; color: #475569; transition: all .2s ease; }
    .dark .kategori-chip, .dark .oncelik-btn { background: rgba(255, 255, 255, .06); color: #cbd5e1; }

    .kategori-chip.active {
        background: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
        color: var(--primary);
    }

    .oncelik-btn.active {
        background: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
    }

    .pwa-round-btn { flex: none; width: 52px; height: 52px; border: 0; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: rgba(var(--primary-rgb), .1); color: var(--primary); }
    .pwa-drop { min-height: 96px; border-radius: 22px; border: 2px dashed rgba(100, 116, 139, .35); background: #f6f8fb; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; font-size: 12px; font-weight: 800; color: #475569; cursor: pointer; }
    .pwa-drop .material-symbols-outlined { font-size: 28px; color: var(--primary); }
    .dark .pwa-drop { background: rgba(255, 255, 255, .04); border-color: rgba(255, 255, 255, .16); color: #cbd5e1; }
</style>

<script>
    let currentTalepTab = 'tum';
    let taleplerData = [];
    let editingTalepId = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadTalepStats();
        loadTalepler();

        // Talep form submit
        document.getElementById('talep-form').addEventListener('submit', async function (e) {
            e.preventDefault();
            await submitTalepBildirimi(this);
        });

        // Default selections
        selectOncelik(document.querySelector('.oncelik-btn:nth-child(2)'), 'orta');
    });

    function openNewTalepModal() {
        setTalepFormMode(null);
        Modal.open('talep-modal');
    }

    function closeTalepModal() {
        setTalepFormMode(null);
        Modal.close('talep-modal');
    }

    function setTalepFormMode(talepId) {
        editingTalepId = talepId ? Number(talepId) : null;
        const submitBtn = document.getElementById('talebiGonder');
        if (submitBtn) {
            submitBtn.textContent = editingTalepId ? 'Güncelle' : 'Talebi Gönder';
        }
    }

    async function loadTalepStats() {
        try {
            const response = await API.request('getTalepStats');
            if (response.success) {
                document.getElementById('acik-talepler').textContent = response.data.acik;
                document.getElementById('cozulen-talepler').textContent = response.data.cozulen;
                document.getElementById('ort-sure').textContent = response.data.ort_sure;
            }
        } catch (error) {
            console.error('Stats load error:', error);
        }
    }

    async function loadTalepler() {
        const container = document.getElementById('talep-list');

        try {
            const response = await API.request('getTalepler');

            if (response.success && response.data.length > 0) {
                taleplerData = response.data;
                renderTalepler();
            } else {
                container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <span class="material-symbols-outlined">assignment</span>
                    </div>
                    <p class="text-slate-600 dark:text-slate-400 font-medium">Talep kaydı yok</p>
                    <p class="text-sm text-slate-500">Yeni talep oluşturabilirsiniz.</p>
                </div>
            `;
            }
        } catch (error) {
            console.error('Talep load error:', error);
            container.innerHTML = '<p class="text-center text-slate-500 py-8">Veriler yüklenemedi</p>';
        }
    }

    function renderTalepler() {
        const container = document.getElementById('talep-list');
        let filtered = taleplerData;

        const sayilar = {
            tum: taleplerData.length,
            devam: taleplerData.filter(a => a.durum !== 'cozuldu').length,
            cozuldu: taleplerData.filter(a => a.durum === 'cozuldu').length
        };
        document.querySelectorAll('[data-talep-sayi]').forEach(el => {
            el.textContent = sayilar[el.getAttribute('data-talep-sayi')];
        });

        if (currentTalepTab === 'devam') {
            filtered = taleplerData.filter(a => a.durum !== 'cozuldu');
        } else if (currentTalepTab === 'cozuldu') {
            filtered = taleplerData.filter(a => a.durum === 'cozuldu');
        }

        const aramaKutusu = document.getElementById('talep-search');
        const terim = aramaKutusu ? aramaKutusu.value.toLocaleLowerCase('tr-TR').trim() : '';
        if (terim) {
            filtered = filtered.filter(a => [a.ref_no, a.baslik, a.konum].join(' ').toLocaleLowerCase('tr-TR').includes(terim));
        }

        if (filtered.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-symbols-outlined">filter_list</span>
                </div>
                <p class="text-slate-600 dark:text-slate-400 font-medium">Kayıt bulunamadı</p>
            </div>
        `;
            return;
        }

        container.innerHTML = filtered.map(talep => `
        <div class="card p-4" onclick="showTalepDetay(${talep.id})">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl ${getKategoriColor(talep.kategori)} flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-xl">${getKategoriIcon(talep.kategori)}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-bold text-primary">#${talep.ref_no}</span>
                                <span class="w-2 h-2 rounded-full ${getOncelikColor(talep.oncelik)}"></span>
                            </div>
                            <p class="font-bold text-sm text-slate-900 dark:text-white mt-1">${talep.baslik}</p>
                            <p class="text-xs text-slate-500 mt-0.5">${talep.konum}</p>
                        </div>
                        ${talep.durum === 'beklemede' ? `
                        <div class="flex flex-col items-end gap-2 flex-shrink-0">
                            <button type="button" onclick="event.stopPropagation(); startEditTalep(${talep.id});"
                                class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center border border-slate-200 dark:border-slate-700 hover:bg-primary/10 hover:text-primary hover:border-primary transition-all">
                                <span class="material-symbols-outlined text-[20px]">edit</span>
                            </button>
                            <button type="button" onclick="event.stopPropagation(); deleteTalep(${talep.id});"
                                class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-900/20 text-red-600 flex items-center justify-center border border-red-200 dark:border-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/30 transition-all">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                        ` : ''}
                    </div>
                    <div class="flex items-center justify-between mt-3">
                        <span class="badge ${getStatusBadge(talep.durum)}">${talep.durum_text}</span>
                        <span class="text-xs text-slate-400">${talep.tarih}</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const aramaKutusu = document.getElementById('talep-search');
        if (aramaKutusu) {
            aramaKutusu.addEventListener('input', () => {
                if (taleplerData.length) renderTalepler();
            });
        }
    });

    function changeTalepTab(tab) {
        currentTalepTab = tab;

        // Update tab buttons
        document.querySelectorAll('.talep-tab-btn').forEach(btn => {
            btn.classList.remove('is-active');
            btn.setAttribute('aria-selected', 'false');
        });

        const activeBtn = document.querySelector(`.talep-tab-btn[data-tab="${tab}"]`);
        activeBtn.classList.add('is-active');
        activeBtn.setAttribute('aria-selected', 'true');

        renderTalepler();
    }

    function selectKategori(btn, value) {
        document.querySelectorAll('.kategori-chip').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('kategori-input').value = value;
    }

    function selectOncelik(btn, value) {
        document.querySelectorAll('.oncelik-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('oncelik-input').value = value;
    }

    function previewFoto(input) {
        const preview = document.getElementById('foto-preview');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const ext = file.name.split('.').pop().toLowerCase();

            if (!['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                Alert.error('Hata', 'Sadece JPG, PNG ve WEBP formatında resim yükleyebilirsiniz.');
                input.value = '';
                preview.classList.add('hidden');
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = `
                <div class="relative inline-block">
                    <img src="${e.target.result}" class="w-24 h-24 object-cover rounded-xl">
                    <button type="button" onclick="removeFoto()" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                </div>
            `;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    }

    function removeFoto() {
        document.getElementById('foto-input').value = '';
        document.getElementById('foto-preview').classList.add('hidden');
    }

    function getKategoriColor(kategori) {
        const colors = {
            'ariza': 'bg-amber-100 dark:bg-amber-900/30 text-amber-600',
            'oneri': 'bg-blue-100 dark:bg-blue-900/30 text-blue-600',
            'sikayet': 'bg-red-100 dark:bg-red-900/30 text-red-600',
            'istek': 'bg-purple-100 dark:bg-purple-900/30 text-purple-600',
            'diger': 'bg-slate-100 dark:bg-slate-900/30 text-slate-600'
        };
        return colors[kategori] || colors['diger'];
    }

    function getKategoriIcon(kategori) {
        const icons = {
            'ariza': 'construction',
            'oneri': 'lightbulb',
            'sikayet': 'report_problem',
            'istek': 'inventory_2',
            'diger': 'more_horiz'
        };
        return icons[kategori] || icons['diger'];
    }

    function getOncelikColor(oncelik) {
        switch (oncelik) {
            case 'yuksek': return 'bg-red-500';
            case 'orta': return 'bg-amber-500';
            case 'dusuk': return 'bg-green-500';
            default: return 'bg-gray-400';
        }
    }

    function getStatusBadge(status) {
        switch (status) {
            case 'cozuldu': return 'badge-success';
            case 'devam': return 'badge-primary';
            case 'beklemede': return 'badge-warning';
            default: return 'badge-gray';
        }
    }

    async function showTalepDetay(id) {
        Modal.open('talep-detay-modal');

        const talep = taleplerData.find(a => Number(a.id) === Number(id));
        if (!talep) return;

        document.getElementById('talep-modal-title').textContent = '#' + talep.ref_no;
        document.getElementById('talep-detay-content').innerHTML = `
        <div class="flex flex-col gap-4">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-12 h-12 rounded-xl ${getKategoriColor(talep.kategori)} flex items-center justify-center">
                    <span class="material-symbols-outlined text-xl">${getKategoriIcon(talep.kategori)}</span>
                </div>
                <div>
                    <p class="font-bold text-slate-900 dark:text-white">${talep.baslik}</p>
                    <span class="badge ${getStatusBadge(talep.durum)}">${talep.durum_text}</span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                    <p class="text-xs text-slate-500 mb-1">Konum</p>
                    ${talep.latitude && talep.longitude ?
                `<a href="https://www.google.com/maps?q=${talep.latitude},${talep.longitude}" target="_blank" class="font-semibold text-sm text-primary hover:underline flex items-center gap-1">
                        ${talep.konum}
                        <span class="material-symbols-outlined text-xs">open_in_new</span>
                    </a>` :
                `<p class="font-semibold text-sm text-slate-900 dark:text-white">${talep.konum}</p>`
            }
                </div>
                <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                    <p class="text-xs text-slate-500 mb-1">Kategori</p>
                    <p class="font-semibold text-sm text-slate-900 dark:text-white">${talep.kategori_text}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 py-3 border-t border-slate-100 dark:border-slate-800">
                <span class="w-3 h-3 rounded-full ${getOncelikColor(talep.oncelik)}"></span>
                <span class="text-slate-600 dark:text-slate-400">Öncelik:</span>
                <span class="font-bold text-slate-900 dark:text-white">${talep.oncelik_text}</span>
            </div>
            
            <div>
                <p class="text-xs text-slate-500 mb-1">Açıklama</p>
                <p class="text-sm text-slate-700 dark:text-slate-300">${talep.aciklama}</p>
            </div>
            
            ${talep.cozum_aciklama ? `
            <div class="mt-3 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl">
                <p class="text-xs text-green-600 dark:text-green-400 font-semibold mb-1">Sonuç Açıklaması</p>
                <p class="text-sm text-green-700 dark:text-green-300">${talep.cozum_aciklama}</p>
            </div>
            ` : ''}

            ${talep.foto ? `
            <div>
                <p class="text-xs text-slate-500 mb-2">Fotoğraf</p>
                <div class="relative">
                    <img src="https://ersantr.com/${talep.foto}" 
                         class="w-full max-w-[250px] rounded-xl cursor-pointer bg-slate-100 border border-slate-200" 
                         onclick="window.open('https://ersantr.com/${talep.foto}', '_blank')"
                         onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22200%22%20height%3D%22150%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%3E%3Crect%20width%3D%22100%25%22%20height%3D%22100%25%22%20fill%3D%22%23f1f5f9%22%2F%3E%3Ctext%20x%3D%2250%25%22%20y%3D%2250%25%22%20font-family%3D%22sans-serif%22%20font-size%3D%2212%22%20fill%3D%22%2394a3b8%22%20text-anchor%3D%22middle%22%20dy%3D%22.3em%22%3EResim%20Yüklenemedi%3C%2Ftext%3E%3C%2Fsvg%3E';">
                </div>
            </div>
            ` : ''}

            <div class="mt-2">
                <p class="text-xs text-slate-500 mb-3">Süreç</p>
                <div class="flex flex-col gap-3">
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-white text-sm">add</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">Oluşturuldu</p>
                            <p class="text-xs text-slate-500">${talep.tarih}</p>
                        </div>
                    </div>
                    ${talep.durum !== 'beklemede' ? `
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-primary text-sm">pending</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">İşleme Alındı</p>
                            <p class="text-xs text-slate-500">Talep inceleniyor</p>
                        </div>
                    </div>
                    ` : ''}
                    ${talep.durum === 'cozuldu' ? `
                    <div class="flex items-start gap-3">
                        <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-white text-sm">check</span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">Çözüldü</p>
                            <p class="text-xs text-slate-500">${talep.cozum_tarihi || ''}</p>
                        </div>
                    </div>
                    ` : ''}
                </div>
            </div>
        </div>
        `;
    }

    function startEditTalep(id) {
        const talep = taleplerData.find(a => Number(a.id) === Number(id));
        if (!talep) return;

        setTalepFormMode(id);

        const form = document.getElementById('talep-form');
        if (!form) return;

        const konumInput = document.getElementById('konum-input');
        const latInput = document.getElementById('lat-input');
        const lngInput = document.getElementById('lng-input');
        const aciklamaInput = form.querySelector('textarea[name="aciklama"]');

        if (konumInput) konumInput.value = talep.konum || '';
        if (latInput) latInput.value = talep.latitude || '';
        if (lngInput) lngInput.value = talep.longitude || '';
        if (aciklamaInput) aciklamaInput.value = talep.aciklama || '';

        document.getElementById('location-status').classList.add('hidden');
        document.getElementById('location-btn').innerHTML = '<span class="material-symbols-outlined">my_location</span>';
        document.getElementById('location-btn').classList.remove('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');
        removeFoto();

        const kategori = talep.kategori || '';
        const kategoriBtn = Array.from(document.querySelectorAll('.kategori-chip')).find((btn) => {
            const onClick = btn.getAttribute('onclick') || '';
            return onClick.includes(`'${kategori}'`);
        });
        if (kategoriBtn) selectKategori(kategoriBtn, kategori);

        const oncelik = talep.oncelik || 'orta';
        const oncelikBtn = Array.from(document.querySelectorAll('.oncelik-btn')).find((btn) => {
            const onClick = btn.getAttribute('onclick') || '';
            return onClick.includes(`'${oncelik}'`);
        });
        if (oncelikBtn) selectOncelik(oncelikBtn, oncelik);

        Modal.close('talep-detay-modal');
        Modal.open('talep-modal');
    }

    async function deleteTalep(id) {
        const talep = taleplerData.find(a => Number(a.id) === Number(id));
        if (!talep) return;

        try {
            const isConfirmed = await Alert.confirmDelete(
                'Silmek istediğinize emin misiniz?',
                `#${talep.ref_no} numaralı talep silinecek.`,
                'Evet, Sil',
                'Vazgeç'
            );

            if (!isConfirmed) return;

            const response = await API.request('deleteTalepBildirimi', { id: Number(id) });
            if (!response.success) {
                throw new Error(response.message || response.error || 'Silme işlemi başarısız');
            }

            Modal.close('talep-detay-modal');
            await Alert.success('Silindi', response.message || 'Talep silindi.');

            await loadTalepler();
            await loadTalepStats();
        } catch (error) {
            console.error('Delete error:', error);
            Alert.error('Hata', error.message || 'Silme işlemi sırasında bir sorun oluştu.');
        }
    }

    function getLocation() {
        const btn = document.getElementById('location-btn');
        const status = document.getElementById('location-status');
        const input = document.getElementById('konum-input');
        const latInput = document.getElementById('lat-input');
        const lngInput = document.getElementById('lng-input');

        if (!navigator.geolocation) {
            status.textContent = 'Tarayıcınız konum servisini desteklemiyor';
            status.classList.remove('hidden', 'text-green-500');
            status.classList.add('text-red-500');
            return;
        }

        btn.innerHTML = '<span class="material-symbols-outlined animate-spin">refresh</span>';
        status.textContent = 'Konum alınıyor...';
        status.classList.remove('hidden', 'text-red-500', 'text-green-500');
        status.classList.add('text-slate-500');

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                latInput.value = lat;
                lngInput.value = lng;

                // Google Maps linki oluştur
                const mapsLink = `https://www.google.com/maps?q=${lat},${lng}`;
                input.value = `Konum: ${lat.toFixed(6)}, ${lng.toFixed(6)}`;

                btn.innerHTML = '<span class="material-symbols-outlined text-green-500">my_location</span>';
                btn.classList.add('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');

                status.textContent = 'Konum başarıyla alındı';
                status.classList.remove('text-slate-500', 'text-red-500');
                status.classList.add('text-green-500');
            },
            (error) => {
                let msg = 'Konum alınamadı';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        msg = 'Konum izni reddedildi';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        msg = 'Konum bilgisi kullanılamıyor';
                        break;
                    case error.TIMEOUT:
                        msg = 'Konum isteği zaman aşımına uğradı';
                        break;
                }

                status.textContent = msg;
                status.classList.remove('text-slate-500', 'text-green-500');
                status.classList.add('text-red-500');
                btn.innerHTML = '<span class="material-symbols-outlined text-red-500">location_off</span>';
            }
        );
    }

    async function submitTalepBildirimi(form) {
        const formData = new FormData(form);
        let $btn = document.getElementById("talebiGonder");
        let $originalHtml = $btn.innerHTML;

        try {
            if (!formData.get('kategori')) {
                Alert.error('Hata', 'Lütfen bir kategori seçin.');
                return;
            }

            $btn.innerHTML = '<span class="material-symbols-outlined animate-spin">refresh</span>';
            $btn.disabled = true;

            const payload = Object.fromEntries(formData);
            delete payload.action;

            const action = editingTalepId ? 'updateTalepBildirimi' : 'createTalepBildirimi';
            const openedTalepId = editingTalepId ? Number(editingTalepId) : null;
            const response = await API.request(action, {
                ...payload,
                ...(editingTalepId ? { id: Number(editingTalepId) } : {})
            });
            const createdTalepId = response?.data?.id ? Number(response.data.id) : null;

            $btn.innerHTML = $originalHtml;
            $btn.disabled = false;
            if (response.success) {
                closeTalepModal();
                form.reset();

                // Reset UI elements
                document.getElementById('location-status').classList.add('hidden');
                document.getElementById('location-btn').innerHTML = '<span class="material-symbols-outlined">my_location</span>';
                document.getElementById('location-btn').classList.remove('border-green-500', 'bg-green-50', 'dark:bg-green-900/20');
                document.getElementById('foto-preview').classList.add('hidden');
                selectOncelik(document.querySelector('.oncelik-btn:nth-child(2)'), 'orta');

                await Alert.success('Başarılı', response.message || 'Talebiniz başarıyla oluşturuldu.');

                loadTalepStats();
                await loadTalepler();
                const detailId = createdTalepId || openedTalepId;
                if (detailId) {
                    showTalepDetay(detailId);
                }
            } else {
                throw new Error(response.error || 'Bir hata oluştu');
            }
        } catch (error) {
            console.error('Submit error:', error);
            Alert.error('Hata', error.message || 'Talep oluşturulurken bir sorun oluştu.');
        }
    }
</script>