<?php
/**
 * KM Bildirim Modalı ve Ortak Fonksiyonlar
 * Bu dosya farklı sayfalardan KM bildirimi modalını açmak için kullanılır.
 */
?>
<script>
    // Global Değişkenler (Ana Sayfadan veya İlgili Sayfadan Alınabilir)
    var sharedAktifAracId = <?php echo json_encode($aktifAracZimmeti->arac_id ?? null); ?>;
    var sharedAktifAracPlaka = <?php echo json_encode($aktifAracZimmeti->plaka ?? ''); ?>;

    /**
     * KM Bildirim Modalını Açar
     * @param {Object|null} editData Düzenleme yapılacak veri varsa gönderilir
     */
    async function openKmBildirModal(editData = null) {
        if (!sharedAktifAracId && !editData) {
            Alert.warning('Araç Zimmeti Yok', 'Zimmetinizde aktif bir araç bulunmuyor.');
            return;
        }

        const plaka = editData ? editData.plaka : sharedAktifAracPlaka;
        const aracId = editData ? editData.arac_id : sharedAktifAracId;

        showPwaFullModal({
            html: `
                <div class="header-main relative px-6 pt-12 pb-8 flex flex-col items-start shadow-xl rounded-b-[2.5rem] safe-area-top shrink-0 overflow-hidden" 
                     style="background: linear-gradient(135deg, #135bec 0%, #0d47c1 100%);">
                    <div class="flex items-center justify-between w-full mb-4">
                        <span class="bg-white/20 backdrop-blur-md border border-white/10 text-white rounded-lg px-3 py-1 text-[11px] font-semibold tracking-wide shadow-sm">ARAÇ TAKİP</span>
                    </div>
                    <h1 class="text-white text-2xl font-black tracking-tight leading-[1.15]" style="text-shadow: 0 4px 8px rgba(0,0,0,0.5);">${editData ? 'Bildirim Düzenle' : 'KM Bildirimi'}</h1>
                    <p class="text-blue-100/80 text-sm font-medium mt-1">${plaka} plakalı araç için güncel KM bilgisini giriniz.</p>
                </div>

                <div class="px-5 pb-8 flex-1 bg-transparent -mt-5 relative z-20">
                    <div class="bg-white dark:bg-card-dark rounded-[2rem] p-6 shadow-xl shadow-black/5 dark:shadow-black/20 border border-slate-100 dark:border-slate-800">
                    <form id="km-bildirim-form" class="space-y-6">
                        <input type="hidden" name="arac_id" value="${aracId}">
                        ${editData ? `<input type="hidden" name="id" value="${editData.id}">` : ''}
                        
                        <!-- Bildirim Türü (Side-by-side Switch) -->
                        <div class="flex items-center justify-between gap-4 py-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300">Bildirim Türü</label>
                            <div class="relative w-44 bg-slate-100 dark:bg-slate-800/50 rounded-2xl p-1 flex items-center h-12 shadow-inner border border-slate-200 dark:border-slate-800 overflow-hidden shrink-0">
                                <div id="km-tur-handle" 
                                    class="absolute h-[calc(100%-8px)] w-[calc(50%-4px)] bg-gradient-to-r from-amber-400 to-orange-500 rounded-xl shadow-lg transition-all duration-300 ease-out left-[4px] translate-x-0">
                                </div>
                                <button type="button" onclick="setKmTur('sabah')" 
                                    class="relative flex-1 h-full flex items-center justify-center gap-1 z-10 transition-colors duration-300 text-white" id="btn-tur-sabah">
                                    <span class="material-symbols-outlined text-base">wb_sunny</span>
                                    <span class="text-[11px] font-extrabold uppercase tracking-tight">Sabah</span>
                                </button>
                                <button type="button" onclick="setKmTur('aksam')" 
                                    class="relative flex-1 h-full flex items-center justify-center gap-1 z-10 transition-colors duration-300 text-slate-500" id="btn-tur-aksam">
                                    <span class="material-symbols-outlined text-base">nights_stay</span>
                                    <span class="text-[11px] font-extrabold uppercase tracking-tight">Akşam</span>
                                </button>
                                <input type="hidden" name="tur" id="km-tur-input" value="${editData ? editData.tur : 'sabah'}">
                            </div>
                        </div>

                        <!-- Tarih Seçimi -->
                        <div class="flex items-center justify-between gap-4 py-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300">Rapor Tarihi</label>
                            <div class="relative w-44">
                                <input type="date" name="tarih" id="km-tarih-input" required 
                                    min="${(() => { 
                                        let d = new Date(); 
                                        d.setDate(d.getDate() - 1); 
                                        return d.toLocaleDateString('en-CA'); 
                                    })()}" 
                                    max="${new Date().toLocaleDateString('en-CA')}"
                                    value="${editData ? editData.tarih : (new Date().toLocaleDateString('en-CA'))}"
                                    class="w-full pl-4 pr-10 py-3 bg-slate-100 dark:bg-slate-800/50 border-none rounded-2xl focus:ring-2 focus:ring-primary/20 transition-all font-bold text-xs text-slate-700 dark:text-slate-300">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400">
                                    <span class="material-symbols-outlined text-sm">calendar_month</span>
                                </div>
                            </div>
                        </div>

                        <!-- KM Değeri -->
                        <div class="space-y-2">
                            <div class="flex justify-between items-center ml-1">
                                <label class="text-sm font-bold text-slate-700 dark:text-slate-300" id="km-label">Başlangıç KM</label>
                                <span id="last-km-hint" class="text-[10px] font-bold text-primary/70 italic hidden">Önceki: - KM</span>
                            </div>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary text-slate-400">
                                    <span class="material-symbols-outlined">speed</span>
                                </div>
                                <input type="number" name="bitis_km" id="km-value-input" required value="${editData ? editData.bitis_km : ''}"
                                    class="w-full pl-12 pr-4 py-4 bg-slate-50 dark:bg-slate-800/50 border-2 border-slate-100 dark:border-slate-800 rounded-2xl focus:border-primary focus:ring-0 transition-all font-bold text-lg"
                                    placeholder="Güncel KM değerini girin">
                            </div>
                        </div>

                        <!-- Açıklama -->
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">Açıklama (Opsiyonel)</label>
                            <textarea name="aciklama" rows="3"
                                class="w-full p-4 bg-slate-50 dark:bg-slate-800/50 border-2 border-slate-100 dark:border-slate-800 rounded-2xl focus:border-primary focus:ring-0 transition-all text-sm"
                                placeholder="Eklemek istediğiniz notlar...">${editData ? editData.aciklama : ''}</textarea>
                        </div>

                        <!-- Resim Yükleme -->
                        <div class="space-y-2">
                            <label class="text-sm font-bold text-slate-700 dark:text-slate-300 ml-1">KM Gösterge Resmi ${editData ? '<span class="text-[10px] text-primary font-medium">(Değiştirmek istemiyorsanız boş bırakın)</span>' : ''}</label>
                            <div class="relative group">
                                <input type="file" name="resim" id="km-resim-input" accept="image/*" capture="camera" class="hidden">
                                <label for="km-resim-input" 
                                    class="flex flex-col items-center justify-center w-full min-h-[160px] border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-800/30 cursor-pointer hover:border-primary/50 transition-all p-4 text-center">
                                    <div class="w-16 h-16 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                        <span class="material-symbols-outlined text-3xl">add_a_photo</span>
                                    </div>
                                    <span class="text-sm font-bold text-slate-600 dark:text-slate-400" id="resim-placeholder">Fotoğraf Çek veya Yükle</span>
                                    <img id="resim-preview" src="${editData && editData.resim_url ? editData.resim_url : ''}" class="${editData && editData.resim_url ? '' : 'hidden'} mt-2 rounded-xl max-h-[120px] object-cover border-2 border-primary/20 shadow-lg">
                                </label>
                            </div>
                        </div>

                        <div class="pt-4">
                            <button type="submit" id="km-submit-btn"
                                class="w-full py-4 bg-gradient-to-r from-primary to-primary-dark text-white font-bold rounded-2xl shadow-lg shadow-primary/30 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">${editData ? 'save' : 'send'}</span>
                                <span id="km-submit-text">${editData ? 'DEĞİŞİKLİKLERİ KAYDET' : 'KM GÖNDER'}</span>
                            </button>
                        </div>
                    </form>
                    </div>
                </div>
            `,
            onOpen: () => {
                let currentLastKm = 0;

                // Previous KM bilgisini çek
                const fetchLastKm = async () => {
                    const dateInput = document.getElementById('km-tarih-input');
                    const turInput = document.getElementById('km-tur-input');
                    const hint = document.getElementById('last-km-hint');
                    const valueInput = document.getElementById('km-value-input');
                    const submitBtn = document.getElementById('km-submit-btn');

                    if (!dateInput || !turInput || !hint) return;

                    hint.innerText = 'Yükleniyor...';
                    hint.classList.remove('hidden');
                    if (submitBtn) submitBtn.disabled = true;

                    try {
                        const params = {
                            arac_id: aracId,
                            tarih: dateInput.value,
                            tur: turInput.value
                        };
                        
                        if (editData && editData.id) {
                            params.exclude_id = editData.id;
                        }

                        const response = await API.request('get-last-km', params, false);

                        if (response.success) {
                            currentLastKm = parseInt(response.last_km || 0);
                            if (currentLastKm > 0) {
                                hint.innerText = `Önceki: ${currentLastKm.toLocaleString()} KM`;
                                hint.classList.remove('hidden');
                                valueInput.setAttribute('min', currentLastKm);
                            } else {
                                hint.innerText = 'Önceki kayıt yok';
                                valueInput.removeAttribute('min');
                            }
                        }
                    } catch (e) {
                        console.error('Son KM çekilemedi:', e);
                        hint.classList.add('hidden');
                    } finally {
                        if (submitBtn) submitBtn.disabled = false;
                    }
                };

                // Switch Kontrolü
                window.setKmTur = (val) => {
                    const handle = document.getElementById('km-tur-handle');
                    const input = document.getElementById('km-tur-input');
                    const btnSabah = document.getElementById('btn-tur-sabah');
                    const btnAksam = document.getElementById('btn-tur-aksam');
                    const kmLabel = document.getElementById('km-label');
                    
                    if (!handle || !input) return;
                    
                    input.value = val;
                    if (val === 'sabah') {
                        handle.classList.remove('translate-x-full', 'from-indigo-500', 'to-purple-600');
                        handle.classList.add('translate-x-0', 'from-amber-400', 'to-orange-500');
                        btnSabah.classList.replace('text-slate-500', 'text-white');
                        btnAksam.classList.replace('text-white', 'text-slate-500');
                        kmLabel.innerText = 'Başlangıç KM';
                    } else {
                        handle.classList.remove('translate-x-0', 'from-amber-400', 'to-orange-500');
                        handle.classList.add('translate-x-full', 'from-indigo-500', 'to-purple-600');
                        btnAksam.classList.replace('text-slate-500', 'text-white');
                        btnSabah.classList.replace('text-white', 'text-slate-500');
                        kmLabel.innerText = 'Bitiş KM';
                    }

                    fetchLastKm();
                };

                // Tarih değişince son KM'yi tekrar çek
                const dateInput = document.getElementById('km-tarih-input');
                if (dateInput) {
                    dateInput.addEventListener('change', fetchLastKm);
                }

                // İlk yükleme
                fetchLastKm();

                // Edit Modu ise switch ayarla
                if (editData) {
                    setKmTur(editData.tur);
                }

                 // Resim önizleme
                const resimInput = document.getElementById('km-resim-input');
                if (resimInput) {
                    resimInput.addEventListener('change', function(e) {
                        const file = e.target.files[0];
                        if (file) {
                            const reader = new FileReader();
                            reader.onload = function(event) {
                                const preview = document.getElementById('resim-preview');
                                const placeholder = document.getElementById('resim-placeholder');
                                preview.src = event.target.result;
                                preview.classList.remove('hidden');
                                placeholder.classList.add('hidden');
                            }
                            reader.readAsDataURL(file);
                        }
                    });
                }

                // Form submit
                const form = document.getElementById('km-bildirim-form');
                if (form) {
                    form.addEventListener('submit', async function(e) {
                        e.preventDefault();
                        const f = e.target;
                        
                        const btn = document.getElementById('km-submit-btn');
                        const btnText = document.getElementById('km-submit-text');
                        
                        const bitisKm = parseInt(f.querySelector('[name=bitis_km]').value);
                        if (currentLastKm > 0 && bitisKm < currentLastKm) {
                            Alert.error('Geçersiz KM', `Girilen KM değeri önceki değerden (${currentLastKm}) düşük olamaz.`);
                            return;
                        }

                        const rInput = document.getElementById('km-resim-input');
                        if (!editData && rInput.files.length === 0) {
                            Alert.warning('Resim Zorunlu', 'Lütfen KM gösterge resmini çekiniz veya yükleyiniz.');
                            return;
                        }

                        // Buton durumunu ayarla
                        btn.disabled = true;
                        btnText.innerText = 'GÖNDERİLİYOR...';
                        btn.classList.add('opacity-70');

                        // Verileri hazırla
                        const data = {
                            arac_id: f.querySelector('[name=arac_id]').value,
                            bitis_km: bitisKm,
                            tarih: f.querySelector('[name=tarih]').value,
                            tur: f.querySelector('[name=tur]').value,
                            aciklama: f.querySelector('[name=aciklama]').value,
                            resim: rInput.files[0]
                        };
                        
                        if (editData) {
                            data.id = editData.id;
                        }

                        try {
                            const response = await API.request('save-km-report', data, false);
                            if (response.success) {
                                Alert.success('Başarılı', response.message);
                                
                                // Sayfa yenileme veya modal güncelleme
                                if (typeof loadAllKmReports === 'function') {
                                    loadAllKmReports();
                                    closePwaFullModal();
                                } else {
                                    openKmBildirModal();
                                }
                            } else {
                                Alert.error('Hata', response.message || 'Kaydedilirken bir hata oluştu.');
                            }
                        } catch (error) {
                            console.error('KM Bildirim Hatası:', error);
                            Alert.error('Bağlantı Hatası', 'Sunucuya ulaşılamadı.');
                        } finally {
                            btn.disabled = false;
                            btnText.innerText = editData ? 'DEĞİŞİKLİKLERİ KAYDET' : 'KM GÖNDER';
                            btn.classList.remove('opacity-70');
                        }
                    });
                }
            }
        });
    }

    /**
     * JSON nesnesini güvenli şekilde stringe çevirir
     */
    function JSON_safe(obj) {
        return JSON.stringify(obj).replace(/'/g, "&apos;");
    }
</script>
