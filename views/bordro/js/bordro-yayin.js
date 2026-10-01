(() => {
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const para = v => new Intl.NumberFormat('tr-TR', {style:'currency',currency:'TRY'}).format(v / 100);
  
  const bildirimBadge = d => {
    switch (d) {
      case 'gonderildi': return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check me-1"></i>Gönderildi</span>';
      case 'isleniyor': return '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bx bx-loader bx-spin me-1"></i>Gönderiliyor</span>';
      case 'bekliyor': return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Kuyrukta</span>';
      case 'basarisiz': return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Başarısız</span>';
      default: return '<span class="text-muted">—</span>';
    }
  };

  const durumBadge = d => {
    switch (d) {
      case 'yayinda': return '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-circle me-1"></i>Yayında</span>';
      case 'test': return '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-test-tube me-1"></i>Bildirimsiz Test</span>';
      case 'revizyon': return '<span class="badge bg-orange-subtle text-warning border border-warning-subtle"><i class="bx bx-revision me-1"></i>Revizyon</span>';
      case 'arsiv': return '<span class="badge bg-secondary-subtle text-secondary border">Arşiv</span>';
      default: return `<span class="badge bg-light text-dark border">${esc(d)}</span>`;
    }
  };

  let tablo, kayitlar = [], onizleme = null, filtre = '', isleniyor = false;
  let seciliOnizlemeIndex = 0;
  let detayModal = null;
  const el = id => document.getElementById(id);
  
  const mesaj = (t, isError = true) => {
    const box = el('bordroYayinMesaj');
    if (!box) return;
    if (!t) {
      box.textContent = '';
      box.classList.add('d-none');
      return;
    }
    box.textContent = t;
    box.className = isError ? 'alert alert-danger py-2 px-3 mb-3' : 'alert alert-success py-2 px-3 mb-3';
    box.classList.remove('d-none');
  };

  async function api(action, data = {}) {
    const body = new FormData();
    Object.entries({action, ...window.bordroYayinConfig, ...data}).forEach(([k,v]) => body.append(k,v));
    let j;
    try {
      const r = await fetch('views/bordro/api.php', {method:'POST', body, credentials:'same-origin', cache:'no-store'});
      j = await r.json();
    } catch (e) {
      throw new Error('Sunucuya ulaşılamadı. Bağlantınızı kontrol edip tekrar deneyin.');
    }
    if (j.status !== 'success') throw new Error(j.message || 'İşlem tamamlanamadı.');
    return j.data;
  }

  function suz() {
    return kayitlar.filter(r => {
      if (!filtre) return true;
      if (filtre === 'goruntuleyen') return Boolean(r.goruntuleme_tarihi && r.goruntuleme_tarihi !== '—');
      if (filtre === 'beyan') return Boolean(r.beyan_tarihi && r.beyan_tarihi !== 'Bekleniyor' && r.beyan_tarihi !== '—');
      if (filtre === 'talep') return (r.acik_talep || 0) > 0;
      if (filtre === 'bekleyen') return r.durum === 'yayinda' && (!r.beyan_tarihi || r.beyan_tarihi === 'Bekleniyor');
      if (filtre === 'arsiv') return r.durum !== 'yayinda';
      return true;
    });
  }

  function ciz() {
    const suzulen = suz();
    tablo.clear().rows.add(suzulen).draw();
    const aktif = kayitlar.filter(r => r.durum === 'yayinda' || r.durum === 'revizyon');
    const goruntuleyen = aktif.filter(r => r.goruntuleme_tarihi && r.goruntuleme_tarihi !== '—').length;
    const beyanVeren = aktif.filter(r => r.beyan_tarihi && r.beyan_tarihi !== 'Bekleniyor' && r.beyan_tarihi !== '—').length;
    const acikTalep = kayitlar.reduce((toplam, r) => toplam + (r.acik_talep || 0), 0);

    if (el('statYayinlanan')) el('statYayinlanan').textContent = aktif.length;
    if (el('statGoruntuleyen')) el('statGoruntuleyen').textContent = goruntuleyen;
    if (el('statBeyanVeren')) el('statBeyanVeren').textContent = beyanVeren;
    if (el('statTalepVar')) el('statTalepVar').textContent = acikTalep;
    if (el('tabBadgeTakipAdet')) el('tabBadgeTakipAdet').textContent = aktif.length || kayitlar.length;
    if (el('bordroYayinTabloSayisi')) el('bordroYayinTabloSayisi').textContent = `${suzulen.length} / ${kayitlar.length} Kayıt`;
  }

  async function yukle() {
    kayitlar = await api('yayin-takip');
    ciz();
  }

  async function calistir(fn) {
    if (isleniyor) return;
    isleniyor = true;
    mesaj('');
    if (el('bordroYayinOnizle')) el('bordroYayinOnizle').disabled = true;
    if (el('btnOnizlemeYenile')) el('btnOnizlemeYenile').disabled = true;
    if (el('btnTakipYenile')) el('btnTakipYenile').disabled = true;
    const takipIcon = el('btnTakipYenile')?.querySelector('i');
    if (takipIcon) takipIcon.classList.add('bx-spin');
    try {
      await fn();
    } catch (e) {
      mesaj(e.message, true);
    } finally {
      isleniyor = false;
      if (el('bordroYayinOnizle')) el('bordroYayinOnizle').disabled = false;
      if (el('btnOnizlemeYenile')) el('btnOnizlemeYenile').disabled = false;
      if (el('btnTakipYenile')) el('btnTakipYenile').disabled = false;
      if (takipIcon) takipIcon.classList.remove('bx-spin');
    }
  }

  /* ================= MASTER-DETAIL ÖNİZLEME RENDER ================= */

  function renderOnizlemePusula(kayit) {
    const container = el('onizlemePusulaContainer');
    if (!container) return;

    if (!kayit) {
      container.innerHTML = `
        <div class="card-body p-5 text-center text-muted d-flex flex-column align-items-center justify-content-center h-100">
          <div class="avatar-md mb-3">
            <span class="avatar-title bg-light text-primary rounded-circle fs-1 p-3">
              <i class="bx bx-receipt"></i>
            </span>
          </div>
          <h6 class="fw-bold text-dark mt-2 fs-15">Bordro Pusula Önizlemesi</h6>
          <p class="mb-0 text-muted fs-13">Detayları ve kazanç kalemlerini incelemek için soldaki listeden bir personel seçin.</p>
        </div>`;
      return;
    }

    const initial = (kayit.personel || '').trim().charAt(0).toUpperCase();

    container.innerHTML = `
      <div class="p-3 d-flex flex-column gap-3">
        <!-- Personel Başlık Kartı -->
        <div class="card border-0 rounded-3 text-white shadow-sm mb-0" style="background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); padding: 14px 18px;">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center" style="gap: 14px;">
              <div class="flex-shrink-0 d-flex align-items-center justify-content-center fw-bold fs-15" style="width: 40px; height: 40px; min-width: 40px; background: rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.25) !important; border-radius: 8px;">
                ${esc(initial)}
              </div>
              <div>
                <h5 class="fw-bold mb-0 text-white fs-15" style="color: #ffffff !important; line-height: 1.3;">${esc(kayit.personel)}</h5>
                <p class="fs-12 text-white-50 mb-0 mt-0.5">${esc(kayit.donem)} · ${esc(kayit.departman || '—')} / ${esc(kayit.gorev || '—')}</p>
              </div>
            </div>
            <div class="d-flex gap-2">
              <span class="fs-12 px-3 py-1.5 rounded-pill fw-semibold d-inline-flex align-items-center" style="background: rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.25) !important;">
                <i class="bx bx-calendar-check me-1.5"></i>Çalışma: ${kayit.calisma_gun || 0} gün
              </span>
              <span class="fs-12 px-3 py-1.5 rounded-pill fw-semibold d-inline-flex align-items-center" style="background: rgba(255, 255, 255, 0.15) !important; color: #ffffff !important; border: 1px solid rgba(255, 255, 255, 0.25) !important;">
                <i class="bx bx-time me-1.5"></i>Fiilî: ${kayit.fiili_gun || 0} gün
              </span>
            </div>
          </div>
        </div>

        <!-- Kalemler Tablosu -->
        <div class="card border rounded-3 p-0 bg-white shadow-none overflow-hidden mb-0" style="border: 1px solid #e2e8f0 !important;">
          <div class="d-flex align-items-center justify-content-between px-3 py-2.5 bg-light border-bottom" style="border-color: #e2e8f0 !important;">
            <span class="fw-bold text-dark fs-13"><i class="bx bx-list-ul text-primary me-1.5"></i> Hesaplanmış Kazanç ve Kesinti Kalemleri</span>
            <span class="badge bg-white text-muted border fs-11 px-2.5 py-1">${esc(kayit.baslangic || '')} – ${esc(kayit.bitis || '')}</span>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="border-collapse: collapse;">
              <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #cbd5e1;">
                  <th class="ps-3 py-2 text-muted fs-11 fw-bold text-uppercase">Kalem Açıklaması</th>
                  <th class="pe-3 py-2 text-muted fs-11 fw-bold text-uppercase text-end">Tutar</th>
                </tr>
              </thead>
              <tbody>
                ${(kayit.kalemler || []).map(k => `
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td class="py-2.5 text-dark ps-3 fs-13">${esc(k.etiket)}</td>
                    <td class="py-2.5 text-end fw-semibold pe-3 fs-13 text-dark">${esc(para(k.kurus))}</td>
                  </tr>
                `).join('')}
                <tr style="background: #eff6ff; border-top: 2px solid #bfdbfe;">
                  <td class="py-3 text-primary ps-3 fw-bold fs-14">Bankadan Ödenecek Net Tutar</td>
                  <td class="py-3 text-end text-primary fs-16 fw-bold pe-3">${esc(para(kayit.banka_net_kurus))}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Hızlı Test Aksiyonu -->
        <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded-3 border" style="border: 1px solid #e2e8f0 !important;">
          <div class="fs-12 text-muted">
            <i class="bx bx-info-circle text-primary me-1.5"></i>Bu personele bildirim gitmeden tekil PWA testi oluşturabilirsiniz.
          </div>
          <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1.5 fw-semibold" onclick="document.getElementById('bordroYayinTestYayinla')?.click();" style="border-radius: 6px; padding: 6px 14px; font-size: 12.5px;">
            <i class="bx bx-test-tube fs-5"></i> Bu Personeli Test Et
          </button>
        </div>
      </div>`;
  }

  function renderOnizlemeListesi(aramaMetni = '') {
    const listContainer = el('onizlemePersonelListe');
    if (!listContainer || !onizleme) return;

    const filtreliKayitlar = (onizleme.kayitlar || []).map((k, idx) => ({ ...k, originalIndex: idx }))
      .filter(k => {
        if (!aramaMetni) return true;
        const q = aramaMetni.toLowerCase();
        return (k.personel || '').toLowerCase().includes(q) || (k.departman || '').toLowerCase().includes(q) || (k.gorev || '').toLowerCase().includes(q);
      });

    if (el('onizlemeListeSayisi')) {
      el('onizlemeListeSayisi').textContent = `${filtreliKayitlar.length} / ${onizleme.kayitlar?.length || 0} Personel`;
    }

    if (!filtreliKayitlar.length) {
      listContainer.innerHTML = '<div class="p-4 text-center text-muted fs-13"><i class="bx bx-search fs-3 text-muted opacity-50 mb-1 d-block"></i>Aramaya uygun personel bulunamadı.</div>';
      renderOnizlemePusula(null);
      return;
    }

    listContainer.innerHTML = filtreliKayitlar.map(k => {
      const isSelected = k.originalIndex === seciliOnizlemeIndex;
      const initial = (k.personel || '').trim().charAt(0).toUpperCase();
      return `
        <div class="onizleme-item ${isSelected ? 'active' : ''}" data-onizleme-idx="${k.originalIndex}">
          <input type="radio" name="bordroTestPersonel" value="${esc(k.secim_token)}" class="form-check-input d-none" ${isSelected ? 'checked' : ''}>
          <div class="d-flex align-items-center min-w-0" style="gap: 12px;">
            <div class="personel-avatar">${esc(initial)}</div>
            <div class="min-w-0" style="padding-left: 2px;">
              <span class="personel-name d-block text-truncate">${esc(k.personel)}</span>
              <small class="text-muted text-truncate d-block fs-11">${esc(k.departman || '—')} · ${esc(k.gorev || '—')}</small>
            </div>
          </div>
          <div class="text-end flex-shrink-0 ps-2">
            <span class="personel-net d-block">${esc(para(k.banka_net_kurus))}</span>
            <span class="badge bg-light text-secondary border fs-11 px-1.5 py-0.5">${k.fiili_gun || 0} gün</span>
          </div>
        </div>`;
    }).join('');

    listContainer.querySelectorAll('[data-onizleme-idx]').forEach(item => {
      item.addEventListener('click', () => {
        seciliOnizlemeIndex = Number(item.dataset.onizlemeIdx);
        renderOnizlemeListesi(el('onizlemeArama')?.value || '');
        renderOnizlemePusula(onizleme.kayitlar[seciliOnizlemeIndex]);
      });
    });

    const activeRecord = onizleme.kayitlar[seciliOnizlemeIndex] || filtreliKayitlar[0];
    renderOnizlemePusula(activeRecord);
  }

  function dislananlariGoster() {
    const modalEl = el('bordroYayinDislananlarModal');
    if (!modalEl) return;

    const tumDislananlar = onizleme?.dislananlar || [];
    if (!tumDislananlar.length) {
      Swal.fire({
        title: 'Dışlanan Personel Yok',
        text: 'Bu döneme ait yayından hariç tutulan pasif veya ayrılmış personel bulunmuyor.',
        icon: 'info',
        confirmButtonText: 'Tamam',
        confirmButtonColor: '#4f46e5'
      });
      return;
    }

    // Gerekçeleri grupla
    const gerekceler = {};
    tumDislananlar.forEach(d => {
      const g = (d.mesaj || 'Diğer Gerekçe').trim();
      if (!gerekceler[g]) gerekceler[g] = [];
      gerekceler[g].push(d);
    });

    const gerekceListesi = Object.keys(gerekceler);
    let aktifGerekce = '';
    let aktifArama = '';

    const renderIcerik = () => {
      const q = (aktifArama || '').toLowerCase().trim();
      
      const filteredGroups = {};
      Object.entries(gerekceler).forEach(([g, list]) => {
        if (aktifGerekce && g !== aktifGerekce) return;
        const matched = list.filter(item => {
          if (!q) return true;
          return (item.personel || '').toLowerCase().includes(q) || (item.mesaj || '').toLowerCase().includes(q);
        });
        if (matched.length) filteredGroups[g] = matched;
      });

      const toplamBulunan = Object.values(filteredGroups).reduce((acc, l) => acc + l.length, 0);

      if (el('dislananFiltreAdet')) {
        el('dislananFiltreAdet').textContent = `${toplamBulunan} / ${tumDislananlar.length} Personel`;
      }

      if (!toplamBulunan) {
        return `
          <div class="p-5 text-center text-muted bg-white border rounded-3">
            <i class="bx bx-search-alt fs-1 text-muted opacity-50 mb-2 d-block"></i>
            <p class="mb-0 fs-13">Arama kriterinize uygun dışlanan personel bulunamadı.</p>
          </div>`;
      }

      return Object.entries(filteredGroups).map(([g, list]) => `
        <div class="card border rounded-3 mb-3.5 bg-white overflow-hidden shadow-none" style="border: 1px solid #e2e8f0 !important;">
          <div class="bg-light px-4 py-3 border-bottom d-flex align-items-center justify-content-between" style="border-color: #e2e8f0 !important; min-height: 52px;">
            <div class="d-flex align-items-center gap-2.5">
              <span class="avatar-xs flex-shrink-0" style="width: 28px; height: 28px;">
                <span class="avatar-title bg-warning-subtle text-warning rounded-2 fs-6 px-1.5 py-1 d-flex align-items-center justify-content-center">
                  <i class="bx bx-info-circle"></i>
                </span>
              </span>
              <span class="fw-bold text-dark fs-13.5">${esc(g)}</span>
            </div>
            <span class="badge bg-white text-secondary border px-3 py-1.5 fs-11 fw-bold">${list.length} Personel</span>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="border-collapse: collapse;">
              <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                  <th class="ps-4 py-2.5 text-muted fs-11 fw-bold text-uppercase" style="width: 70px;">SIRA</th>
                  <th class="py-2.5 text-muted fs-11 fw-bold text-uppercase" style="min-width: 280px;">PERSONEL</th>
                  <th class="pe-4 py-2.5 text-muted fs-11 fw-bold text-uppercase">DURUM / GEREKÇE</th>
                </tr>
              </thead>
              <tbody>
                ${list.map((p, idx) => {
                  const initial = (p.personel || '').trim().charAt(0).toUpperCase();
                  return `
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td class="ps-4 py-3 text-muted fs-12 font-monospace">#${idx + 1}</td>
                      <td class="py-3">
                        <div class="d-flex align-items-center gap-3">
                          <div style="width: 34px; height: 34px; min-width: 34px; border-radius: 6px; background: #f1f5f9; color: #475569; font-weight: 700; font-size: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            ${esc(initial)}
                          </div>
                          <span class="fw-bold text-dark fs-13.5" style="white-space: nowrap;">${esc(p.personel)}</span>
                        </div>
                      </td>
                      <td class="pe-4 py-3 text-secondary fs-13">
                        <span class="badge bg-light text-secondary border fs-12 px-3 py-1.5"><i class="bx bx-info-circle me-1.5 text-muted"></i>${esc(p.mesaj)}</span>
                      </td>
                    </tr>`;
                }).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `).join('');
    };

    // Filtre haplarını çiz
    const pillsContainer = el('dislananFilterPills');
    if (pillsContainer) {
      pillsContainer.innerHTML = `
        <button type="button" class="btn btn-sm btn-primary active-pill px-3 py-1 fs-12 fw-semibold" data-gerekce="" style="border-radius: 6px;">
          Tümü (${tumDislananlar.length})
        </button>
        ${gerekceListesi.map(g => `
          <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1 fs-12" data-gerekce="${esc(g)}" style="border-radius: 6px;">
            ${esc(g.length > 45 ? g.substring(0, 45) + '...' : g)} (${gerekceler[g].length})
          </button>
        `).join('')}
      `;

      pillsContainer.querySelectorAll('[data-gerekce]').forEach(btn => {
        btn.addEventListener('click', () => {
          pillsContainer.querySelectorAll('[data-gerekce]').forEach(b => {
            b.className = 'btn btn-sm btn-outline-secondary px-2.5 py-1 fs-12';
          });
          btn.className = 'btn btn-sm btn-primary px-3 py-1 fs-12 fw-semibold';
          aktifGerekce = btn.dataset.gerekce || '';
          if (el('dislananListeGovde')) el('dislananListeGovde').innerHTML = renderIcerik();
        });
      });
    }

    const aramaInput = el('dislananAramaInput');
    if (aramaInput) {
      aramaInput.value = '';
      aramaInput.oninput = (e) => {
        aktifArama = e.target.value;
        if (el('dislananListeGovde')) el('dislananListeGovde').innerHTML = renderIcerik();
      };
    }

    if (el('dislananListeGovde')) {
      el('dislananListeGovde').innerHTML = renderIcerik();
    }

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
  }

  async function hazirlaVeGosterOnizleme() {
    await calistir(async () => {
      onizleme = await api('yayin-onizle');
      
      // Sekmeyi aktifleştir
      const onizlemeTabBtn = el('tab-onizleme-btn');
      if (onizlemeTabBtn) {
        bootstrap.Tab.getOrCreateInstance(onizlemeTabBtn).show();
      }

      el('onizlemeBosDurum')?.classList.add('d-none');
      el('onizlemeDoluDurum')?.classList.remove('d-none');

      const adet = onizleme.kayitlar?.length || 0;
      const hataAdet = onizleme.hatalar?.length || 0;
      const dislananAdet = onizleme.dislananlar?.length || 0;
      const toplamNet = (onizleme.kayitlar || []).reduce((acc, r) => acc + (r.banka_net_kurus || 0), 0);
      
      if (el('tabBadgeOnizlemeAdet')) {
        el('tabBadgeOnizlemeAdet').textContent = adet + ' Personel';
        el('tabBadgeOnizlemeAdet').className = hataAdet > 0 ? 'badge bg-danger ms-1' : 'badge bg-success ms-1';
      }

      if (el('onizlemeStatHazir')) el('onizlemeStatHazir').textContent = adet;
      if (el('onizlemeStatTutar')) el('onizlemeStatTutar').textContent = para(toplamNet);
      if (el('onizlemeStatHata')) {
        el('onizlemeStatHata').textContent = hataAdet > 0 ? `${hataAdet} Hata` : '0 Hata';
        el('onizlemeStatHata').className = hataAdet > 0 ? 'card-value-text text-danger' : 'card-value-text text-success';
      }
      if (el('onizlemeStatDislanan')) el('onizlemeStatDislanan').textContent = dislananAdet;
      if (el('toolbarDislananAdet')) el('toolbarDislananAdet').textContent = dislananAdet;
      if (el('btnToolbarDislanan')) el('btnToolbarDislanan').classList.toggle('d-none', !dislananAdet);

      // Yalnızca kritik hata bildirimleri
      const hatalarHtml = (onizleme.hatalar || []).map(h => `<div class="alert alert-danger py-2 px-3 mb-2 fs-12 d-flex align-items-center gap-2" style="border-radius: 8px;"><i class="bx bx-error fs-5"></i><div><strong>${esc(h.personel)}:</strong> ${esc(h.mesaj)}</div></div>`).join('');
      el('bordroYayinHatalar').innerHTML = hatalarHtml;

      el('bordroYayinYayinla').disabled = !!hataAdet || !adet;
      el('bordroYayinTestYayinla').disabled = !adet;

      seciliOnizlemeIndex = 0;
      renderOnizlemeListesi();
    });
  }

  /* ================= DETAY MODAL ================= */

  async function detay(token) {
    const d = await api('yayin-detay', {token});
    const govde = el('bordroYayinDetayGovde');
    if (!govde) return;

    const baslikEl = el('bordroYayinDetayBaslik');
    if (baslikEl) baslikEl.textContent = `${d.icerik?.personel || 'Personel'} · ${d.icerik?.donem || 'Dönem'}`;
    const altBaslikEl = el('bordroYayinDetayAltBaslik');
    if (altBaslikEl) altBaslikEl.textContent = `Sürüm ${d.surum} · ${d.durum === 'test' ? 'Bildirimsiz Test Yayını' : 'Resmî Bordro'}`;

    const i = d.icerik || {};
    const taleplerHtml = (d.talepler || []).map((t, n) => `
      <div class="card border rounded-3 mb-4 overflow-hidden shadow-none bg-white" style="border: 1px solid #e2e8f0 !important;">
        <!-- Talep Başlığı / Durum Barı -->
        <div class="px-4 py-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2" style="border-color: #e2e8f0 !important; min-height: 48px;">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary-subtle text-secondary border fs-11 fw-bold">Talep #${n + 1}</span>
            <span class="badge ${t.durum === 'acik' ? 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' : 'bg-success-subtle text-success border border-success-subtle'} px-2.5 py-1 fs-11 fw-semibold">
              <i class="bx ${t.durum === 'acik' ? 'bx-time-five' : 'bx-check-double'} me-1"></i>${t.durum === 'acik' ? 'Açık İnceleme Talebi' : 'Sonuçlandı'}
            </span>
          </div>
          <span class="text-muted fs-11"><i class="bx bx-calendar me-1"></i>${esc(t.tarih)}</span>
        </div>

        <div class="p-4">
          <!-- Modern Zaman Çizelgesi -->
          <div class="talep-timeline">
            
            <!-- 1. Adım: Personel Talebi -->
            <div class="talep-timeline-item">
              <div class="talep-timeline-marker marker-personel" title="Personel Talebi">
                <i class="bx bx-user"></i>
              </div>
              <div class="talep-bubble bubble-personel">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="fw-bold text-dark fs-13">${esc(d.icerik?.personel || 'Personel')}</span>
                  <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-11"><i class="bx bx-time-five me-1"></i>${esc(t.tarih)}</span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-10 px-2 py-0.5 fw-bold">Personel</span>
                  </div>
                </div>
                <p class="text-dark fs-13 mb-0 mt-0.5" style="line-height: 1.45; white-space: pre-wrap;">${esc(t.mesaj)}</p>
              </div>
            </div>

            <!-- 2. Adım: Yetkili Yanıtları (Varsa) -->
            ${(t.yanitlar || []).map(y => `
              <div class="talep-timeline-item">
                <div class="talep-timeline-marker marker-yetkili" title="Yetkili Yanıtı">
                  <i class="bx bx-check-shield"></i>
                </div>
                <div class="talep-bubble bubble-yetkili">
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold text-success-emphasis fs-13">${esc(y.kullanici || 'Yetkili')}</span>
                    <div class="d-flex align-items-center gap-2">
                      <span class="text-muted fs-11"><i class="bx bx-time me-1"></i>${esc(y.tarih)}</span>
                      <span class="badge bg-success text-white fs-10 px-2 py-0.5 fw-bold"><i class="bx bx-check me-0.5"></i> Yetkili</span>
                    </div>
                  </div>
                  <p class="text-dark fs-13 mb-0 mt-0.5 fw-medium" style="line-height: 1.45; white-space: pre-wrap;">${esc(y.mesaj)}</p>
                </div>
              </div>
            `).join('')}

            <!-- 3. Adım: Talep Açıksa Yanıt Formu -->
            ${t.durum === 'acik' ? `
              <div class="talep-timeline-item">
                <div class="talep-timeline-marker marker-bekliyor" title="Yetkili Yanıtı Bekleniyor">
                  <i class="bx bx-edit-alt"></i>
                </div>
                <div class="talep-bubble p-3" style="background: #fffdf5; border: 1px dashed #f59e0b;">
                  <form data-talep="${n}">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                      <label for="yayinYanit${n}" class="form-label fs-12 fw-bold text-dark mb-0 d-flex align-items-center gap-1">
                        <i class="bx bx-edit text-warning fs-5"></i> Gerekçeli Sonuç Yanıtı
                      </label>
                      <span class="badge bg-warning text-dark fs-10 px-2 py-0.5">Yanıt Bekliyor</span>
                    </div>
                    <textarea id="yayinYanit${n}" name="mesaj" class="form-control form-control-sm bg-white" rows="3" minlength="5" maxlength="4000" placeholder="Personele iletilecek inceleme sonucunuzu yazın..." required style="border: 1px solid #cbd5e1; font-size: 13px;"></textarea>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2.5">
                      <small class="text-muted fs-11"><i class="bx bx-info-circle me-1 text-primary"></i>Yanıtınız personele PWA üzerinden gösterilir ve talep sonuçlandırılır.</small>
                      <button class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1.5 px-3 py-1.5 fw-semibold" type="submit" style="border-radius: 6px;">
                        <i class="bx bx-send"></i> Yanıtla ve Sonuçlandır
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            ` : ''}

          </div>
        </div>
      </div>
    `).join('');

    const olaylarHtml = (d.olaylar || []).map(o => `
      <div class="d-flex align-items-start gap-2 py-1 border-bottom border-light">
        <span class="badge bg-light text-dark border fs-11">${esc(o.tarih)}</span>
        <span class="fw-semibold fs-12 text-secondary">${esc(o.tur)}</span>
        <span class="text-muted fs-12 ms-auto">${o.detay ? esc(o.detay) : ''}</span>
      </div>
    `).join('');

    govde.innerHTML = `
      <!-- Üst Özet Kartı -->
      <div class="card border-0 rounded-4 bg-primary text-white p-4 shadow-sm mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div>
            <p class="text-white-50 fs-12 text-uppercase fw-bold mb-1">Bankadan Ödenecek Net Tutar</p>
            <h2 class="display-6 fw-bold text-white mb-0">${esc(para(i.banka_net_kurus))}</h2>
          </div>
          <div class="text-end">
            <div class="d-inline-flex gap-2">
              <span class="badge bg-white bg-opacity-25 fs-12 px-3 py-2 rounded-pill">
                Çalışma: ${i.calisma_gun || 0} gün
              </span>
              <span class="badge bg-white bg-opacity-25 fs-12 px-3 py-2 rounded-pill">
                Fiilî: ${i.fiili_gun || 0} gün
              </span>
            </div>
            <p class="fs-12 text-white-50 mt-2 mb-0">Dönem: ${esc(i.baslangic || '')} – ${esc(i.bitis || '')}</p>
          </div>
        </div>
      </div>

      <!-- Döküm Kalemleri -->
      <div class="card border rounded-3 p-3 mb-4 shadow-none">
        <h6 class="fw-bold text-dark mb-3"><i class="bx bx-receipt text-primary me-1"></i> Resmî Döküm Kalemleri</h6>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
            <tbody>
              ${(i.kalemler || []).map(k => `
                <tr>
                  <td class="py-2 text-secondary ps-2">${esc(k.etiket)}</td>
                  <td class="py-2 text-end fw-bold text-dark pe-2">${esc(para(k.kurus))}</td>
                </tr>
              `).join('')}
              <tr class="table-primary fw-bold">
                <td class="py-2 text-primary ps-2">Bankadan Ödenecek Net</td>
                <td class="py-2 text-end text-primary fs-14 pe-2">${esc(para(i.banka_net_kurus))}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Okuma Beyanı Durumu -->
      <div class="card border rounded-3 p-3 mb-4 shadow-none ${d.beyan_tarihi ? 'border-success-subtle bg-success-subtle bg-opacity-10' : 'border-warning-subtle bg-warning-subtle bg-opacity-10'}">
        <div class="d-flex align-items-start gap-3">
          <div class="avatar-xs flex-shrink-0">
            <span class="avatar-title rounded-circle ${d.beyan_tarihi ? 'bg-success text-white' : 'bg-warning text-dark'} p-2 d-flex align-items-center justify-center">
              <i class="bx ${d.beyan_tarihi ? 'bx-check-double' : 'bx-time-five'} fs-5"></i>
            </span>
          </div>
          <div class="flex-grow-1">
            <h6 class="fw-bold mb-1 ${d.beyan_tarihi ? 'text-success' : 'text-warning text-dark'}">
              ${d.beyan_tarihi ? 'Okuma Beyanı Onaylandı' : 'Okuma Beyanı Bekleniyor'}
            </h6>
            <p class="fs-13 text-secondary mb-1">${esc(d.beyan_metni || '')}</p>
            <p class="fs-12 text-muted mb-0">
              ${d.beyan_tarihi ? `<i class="bx bx-calendar-check me-1"></i>Onay Tarihi: ${esc(d.beyan_tarihi)}` : '<i class="bx bx-info-circle me-1"></i>Personel henüz PWA üzerinden okudum onayını vermedi.'}
            </p>
          </div>
        </div>
      </div>

      <!-- İnceleme Talepleri -->
      <div class="card border rounded-3 p-3 mb-4 shadow-none">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h6 class="fw-bold text-dark mb-0"><i class="bx bx-support text-primary me-1"></i> İnceleme Talepleri (${(d.talepler || []).length})</h6>
        </div>
        ${taleplerHtml || '<p class="text-muted fs-13 mb-0">Bu döküm için personel tarafından iletilen herhangi bir inceleme talebi bulunmuyor.</p>'}
      </div>

      <!-- İşlem Geçmişi (Audit) -->
      <div class="accordion" id="yayinOlaylarAccordion">
        <div class="accordion-item border rounded-3">
          <h2 class="accordion-header" id="headingOlaylar">
            <button class="accordion-button collapsed py-2 fs-13 fw-semibold text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOlaylar" aria-expanded="false" aria-controls="collapseOlaylar">
              <i class="bx bx-history me-1 text-primary"></i> İşlem Geçmişi ve Güvenlik Özeti
            </button>
          </h2>
          <div id="collapseOlaylar" class="accordion-collapse collapse" aria-labelledby="headingOlaylar" data-bs-parent="#yayinOlaylarAccordion">
            <div class="accordion-body p-3 fs-12">
              <p class="text-muted mb-2 font-monospace">İçerik SHA-256: ${esc(d.icerik_hash)}</p>
              ${olaylarHtml || '<p class="text-muted mb-0">İşlem kaydı bulunamadı.</p>'}
            </div>
          </div>
        </div>
      </div>
    `;

    govde.querySelectorAll('[data-talep]').forEach(f => {
      f.addEventListener('submit', e => {
        e.preventDefault();
        const submitBtn = f.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        calistir(async () => {
          const talepIndex = Number(f.dataset.talep);
          await api('yayin-yanitla', {
            talep_token: d.talepler[talepIndex].token,
            mesaj: f.elements.mesaj.value
          });
          await Swal.fire({
            icon: 'success',
            title: 'Yanıt Kaydedildi',
            text: 'İnceleme talebi sonuçlandırıldı ve personele iletildi.',
            timer: 2000,
            showConfirmButton: true,
            confirmButtonText: 'Tamam'
          });
          await detay(token);
          await yukle();
        });
      });
    });

    if (!detayModal) {
      detayModal = new bootstrap.Modal(el('bordroYayinDetayModal'));
    }
    detayModal.show();
  }

  /* ================= INIT ================= */

  document.addEventListener('DOMContentLoaded', () => {
    if (!el('bordroYayinAc')) return;
    const text = $.fn.dataTable.render.text();
    
    tablo = $('#bordroYayinTable').DataTable(applyLengthStateSave({
      ...getDatatableOptions(),
      data: [],
      order: [[0, 'asc']],
      columns: [
        {
          data: 'personel',
          render: (val, type, r) => `
            <div class="d-flex align-items-center gap-2">
              <div class="avatar-xs flex-shrink-0">
                <span class="avatar-title bg-light text-primary rounded-circle fw-bold fs-12 p-2 d-flex align-items-center justify-center">
                  ${esc(val.charAt(0))}
                </span>
              </div>
              <div>
                <span class="fw-bold text-dark d-block">${esc(val)}</span>
                <span class="text-muted fs-11">${esc(r.departman || '')}</span>
              </div>
            </div>`
        },
        {
          data: 'surum',
          render: v => `<span class="badge bg-light text-secondary border px-2 py-1">${esc(v)}</span>`
        },
        {
          data: 'durum',
          render: v => durumBadge(v)
        },
        {
          data: 'yayin_tarihi',
          render: text
        },
        {
          data: 'goruntuleme_tarihi',
          defaultContent: '—',
          render: v => v && v !== '—' ? `<span class="text-success"><i class="bx bx-check me-1"></i>${esc(v)}</span>` : '<span class="text-muted">—</span>'
        },
        {
          data: 'beyan_tarihi',
          defaultContent: 'Bekleniyor',
          render: v => v && v !== 'Bekleniyor' ? `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-double me-1"></i>${esc(v)}</span>` : '<span class="badge bg-light text-muted border">Bekleniyor</span>'
        },
        {
          data: null,
          render: r => {
            if (r.acik_talep > 0) {
              return `<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bx bx-error-circle me-1"></i>${r.acik_talep} açık / ${r.talep_sayisi} talep</span>`;
            }
            if (r.talep_sayisi > 0) {
              return `<span class="badge bg-secondary-subtle text-secondary">${r.talep_sayisi} talep</span>`;
            }
            return '<span class="text-muted">—</span>';
          }
        },
        {
          data: null,
          render: r => bildirimBadge(r.ilk_bildirim_durumu)
        },
        {
          data: null,
          orderable: false,
          searchable: false,
          render: r => `<button type="button" class="btn btn-sm btn-outline-primary yayin-detay d-inline-flex align-items-center gap-1" data-token="${esc(r?.token || '')}"><i class="bx bx-search-alt"></i> İncele</button>`
        }
      ]
    }));

    $('#bordroYayinTable').on('click', '.yayin-detay', function () {
      let tr = $(this).closest('tr');
      if (tr.hasClass('child')) tr = tr.prev();
      const r = tablo.row(tr).data();
      const token = $(this).data('token') || r?.token;
      if (token) {
        calistir(() => detay(token));
      }
    });

    el('bordroYayinModal')?.addEventListener('shown.bs.modal', () => tablo.columns.adjust());

    el('bordroYayinAc')?.addEventListener('click', () => {
      new bootstrap.Modal(el('bordroYayinModal')).show();
      calistir(yukle);
    });

    document.querySelectorAll('[data-yayin-filtre]').forEach(b => {
      b.addEventListener('click', () => {
        filtre = b.dataset.yayinFiltre || '';
        document.querySelectorAll('[data-yayin-filtre]').forEach(x => {
          const isActive = (x.dataset.yayinFiltre || '') === filtre;
          x.classList.toggle('active-card', isActive);
          x.classList.toggle('active', isActive);
        });
        ciz();
      });
    });

    // Sekme 1 Takip Tetikleyicileri
    el('btnTakipYenile')?.addEventListener('click', () => calistir(yukle));

    // Sekme 2 Önizleme Tetikleyicileri
    el('bordroYayinOnizle')?.addEventListener('click', hazirlaVeGosterOnizleme);
    el('btnOnizlemeYenile')?.addEventListener('click', hazirlaVeGosterOnizleme);
    el('btnDislananKpiCard')?.addEventListener('click', dislananlariGoster);
    el('btnToolbarDislanan')?.addEventListener('click', dislananlariGoster);

    // Önizleme arama kutusu
    el('onizlemeArama')?.addEventListener('input', (e) => {
      renderOnizlemeListesi(e.target.value);
    });

    el('bordroYayinTestYayinla')?.addEventListener('click', () => calistir(async () => {
      if (!onizleme) return;
      const secili = document.querySelector('input[name="bordroTestPersonel"]:checked');
      if (!secili) { mesaj('Test edilecek personeli seçin.', true); return; }
      const kayit = onizleme.kayitlar.find(i => i.secim_token === secili.value);
      const r = await Swal.fire({
        title: 'Bildirimsiz Personel Testi',
        text: `${kayit?.personel || 'Seçilen personel'} için PWA dökümü oluşturulacak. Push bildirim kuyruğu oluşturulmayacak.`,
        showCancelButton: true,
        confirmButtonText: 'Test Yayınını Oluştur',
        cancelButtonText: 'Vazgeç',
        icon: 'info'
      });
      if (!r.isConfirmed) return;
      el('bordroYayinTestYayinla').disabled = true;
      await api('yayin-test-yayinla', {onizleme_hash: onizleme.onizleme_hash, personel_token: secili.value});
      
      await Swal.fire('Test Yayını Hazır', 'Bildirimsiz test yayını oluşturuldu. Yalnız seçilen personelin PWA hesabında görünür.', 'success');
      
      // Takip sekmesine geri dönüp listeyi yenile
      const takipTabBtn = el('tab-takip-btn');
      if (takipTabBtn) bootstrap.Tab.getOrCreateInstance(takipTabBtn).show();
      await yukle();
    }));

    el('bordroYayinYayinla')?.addEventListener('click', () => calistir(async () => {
      if (!onizleme || onizleme.hatalar.length) return;
      const r = await Swal.fire({
        title: 'Resmî Dökümleri Yayınla',
        text: `${onizleme.kayitlar.length} personele ait kontrol edilen dökümler yayınlanacak ve bildirim kuyruğuna alınacak.`,
        showCancelButton: true,
        confirmButtonText: 'Evet, Yayınla',
        cancelButtonText: 'Vazgeç',
        icon: 'question'
      });
      if (!r.isConfirmed) return;
      el('bordroYayinYayinla').disabled = true;
      const data = await api('yayin-yayinla', {onizleme_hash: onizleme.onizleme_hash});
      
      await Swal.fire(
        data.mevcut ? 'Zaten Yayında' : 'Yayınlandı',
        data.mevcut ? 'Bu dönemin güncel sürümü zaten yayınlanmış.' : 'Dökümler personele yayınlandı ve bildirimler kuyruğa alındı.',
        'success'
      );
      
      // Takip sekmesine dön ve yenile
      const takipTabBtn = el('tab-takip-btn');
      if (takipTabBtn) bootstrap.Tab.getOrCreateInstance(takipTabBtn).show();
      await yukle();
    }));

    el('bordroYayinCsv')?.addEventListener('click', () => {
      const safe = v => '"' + String(v ?? '').replace(/^[=+@\-\t\r]/, "'$&").replace(/"/g, '""') + '"';
      const rows = [
        ['Personel', 'Dönem', 'Sürüm', 'Durum', 'Yayın Tarihi', 'Görüntüleme', 'Okuma Beyanı', 'Talep Sayısı', 'Açık Talep', 'Push Durumu', 'Başarısız Bildirim'],
        ...tablo.rows({search: 'applied'}).data().toArray().map(r => [
          r.personel, r.donem, r.surum, r.durum, r.yayin_tarihi, r.goruntuleme_tarihi, r.beyan_tarihi, r.talep_sayisi, r.acik_talep, r.ilk_bildirim_durumu, r.basarisiz_bildirim
        ])
      ];
      const url = URL.createObjectURL(new Blob(['\ufeff' + rows.map(r => r.map(safe).join(';')).join('\r\n')], {type: 'text/csv;charset=utf-8'}));
      const a = document.createElement('a');
      a.href = url;
      a.download = 'bordro-yayin-takibi.csv';
      a.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
  });
})();
