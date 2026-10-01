/* Resmî döküm yalnız ağdan okunur; beyan çevrimdışı kuyruğa girmez. */
(() => {
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const para = v => new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'}).format(v / 100);
  const durum = v => ({yayinda:'Yayında', test:'Test yayını', revizyon:'Revizyon sürecinde', arsiv:'Arşiv'})[v] || v;
  const durumSinifi = v => ({yayinda:'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400', test:'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400', revizyon:'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400', arsiv:'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'})[v] || 'bg-slate-100 text-slate-600';
  let secili = null;
  let isleniyor = false;
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
      return `<button type="button" data-dokum="${n}" class="w-full card p-0 text-left overflow-hidden border border-slate-100 dark:border-slate-800 shadow-sm active:scale-[0.99] transition-transform cursor-pointer"><div class="p-4 flex items-start gap-3"><div class="w-11 h-11 shrink-0 rounded-2xl ${tamam ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20' : 'bg-primary/10 text-primary'} flex items-center justify-center"><span class="material-symbols-outlined">${tamam ? 'task_alt' : 'description'}</span></div><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><h3 class="font-bold text-slate-900 dark:text-white">${esc(r.donem)}</h3><p class="text-xs text-slate-500 mt-0.5">Sürüm ${esc(r.surum)} · ${esc(r.yayin_tarihi)}</p></div><span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold ${durumSinifi(r.durum)}">${esc(durum(r.durum))}</span></div><div class="mt-4 flex items-end justify-between gap-3"><div><p class="text-[10px] uppercase tracking-wide text-slate-400 font-bold">Bankadan ödenecek net</p><p class="text-xl font-extrabold text-slate-900 dark:text-white mt-0.5">${esc(para(r.banka_net_kurus))}</p></div><span class="material-symbols-outlined text-slate-300">chevron_right</span></div></div></div><div class="px-4 py-2.5 text-xs font-semibold ${tamam ? 'bg-emerald-50/70 text-emerald-700 dark:bg-emerald-900/10 dark:text-emerald-400' : 'bg-amber-50/70 text-amber-700 dark:bg-amber-900/10 dark:text-amber-400'}">${tamam ? `Okuma beyanı ${esc(r.beyan_tarihi)} tarihinde kaydedildi` : 'Okuma beyanınız bekleniyor'}</div></button>`;
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
    const talepler = (d.talepler || []).map(t => `<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 mt-3"><div class="flex items-center justify-between gap-2 mb-2"><span class="text-xs font-bold ${t.durum === 'acik' ? 'text-amber-600' : 'text-emerald-600'}">${t.durum === 'acik' ? 'İnceleniyor' : 'Sonuçlandı'}</span><small class="text-[11px] text-slate-400">${esc(t.tarih)}</small></div><p class="text-sm text-slate-700 dark:text-slate-200">${esc(t.mesaj)}</p>${(t.yanitlar || []).map(y => `<div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-xs font-bold text-primary mb-1">Yetkili yanıtı</p><p class="text-sm">${esc(y.mesaj)}</p><small class="text-[10px] text-slate-400">${esc(y.tarih)}</small></div>`).join('')}</article>`).join('');
    if ((d.talepler || []).some(t => t.durum === 'acik')) return talepler + '<p class="text-xs text-amber-600 mt-3">Açık talebiniz yetkilinin incelemesini bekliyor.</p>';
    return talepler + `<form id="resmi-talep-form" class="mt-4"><label for="resmi-talep-mesaj" class="form-label">İnceleme talebi açıklaması</label><textarea id="resmi-talep-mesaj" class="form-input w-full min-h-[100px]" name="mesaj" minlength="10" maxlength="4000" placeholder="Kontrol edilmesini istediğiniz konuyu açıklayın" required></textarea><button type="submit" class="w-full mt-3 py-3 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold">İnceleme talebi oluştur</button></form>`;
  }

  function detay(d) {
    secili = d;
    const i = d.icerik;
    const beyanAlinir = ['yayinda', 'test'].includes(d.durum);
    const modalBaslik = $('resmi-bordro-modal-baslik');
    if (modalBaslik) modalBaslik.textContent = `${i.donem} · Sürüm ${d.surum}`;
    const root = $('resmi-bordro-detay');
    if (!root) return;
    root.innerHTML = `${d.durum === 'test' ? '<div class="mb-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 p-3 flex gap-3 text-amber-700 dark:text-amber-400"><span class="material-symbols-outlined">science</span><p class="text-xs font-medium">Bu bildirimsiz bir test yayınıdır. Görüntüleme ve okuma beyanı gerçek akışla aynı şekilde kaydedilir.</p></div>' : ''}<div class="rounded-3xl bg-gradient-primary text-white p-5 shadow-lg shadow-primary/20 relative overflow-hidden"><div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div><p class="text-xs text-white/75 font-medium">Bankadan ödenecek net tutar</p><p class="text-3xl font-extrabold mt-1">${esc(para(i.banka_net_kurus))}</p><div class="mt-4 pt-3 border-t border-white/20 flex justify-between text-xs"><span>${esc(i.baslangic)} – ${esc(i.bitis)}</span><span>${esc(durum(d.durum))}</span></div></div><div class="mt-4 px-1"><p class="font-bold text-slate-900 dark:text-white">${esc(i.personel)}</p><p class="text-xs text-slate-500">${esc(i.departman)} · ${esc(i.gorev)}</p></div><div class="grid grid-cols-2 gap-3 my-4"><div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-[10px] uppercase text-slate-400 font-bold">Çalışma günü</p><p class="font-bold text-slate-900 dark:text-white mt-1">${i.calisma_gun} gün</p></div><div class="rounded-2xl bg-slate-50 dark:bg-slate-800 p-3"><p class="text-[10px] uppercase text-slate-400 font-bold">Fiilî gün</p><p class="font-bold text-slate-900 dark:text-white mt-1">${i.fiili_gun} gün</p></div></div><div class="card p-4 border border-slate-100 dark:border-slate-800"><div class="flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary">receipt_long</span><h4 class="font-bold text-slate-900 dark:text-white">Döküm kalemleri</h4></div><dl>${i.kalemler.map(k => `<div class="flex justify-between gap-4 py-3 border-b border-slate-100 dark:border-slate-800 last:border-0"><dt class="text-sm text-slate-600 dark:text-slate-300">${esc(k.etiket)}</dt><dd class="text-sm font-bold text-slate-900 dark:text-white whitespace-nowrap">${esc(para(k.kurus))}</dd></div>`).join('')}</dl></div><a class="mt-4 w-full py-3 rounded-xl border border-primary/30 text-primary font-bold flex items-center justify-center gap-2" href="pages/bordro-goster.php?token=${encodeURIComponent(d.token)}"><span class="material-symbols-outlined">picture_as_pdf</span>PDF indir</a><div class="mt-5 rounded-2xl border ${d.beyan_tarihi ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-800 dark:bg-emerald-900/10' : 'border-primary/20 bg-primary/5'} p-4">${d.beyan_tarihi ? `<div class="flex gap-3"><span class="material-symbols-outlined text-emerald-600">verified</span><div><h4 class="font-bold text-emerald-700 dark:text-emerald-400">Beyanınız kaydedildi</h4><p class="text-sm mt-1 text-slate-600 dark:text-slate-300">${esc(d.beyan_metni)}</p><p class="text-xs text-slate-400 mt-2">${esc(d.beyan_tarihi)}</p></div></div>` : beyanAlinir ? `<form id="resmi-beyan-form"><h4 class="font-bold text-slate-900 dark:text-white mb-3">Okuma beyanı</h4><label class="flex gap-3 items-start cursor-pointer"><input type="checkbox" name="okudum" class="mt-0.5 w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary" required><span class="text-sm text-slate-700 dark:text-slate-200">${esc(d.beyan_metni)}</span></label><button type="submit" class="btn-primary w-full py-3 mt-4 flex items-center justify-center gap-2"><span class="material-symbols-outlined text-lg">check_circle</span>Okudum beyanını kaydet</button></form>` : '<p class="text-sm text-slate-500">Bu sürüm için yeni okuma beyanı alınmıyor.</p>'}</div><div class="mt-6"><div class="flex items-center gap-2"><span class="material-symbols-outlined text-primary">support_agent</span><h4 class="font-bold text-slate-900 dark:text-white">İnceleme talepleri</h4></div><p class="text-xs text-slate-500 mt-1">Dökümle ilgili kontrol edilmesini istediğiniz konuyu iletebilirsiniz.</p>${talepBolumu(d)}</div>`;
    root.querySelector('#resmi-beyan-form')?.addEventListener('submit', e => { e.preventDefault(); if (e.target.elements.okudum.checked) kaydet('bordro-yayin-beyan', {okudum:'1', beyan_metin_surumu:secili.beyan_metin_surumu}); });
    root.querySelector('#resmi-talep-form')?.addEventListener('submit', e => { e.preventDefault(); kaydet('bordro-yayin-talep', {mesaj:e.target.elements.mesaj.value}); });
  }

  async function ac(token) {
    if (isleniyor) return;
    isleniyor = true;
    try {
      mesaj('');
      const d = await api('bordro-yayin-detay', {token});
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
      detay(d);
      mesaj(action === 'bordro-yayin-beyan' ? 'Okuma beyanınız kaydedildi.' : 'İnceleme talebiniz oluşturuldu.');
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
