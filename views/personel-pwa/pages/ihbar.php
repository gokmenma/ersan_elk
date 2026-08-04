<?php
/**
 * Personel PWA - İhbar Sayfası
 * Kaçak Su ihbarı bildirimi ve takibi
 */
?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="px-4 pt-5 pb-6 sticky top-0 z-30 shadow-lg"
        style="background: linear-gradient(135deg, #dc2626 0%, #7f1d1d 100%); border-radius: 0 0 24px 24px;">
        <div class="flex items-center justify-between gap-3">
            <div>
                <span class="bg-white/20 backdrop-blur-md border border-white/10 text-white rounded-lg px-3 py-1 text-[11px] font-semibold tracking-wide shadow-sm">KAÇAK KONTROL</span>
                <h1 class="text-white text-xl font-black tracking-tight mt-2">İhbarlar</h1>
                <p class="text-red-100/80 text-xs font-medium mt-0.5">Kaçak Su ihbarlarını takip edin</p>
            </div>
            <button onclick="openYeniIhbarModal()"
                class="flex-shrink-0 flex items-center gap-1.5 px-4 py-2.5 text-sm font-bold rounded-2xl bg-white text-red-700 shadow-lg active:scale-95 transition-transform">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                <span>İhbar Yap</span>
            </button>
        </div>
    </header>

    <!-- Tab Navigation -->
    <div
        class="px-4 py-2 bg-white dark:bg-card-dark border-b border-slate-200 dark:border-slate-800 sticky top-[108px] z-20">
        <div class="flex gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
            <button onclick="changeIhbarTab('gelen')"
                class="ihbar-tab-btn active flex-1 py-2 text-sm font-semibold rounded-md" data-tab="gelen">
                Gelen İhbarlar
            </button>
            <button onclick="changeIhbarTab('bildirdiklerim')"
                class="ihbar-tab-btn flex-1 py-2 text-sm font-medium rounded-md text-slate-500"
                data-tab="bildirdiklerim">
                Bildirdiklerim
            </button>
        </div>
    </div>

    <!-- İhbar List -->
    <div class="flex-1 px-4 py-4">
        <div class="flex flex-col gap-3" id="ihbar-list">
            <div class="shimmer h-20 rounded-xl"></div>
            <div class="shimmer h-20 rounded-xl"></div>
            <div class="shimmer h-20 rounded-xl"></div>
        </div>
    </div>
</div>

<!-- İhbar Detay Modal -->
<div id="ihbar-detay-modal" class="modal-overlay" style="z-index: 200;">
    <div class="modal-content"
        style="display: flex !important; flex-direction: column !important; max-height: 85vh !important; overflow: hidden !important; padding: 0 !important;">
        <div class="px-6 pt-3 pb-2 flex-shrink-0 bg-white dark:bg-card-dark z-10 border-b border-transparent">
            <div class="modal-handle mb-4"></div>
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">İhbar Detayı</h3>
                <button onclick="Modal.close('ihbar-detay-modal')"
                    class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600">close</span>
                </button>
            </div>
        </div>
        <div class="p-6 pt-4 overflow-y-auto flex-1 pb-10" style="overscroll-behavior-y: contain;">
            <div id="ihbar-detay-content"></div>
            <button onclick="Modal.close('ihbar-detay-modal')"
                class="w-full mt-6 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
                Kapat
            </button>
        </div>
    </div>
</div>

<style>
    .ihbar-tab-btn.active {
        background: white;
        color: var(--primary);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .dark .ihbar-tab-btn.active {
        background: #1a2130;
    }

    #pwa-full-modal.ihbar-form-modal > .fixed.top-0,
    #pwa-full-modal.ihbar-form-modal > button.fixed {
        display: none !important;
    }

    #pwa-full-modal.ihbar-form-modal {
        top: auto !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: min(100%, 480px);
        height: 90vh;
        height: min(90dvh, 760px);
        margin: 0 auto;
        border-radius: 24px 24px 0 0;
        overflow: hidden;
        box-shadow:
            0 -100vh 0 100vh rgba(15, 23, 42, .42),
            0 -14px 40px rgba(15, 23, 42, .22);
    }

    #pwa-full-modal.ihbar-form-modal #pwa-full-modal-content {
        padding-bottom: 7rem !important;
    }

    .ihbar-form-footer {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 135;
        margin: 0;
        padding: 1rem 1.25rem calc(1rem + env(safe-area-inset-bottom));
        background: rgba(248, 250, 252, .96);
        border-top: 1px solid #e2e8f0;
        backdrop-filter: blur(12px);
        box-shadow: 0 -8px 24px rgba(15, 23, 42, .06);
    }

    .dark .ihbar-form-footer {
        background: rgba(15, 23, 42, .96);
        border-top-color: #334155;
    }
