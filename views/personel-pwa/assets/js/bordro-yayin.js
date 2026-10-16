/* Resmî döküm yalnız ağdan okunur; beyan çevrimdışı kuyruğa girmez. */
(() => {
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const para = v => new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'}).format(v / 100);
  const durum = v => ({yayinda:'Yayında', test:'Test yayını', revizyon:'Revizyon sürecinde', arsiv:'Arşiv'})[v] || v;
  const durumSinifi = v => ({yayinda:'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400', test:'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400', revizyon:'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400', arsiv:'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'})[v] || 'bg-slate-100 text-slate-600';
  const islemDurumu = v => ({bekliyor:'Onay bekliyor', goruntulendi:'Görüntülendi – onay bekliyor', onaylandi:'Onaylandı', itiraz_edildi:'İtiraz edildi', revize_edildi:'İptal / revize edildi'})[v] || v;
  const islemDurumuSinifi = v => ({bekliyor:'bg-amber-50 text-amber-700', goruntulendi:'bg-blue-50 text-blue-700', onaylandi:'bg-emerald-50 text-emerald-700', itiraz_edildi:'bg-rose-50 text-rose-700', revize_edildi:'bg-slate-100 text-slate-600'})[v] || 'bg-slate-100 text-slate-600';
  let secili = null;
  let isleniyor = false;
  let aktifSekme = 'detay';
  const $ = id => document.getElementById(id);

  function modalAc(id) {
    const m = $(id);
    if (m) {
      m.classList.add('active');
      m.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }
  }

  function modalKapat(id) {
    const m = $(id);
    if (m) {
      m.classList.remove('active');
      m.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }
  }

  function mesaj(v, hata = false) {
    const root = $('resmi-bordro-mesaj');
    if (!root) return;
    if (!v) { root.textContent = ''; root.classList.add('hidden'); return; }
    root.textContent = v;
    root.className = `mb-3 rounded-2xl px-4 py-3 text-sm font-medium ${hata ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-400' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400'}`;
    root.classList.remove('hidden');
  }

  async function api(action, data = {}) {
    const body = new FormData();
    Object.entries({action, csrf_token:window.bordroYayinCsrf, ...data}).forEach(([k,v]) => body.append(k,v));
    let j;
    try { const r = await fetch('api.php', {method:'POST', body, credentials:'same-origin', cache:'no-store'}); j = await r.json(); }
    catch (e) { throw new Error('Sunucuya ulaşılamadı. Bağlantınızı kontrol edip tekrar deneyin.'); }
    if (!j.success) throw new Error(j.message || 'İşlem tamamlanamadı.');
    return j.data;
  }

  const bosDurum = () => `<div class="card p-8 text-center border border-dashed border-slate-200 dark:border-slate-700"><div class="w-14 h-14 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-3"><span class="material-symbols-outlined text-3xl">receipt_long</span></div><h3 class="font-bold text-slate-800 dark:text-white">Henüz bordro yayınlanmadı</h3><p class="text-sm text-slate-500 mt-1">Yayınlanan resmî dökümleriniz burada görünecek.</p></div>`;

  async function liste() {
    const rows = await api('bordro-yayin-liste');
    const root = $('resmi-bordro-liste');
    if (!root) return [];
    root.innerHTML = rows.length ? rows.map((r, n) => {
      const tamam = !!r.beyan_tarihi;
      return `<button type="button" data-dokum="${n}" class="w-full card p-0 text-left overflow-hidden border border-slate-100 dark:border-slate-800 shadow-sm active:scale-[0.99] transition-transform cursor-pointer"><div class="p-4 flex items-start gap-3"><div class="w-11 h-11 shrink-0 rounded-2xl ${tamam ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20' : 'bg-primary/10 text-primary'} flex items-center justify-center"><span class="material-symbols-outlined">${tamam ? 'task_alt' : 'description'}</span></div><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><h3 class="font-bold text-slate-900 dark:text-white">${esc(r.donem)}</h3><p class="text-xs text-slate-500 mt-0.5">${esc(r.pusula_no || '')} · Sürüm ${esc(r.surum)}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold ${islemDurumuSinifi(r.islem_durumu)}">${esc(islemDurumu(r.islem_durumu))}</span></div><div class="mt-4 flex items-end justify-between gap-3"><div><p class="text-[10px] uppercase tracking-wide text-slate-400 font-bold">Bankadan ödenecek net</p><p class="text-xl font-extrabold text-slate-900 dark:text-white mt-0.5">${esc(para(r.banka_net_kurus))}</p></div><span class="material-symbols-outlined text-slate-300">chevron_right</span></div></div></div><div class="px-4 py-2.5 text-xs font-semibold ${islemDurumuSinifi(r.islem_durumu)}">${esc(islemDurumu(r.islem_durumu))}${r.beyan_tarihi ? ` · ${esc(r.beyan_tarihi)}` : ''}</div></button>`;
    }).join('') : bosDurum();
    root.querySelectorAll('[data-dokum]').forEach(b => {
      b.addEventListener('click', (e) => {
        e.preventDefault();
        const index = Number(b.dataset.dokum);
        if (rows[index]?.token) {
          ac(rows[index].token);
        }
      });
    });
    return rows;
  }

  function talepBolumu(d) {
    const durumlar = {acik:'İnceleniyor', alindi:'İtirazınız alındı', inceleniyor:'İnceleniyor', ek_bilgi_bekleniyor:'Ek bilgi bekleniyor', sonuclandi:'Sonuçlandırıldı'};
    const konular = {eksik_calisma:'Eksik çalışma ücreti', fazla_mesai:'Fazla mesai', prim:'Prim', avans:'Avans', kesinti:'Kesinti', yemek_yol:'Yemek / yol yardımı', diger:'Diğer'};
    const talepler = (d.talepler || []).map(t => {
      const ekler = (t.ekler || []).map(ek => `<a class="mt-2 flex items-center gap-2 text-xs font-bold text-primary" href="pages/bordro-talep-ek.php?token=${encodeURIComponent(ek.token)}" target="_blank" rel="noopener"><span class="material-symbols-outlined text-base">attachment</span>${esc(ek.dosya_adi)}</a>`).join('');
      const yanitlar = (t.yanitlar || []).map(y => `<div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-xs font-bold text-primary mb-1">Yetkili yanıtı</p><p class="text-sm">${esc(y.mesaj)}</p><small class="text-[10px] text-slate-400">${esc(y.tarih)}</small></div>`).join('');
      return `<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 mt-3"><div class="flex items-center justify-between gap-2 mb-2"><span class="text-xs font-bold ${t.durum === 'sonuclandi' ? 'text-emerald-600' : 'text-amber-600'}">${esc(durumlar[t.durum] || t.durum)}</span><small class="text-[11px] text-slate-400">${esc(t.tarih)}</small></div><p class="text-xs font-bold text-primary mb-1">${esc(konular[t.konu] || t.konu || 'Diğer')}</p><p class="text-sm text-slate-700 dark:text-slate-200">${esc(t.mesaj)}</p>${ekler}${yanitlar}</article>`;
    }).join('');
    if ((d.talepler || []).some(t => t.durum !== 'sonuclandi')) return talepler + '<p class="text-xs text-amber-600 mt-3">Açık itirazınız yetkilinin incelemesini bekliyor.</p>';
    return talepler + `<form id="resmi-talep-form" class="mt-4"><label for="resmi-talep-konu" class="form-label">İtiraz konusu</label><select id="resmi-talep-konu" name="konu" class="form-input w-full mb-3" required><option value="">Konu seçiniz</option>${Object.entries(konular).map(([v,l]) => `<option value="${v}">${esc(l)}</option>`).join('')}</select><label for="resmi-talep-mesaj" class="form-label">Açıklama</label><textarea id="resmi-talep-mesaj" class="form-input w-full min-h-[100px]" name="mesaj" minlength="10" maxlength="4000" placeholder="Hata veya eksikliği açıklayın" required></textarea><button type="submit" class="w-full mt-3 py-3 rounded-xl bg-rose-50 text-rose-700 font-bold">İtiraz / hata bildir</button></form>`;
  }

  function gorunenResmiKalemler(kalemler) {
    const sonuc = (kalemler || []).map(k => ({...k, kurus:Number(k.kurus || 0)}));
    const yuvarlamaMi = k => String(k.etiket || '').toLocaleLowerCase('tr-TR').includes('yemek günlük yuvarlama farkı');
    const yuvarlama = sonuc.filter(yuvarlamaMi).reduce((toplam, k) => toplam + k.kurus, 0);
    const gorunen = sonuc.filter(k => !yuvarlamaMi(k));
    if (yuvarlama) {
      const yemek = gorunen.find(k => String(k.etiket || '').toLocaleLowerCase('tr-TR') === 'yemek yardımı');
      if (yemek) yemek.kurus += yuvarlama;
      else gorunen.push({etiket:'Yemek yardımı', kurus:yuvarlama});
    }
    return gorunen;
  }

  function resmiPusulaDetayi(i, d) {
    const snapshot = i.resmi_pusula;
    if (!snapshot?.pusula) return '';
    const pusula = snapshot.pusula || {};
    const detay = pusula.detay || {};
    const matrah = detay.matrahlar || {};
    const indirim = detay.indirimler || {};
    const gunler = i.calisma_ve_izin_gunleri || {};
    const gunEtiketleri = {
      ssk:'SSK günü', normal:'Normal gün', hafta_tatili:'Hafta tatili', genel_tatil:'Genel tatil',
      ucretli_izin:'Ücretli izin', ucretsiz_izin:'Ücretsiz izin', rapor:'Rapor'
    };
    const paraSatiri = (etiket, kurus, renk = '') => `<div class="flex justify-between gap-4 py-2.5 border-b border-slate-100 dark:border-slate-800 last:border-0"><dt class="text-xs text-slate-500">${esc(etiket)}</dt><dd class="text-xs font-bold text-right whitespace-nowrap text-slate-900 dark:text-white ${renk}">${esc(para(Number(kurus || 0)))}</dd></div>`;
    const gunKartlari = Object.entries(gunEtiketleri).map(([anahtar, etiket]) => `<div class="rounded-xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-[10px] uppercase text-slate-400 font-bold">${esc(etiket)}</p><p class="mt-1 text-sm font-extrabold text-slate-900 dark:text-white">${esc(Number(gunler[anahtar] || 0))} gün</p></div>`).join('');
    const matrahSatirlari = [
      paraSatiri('SGK matrahı', i.resmi_matrahlar?.sgk_matrahi_kurus),
      paraSatiri('Gelir vergisi matrahı', i.resmi_matrahlar?.gelir_vergisi_matrahi_kurus),
      paraSatiri('Önceki kümülatif matrah', i.resmi_matrahlar?.onceki_kumulatif_kurus),
      paraSatiri('Yıl içi kümülatif matrah', i.resmi_matrahlar?.yeni_kumulatif_kurus)
    ].join('');
    const gorunenKalemler = gorunenResmiKalemler(i.kalemler);
    const gelirler = gorunenKalemler.filter(k => k.kurus > 0);
    const giderler = [
      ...(i.yasal_kesintiler || []).map(k => ({etiket:k.etiket, kurus:Number(k.kurus || 0)})),
      ...gorunenKalemler.filter(k => k.kurus < 0).map(k => ({etiket:k.etiket, kurus:Math.abs(k.kurus)}))
    ];
    const kalemSatirlari = kalemler => kalemler.map(k => paraSatiri(k.etiket, k.kurus)).join('') || '<p class="py-3 text-xs text-slate-500">Bu bölümde kayıtlı kalem bulunmuyor.</p>';
    const ozetSatirlari = [
      paraSatiri('Kazanç toplamı', i.kazanc_toplami_kurus),
      paraSatiri('Yasal kesinti toplamı', i.yasal_kesinti_toplami_kurus),
      paraSatiri('Özel kesinti toplamı', i.ozel_kesinti_toplami_kurus),
      paraSatiri('Kesintiler toplamı', i.toplam_kesinti_kurus),
      paraSatiri('Asgari ücret gelir vergisi istisnası', i.vergi_indirimleri?.asgari_ucret_istisna_gv_kurus ?? Math.round(Number(indirim.asgari_ucret_istisna_gv || 0) * 100)),
      paraSatiri('Asgari ücret damga vergisi istisnası', i.vergi_indirimleri?.asgari_ucret_istisna_dv_kurus ?? Math.round(Number(indirim.asgari_ucret_istisna_dv || 0) * 100)),
      paraSatiri('Net ödenen', i.banka_net_kurus, 'text-primary')
    ].join('');
    const bolum = (ikon, baslik, icerik) => `<section class="card p-4 border border-slate-100 dark:border-slate-800"><div class="flex items-center gap-2 mb-2"><span class="material-symbols-outlined text-primary">${ikon}</span><h5 class="font-bold text-slate-900 dark:text-white">${esc(baslik)}</h5></div><dl>${icerik}</dl></section>`;
    const parasalBolum = (tur, ikon, baslik, icerik) => `<section class="rounded-2xl border p-4 ${tur === 'gelir' ? 'bg-emerald-50/70 border-emerald-200 dark:bg-emerald-900/15 dark:border-emerald-800' : 'bg-rose-50/70 border-rose-200 dark:bg-rose-900/15 dark:border-rose-800'}"><div class="flex items-center gap-2 mb-2"><span class="material-symbols-outlined ${tur === 'gelir' ? 'text-emerald-600' : 'text-rose-600'}">${ikon}</span><h5 class="font-bold ${tur === 'gelir' ? 'text-emerald-800 dark:text-emerald-300' : 'text-rose-800 dark:text-rose-300'}">${esc(baslik)}</h5></div><dl>${icerik}</dl></section>`;

    return `<details open class="mt-4 group"><summary class="list-none cursor-pointer rounded-2xl bg-primary/5 border border-primary/15 px-4 py-3 flex items-center justify-between gap-3"><span class="flex items-center gap-2 font-bold text-primary"><span class="material-symbols-outlined">fact_check</span>Ücret pusulası ayrıntıları</span><span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span></summary><div class="mt-3 space-y-3">${parasalBolum('gelir', 'add_card', 'Gelirler', kalemSatirlari(gelirler))}${parasalBolum('gider', 'remove_circle', 'Kesintiler', kalemSatirlari(giderler))}${bolum('summarize', 'Özet bilgiler', ozetSatirlari)}<section class="card p-4 border border-slate-100 dark:border-slate-800"><div class="flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary">calendar_month</span><h5 class="font-bold text-slate-900 dark:text-white">Çalışma ve izin günleri</h5></div><div class="grid grid-cols-2 gap-2">${gunKartlari}</div></section>${bolum('account_balance', 'Yasal matrah bilgileri', matrahSatirlari)}</div></details>`;
  }

  function sekmelereAyir(root, d) {
    const mevcut = Array.from(root.children);
    const itirazIcerigi = mevcut.pop();
    if (!itirazIcerigi) return;
    const acikTalep = (d.talepler || []).filter(t => t.durum !== 'sonuclandi').length;
    const sekmeler = document.createElement('div');
    sekmeler.className = 'sticky top-0 z-10 -mx-1 mb-4 rounded-2xl bg-white/95 dark:bg-slate-900/95 p-1 shadow-sm ring-1 ring-slate-200 dark:ring-slate-700 backdrop-blur flex gap-1';
    sekmeler.setAttribute('role', 'tablist');
    sekmeler.innerHTML = `<button type="button" role="tab" data-bordro-tab="detay" class="min-h-[44px] flex-1 rounded-xl px-3 py-2 text-xs font-bold flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-lg">receipt_long</span>Pusula Detayı</button><button type="button" role="tab" data-bordro-tab="itiraz" class="min-h-[44px] flex-1 rounded-xl px-3 py-2 text-xs font-bold flex items-center justify-center gap-1.5"><span class="material-symbols-outlined text-lg">support_agent</span>İtiraz / İnceleme${acikTalep ? `<span class="min-w-5 h-5 px-1 rounded-full bg-rose-500 text-white text-[10px] flex items-center justify-center">${esc(acikTalep)}</span>` : ''}</button>`;
    const detayPanel = document.createElement('div');
    detayPanel.dataset.bordroPanel = 'detay';
    detayPanel.setAttribute('role', 'tabpanel');
    mevcut.forEach(node => detayPanel.appendChild(node));
    const itirazPanel = document.createElement('div');
    itirazPanel.dataset.bordroPanel = 'itiraz';
    itirazPanel.setAttribute('role', 'tabpanel');
    itirazPanel.appendChild(itirazIcerigi);
    root.replaceChildren(sekmeler, detayPanel, itirazPanel);

    const sekmeAc = ad => {
      aktifSekme = ad;
      root.querySelectorAll('[data-bordro-panel]').forEach(panel => panel.classList.toggle('hidden', panel.dataset.bordroPanel !== ad));
      root.querySelectorAll('[data-bordro-tab]').forEach(button => {
        const seciliMi = button.dataset.bordroTab === ad;
        button.setAttribute('aria-selected', seciliMi ? 'true' : 'false');
        button.classList.toggle('bg-primary', seciliMi);
        button.classList.toggle('text-white', seciliMi);
        button.classList.toggle('text-slate-500', !seciliMi);
        button.classList.toggle('dark:text-slate-300', !seciliMi);
      });
    };
    root.querySelectorAll('[data-bordro-tab]').forEach(button => button.addEventListener('click', () => sekmeAc(button.dataset.bordroTab)));
    sekmeAc(aktifSekme);
  }

  function detay(d) {
    secili = d;
    const i = d.icerik;
    const beyanAlinir = ['yayinda', 'test'].includes(d.durum);
    const modalBaslik = $('resmi-bordro-modal-baslik');
    if (modalBaslik) modalBaslik.textContent = `${i.donem} · Sürüm ${d.surum}`;
    const root = $('resmi-bordro-detay');
    if (!root) return;
    const resmiOzet = '';
    const resmiDetay = i.icerik_turu === 'ucret_hesap_pusulasi' ? resmiPusulaDetayi(i, d) : '';
    root.innerHTML = `${d.durum === 'test' ? '<div class="mb-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 p-3 flex gap-3 text-amber-700 dark:text-amber-400"><span class="material-symbols-outlined">science</span><p class="text-xs font-medium">Bu bildirimsiz bir test yayınıdır. Görüntüleme ve okuma beyanı gerçek akışla aynı şekilde kaydedilir.</p></div>' : ''}<div class="rounded-3xl bg-gradient-primary text-white p-5 shadow-lg shadow-primary/20 relative overflow-hidden"><div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div><p class="text-xs text-white/75 font-medium">Bankadan ödenecek net tutar</p><p class="text-3xl font-extrabold mt-1">${esc(para(i.banka_net_kurus))}</p><div class="mt-4 pt-3 border-t border-white/20 flex justify-between text-xs"><span>${esc(i.baslangic)} – ${esc(i.bitis)}</span><span>${esc(durum(d.durum))}</span></div></div><div class="mt-4 px-1"><p class="font-bold text-slate-900 dark:text-white">${esc(i.personel)}</p><p class="text-xs text-slate-500">${esc(i.departman)} · ${esc(i.gorev)}</p></div><div class="grid grid-cols-2 gap-3 my-4"><div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-[10px] uppercase text-slate-400 font-bold">Çalışma günü</p><p class="font-bold text-slate-900 dark:text-white mt-1">${i.calisma_gun} gün</p></div><div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-[10px] uppercase text-slate-400 font-bold">Fiilî gün</p><p class="font-bold text-slate-900 dark:text-white mt-1">${i.fiili_gun} gün</p></div></div>${resmiOzet}<div class="card p-4 border border-slate-100 dark:border-slate-800"><div class="flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary">receipt_long</span><h4 class="font-bold text-slate-900 dark:text-white">Resmî ödeme kalemleri</h4></div><dl>${i.kalemler.map(k => `<div class="flex justify-between gap-4 py-3 border-b border-slate-100 dark:border-slate-800 last:border-0"><dt class="text-sm text-slate-600 dark:text-slate-300">${esc(k.etiket)}</dt><dd class="text-sm font-bold text-slate-900 dark:text-white whitespace-nowrap">${esc(para(k.kurus))}</dd></div>`).join('')}</dl></div>${resmiDetay}<a class="mt-4 w-full py-3 rounded-xl border border-primary/30 text-primary font-bold flex items-center justify-center gap-2" href="pages/bordro-goster.php?token=${encodeURIComponent(d.token)}"><span class="material-symbols-outlined">picture_as_pdf</span>PDF indir</a><div class="mt-5 rounded-2xl border ${d.beyan_tarihi ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-900/10' : 'border-primary/20 bg-primary/5'} ${!d.beyan_tarihi && beyanAlinir ? 'bordro-onay-dikkat' : ''} p-4">${d.beyan_tarihi ? `<div class="flex gap-3"><span class="material-symbols-outlined text-emerald-600">verified</span><div><h4 class="font-bold text-emerald-700 dark:text-emerald-400">Beyanınız kaydedildi</h4><p class="text-sm mt-1 text-slate-600 dark:text-slate-300">${esc(d.beyan_metni)}</p><p class="text-xs text-slate-400 mt-2">${esc(d.beyan_tarihi)}</p></div></div>` : beyanAlinir ? `<form id="resmi-beyan-form"><h4 class="font-bold text-slate-900 dark:text-white mb-3">Okuma beyanı</h4><label class="flex gap-3 items-start cursor-pointer"><input type="checkbox" name="okudum" class="mt-0.5 w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary" required><span class="text-sm text-slate-700 dark:text-slate-200">${esc(d.beyan_metni)}</span></label><button type="submit" class="btn-primary w-full py-3 mt-4 flex items-center justify-center gap-2"><span class="material-symbols-outlined text-lg">check_circle</span>Okudum beyanını kaydet</button></form>` : '<p class="text-sm text-slate-500">Bu sürüm için yeni okuma beyanı alınmıyor.</p>'}</div><div class="mt-6"><div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary">support_agent</span><h4 class="font-bold text-slate-900 dark:text-white">İnceleme talepleri</h4></div><p class="text-xs text-slate-500 mt-1">Dökümle ilgili kontrol edilmesini istediğiniz konuyu iletebilirsiniz.</p>${talepBolumu(d)}</div>`;
    if (i.icerik_turu === 'ucret_hesap_pusulasi') {
      Array.from(root.querySelectorAll('h4')).find(el => el.textContent.trim() === 'Resmî ödeme kalemleri')?.closest('.card')?.remove();
    }
    sekmelereAyir(root, d);
    const onayButon = root.querySelector('#resmi-beyan-form button[type="submit"]');
    if (onayButon) onayButon.innerHTML = '<span class="material-symbols-outlined text-lg">check_circle</span>Ücret pusulasını onayla';
    const onayBaslik = root.querySelector('#resmi-beyan-form h4');
    if (onayBaslik) onayBaslik.textContent = 'Elektronik Ücret Pusulası Bildirimi';
    const tamamBaslik = root.querySelector('.material-symbols-outlined.text-emerald-600')?.parentElement?.querySelector('h4');
    if (tamamBaslik) tamamBaslik.textContent = 'Ücret pusulanız onaylandı';
    const itirazForm = root.querySelector('#resmi-talep-form');
    itirazForm?.querySelector('textarea')?.insertAdjacentHTML('afterend', '<label for="resmi-talep-dosya" class="form-label mt-3">Belge / fotoğraf (isteğe bağlı)</label><input id="resmi-talep-dosya" class="form-input w-full" type="file" name="dosya" accept="application/pdf,image/jpeg,image/png,image/webp"><p class="text-[10px] text-slate-400 mt-1">PDF, JPG, PNG veya WebP · en fazla 10 MB</p>');
    root.querySelector('#resmi-beyan-form')?.addEventListener('submit', e => { e.preventDefault(); if (e.target.elements.okudum.checked) kaydet('bordro-yayin-beyan', {okudum:'1', beyan_metin_surumu:secili.beyan_metin_surumu}); });
    root.querySelector('#resmi-talep-form')?.addEventListener('submit', e => { e.preventDefault(); const veri = {konu:e.target.elements.konu.value, mesaj:e.target.elements.mesaj.value}; if (e.target.elements.dosya?.files?.[0]) veri.dosya = e.target.elements.dosya.files[0]; kaydet('bordro-yayin-talep', veri); });
  }

  async function ac(token) {
    if (isleniyor) return;
    isleniyor = true;
    try {
      mesaj('');
      const d = await api('bordro-yayin-detay', {token});
      aktifSekme = 'detay';
      detay(d);
      modalAc('resmi-bordro-modal');
      api('bordro-yayin-goruntule', {token}).then(guncel => {
        if (guncel) secili = guncel;
      }).catch(err => console.warn('Görüntüleme loglama uyarısı:', err));
    } catch (e) {
      mesaj(e.message, true);
    } finally {
      isleniyor = false;
    }
  }

  async function kaydet(action, data) {
    if (isleniyor || !secili) return;
    isleniyor = true;
    document.querySelectorAll('#resmi-bordro-detay button').forEach(b => b.disabled = true);
    try {
      const d = await api(action, {token:secili.token, ...data});
      aktifSekme = action === 'bordro-yayin-talep' ? 'itiraz' : 'detay';
      detay(d);
      mesaj(action === 'bordro-yayin-beyan' ? 'Ücret pusulası onayınız kaydedildi.' : 'İtirazınız alınmıştır.');
      await liste();
    } catch (e) {
      mesaj(e.message, true);
    } finally {
      isleniyor = false;
      document.querySelectorAll('#resmi-bordro-detay button').forEach(b => b.disabled = false);
    }
  }

  function baslat() {
    if (!$('resmi-bordro-panel')) return;
    $('resmi-bordro-kapat')?.addEventListener('click', () => modalKapat('resmi-bordro-modal'));
    $('resmi-bordro-modal')?.addEventListener('click', (e) => {
      if (e.target === $('resmi-bordro-modal')) modalKapat('resmi-bordro-modal');
    });
    liste().then(() => {
      const token = new URLSearchParams(location.search).get('dokum');
      if (token) ac(token);
    }).catch(e => mesaj(e.message, true));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', baslat);
  } else {
    baslat();
  }
})();
