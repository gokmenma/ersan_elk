<?php
/**
 * KM Bildirim Modalı ve Ortak Fonksiyonlar
 * Bu dosya farklı sayfalardan KM bildirimi modalını açmak için kullanılır.
 */
?>
<script>
    // Global Değişkenler (Ana Sayfadan veya İlgili Sayfadan Alınabilir)
    var sharedAktifAraclar = <?php echo json_encode(array_map(static function ($zimmet) {
        return ['id' => (int) $zimmet->arac_id, 'plaka' => (string) $zimmet->plaka];
    }, $aktifAracZimmetleri ?? []), JSON_UNESCAPED_UNICODE); ?>;
    var sharedAktifAracId = sharedAktifAraclar[0]?.id || null;
    var sharedAktifAracPlaka = sharedAktifAraclar[0]?.plaka || '';
    var sharedPersonelAdi = <?php echo json_encode($_SESSION['personel_adi'] ?? 'Bilinmeyen'); ?>;

    /**
     * KM Bildirim Modalını Açar
     * @param {Object|null} editData Düzenleme yapılacak veri varsa gönderilir
     */
    async function watermarkImage(file, lines) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Rakam okunabilirliğini koruyarak uzun kenarı en fazla 1920px yap.
                    const maxDimension = 1920;
                    let w = img.width;
                    let h = img.height;

                    if (Math.max(w, h) > maxDimension) {
                        const scale = maxDimension / Math.max(w, h);
                        w = Math.round(w * scale);
                        h = Math.round(h * scale);
                    }

                    canvas.width = w;
                    canvas.height = h;
                    
                    // Resmi çiz
                    ctx.drawImage(img, 0, 0, w, h);
                    
                    // Filigran stili
                    const fontSize = Math.max(14, Math.floor(w / 45));
                    ctx.font = `bold ${fontSize}px sans-serif`;
                    ctx.textAlign = 'right';
                    ctx.textBaseline = 'bottom';

                    const padding = 20;
                    const lineHeight = fontSize + 8;
                    
                    // En uzun satırı bul
                    let maxTextWidth = 0;
                    lines.forEach(line => {
                        const width = ctx.measureText(line).width;
                        if (width > maxTextWidth) maxTextWidth = width;
                    });

                    // Arka plan bandı (daha iyi okunabilirlik için)
                    const bgWidth = maxTextWidth + 20;
                    const bgHeight = (lines.length * lineHeight) + 10;
                    
                    ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
                    ctx.fillRect(w - bgWidth - padding + 5, h - bgHeight - padding + 5, bgWidth, bgHeight);
                    
                    // Metinleri çiz
                    ctx.fillStyle = '#FFFFFF';
                    // Satırları tersten (alttan üste) çiziyoruz
                    [...lines].reverse().forEach((line, index) => {
                        ctx.fillText(line, w - padding, h - padding - (index * lineHeight));
                    });
                    
                    canvas.toBlob((blob) => {
                        if (!blob) {
                            reject(new Error('Resim sıkıştırılamadı.'));
                            return;
                        }
                        const baseName = file.name.replace(/\.[^.]+$/, '') || 'km-fotografi';
                        resolve(new File([blob], `${baseName}.jpg`, {
                            type: 'image/jpeg',
                            lastModified: Date.now()
                        }));
                    }, 'image/jpeg', 0.85);
                };
                img.onerror = () => reject(new Error('Bu resim formatı tarayıcı tarafından okunamadı.'));
                img.src = e.target.result;
            };
            reader.onerror = () => reject(new Error('Resim okunamadı.'));
            reader.readAsDataURL(file);
        });
    }

    function kmEsc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));
    }

    async function kmFotoKucult(file) {
        const url = URL.createObjectURL(file);
        try {
            const img = await new Promise((resolve, reject) => {
                const el = new Image();
                el.onload = () => resolve(el);
                el.onerror = () => reject(new Error('Fotoğraf okunamadı.'));
                el.src = url;
            });
            const scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(img.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(img.naturalHeight * scale));
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.88));
            if (!blob) throw new Error('Fotoğraf hazırlanamadı.');
            return new File([blob], 'km-okuma.jpg', { type: 'image/jpeg', lastModified: Date.now() });
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async function openKmBildirModal(editData = null, zorunluSabah = false, gorevBitirSonrasi = false, oncekiAksamEksik = false, istenenTur = null) {
        if (!sharedAktifAracId && !editData) {
            Alert.warning('Araç Zimmeti Yok', 'Zimmetinizde aktif bir araç bulunmuyor.');
            return;
        }

        let plaka = editData ? editData.plaka : sharedAktifAracPlaka;
        let aracId = editData ? editData.arac_id : sharedAktifAracId;
        if (!editData) {
            const bildirilenler = (istenenTur && window.kmBugunBildirilen && window.kmBugunBildirilen[istenenTur]) || {};
            const adaylar = sharedAktifAraclar.filter(arac => !bildirilenler[arac.id]);
            if (adaylar.length === 0) {
                Alert.warning('Bildirim Tamam', 'Bu dönem için tüm araçlarınızın KM bildirimi yapılmış.');
                return;
            }
            if (adaylar.length === 1) {
                aracId = adaylar[0].id;
                plaka = adaylar[0].plaka;
            } else {
                const aracSecimi = await Swal.fire({
                    title: 'Araç Seçin',
                    input: 'select',
                    inputOptions: Object.fromEntries(adaylar.map(arac => [arac.id, arac.plaka])),
                    inputPlaceholder: 'KM bildirimi yapılacak araç',
                    showCancelButton: true,
                    confirmButtonText: 'Devam Et',
                    cancelButtonText: 'İptal',
                    inputValidator: value => value ? undefined : 'Lütfen bir araç seçin.'
                });
                if (!aracSecimi.isConfirmed) return;
                aracId = parseInt(aracSecimi.value, 10);
                plaka = adaylar.find(arac => arac.id === aracId)?.plaka || '';
            }
        }

        const varsayilanTur = editData
            ? editData.tur
            : (gorevBitirSonrasi ? 'aksam' : (zorunluSabah ? 'sabah' : (istenenTur || (new Date().getHours() >= 13 ? 'aksam' : 'sabah'))));
        const mevcutResim = editData && editData.resim_url ? editData.resim_url : '';
        const mevcutNot = editData && editData.aciklama ? editData.aciklama : '';

        showPwaFullModal({
            html: `
                <div class="pwa-km-hero safe-area-top shrink-0">
                    <span class="pwa-km-hero-badge"><span class="material-symbols-outlined">directions_car</span>ARAÇ TAKİP</span>
                    <h1>${editData ? 'Bildirim Düzenle' : 'KM Bildirimi'}</h1>
                    <p>${zorunluSabah ? 'Göreve başlamak için ' : ''}<span class="pwa-km-plate">${kmEsc(plaka)}</span> plakalı araç için ${zorunluSabah ? 'sabah ' : ''}göstergenin fotoğrafını ekleyin, KM'yi sizin için okuyalım.</p>
                    <div class="pwa-km-hero-gauge" aria-hidden="true">
                        <svg viewBox="0 0 56 56" fill="none" stroke-linecap="round">
                            <path class="arc-bg" d="M8 40 A22 22 0 1 1 48 40" stroke-width="4"/>
                            <path class="arc-fg" pathLength="100" d="M8 40 A22 22 0 1 1 48 40" stroke-width="4"/>
                            <g class="needle"><path d="M28 34 L28 13" stroke="#fff" stroke-width="3.5"/></g>
                            <circle cx="28" cy="34" r="3.5" fill="#fff"/>
                        </svg>
                    </div>
                </div>

                <div class="pb-6 flex-1 bg-transparent">
                    <div class="pwa-km-sheet pwa-fx-card--indigo">
                    <form id="km-bildirim-form" class="pwa-km-form">
                        <input type="hidden" name="arac_id" value="${kmEsc(aracId)}">
                        ${editData ? `<input type="hidden" name="id" value="${kmEsc(editData.id)}">` : ''}
                        ${oncekiAksamEksik ? `
                            <div class="pwa-km-alert">
                                <span class="material-symbols-outlined">warning</span>
                                <p>Dünkü akşam KM bildiriminiz eksik. Bugünkü başlangıç KM bilginizi eksiksiz giriniz.</p>
                            </div>
                        ` : ''}

                        <div class="pwa-km-field" style="--i:0">
                            <div class="pwa-km-label">
                                <span>Gösterge Fotoğrafı</span>
                                ${editData ? '<span class="pwa-km-edit-note">Değiştirmek istemiyorsanız dokunmayın</span>' : '<span class="pwa-km-step">1</span>'}
                            </div>
                            <input type="file" name="resim" id="km-resim-input" accept="image/*" class="hidden">
                            <label for="km-resim-input" id="km-shot" class="pwa-km-shot pwa-fx-card--indigo${mevcutResim ? ' has-image' : ''}">
                                <div class="pwa-km-shot-empty" id="resim-placeholder">
                                    <div class="pwa-fx-orb pwa-fx-orb--float"><span class="material-symbols-outlined">add_a_photo</span></div>
                                    <span class="pwa-km-drop-text">Fotoğraf çek veya yükle</span>
                                    <span class="pwa-km-drop-sub">Kamera ya da galeri açılır · KM otomatik okunur</span>
                                </div>
                                <div class="pwa-km-shot-view">
                                    <img id="resim-preview" alt="" src="${kmEsc(mevcutResim)}">
                                    <span class="pwa-km-scan"></span>
                                    <span class="pwa-km-shot-change"><span class="material-symbols-outlined">photo_camera</span>Değiştir</span>
                                </div>
                            </label>
                            <div id="km-ai-status" class="pwa-km-ai hidden" data-state="reading" aria-live="polite">
                                <span class="material-symbols-outlined" id="km-ai-icon">progress_activity</span>
                                <span id="km-ai-text"></span>
                            </div>
                        </div>

                        <div class="pwa-km-field" style="--i:1">
                            <div class="pwa-km-label">
                                <span id="km-label">Başlangıç KM</span>
                                <span id="last-km-hint" class="pwa-km-hint hidden">Önceki: - KM</span>
                            </div>
                            <div class="pwa-km-input-wrap" id="km-value-wrap">
                                <span class="material-symbols-outlined">speed</span>
                                <input type="number" inputmode="numeric" name="bitis_km" id="km-value-input" required value="${editData ? kmEsc(editData.bitis_km) : ''}"
                                    class="pwa-km-input"
                                    placeholder="Fotoğraftan okunur veya elle girin">
                            </div>
                        </div>

                        <div class="pwa-km-field" style="--i:2">
                            <div class="pwa-km-label"><span>Bildirim Türü</span></div>
                            <div class="pwa-km-seg" id="km-tur-switch" data-tur="${kmEsc(varsayilanTur)}">
                                <div id="km-tur-handle" class="pwa-km-seg-handle"></div>
                                <button type="button" onclick="setKmTur('sabah')" class="is-active" id="btn-tur-sabah">
                                    <span class="material-symbols-outlined">wb_sunny</span>Sabah
                                </button>
                                <button type="button" onclick="setKmTur('aksam')" ${zorunluSabah ? 'disabled' : ''} id="btn-tur-aksam">
                                    <span class="material-symbols-outlined">nights_stay</span>Akşam
                                </button>
                                <input type="hidden" name="tur" id="km-tur-input" value="${kmEsc(varsayilanTur)}">
                            </div>
                        </div>

                        <div class="pwa-km-field" style="--i:3">
                            <div class="pwa-km-label"><span>Rapor Tarihi</span></div>
                            <div class="pwa-km-input-wrap pwa-km-input-wrap--date">
                                <input type="date" name="tarih" id="km-tarih-input" required
                                    min="${(() => {
                                        let d = new Date();
                                        d.setDate(d.getDate() - 1);
                                        return d.toLocaleDateString('en-CA');
                                    })()}"
                                    max="${new Date().toLocaleDateString('en-CA')}"
                                    value="${editData ? kmEsc(editData.tarih) : (new Date().toLocaleDateString('en-CA'))}"
                                    ${zorunluSabah ? 'readonly' : ''}
                                    class="pwa-km-input pwa-km-input--date">
                                <span class="material-symbols-outlined">calendar_month</span>
                            </div>
                        </div>

                        <div class="pwa-km-field" style="--i:4">
                            <button type="button" id="km-note-toggle" class="pwa-km-note-toggle${mevcutNot ? ' hidden' : ''}">
                                <span class="material-symbols-outlined">edit_note</span>Not ekle (opsiyonel)
                            </button>
                            <div id="km-note-wrap" class="${mevcutNot ? '' : 'hidden'}">
                                <div class="pwa-km-label"><span>Açıklama (Opsiyonel)</span></div>
                                <textarea name="aciklama" rows="3" class="pwa-km-input"
                                    placeholder="Eklemek istediğiniz notlar...">${kmEsc(mevcutNot)}</textarea>
                            </div>
                        </div>
                    </form>
                    </div>
                </div>

                <div class="pwa-km-sticky pwa-fx-card--indigo">
                    <button type="submit" form="km-bildirim-form" id="km-submit-btn" class="pwa-fx-btn">
                        <span class="material-symbols-outlined">${editData ? 'save' : 'send'}</span>
                        <span id="km-submit-text">${editData ? 'DEĞİŞİKLİKLERİ KAYDET' : 'KM GÖNDER'}</span>
                    </button>
                </div>
            `,
            onOpen: () => {
                let currentLastKm = 0;
                let kmElleGirildi = false;
                let okumaSayaci = 0;
                const modalEl = document.getElementById('pwa-full-modal');
                if (modalEl) modalEl.classList.add('pwa-km-modal');

                const kmInput = document.getElementById('km-value-input');
                const kmWrap = document.getElementById('km-value-wrap');
                const aiBox = document.getElementById('km-ai-status');
                const aiIcon = document.getElementById('km-ai-icon');
                const aiText = document.getElementById('km-ai-text');
                const shot = document.getElementById('km-shot');

                const setAiDurum = (durum, metin) => {
                    if (!aiBox) return;
                    const ikonlar = { reading: 'progress_activity', ok: 'auto_awesome', warn: 'info', fail: 'edit_note' };
                    aiBox.dataset.state = durum;
                    aiIcon.textContent = ikonlar[durum] || 'info';
                    aiText.textContent = metin;
                    aiBox.classList.remove('hidden');
                };

                const kmYaz = (hedef) => {
                    kmWrap.classList.remove('is-ai');
                    void kmWrap.offsetWidth;
                    kmWrap.classList.add('is-ai');
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                        kmInput.value = hedef;
                        return;
                    }
                    const sure = 700;
                    const basla = performance.now();
                    const adim = (simdi) => {
                        const t = Math.min(1, (simdi - basla) / sure);
                        kmInput.value = Math.round(hedef * (1 - Math.pow(1 - t, 3)));
                        if (t < 1) requestAnimationFrame(adim);
                        else kmInput.value = hedef;
                    };
                    requestAnimationFrame(adim);
                };

                const fotodanKmOku = async (file) => {
                    const sayac = ++okumaSayaci;
                    shot.classList.add('is-reading');
                    setAiDurum('reading', 'Göstergedeki KM okunuyor…');
                    try {
                        const kucuk = await kmFotoKucult(file);
                        const response = await API.request('read-km-photo', { arac_id: aracId, resim: kucuk }, false);
                        if (sayac !== okumaSayaci) return;

                        if (!response || !response.success) {
                            setAiDurum('fail', (response && response.message) || 'KM okunamadı. Lütfen elle giriniz.');
                            kmInput.focus();
                            return;
                        }
                        const veri = response.data || {};
                        if (!veri.okundu || !veri.km) {
                            setAiDurum('fail', 'KM net okunamadı. Fotoğrafı yakından çekebilir veya KM\'yi elle girebilirsiniz.');
                            kmInput.focus();
                            return;
                        }
                        if (kmElleGirildi) {
                            setAiDurum('warn', 'Okunan değer: ' + Number(veri.km).toLocaleString('tr-TR') + ' KM. Girdiğiniz değer korundu.');
                            return;
                        }
                        kmYaz(parseInt(veri.km, 10));
                        if (currentLastKm > 0 && veri.km < currentLastKm) {
                            setAiDurum('warn', 'Okunan değer önceki KM\'den (' + currentLastKm.toLocaleString('tr-TR') + ') düşük. Lütfen kontrol edin.');
                        } else if (veri.ondalik_hane) {
                            setAiDurum('warn', 'Yapay zekâ okudu. Ondalık hane yok sayıldı, lütfen kontrol edin.');
                        } else {
                            setAiDurum('ok', 'Yapay zekâ okudu · Lütfen değeri kontrol edin.');
                        }
                    } catch (error) {
                        console.error('KM fotoğraf okuma hatası:', error);
                        if (sayac === okumaSayaci) {
                            setAiDurum('fail', 'KM okunamadı. Lütfen elle giriniz.');
                        }
                    } finally {
                        if (sayac === okumaSayaci) shot.classList.remove('is-reading');
                    }
                };

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

                            if (response.exists) {
                                hint.innerHTML = `<span class="text-red-500 font-bold"><span class="material-symbols-outlined text-xs align-middle">warning</span> Bu döneme ait bildirim zaten var!</span>`;
                                hint.classList.remove('hidden');
                                if (submitBtn) submitBtn.disabled = true;
                                return;
                            }

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

                const turUiUygula = (val) => {
                    const input = document.getElementById('km-tur-input');
                    const sw = document.getElementById('km-tur-switch');
                    const btnSabah = document.getElementById('btn-tur-sabah');
                    const btnAksam = document.getElementById('btn-tur-aksam');
                    const kmLabel = document.getElementById('km-label');
                    if (!input || !sw) return;
                    input.value = val;
                    sw.dataset.tur = val;
                    btnSabah.classList.toggle('is-active', val === 'sabah');
                    btnAksam.classList.toggle('is-active', val !== 'sabah');
                    kmLabel.innerText = val === 'sabah' ? 'Başlangıç KM' : 'Bitiş KM';
                };

                window.setKmTur = (val) => {
                    if (zorunluSabah && val !== 'sabah') return;
                    turUiUygula(val);
                    fetchLastKm();
                };

                turUiUygula(varsayilanTur);

                const dateInput = document.getElementById('km-tarih-input');
                if (dateInput) {
                    dateInput.addEventListener('change', fetchLastKm);
                }

                fetchLastKm();

                kmInput.addEventListener('input', () => {
                    kmElleGirildi = true;
                    kmWrap.classList.remove('is-ai');
                });

                const noteToggle = document.getElementById('km-note-toggle');
                if (noteToggle) {
                    noteToggle.addEventListener('click', () => {
                        noteToggle.classList.add('hidden');
                        const wrap = document.getElementById('km-note-wrap');
                        wrap.classList.remove('hidden');
                        const alan = wrap.querySelector('textarea');
                        if (alan) alan.focus();
                    });
                }

                const resimInput = document.getElementById('km-resim-input');
                if (resimInput) {
                    resimInput.addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        const reader = new FileReader();
                        reader.onload = function (event) {
                            const preview = document.getElementById('resim-preview');
                            preview.src = event.target.result;
                            shot.classList.add('has-image');
                        };
                        reader.readAsDataURL(file);
                        fotodanKmOku(file);
                    });

                    if (!editData) {
                        try {
                            resimInput.click();
                        } catch (e) {
                            console.warn('Fotoğraf seçici otomatik açılamadı:', e);
                        }
                    }
                }

                const form = document.getElementById('km-bildirim-form');
                if (form) {
                    form.addEventListener('submit', async function (e) {
                        e.preventDefault();
                        const f = e.target;

                        const btn = document.getElementById('km-submit-btn');
                        const btnText = document.getElementById('km-submit-text');

                        const rInput = document.getElementById('km-resim-input');
                        if (!editData && rInput.files.length === 0) {
                            Alert.warning('Fotoğraf Zorunlu', 'Lütfen KM gösterge fotoğrafını çekiniz veya yükleyiniz.');
                            return;
                        }

                        const bitisKm = parseInt(f.querySelector('[name=bitis_km]').value);
                        if (!bitisKm || bitisKm <= 0) {
                            Alert.warning('KM Girilmedi', 'Lütfen KM değerini giriniz.');
                            kmInput.focus();
                            return;
                        }
                        if (currentLastKm > 0 && bitisKm < currentLastKm) {
                            Alert.error('Geçersiz KM', `Girilen KM değeri önceki değerden (${currentLastKm}) düşük olamaz.`);
                            return;
                        }

                        btn.disabled = true;
                        btnText.innerText = 'FOTOĞRAF HAZIRLANIYOR...';
                        btn.classList.add('opacity-70');

                        let resimFile = rInput.files[0];
                        if (resimFile) {
                            const now = new Date();
                            const timestamp = now.toLocaleDateString('tr-TR') + ' ' + now.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
                            const turLabel = f.querySelector('[name=tur]').value === 'sabah' ? 'Sabah Bildirimi' : 'Akşam Bildirimi';

                            const watermarkLines = [
                                sharedPersonelAdi,
                                plaka,
                                turLabel,
                                timestamp
                            ];

                            try {
                                resimFile = await watermarkImage(resimFile, watermarkLines);
                            } catch (error) {
                                Alert.error('Resim Hatası', error.message || 'Resim hazırlanamadı.');
                                btn.disabled = false;
                                btnText.innerText = editData ? 'DEĞİŞİKLİKLERİ KAYDET' : 'KM GÖNDER';
                                btn.classList.remove('opacity-70');
                                return;
                            }
                        }

                        btnText.innerText = 'GÖNDERİLİYOR...';

                        const data = {
                            arac_id: f.querySelector('[name=arac_id]').value,
                            bitis_km: bitisKm,
                            tarih: f.querySelector('[name=tarih]').value,
                            tur: f.querySelector('[name=tur]').value,
                            aciklama: f.querySelector('[name=aciklama]').value,
                            resim: resimFile
                        };

                        if (editData) {
                            data.id = editData.id;
                        }

                        try {
                            const response = await API.request('save-km-report', data, false);
                            if (response.success) {
                                Alert.success('Başarılı', response.message);

                                const gorevBaslatilacak = zorunluSabah
                                    && data.tur === 'sabah'
                                    && data.tarih === new Date().toLocaleDateString('en-CA');

                                if (data.tarih === new Date().toLocaleDateString('en-CA') && typeof kmBildirimiKaydedildi === 'function') {
                                    kmBildirimiKaydedildi(data.tur, parseInt(data.arac_id, 10), data.bitis_km);
                                }

                                if (typeof loadAllKmReports === 'function') {
                                    loadAllKmReports();
                                }
                                closePwaFullModal();

                                if (gorevBaslatilacak && typeof gorevBasla === 'function') {
                                    setTimeout(gorevBasla, 350);
                                } else if (gorevBitirSonrasi && data.tur === 'aksam' && typeof gorevBitir === 'function') {
                                    setTimeout(() => gorevBitir(true), 350);
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