</style>

<script>
    let currentIhbarTab = 'gelen';
    let ihbarBildirdiklerimData = [];
    let ihbarGelenData = [];
    let ihbarSeciliFotolar = [];
    let ihbarEditId = null;
    let ihbarEditToken = null;
    let ihbarMevcutFotograflar = [];

    document.addEventListener('DOMContentLoaded', function () {
        loadIhbarlar();
    });

    async function loadIhbarlar() {
        try {
            const [bildirdiklerimRes, gelenRes] = await Promise.all([
                API.request('listIhbarlarim'),
                API.request('listGelenIhbarlar')
            ]);

            ihbarBildirdiklerimData = bildirdiklerimRes.success ? bildirdiklerimRes.data : [];
            ihbarGelenData = gelenRes.success ? gelenRes.data : [];

            renderIhbarlar();
        } catch (error) {
            console.error('İhbar load error:', error);
            document.getElementById('ihbar-list').innerHTML = '<p class="text-center text-slate-500 py-8">Veriler yüklenemedi</p>';
        }
    }

    function changeIhbarTab(tab) {
        currentIhbarTab = tab;

        document.querySelectorAll('.ihbar-tab-btn').forEach(btn => {
            btn.classList.remove('active');
            btn.classList.add('text-slate-500');
        });

        const activeBtn = document.querySelector(`[data-tab="${tab}"]`);
        activeBtn.classList.add('active');
        activeBtn.classList.remove('text-slate-500');

        renderIhbarlar();
    }

    function ihbarDurumBadge(durum) {
        switch (durum) {
            case 'yeni': return 'badge-gray';
            case 'yonlendirildi': return 'badge-primary';
            case 'islemde': return 'badge-warning';
            case 'olumlu': return 'badge-success';
            case 'olumsuz': return 'badge-danger';
            default: return 'badge-gray';
        }
    }

    function ihbarDurumText(durum) {
        const map = { yeni: 'Yeni', yonlendirildi: 'Yönlendirildi', islemde: 'İşlemde', olumlu: 'Olumlu', olumsuz: 'Olumsuz' };
        return map[durum] || durum;
    }

    function renderIhbarlar() {
        const container = document.getElementById('ihbar-list');
        const data = currentIhbarTab === 'gelen' ? ihbarGelenData : ihbarBildirdiklerimData;

        if (!data || data.length === 0) {
            container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon">
                    <span class="material-symbols-outlined">campaign</span>
                </div>
                <p class="text-slate-600 dark:text-slate-400 font-medium">Kayıt bulunamadı</p>
            </div>`;
            return;
        }

        container.innerHTML = data.map(ihbar => `
        <div class="card p-4" onclick="showIhbarDetay(${ihbar.id}, '${currentIhbarTab}')">
            <div class="flex items-start justify-between gap-2">
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-slate-900 dark:text-white">${ihbar.ilce || '-'} / ${ihbar.mahalle || '-'}</p>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2">${ihbar.aciklama || ''}</p>
                </div>
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    <span class="badge ${ihbarDurumBadge(ihbar.durum)}">${ihbarDurumText(ihbar.durum)}</span>
                    ${(currentIhbarTab === 'bildirdiklerim' && ihbar.duzenlenebilir) ? `
                    <button type="button" onclick="event.stopPropagation(); startEditIhbar('${ihbar.edit_token}');"
                        class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center border border-slate-200 dark:border-slate-700 hover:bg-primary/10 hover:text-primary hover:border-primary transition-all">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                    </button>` : ''}
                </div>
            </div>
            <div class="flex items-center justify-between mt-3">
                <span class="text-xs text-slate-400">${ihbar.tarih}</span>
                ${ihbar.atanan_ekip_adi ? `<span class="text-xs text-primary font-medium">${ihbar.atanan_ekip_adi}</span>` : ''}
            </div>
        </div>`).join('');
    }

    async function startEditIhbar(token) {
        const ihbar = ihbarBildirdiklerimData.find(i => i.edit_token === token && i.duzenlenebilir);
        if (!ihbar) return;

        try {
            const response = await API.request('ihbarDetay', { id: ihbar.id });
            if (response.success) {
                openYeniIhbarModal({ ...ihbar, ...response.data });
            } else {
                openYeniIhbarModal(ihbar);
            }
        } catch (error) {
            console.error('İhbar detay hatası:', error);
            openYeniIhbarModal(ihbar);
        }
    }

    function openYeniIhbarModal(editData = null) {
        ihbarSeciliFotolar = [];
        ihbarEditId = editData ? Number(editData.id) : null;
        ihbarEditToken = editData?.edit_token || null;
        ihbarMevcutFotograflar = editData?.fotograflar || [];
        document.getElementById('pwa-full-modal')?.classList.add('ihbar-form-modal');

        showPwaFullModal({
            html: `
                <div class="px-5 pt-8 pb-8">
                    <h1 class="text-xl font-black text-slate-900 dark:text-white">${editData ? 'İhbarı Güncelle' : 'İhbar Bildir'}</h1>
                    <p class="text-sm text-slate-500 mt-1 mb-6">${editData ? 'İhbar sonuçlandırılmadan önce bilgileri düzenleyebilirsiniz.' : 'Kaçak Su kullanımı ihbarınızı bildirin.'}</p>

                    <form id="ihbar-form" class="space-y-4">
                        <div>
                            <label class="form-label">İlçe</label>
                            <select name="ilce" class="form-input">
                                <option value="">İlçe seçin...</option>
                                ${['Afşin', 'Andırın', 'Çağlayancerit', 'Dulkadiroğlu', 'Ekinözü', 'Elbistan', 'Göksun', 'Nurhak', 'Onikişubat', 'Pazarcık', 'Türkoğlu']
                                    .map(ilce => `<option value="${ilce}" ${editData?.ilce === ilce ? 'selected' : ''}>${ilce}</option>`).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Mahalle</label>
                            <input type="text" name="mahalle" class="form-input" placeholder="Mahalle" value="${editData?.mahalle ?? ''}">
                        </div>
                        <div>
                            <label class="form-label">Komşu / Abone Telefonu</label>
                            <input type="tel" name="telefon" class="form-input" placeholder="05XX XXX XX XX" value="${editData?.telefon ?? ''}">
                        </div>
                        <div>
                            <label class="form-label">Komşu Abone No</label>
                            <input type="text" name="komsu_abone_no" class="form-input" placeholder="Abone numarasını yazın" value="${editData?.komsu_abone_no ?? ''}">
                        </div>
                        <div>
                            <label class="form-label">Konum Bilgisi</label>
                            <input type="hidden" name="konum_lat" id="ihbar-konum-lat" value="${editData?.konum_lat ?? ''}">
                            <input type="hidden" name="konum_lng" id="ihbar-konum-lng" value="${editData?.konum_lng ?? ''}">
                            <input type="hidden" name="konum_dogruluk" id="ihbar-konum-dogruluk" value="${editData?.konum_dogruluk ?? ''}">
                            <button type="button" id="ihbar-konum-btn" onclick="ihbarKonumAl()"
                                class="w-full min-h-[52px] px-4 rounded-xl border border-primary/30 bg-primary/5 text-primary font-bold text-sm flex items-center justify-center gap-2 active:scale-[.98] transition-transform">
                                <span class="material-symbols-outlined">my_location</span>
                                <span id="ihbar-konum-text">${editData?.konum_lat && editData?.konum_lng ? 'Konum eklendi · Yenilemek için dokunun' : 'Mevcut Konumumu Ekle'}</span>
                            </button>
                            <p id="ihbar-konum-durum" class="text-xs text-slate-500 mt-2">
                                ${editData?.konum_lat && editData?.konum_lng ? `${Number(editData.konum_lat).toFixed(6)}, ${Number(editData.konum_lng).toFixed(6)}` : 'Konum izni yalnızca bu ihbar için kullanılacaktır.'}
                            </p>
                        </div>
                        <div>
                            <label class="form-label">Açıklama</label>
                            <textarea name="aciklama" class="form-input min-h-[100px]"
                                placeholder="İhbar detaylarını yazınız..." required>${editData?.aciklama ?? ''}</textarea>
                        </div>
                        <div>
                            <label class="form-label">Fotoğraf Ekle (toplam en fazla 10 adet)</label>
                            ${editData && ihbarMevcutFotograflar.length > 0 ? `
                            <div class="mb-2">
                                <p class="text-xs text-slate-500 mb-1">Yüklü fotoğraflar</p>
                                <div class="flex flex-wrap gap-2">
                                    ${ihbarMevcutFotograflar.map(f => `<img src="${f.url}" class="w-16 h-16 object-cover rounded-xl border border-slate-200 dark:border-slate-700">`).join('')}
                                </div>
                            </div>` : ''}
                            <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-6 flex flex-col items-center justify-center gap-2 cursor-pointer hover:border-primary transition-colors"
                                onclick="document.getElementById('ihbar-foto-input').click()">
                                <span class="material-symbols-outlined text-3xl text-slate-400">add_a_photo</span>
                                <p class="text-xs text-slate-500 text-center">Yüklemek için tıklayın</p>
                            </div>
                            <input type="file" id="ihbar-foto-input" accept="image/*" multiple class="hidden">
                            <div id="ihbar-foto-preview" class="flex flex-wrap gap-2 mt-2"></div>
                        </div>
                    </form>

                    <div class="ihbar-form-footer grid grid-cols-2 gap-3">
                        <button type="button" onclick="closePwaFullModal()"
                            class="h-12 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-sm active:scale-95 transition-transform">
                            Kapat
                        </button>
                        <button type="button" onclick="document.getElementById('ihbar-form').requestSubmit()" id="ihbar-submit-btn"
                            class="h-12 rounded-xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-1.5 active:scale-95 transition-transform shadow-lg">
                            <span class="material-symbols-outlined text-[18px]">${editData ? 'save' : 'send'}</span>
                            <span id="ihbar-submit-text">${editData ? 'Güncelle' : 'Kaydet'}</span>
                        </button>
                    </div>
                </div>
            `,
            onOpen: () => {
                const fotoInput = document.getElementById('ihbar-foto-input');

                if (fotoInput) {
                    fotoInput.addEventListener('change', function () {
                        const yeniDosyalar = Array.from(this.files);
                        const toplamMevcut = ihbarMevcutFotograflar.length + ihbarSeciliFotolar.length;
                        if (toplamMevcut + yeniDosyalar.length > 10) {
                            Alert.warning('Sınır Aşıldı', 'Toplamda en fazla 10 fotoğraf olabilir.');
                            this.value = '';
                            return;
                        }

                        yeniDosyalar.forEach(file => {
                            const ext = file.name.split('.').pop().toLowerCase();
                            if (!['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                                Alert.error('Hata', 'Sadece JPG, PNG ve WEBP formatında resim yükleyebilirsiniz.');
                                return;
                            }
                            ihbarSeciliFotolar.push(file);
                        });

                        this.value = '';
                        renderIhbarFotoPreview();
                    });
                }

                document.getElementById('ihbar-form').addEventListener('submit', async function (e) {
                    e.preventDefault();
                    await submitYeniIhbar(this);
                });
            }
        });
    }

    function renderIhbarFotoPreview() {
        const preview = document.getElementById('ihbar-foto-preview');
        if (!preview) return;

        preview.innerHTML = ihbarSeciliFotolar.map((file, idx) => {
            const url = URL.createObjectURL(file);
            return `
            <div class="relative inline-block">
                <img src="${url}" class="w-20 h-20 object-cover rounded-xl">
                <button type="button" onclick="removeIhbarFoto(${idx})" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center">
                    <span class="material-symbols-outlined text-sm">close</span>
                </button>
            </div>`;
        }).join('');
    }

    function removeIhbarFoto(idx) {
        ihbarSeciliFotolar.splice(idx, 1);
        renderIhbarFotoPreview();
    }

    function ihbarKonumAl() {
        const button = document.getElementById('ihbar-konum-btn');
        const text = document.getElementById('ihbar-konum-text');
        const durum = document.getElementById('ihbar-konum-durum');

        if (!navigator.geolocation) {
            Alert.error('Konum Alınamadı', 'Cihazınız konum paylaşımını desteklemiyor.');
            return;
        }

        button.disabled = true;
        text.textContent = 'Konum alınıyor...';
        durum.textContent = 'Lütfen konum izni isteğini onaylayın.';

        navigator.geolocation.getCurrentPosition(position => {
            const { latitude, longitude, accuracy } = position.coords;
            document.getElementById('ihbar-konum-lat').value = latitude.toFixed(7);
            document.getElementById('ihbar-konum-lng').value = longitude.toFixed(7);
            document.getElementById('ihbar-konum-dogruluk').value = accuracy.toFixed(2);
            text.textContent = 'Konum Eklendi';
            durum.textContent = `${latitude.toFixed(6)}, ${longitude.toFixed(6)} · Yaklaşık ${Math.round(accuracy)} m doğruluk`;
            button.disabled = false;
        }, error => {
            const messages = {
                1: 'Konum izni verilmedi. Telefon ayarlarından konum iznini açabilirsiniz.',
                2: 'Cihaz konumu belirleyemedi. Lütfen tekrar deneyin.',
                3: 'Konum alınırken zaman aşımı oluştu.'
            };
            text.textContent = 'Mevcut Konumumu Ekle';
            durum.textContent = messages[error.code] || 'Konum alınamadı.';
            button.disabled = false;
            Alert.warning('Konum Alınamadı', durum.textContent);
        }, {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 30000
        });
    }

    async function submitYeniIhbar(form) {
        const btn = document.getElementById('ihbar-submit-btn');
        const btnText = document.getElementById('ihbar-submit-text');

        const isEdit = !!ihbarEditId;
        btn.disabled = true;
        btnText.innerText = isEdit ? 'GÜNCELLENİYOR...' : 'GÖNDERİLİYOR...';

        try {
            const formData = new FormData();
            formData.append('action', isEdit ? 'updateIhbar' : 'createIhbar');
            if (isEdit) {
                formData.append('edit_token', ihbarEditToken);
            }
            formData.append('ilce', form.querySelector('[name=ilce]').value);
            formData.append('mahalle', form.querySelector('[name=mahalle]').value);
            formData.append('telefon', form.querySelector('[name=telefon]').value);
            formData.append('komsu_abone_no', form.querySelector('[name=komsu_abone_no]').value);
            formData.append('aciklama', form.querySelector('[name=aciklama]').value);
            formData.append('konum_lat', form.querySelector('[name=konum_lat]').value);
            formData.append('konum_lng', form.querySelector('[name=konum_lng]').value);
            formData.append('konum_dogruluk', form.querySelector('[name=konum_dogruluk]').value);

            ihbarSeciliFotolar.forEach(file => {
                formData.append('fotograflar[]', file);
            });

            const requestSummary = {
                action: isEdit ? 'updateIhbar' : 'createIhbar',
                photoCount: ihbarSeciliFotolar.length,
                totalPhotoBytes: ihbarSeciliFotolar.reduce((total, file) => total + file.size, 0)
            };
            console.info('[İhbar] Gönderim başlatıldı', requestSummary);

            const response = await fetch('api.php', { method: 'POST', body: formData });
            const requestId = response.headers.get('X-Request-Id');
            const responseText = await response.text();
            let result;

            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('[İhbar] API JSON olmayan yanıt döndürdü', {
                    requestId,
                    status: response.status,
                    response: responseText.slice(0, 1000),
                    request: requestSummary
                });
                throw new Error(`Sunucudan geçersiz yanıt alındı${requestId ? ` (Kod: ${requestId})` : ''}`);
            }

            if (result.success) {
                console.info('[İhbar] Gönderim tamamlandı', { requestId, result });
                closePwaFullModal();
                await Alert.success('Başarılı', result.message || (isEdit ? 'İhbarınız güncellendi.' : 'İhbarınız kaydedildi.'));
                loadIhbarlar();
            } else {
                console.error('[İhbar] API işlemi reddetti', {
                    requestId,
                    status: response.status,
                    result,
                    request: requestSummary
                });
                Alert.error('Hata', result.message || result.error || 'Bir hata oluştu.');
            }
        } catch (error) {
            console.error('İhbar gönderim hatası:', error);
            Alert.error('Gönderim Hatası', error.message || 'Sunucuya ulaşılamadı.');
        } finally {
            btn.disabled = false;
            btnText.innerText = isEdit ? 'GÜNCELLE' : 'İHBARI GÖNDER';
        }
    }

    async function showIhbarDetay(id, tip) {
        Modal.open('ihbar-detay-modal');
        const content = document.getElementById('ihbar-detay-content');
        content.innerHTML = '<div class="text-center py-8"><span class="material-symbols-outlined animate-spin text-3xl text-primary">refresh</span></div>';

        try {
            const response = await API.request('ihbarDetay', { id: id }, false);
            if (!response.success) {
                content.innerHTML = `<p class="text-center text-red-500 py-4">${response.message || 'Kayıt bulunamadı'}</p>`;
                return;
            }

            const d = response.data;
            const kapaliMi = (d.durum === 'olumlu' || d.durum === 'olumsuz');
            const buAtandiMi = tip === 'gelen';

            const fotoHtml = (d.fotograflar || []).map(f =>
                `<img src="${f.url}" onclick="window.open('${f.url}', '_blank')" class="w-20 h-20 object-cover rounded-xl cursor-pointer">`
            ).join('') || '<p class="text-xs text-slate-400">Fotoğraf yok</p>';

            const tarihceHtml = (d.tarihce || []).map(t => `
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-primary text-sm">history</span>
                    </div>
                    <div>
                        <p class="text-sm text-slate-700 dark:text-slate-300">${t.aciklama}</p>
                        <p class="text-xs text-slate-400">${t.ekleyen_adi || ''} • ${t.tarih}</p>
                    </div>
                </div>`).join('');

            content.innerHTML = `
                <div class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <p class="font-bold text-slate-900 dark:text-white">${d.ilce || '-'} / ${d.mahalle || '-'}</p>
                        <span class="badge ${ihbarDurumBadge(d.durum)}">${ihbarDurumText(d.durum)}</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800 p-3 rounded-xl">
                        <p class="text-xs text-slate-500 mb-1">Açıklama</p>
                        <p class="text-sm text-slate-700 dark:text-slate-300">${d.aciklama || '-'}</p>
                    </div>
                    ${d.telefon ? `<p class="text-xs text-slate-500">Telefon: <span class="text-slate-800 dark:text-slate-200 font-medium">${d.telefon}</span></p>` : ''}
                    ${d.komsu_abone_no ? `<p class="text-xs text-slate-500">Komşu Abone No: <span class="text-slate-800 dark:text-slate-200 font-medium">${d.komsu_abone_no}</span></p>` : ''}
                    ${d.konum_lat && d.konum_lng ? `
                        <a href="https://www.google.com/maps?q=${d.konum_lat},${d.konum_lng}" target="_blank"
                            class="w-full p-3 rounded-xl border border-primary/20 bg-primary/5 text-primary text-sm font-semibold flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-lg">map</span>Konumu Haritada Aç
                        </a>` : ''}
                    <div>
                        <p class="text-xs text-slate-500 mb-2">Fotoğraflar</p>
                        <div class="flex flex-wrap gap-2">${fotoHtml}</div>
                    </div>
                    ${d.durum === 'olumlu' ? `<div class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-sm text-green-700 dark:text-green-300"><strong>Tutanak No:</strong> ${d.tutanak_no || '-'}</div>` : ''}
                    ${d.durum === 'olumsuz' ? `<div class="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-300"><strong>Olumsuz Sebep:</strong> ${d.olumsuz_sebep || '-'}</div>` : ''}

                    ${buAtandiMi ? `
                    ${!kapaliMi ? `
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <p class="text-xs text-slate-500 mb-2">Not Ekle (Tutanak vb.)</p>
                        <div class="flex gap-2">
                            <input type="text" id="ihbar-not-input" class="form-input flex-1" placeholder="Not yazın...">
                            <button type="button" onclick="ihbarNotEkle(${d.id}, '${tip}')" class="btn-primary px-4">Ekle</button>
                        </div>
                    </div>` : ''}
                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <p class="text-xs text-slate-500 mb-2">${kapaliMi ? 'Sonucu Yeniden Düzenle' : 'Sonuçlandır'}</p>
                        <div class="grid grid-cols-2 gap-2 mb-2">
                            <button type="button" onclick="ihbarSonucSec(this, 'olumlu')" class="ihbar-sonuc-btn py-2 rounded-xl border ${d.durum === 'olumlu' ? 'active border-primary text-primary' : 'border-slate-200 dark:border-slate-700'} text-sm font-medium">✅ Olumlu</button>
                            <button type="button" onclick="ihbarSonucSec(this, 'olumsuz')" class="ihbar-sonuc-btn py-2 rounded-xl border ${d.durum === 'olumsuz' ? 'active border-primary text-primary' : 'border-slate-200 dark:border-slate-700'} text-sm font-medium">❌ Olumsuz</button>
                        </div>
                        <input type="hidden" id="ihbar-sonuc-durum" value="${d.durum === 'olumlu' || d.durum === 'olumsuz' ? d.durum : ''}">
                        <input type="text" id="ihbar-tutanak-no" class="form-input ${d.durum === 'olumlu' ? '' : 'hidden'} mb-2" placeholder="Tutanak No" value="${d.tutanak_no || ''}">
                        <textarea id="ihbar-olumsuz-sebep" class="form-input ${d.durum === 'olumsuz' ? '' : 'hidden'} mb-2" placeholder="Olumsuz sebebi">${d.olumsuz_sebep || ''}</textarea>
                        <button type="button" onclick="ihbarSonuclandir(${d.id}, '${tip}')" class="w-full btn-primary py-3">${kapaliMi ? 'Sonucu Güncelle' : 'Sonuçlandır'}</button>
                    </div>` : ''}

                    <div class="border-t border-slate-100 dark:border-slate-800 pt-4">
                        <p class="text-xs text-slate-500 mb-3">İşlem Tarihçesi</p>
                        ${tarihceHtml || '<p class="text-xs text-slate-400">Kayıt yok</p>'}
                    </div>
                </div>`;
        } catch (error) {
            console.error('İhbar detay hatası:', error);
            content.innerHTML = '<p class="text-center text-red-500 py-4">Bir hata oluştu.</p>';
        }
    }

    function ihbarSonucSec(btn, durum) {
        document.querySelectorAll('.ihbar-sonuc-btn').forEach(b => b.classList.remove('active', 'border-primary', 'text-primary'));
        btn.classList.add('active', 'border-primary', 'text-primary');
        document.getElementById('ihbar-sonuc-durum').value = durum;
        document.getElementById('ihbar-tutanak-no').classList.toggle('hidden', durum !== 'olumlu');
        document.getElementById('ihbar-olumsuz-sebep').classList.toggle('hidden', durum !== 'olumsuz');
    }

    async function ihbarNotEkle(id, tip) {
        const input = document.getElementById('ihbar-not-input');
        const not = input.value.trim();
        if (!not) {
            Alert.warning('Uyarı', 'Lütfen bir not yazın.');
            return;
        }

        const response = await API.request('ihbarNotEkle', { id: id, aciklama: not });
        if (response.success) {
            input.value = '';
            showIhbarDetay(id, tip);
        } else {
            Alert.error('Hata', response.message || 'Bir hata oluştu.');
        }
    }

    async function ihbarSonuclandir(id, tip) {
        const durum = document.getElementById('ihbar-sonuc-durum').value;
        if (!durum) {
            Alert.warning('Uyarı', 'Lütfen sonuç seçiniz.');
            return;
        }

        const tutanakNo = document.getElementById('ihbar-tutanak-no').value.trim();
        const sebep = document.getElementById('ihbar-olumsuz-sebep').value.trim();

        if (durum === 'olumlu' && !tutanakNo) {
            Alert.warning('Uyarı', 'Tutanak numarası girilmelidir.');
            return;
        }
        if (durum === 'olumsuz' && !sebep) {
            Alert.warning('Uyarı', 'Olumsuz sebebi girilmelidir.');
            return;
        }

        const response = await API.request('ihbarSonuclandir', { id: id, durum: durum, tutanak_no: tutanakNo, sebep: sebep });
        if (response.success) {
            Modal.close('ihbar-detay-modal');
            await Alert.success('Başarılı', response.message || 'İhbar sonuçlandırıldı.');
            loadIhbarlar();
        } else {
            Alert.error('Hata', response.message || 'Bir hata oluştu.');
        }
    }
</script>
