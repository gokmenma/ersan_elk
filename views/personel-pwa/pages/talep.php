<?php
/**
 * Personel PWA - Talep Bildirimi Sayfası
 * Talep listesi ve yeni talep bildirimi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header
        class="bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 px-4 py-4 sticky top-0 z-30">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-slate-900 dark:text-white">Talepler</h1>
                <p class="text-sm text-slate-500">Taleplerinizi takip edin</p>
            </div>
            <button onclick="openNewTalepModal()" class="btn-primary flex items-center gap-2 px-4 py-2.5 text-sm">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span>Bildir</span>
            </button>
        </div>
    </header>

    <!-- Stats Summary -->
    <section class="px-4 py-4 bg-slate-50 dark:bg-background-dark">
        <div class="grid grid-cols-3 gap-3">
            <div class="card p-3 text-center">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-primary text-lg">pending_actions</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="acik-talepler">0</p>
                <p class="text-[10px] text-slate-500">Açık Talep</p>
            </div>
            <div class="card p-3 text-center">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-green-500 text-lg">check_circle</span>
                </div>
                <p class="text-xl font-bold text-slate-900 dark:text-white" id="cozulen-talepler">0</p>
                <p class="text-[10px] text-slate-500">Bu Ay Çözülen</p>
            </div>
            <div class="card p-3 text-center bg-primary/5 border-primary/20">
                <div class="flex items-center justify-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-primary text-lg">timer</span>
                </div>
                <p class="text-xl font-bold text-primary" id="ort-sure">0</p>
                <p class="text-[10px] text-primary">Ort. Süre (Saat)</p>
            </div>
        </div>
    </section>

    <!-- Tab Navigation -->
    <div
        class="px-4 py-2 bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 sticky top-[73px] z-20">
        <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
            <button onclick="changeTalepTab('tum')"
                class="talep-tab-btn active flex-1 py-2 text-sm font-semibold rounded-md" data-tab="tum">
                Tümü
            </button>
            <button onclick="changeTalepTab('devam')"
                class="talep-tab-btn flex-1 py-2 text-sm font-medium rounded-md text-slate-500" data-tab="devam">
                Devam Eden
            </button>
            <button onclick="changeTalepTab('cozuldu')"
                class="talep-tab-btn flex-1 py-2 text-sm font-medium rounded-md text-slate-500" data-tab="cozuldu">
                Çözüldü
            </button>
        </div>
    </div>

    <!-- Talep List -->
    <div class="flex-1 px-4 py-4">
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
    <div class="modal-content"
        style="display: flex !important; flex-direction: column !important; max-height: 85vh !important; overflow: hidden !important; padding: 0 !important;">
        <!-- Fixed Header -->
        <div class="px-6 pt-3 pb-2 flex-shrink-0 bg-white dark:bg-card-dark z-10 border-b border-transparent">
            <div class="modal-handle mb-4"></div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-primary text-2xl">edit_note</span>
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Talep Bildirimi</h3>
                </div>
                <button onclick="closeTalepModal()"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600">close</span>
                </button>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 pt-4 overflow-y-auto flex-1 pb-10" style="overscroll-behavior-y: contain;">
            <form id="talep-form" class="flex flex-col gap-4">
                <input type="hidden" name="action" value="createTalepBildirimi">
                <div>
                    <label class="form-label">Konum</label>
                    <div class="flex flex-col gap-2">
                        <div class="flex gap-2">
                            <input type="text" name="konum" id="konum-input" class="form-input flex-1"
                                placeholder="Konum girin veya GPS kullanın" required>
                            <button type="button" onclick="getLocation()" id="location-btn"
                                class="flex items-center justify-center w-12 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-primary/10 hover:text-primary hover:border-primary transition-all">
                                <span class="material-symbols-outlined">my_location</span>
                            </button>
                        </div>
                        <div id="location-status" class="hidden text-xs text-center"></div>
                        <input type="hidden" name="latitude" id="lat-input">
                        <input type="hidden" name="longitude" id="lng-input">
                    </div>
                </div>

                <div>
                    <label class="form-label">Talep Türü</label>
                    <div class="flex flex-wrap gap-2" id="kategori-chips">
                        <button type="button" onclick="selectKategori(this, 'ariza')"
                            class="kategori-chip px-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            🔧 Arıza
                        </button>
                        <button type="button" onclick="selectKategori(this, 'oneri')"
                            class="kategori-chip px-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            💡 Öneri
                        </button>
                        <button type="button" onclick="selectKategori(this, 'sikayet')"
                            class="kategori-chip px-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            ⚠️ Şikayet
                        </button>
                        <button type="button" onclick="selectKategori(this, 'istek')"
                            class="kategori-chip px-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            📦 İstek
                        </button>
                        <button type="button" onclick="selectKategori(this, 'diger')"
                            class="kategori-chip px-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            📝 Diğer
                        </button>
                    </div>
                    <input type="hidden" name="kategori" id="kategori-input" required>
                </div>

                <div>
                    <label class="form-label">Öncelik</label>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" onclick="selectOncelik(this, 'dusuk')"
                            class="oncelik-btn py-3 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            <span class="text-green-500 text-lg">●</span>
                            <span class="block text-xs mt-1">Düşük</span>
                        </button>
                        <button type="button" onclick="selectOncelik(this, 'orta')"
                            class="oncelik-btn py-3 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            <span class="text-amber-500 text-lg">●</span>
                            <span class="block text-xs mt-1">Orta</span>
                        </button>
                        <button type="button" onclick="selectOncelik(this, 'yuksek')"
                            class="oncelik-btn py-3 rounded-xl border border-slate-200 dark:border-slate-700 text-sm font-medium text-slate-600 dark:text-slate-400 transition-all">
                            <span class="text-red-500 text-lg">●</span>
                            <span class="block text-xs mt-1">Yüksek</span>
                        </button>
                    </div>
                    <input type="hidden" name="oncelik" id="oncelik-input" value="orta">
                </div>

                <div>
                    <label class="form-label">Açıklama</label>
                    <textarea name="aciklama" class="form-input min-h-[100px]"
                        placeholder="Talebinizi detaylıca açıklayın..." required></textarea>
                </div>

                <div>
                    <label class="form-label">Fotoğraf Ekle (Opsiyonel)</label>
                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-6 flex flex-col items-center justify-center gap-2 cursor-pointer hover:border-primary transition-colors"
                        onclick="document.getElementById('foto-input').click()">
                        <span class="material-symbols-outlined text-3xl text-slate-400">add_a_photo</span>
                        <p class="text-xs text-slate-500 text-center">Yüklemek için tıklayın</p>
                    </div>
                    <input type="file" id="foto-input" name="foto" accept="image/*" class="hidden"
                        onchange="previewFoto(this)">
                    <div id="foto-preview" class="mt-2 hidden"></div>
                </div>

                <div class="flex gap-3 mt-2">
                    <button type="button" onclick="closeTalepModal()"
                        class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                        İptal
                    </button>
                    <button type="submit" class="flex-1 btn-primary py-3" id="talebiGonder">
                        Talebi Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Talep Detay Modal -->
<div id="talep-detay-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content"
        style="display: flex !important; flex-direction: column !important; max-height: 85vh !important; overflow: hidden !important; padding: 0 !important;">
        <!-- Fixed Header -->
        <div class="px-6 pt-3 pb-2 flex-shrink-0 bg-white dark:bg-card-dark z-10 border-b border-transparent">
            <div class="modal-handle mb-4"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="talep-modal-title">Talep Detayı</h3>
                <button onclick="Modal.close('talep-detay-modal')"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600">close</span>
                </button>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 pt-4 overflow-y-auto flex-1 pb-10" style="overscroll-behavior-y: contain;">
            <div id="talep-detay-content">
                <!-- Content will be loaded dynamically -->
            </div>

            <button onclick="Modal.close('talep-detay-modal')"
                class="w-full mt-6 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>
</div>

<style>
    .talep-tab-btn.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .dark .talep-tab-btn.active {
        background: #1a2130;
    }

    .kategori-chip.active {
        background: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
        color: var(--primary);
    }

    .oncelik-btn.active {
        background: rgba(var(--primary-rgb), 0.1);
        border-color: var(--primary);
    }
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

        if (currentTalepTab === 'devam') {
            filtered = taleplerData.filter(a => a.durum !== 'cozuldu');
        } else if (currentTalepTab === 'cozuldu') {
            filtered = taleplerData.filter(a => a.durum === 'cozuldu');
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

    function changeTalepTab(tab) {
        currentTalepTab = tab;

        // Update tab buttons
        document.querySelectorAll('.talep-tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('text-slate-500');
        });

        const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
        activeBtn.classList.add('active');
        activeBtn.classList.remove('text-slate-500');

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