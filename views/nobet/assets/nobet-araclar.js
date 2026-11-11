(function () {
    'use strict';

    const API = 'views/nobet/api.php';
    const EXPORT = 'views/nobet/cizelge-export.php';
    const TIP_KOD = { standart: 'N', hafta_sonu: 'HS', resmi_tatil: 'RT', ozel: 'Ö' };
    const TIP_AD = { standart: 'Standart', hafta_sonu: 'Hafta Sonu', resmi_tatil: 'Resmi Tatil', ozel: 'Özel' };
    const GUN_KISA = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];

    let mode = 'takvim';
    let gridTumPersonel = false;

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function toast(message, error) {
        if (typeof Toastify === 'function') {
            Toastify({
                text: message, duration: 3500, gravity: 'top', position: 'center', stopOnFocus: true,
                style: { background: error ? '#dc2626' : '#000000', borderRadius: '8px', boxShadow: '0 4px 12px rgba(0,0,0,0.15)' }
            }).showToast();
        } else if (window.Swal) {
            Swal.fire({ text: message, icon: error ? 'error' : 'success', timer: 2500, showConfirmButton: false });
        }
    }

    async function api(action, data) {
        const body = new URLSearchParams({ action: action });
        Object.keys(data || {}).forEach(function (k) {
            const v = data[k];
            if (Array.isArray(v)) {
                v.forEach(function (x) { body.append(k + '[]', x); });
            } else if (v !== undefined && v !== null) {
                body.append(k, v);
            }
        });
        const res = await fetch(API, { method: 'POST', body: body });
        const json = await res.json();
        if (json.status === 'error' || json.success === false) {
            throw new Error(json.message || 'İşlem başarısız.');
        }
        return json;
    }

    function dmYtoIso(v) {
        const m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec((v || '').trim());
        return m ? m[3] + '-' + m[2] + '-' + m[1] : '';
    }

    function isoToTr(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? m[3] + '.' + m[2] + '.' + m[1] : '';
    }

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    function localIso(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    function cal() { return window.nobetCalendar || null; }

    // =====================================================
    // Çakışma uyarısı: sunucu "warning" döndüğünde onay iste
    // =====================================================
    const origFetch = window.fetch.bind(window);
    window.fetch = async function (input, init) {
        const res = await origFetch(input, init);
        try {
            const url = typeof input === 'string' ? input : (input && input.url) || '';
            if (url.indexOf(API) === -1 || !init || !init.body) return res;
            const raw = init.body;
            const action = raw instanceof FormData ? raw.get('action') : new URLSearchParams(String(raw)).get('action');
            if (['add-nobet', 'drop-personel', 'move-nobet'].indexOf(action) === -1) return res;

            const data = await res.clone().json();
            if (!data || data.status !== 'warning' || !Array.isArray(data.uyarilar)) return res;

            const html = '<ul class="text-start mb-0 font-size-13">' + data.uyarilar.map(function (u) { return '<li>' + esc(u.mesaj) + '</li>'; }).join('') + '</ul>';
            const karar = await Swal.fire({
                title: 'Çakışma Uyarısı', html: html, icon: 'warning', showCancelButton: true,
                confirmButtonText: 'Yine de Devam Et', cancelButtonText: 'Vazgeç'
            });
            if (karar.isConfirmed) {
                let yeniBody;
                if (raw instanceof FormData) {
                    yeniBody = raw;
                    yeniBody.set('uyari_onay', '1');
                } else {
                    yeniBody = new URLSearchParams(String(raw));
                    yeniBody.set('uyari_onay', '1');
                }
                return origFetch(input, Object.assign({}, init, { body: yeniBody }));
            }
            return new Response(JSON.stringify({ success: false, status: 'error', message: 'İşlem iptal edildi.' }), { headers: { 'Content-Type': 'application/json' } });
        } catch (e) {
            return res;
        }
    };

    // =====================================================
    // Görünüm verisi (ızgara, yazdırma, KPI)
    // =====================================================
    function rangeDays() {
        const c = cal();
        if (!c) return [];
        const start = new Date(c.view.currentStart);
        const end = new Date(c.view.currentEnd);
        const days = [];
        for (let d = new Date(start); d < end; d.setDate(d.getDate() + 1)) {
            days.push(new Date(d));
        }
        return days;
    }

    function activeEvents() {
        const c = cal();
        if (!c) return [];
        return c.getEvents().filter(function (e) { return e.extendedProps.durum !== 'iptal'; });
    }

    function collectGridData(tumPersonel) {
        const days = rangeDays();
        const iso = days.map(localIso);
        const events = activeEvents().filter(function (e) {
            const k = e.startStr.substring(0, 10);
            return iso.indexOf(k) !== -1;
        });

        const rows = {};
        events.forEach(function (e) {
            const pid = String(e.extendedProps.raw_personel_id);
            if (!rows[pid]) rows[pid] = { pid: pid, name: e.title, dept: e.extendedProps.departman || '', cells: {}, total: 0 };
            rows[pid].cells[e.startStr.substring(0, 10)] = e;
            rows[pid].total++;
        });

        if (tumPersonel) {
            document.querySelectorAll('.personel-item[data-is-active="1"]').forEach(function (el) {
                const pid = String(el.dataset.rawId);
                if (!rows[pid]) rows[pid] = { pid: pid, name: el.dataset.name, dept: el.dataset.departman || '', cells: {}, total: 0 };
            });
        }

        const list = Object.keys(rows).map(function (k) { return rows[k]; }).sort(function (a, b) { return a.name.localeCompare(b.name, 'tr'); });
        const dayTotals = {};
        iso.forEach(function (k) { dayTotals[k] = 0; });
        events.forEach(function (e) { dayTotals[e.startStr.substring(0, 10)]++; });

        return { days: days, iso: iso, rows: list, dayTotals: dayTotals };
    }

    function renderGrid() {
        const host = document.getElementById('nobet-grid');
        const c = cal();
        if (!host || !c) return;

        if (c.view.type === 'multiMonthYear') {
            host.innerHTML = '<div class="p-4 text-center text-muted">Izgara görünümü Ay ve Hafta görünümlerinde kullanılabilir.</div>';
            return;
        }

        const data = collectGridData(gridTumPersonel);
        const todayIso = localIso(new Date());

        let html = '<div class="nobet-grid-tools"><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" id="gridTumPersonel"' + (gridTumPersonel ? ' checked' : '') + '><label class="form-check-label font-size-12" for="gridTumPersonel">Nöbeti olmayan personeli de göster</label></div>';
        html += '<div class="nobet-grid-legend"><span class="lg n">N</span> Standart <span class="lg hs">HS</span> Hafta Sonu <span class="lg rt">RT</span> Resmi Tatil <span class="lg oz">Ö</span> Özel</div></div>';
        html += '<div class="nobet-grid-scroll"><table class="nobet-grid-table"><thead><tr><th class="sticky-col">Personel</th>';
        data.days.forEach(function (d, i) {
            const k = data.iso[i];
            const hafta = d.getDay() === 0 || d.getDay() === 6;
            const bos = data.dayTotals[k] === 0;
            html += '<th class="' + (hafta ? 'wk ' : '') + (bos ? 'bos ' : '') + (k === todayIso ? 'today' : '') + '"><div>' + d.getDate() + '</div><small>' + GUN_KISA[d.getDay()] + '</small></th>';
        });
        html += '<th>Top.</th></tr></thead><tbody>';

        data.rows.forEach(function (r) {
            html += '<tr><td class="sticky-col"><div class="gname">' + esc(r.name) + '</div><small>' + esc(r.dept) + '</small></td>';
            data.iso.forEach(function (k, i) {
                const d = data.days[i];
                const hafta = d.getDay() === 0 || d.getDay() === 6;
                const ev = r.cells[k];
                if (ev) {
                    const tip = ev.extendedProps.nobet_tipi || 'standart';
                    const bekleyen = ev.extendedProps.yonetici_onayi == 0 || ev.extendedProps.has_talep;
                    html += '<td class="' + (hafta ? 'wk' : '') + '"><span class="gchip tip-' + esc(tip) + (bekleyen ? ' pending' : '') + '" data-event-id="' + esc(ev.id) + '" title="' + esc(r.name + ' - ' + (TIP_AD[tip] || '') + (bekleyen ? ' (onay bekliyor)' : '')) + '">' + (TIP_KOD[tip] || 'N') + '</span></td>';
                } else {
                    html += '<td class="gempty ' + (hafta ? 'wk' : '') + '" data-date="' + k + '" data-pid="' + esc(r.pid) + '"></td>';
                }
            });
            html += '<td class="gtotal">' + r.total + '</td></tr>';
        });
        if (data.rows.length === 0) {
            html += '<tr><td colspan="' + (data.days.length + 2) + '" class="text-center text-muted py-4">Bu dönemde nöbet bulunmuyor.</td></tr>';
        }

        html += '</tbody><tfoot><tr><td class="sticky-col">Günlük Nöbetçi</td>';
        let genel = 0;
        data.iso.forEach(function (k) {
            const t = data.dayTotals[k];
            genel += t;
            html += '<td class="' + (t === 0 ? 'bos-cell' : '') + '">' + t + '</td>';
        });
        html += '<td class="gtotal">' + genel + '</td></tr></tfoot></table></div>';
        host.innerHTML = html;
    }

    function applyDayMarks() {
        const c = cal();
        const el = document.getElementById('nobet-calendar');
        if (!c || !el) return;

        const counts = {};
        activeEvents().forEach(function (e) {
            const k = e.startStr.substring(0, 10);
            counts[k] = (counts[k] || 0) + 1;
        });
        const max = Math.max.apply(null, Object.keys(counts).map(function (k) { return counts[k]; }).concat([1]));

        el.querySelectorAll('.fc-daygrid-day[data-date]').forEach(function (cell) {
            const k = cell.getAttribute('data-date');
            const n = counts[k] || 0;
            cell.classList.toggle('nobet-bos-gun', n === 0);
            const bucket = n === 0 ? 0 : Math.min(4, Math.max(1, Math.ceil((n / max) * 4)));
            cell.setAttribute('data-heat', String(bucket));
            cell.setAttribute('data-nobet-sayi', String(n));
        });
    }

    function bosGunler() {
        const data = collectGridData(false);
        return data.iso.filter(function (k) { return data.dayTotals[k] === 0; });
    }

    function updateKpis() {
        const c = cal();
        if (!c) return;
        const data = collectGridData(false);
        const bos = data.iso.filter(function (k) { return data.dayTotals[k] === 0; }).length;
        const bosEl = document.getElementById('stat-bos');
        if (bosEl) bosEl.textContent = bos;

        let pazar = 0;
        activeEvents().forEach(function (e) {
            const k = e.startStr.substring(0, 10);
            if (data.iso.indexOf(k) !== -1 && new Date(k + 'T00:00:00').getDay() === 0) pazar++;
        });
        const sub = document.getElementById('stat-total-sub');
        if (sub) sub.textContent = 'Pazar: ' + pazar + ' | Personel: ' + data.rows.length;
    }

    window.nobetRefresh = function () {
        applyDayMarks();
        updateKpis();
        if (mode === 'izgara') renderGrid();
    };

    function setMode(next) {
        mode = next;
        const calEl = document.getElementById('nobet-calendar');
        const grid = document.getElementById('nobet-grid');
        document.querySelectorAll('#nobet-mode-buttons .btn').forEach(function (b) {
            b.classList.toggle('active', b.dataset.mode === next);
        });
        if (!calEl || !grid) return;
        calEl.classList.toggle('nobet-heat-on', next === 'isi');
        if (next === 'izgara') {
            calEl.classList.add('d-none');
            grid.classList.remove('d-none');
            renderGrid();
        } else {
            grid.classList.add('d-none');
            calEl.classList.remove('d-none');
            if (cal()) cal().updateSize();
        }
    }

    // =====================================================
    // KPI kart hızlı filtreleri
    // =====================================================
    function flash(cell) {
        if (!cell) return;
        cell.classList.add('nobet-flash');
        cell.scrollIntoView({ block: 'center', behavior: 'smooth' });
        setTimeout(function () { cell.classList.remove('nobet-flash'); }, 2200);
    }

    function kpiAction(kind) {
        const c = cal();
        if (!c) return;
        if (kind === 'toplam') {
            const tum = document.querySelector('#personel-filter-list [data-personel-id="all"]');
            if (tum) tum.click();
            setMode('takvim');
            c.today();
            toast('Filtre temizlendi, bu aya dönüldü.');
        } else if (kind === 'bugun') {
            setMode('takvim');
            c.today();
            const todayIso = localIso(new Date());
            const ev = activeEvents().filter(function (e) { return e.startStr.substring(0, 10) === todayIso; })[0];
            setTimeout(function () {
                flash(document.querySelector('#nobet-calendar .fc-daygrid-day[data-date="' + todayIso + '"]'));
                if (ev && window.nobetShowDetail) window.nobetShowDetail(ev);
            }, 150);
            if (!ev) toast('Bugün için nöbetçi bulunmuyor.', true);
        } else if (kind === 'talep') {
            window.location.href = 'index.php?p=nobet/talepler';
        } else if (kind === 'bos') {
            const liste = bosGunler();
            if (!liste.length) { toast('Bu dönemde boş gün yok.'); return; }
            const bugun = localIso(new Date());
            const hedef = liste.filter(function (k) { return k >= bugun; })[0] || liste[0];
            if (mode === 'izgara') {
                const hucre = document.querySelector('#nobet-grid thead th.bos');
                flash(hucre);
            } else {
                flash(document.querySelector('#nobet-calendar .fc-daygrid-day[data-date="' + hedef + '"]'));
            }
            toast(liste.length + ' boş gün var. İlk boş gün: ' + isoToTr(hedef));
        }
    }

    // =====================================================
    // Yazdırma
    // =====================================================
    function yazdir() {
        const c = cal();
        if (!c || c.view.type === 'multiMonthYear') { toast('Yazdırma için Ay veya Hafta görünümünü seçin.', true); return; }
        const data = collectGridData(false);
        const baslik = c.view.title;
        let h = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>Nöbet Çizelgesi</title><style>';
        h += 'body{font-family:Arial,sans-serif;font-size:10px;margin:14px;color:#111}h2{margin:0 0 2px}p{margin:0 0 10px;color:#555}';
        h += 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #888;padding:2px 3px;text-align:center}th.n,td.n{text-align:left;white-space:nowrap}';
        h += 'th{background:#e5e7eb}.wk{background:#f1f5f9}.bos{background:#fee2e2}.sign{display:flex;justify-content:space-between;margin-top:40px}.sign div{width:30%;border-top:1px solid #000;text-align:center;padding-top:4px}';
        h += '@page{size:landscape;margin:10mm}</style></head><body>';
        h += '<h2>Nöbet Çizelgesi</h2><p>' + esc(baslik) + ' &middot; N: Standart, HS: Hafta Sonu, RT: Resmi Tatil, Ö: Özel</p><table><thead><tr><th class="n">Personel</th>';
        data.days.forEach(function (d, i) {
            const wk = d.getDay() === 0 || d.getDay() === 6;
            h += '<th class="' + (wk ? 'wk' : '') + '">' + d.getDate() + '<br>' + GUN_KISA[d.getDay()] + '</th>';
        });
        h += '<th>Top.</th></tr></thead><tbody>';
        data.rows.forEach(function (r) {
            h += '<tr><td class="n">' + esc(r.name) + '</td>';
            data.iso.forEach(function (k, i) {
                const d = data.days[i];
                const wk = d.getDay() === 0 || d.getDay() === 6;
                const ev = r.cells[k];
                h += '<td class="' + (wk ? 'wk' : '') + '">' + (ev ? (TIP_KOD[ev.extendedProps.nobet_tipi] || 'N') : '') + '</td>';
            });
            h += '<td><b>' + r.total + '</b></td></tr>';
        });
        h += '</tbody><tfoot><tr><td class="n"><b>Günlük Nöbetçi</b></td>';
        data.iso.forEach(function (k) { h += '<td class="' + (data.dayTotals[k] === 0 ? 'bos' : '') + '">' + data.dayTotals[k] + '</td>'; });
        h += '<td></td></tr></tfoot></table>';
        h += '<div class="sign"><div>Hazırlayan</div><div>Kontrol</div><div>Onaylayan</div></div>';
        h += '<p style="margin-top:14px">Yazdırma tarihi: ' + esc(new Date().toLocaleString('tr-TR')) + '</p></body></html>';

        const w = window.open('', '_blank');
        if (!w) { toast('Açılır pencere engellendi.', true); return; }
        w.document.open();
        w.document.write(h);
        w.document.close();
        w.focus();
        setTimeout(function () { w.print(); }, 300);
    }

    function currentMonthStr() {
        const c = cal();
        const d = c ? new Date(c.view.currentStart) : new Date();
        const mid = new Date(d.getFullYear(), d.getMonth(), 15);
        const ref = c && c.view.type === 'dayGridMonth' ? new Date(c.view.currentStart) : mid;
        return ref.getFullYear() + '-' + pad(ref.getMonth() + 1);
    }

    function exportFile(format) {
        let url = EXPORT + '?format=' + format + '&ay=' + encodeURIComponent(currentMonthStr());
        if (format === 'ics') {
            const aktif = document.querySelector('#personel-filter-list .dropdown-item.active');
            const rawId = aktif ? aktif.getAttribute('data-personel-id') : 'all';
            if (rawId && rawId !== 'all') {
                const kart = document.querySelector('.personel-item[data-raw-id="' + rawId + '"]');
                if (kart && kart.dataset.id) url += '&personel=' + encodeURIComponent(kart.dataset.id);
            }
        }
        window.location.href = url;
    }

    // =====================================================
    // Modallar
    // =====================================================
    function initSelect2(modalEl) {
        if (!window.jQuery || !jQuery.fn.select2) return;
        jQuery(modalEl).find('select.select2').each(function () {
            if (!jQuery(this).data('select2')) {
                jQuery(this).select2({ dropdownParent: jQuery(modalEl), width: '100%' });
            }
        });
    }

    function initDate(id, opts) {
        const el = document.getElementById(id);
        if (!el || !window.flatpickr || el._flatpickr) return;
        flatpickr(el, Object.assign({ locale: 'tr', dateFormat: 'd.m.Y', allowInput: true }, opts || {}));
    }

    function modal(id) {
        const el = document.getElementById(id);
        const inst = bootstrap.Modal.getOrCreateInstance(el);
        initSelect2(el);
        return { el: el, inst: inst };
    }

    function sonucListesi(sonuc) {
        let h = '<div class="alert alert-success py-2 mb-2 font-size-12">' + sonuc.eklenen.length + ' nöbet eklendi.</div>';
        if (sonuc.atlanan.length) {
            h += '<div class="alert alert-warning py-2 mb-0 font-size-12"><b>' + sonuc.atlanan.length + ' kayıt atlandı:</b><ul class="mb-0 ps-3" style="max-height:160px;overflow:auto">';
            sonuc.atlanan.forEach(function (a) { h += '<li>' + esc(a.ad) + ' - ' + esc(a.tarih) + ': ' + esc(a.neden) + '</li>'; });
            h += '</ul></div>';
        }
        return h;
    }

    // ----- Otomatik dağıtım -----
    let dagitimAtamalar = [];

    function dagitimAc() {
        const m = modal('nobetDagitimModal');
        const bugun = new Date();
        const sonrakiAy = new Date(bugun.getFullYear(), bugun.getMonth() + 1, 1);
        const sonrakiAyBit = new Date(bugun.getFullYear(), bugun.getMonth() + 2, 0);
        initDate('dag_baslangic', { defaultDate: sonrakiAy });
        initDate('dag_bitis', { defaultDate: sonrakiAyBit });
        document.getElementById('dagitimSonuc').classList.add('d-none');
        document.getElementById('btnDagitimKaydet').classList.add('d-none');
        m.inst.show();
    }

    function dagitimRender(res) {
        dagitimAtamalar = res.atamalar.slice();
        const govde = document.getElementById('dagitimTablo');
        govde.innerHTML = '';
        dagitimAtamalar.forEach(function (a, i) {
            govde.insertAdjacentHTML('beforeend', '<tr><td>' + esc(a.tarih_tr) + '</td><td>' + esc(a.gun) + (a.ozel_gun ? ' <span class="badge bg-info-subtle text-info">HS</span>' : '') + '</td><td>' + esc(a.ad) + '</td><td class="text-end"><button type="button" class="btn btn-sm btn-subtle-danger table-action-btn" data-dag-sil="' + i + '" title="Öneriden çıkar"><i class="bx bx-x"></i></button></td></tr>');
        });
        document.getElementById('dagitimAdet').textContent = dagitimAtamalar.length;
        document.getElementById('dagitimOzet').innerHTML = res.ozet.map(function (o) {
            return '<tr><td>' + esc(o.ad) + '</td><td class="text-end fw-semibold">' + o.adet + '</td></tr>';
        }).join('');
        const uyari = document.getElementById('dagitimBosUyari');
        if (res.bos_kalan.length) {
            uyari.classList.remove('d-none');
            uyari.textContent = 'Uygun personel bulunamayan günler: ' + res.bos_kalan.join(', ');
        } else {
            uyari.classList.add('d-none');
        }
        document.getElementById('dagitimSonuc').classList.remove('d-none');
        document.getElementById('btnDagitimKaydet').classList.toggle('d-none', dagitimAtamalar.length === 0);
    }

    async function dagitimOner() {
        const bas = dmYtoIso(document.getElementById('dag_baslangic').value);
        const bit = dmYtoIso(document.getElementById('dag_bitis').value);
        if (!bas || !bit) { toast('Başlangıç ve bitiş tarihini seçin.', true); return; }
        const gunler = Array.from(document.querySelectorAll('.dag-gun:checked')).map(function (x) { return x.value; });
        const personeller = jQuery('#dag_personel_ids').val() || [];
        const btn = document.getElementById('btnDagitimOner');
        btn.disabled = true;
        try {
            const res = await api('dagitim-oneri', {
                baslangic: bas, bitis: bit, gunler: gunler, personel_ids: personeller,
                strateji: jQuery('#dag_strateji').val(),
                kisi_sayisi: document.getElementById('dag_kisi_sayisi').value,
                aylik_limit: document.getElementById('dag_aylik_limit').value,
                ardisik_engel: document.getElementById('dag_ardisik_engel').checked ? '1' : '0'
            });
            dagitimRender(res);
        } catch (e) {
            toast(e.message, true);
        } finally {
            btn.disabled = false;
        }
    }

    async function dagitimKaydet() {
        if (!dagitimAtamalar.length) return;
        const onay = await Swal.fire({ title: 'Öneriler kaydedilsin mi?', text: dagitimAtamalar.length + ' nöbet oluşturulacak. İşlem Değişiklik Geçmişi\'nden geri alınabilir.', icon: 'question', showCancelButton: true, confirmButtonText: 'Kaydet', cancelButtonText: 'Vazgeç' });
        if (!onay.isConfirmed) return;
        const payload = dagitimAtamalar.map(function (a) { return { personel_id: a.personel_id, tarih: a.tarih }; });
        try {
            const res = await api('dagitim-kaydet', { atamalar: JSON.stringify(payload) });
            let html = '<div class="text-start font-size-13">' + sonucListesi(res) + '</div>';
            await Swal.fire({ title: 'Dağıtım Kaydedildi', html: html, icon: 'success' });
            bootstrap.Modal.getInstance(document.getElementById('nobetDagitimModal')).hide();
            cal() && cal().refetchEvents();
        } catch (e) {
            toast(e.message, true);
        }
    }

    // ----- Toplu ekle -----
    function topluAc() {
        const m = modal('nobetTopluModal');
        const el = document.getElementById('toplu_tarihler');
        if (window.flatpickr && !el._flatpickr) {
            flatpickr(el, { locale: 'tr', mode: 'multiple', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd.m.Y', conjunction: ', ' });
        }
        document.getElementById('topluSonuc').classList.add('d-none');
        m.inst.show();
    }

    async function topluKaydet() {
        const personel = jQuery('#toplu_personel').val();
        const tarihler = (document.getElementById('toplu_tarihler').value || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean);
        if (!personel || !tarihler.length) { toast('Personel ve en az bir gün seçin.', true); return; }
        try {
            const res = await api('toplu-nobet-ekle', {
                personel_id: personel, tarihler: tarihler, nobet_tipi: jQuery('#toplu_tip').val(),
                uyari_onay: document.getElementById('toplu_uyari_onay').checked ? '1' : '0'
            });
            const kutu = document.getElementById('topluSonuc');
            kutu.innerHTML = sonucListesi(res);
            kutu.classList.remove('d-none');
            cal() && cal().refetchEvents();
        } catch (e) {
            toast(e.message, true);
        }
    }

    // ----- Kopyala -----
    function kopyalaAc() {
        const m = modal('nobetKopyalaModal');
        const c = cal();
        const bas = c ? new Date(c.view.currentStart) : new Date();
        const bit = c ? new Date(c.view.currentEnd.getTime() - 86400000) : new Date();
        const hedef = new Date(bit.getTime() + 86400000);
        initDate('kop_kaynak_bas', { defaultDate: bas });
        initDate('kop_kaynak_bit', { defaultDate: bit });
        initDate('kop_hedef_bas', { defaultDate: hedef });
        document.getElementById('kopyalaSonuc').classList.add('d-none');
        m.inst.show();
    }

    async function kopyalaKaydet() {
        const kb = dmYtoIso(document.getElementById('kop_kaynak_bas').value);
        const kt = dmYtoIso(document.getElementById('kop_kaynak_bit').value);
        const hb = dmYtoIso(document.getElementById('kop_hedef_bas').value);
        if (!kb || !kt || !hb) { toast('Üç tarihi de seçin.', true); return; }
        try {
            const res = await api('nobet-kopyala', {
                kaynak_bas: kb, kaynak_bit: kt, hedef_bas: hb,
                uyari_onay: document.getElementById('kop_uyari_onay').checked ? '1' : '0'
            });
            const kutu = document.getElementById('kopyalaSonuc');
            kutu.innerHTML = sonucListesi(res);
            kutu.classList.remove('d-none');
            cal() && cal().refetchEvents();
        } catch (e) {
            toast(e.message, true);
        }
    }

    // ----- Geçmiş / geri al -----
    const ISLEM_AD = { ekle: 'Ekleme', sil: 'Silme', tasi: 'Taşıma', guncelle: 'Güncelleme' };

    async function gecmisAc() {
        const m = modal('nobetGecmisModal');
        m.inst.show();
        const govde = document.getElementById('gecmisTablo');
        govde.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Yükleniyor...</td></tr>';
        try {
            const res = await api('get-nobet-gecmisi', {});
            const gruplar = [];
            const goruldu = {};
            res.data.forEach(function (r) {
                if (r.grup) {
                    if (goruldu[r.grup]) { goruldu[r.grup].adet++; return; }
                    r.adet = 1;
                    goruldu[r.grup] = r;
                } else {
                    r.adet = 1;
                }
                gruplar.push(r);
            });
            if (!gruplar.length) {
                govde.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Henüz kayıtlı işlem yok.</td></tr>';
                return;
            }
            govde.innerHTML = gruplar.map(function (r) {
                const detay = r.adet > 1 ? r.adet + ' nöbetlik toplu işlem (' + esc(r.aciklama || '') + ' ...)' : esc(r.aciklama || '');
                const durum = r.geri_alindi ? '<span class="badge bg-secondary-subtle text-secondary">Geri alındı</span>' : '<button type="button" class="btn btn-sm btn-outline-primary py-0" data-geri-al="' + esc(r.id) + '" data-adet="' + r.adet + '"><i class="bx bx-undo"></i> Geri Al</button>';
                return '<tr><td>' + esc(r.tarih) + '</td><td>' + esc(r.kullanici || '-') + '</td><td>' + esc(ISLEM_AD[r.islem] || r.islem) + '</td><td>' + detay + '</td><td class="text-end">' + durum + '</td></tr>';
            }).join('');
        } catch (e) {
            govde.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + esc(e.message) + '</td></tr>';
        }
    }

    async function geriAl(id, adet) {
        const onay = await Swal.fire({
            title: 'İşlem geri alınsın mı?', text: adet > 1 ? 'Bu toplu işlemdeki ' + adet + ' nöbetin tamamı geri alınacak.' : 'Nöbet önceki durumuna döndürülecek.',
            icon: 'question', showCancelButton: true, confirmButtonText: 'Geri Al', cancelButtonText: 'Vazgeç'
        });
        if (!onay.isConfirmed) return;
        try {
            const res = await api('geri-al-nobet', { id: id });
            let msg = res.message;
            if (res.atlanan && res.atlanan.length) msg += ' Atlananlar: ' + res.atlanan.join('; ');
            toast(msg);
            cal() && cal().refetchEvents();
            gecmisAc();
        } catch (e) {
            toast(e.message, true);
        }
    }

    // ----- Bordro özeti -----
    async function bordroYukle() {
        const ay = document.getElementById('bordro_ay').value;
        const govde = document.getElementById('bordroTablo');
        const alt = document.getElementById('bordroToplam');
        govde.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Yükleniyor...</td></tr>';
        alt.innerHTML = '';
        try {
            const res = await api('get-bordro-ozeti', { ay: ay });
            if (!res.data.length) {
                govde.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Onaylı nöbet bulunmuyor.</td></tr>';
                return;
            }
            const top = { standart: 0, hafta_sonu: 0, resmi_tatil: 0, ozel: 0, toplam: 0 };
            govde.innerHTML = res.data.map(function (r) {
                ['standart', 'hafta_sonu', 'resmi_tatil', 'ozel', 'toplam'].forEach(function (k) { top[k] += Number(r[k]); });
                return '<tr><td>' + esc(r.adi_soyadi) + '</td><td>' + esc(r.departman || '') + '</td><td class="text-end">' + r.standart + '</td><td class="text-end">' + r.hafta_sonu + '</td><td class="text-end">' + r.resmi_tatil + '</td><td class="text-end">' + r.ozel + '</td><td class="text-end fw-bold">' + r.toplam + '</td></tr>';
            }).join('');
            alt.innerHTML = '<tr class="fw-bold"><td colspan="2">Genel Toplam</td><td class="text-end">' + top.standart + '</td><td class="text-end">' + top.hafta_sonu + '</td><td class="text-end">' + top.resmi_tatil + '</td><td class="text-end">' + top.ozel + '</td><td class="text-end">' + top.toplam + '</td></tr>';
        } catch (e) {
            govde.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">' + esc(e.message) + '</td></tr>';
        }
    }

    // ----- Adalet raporu -----
    async function adaletYukle() {
        const yil = jQuery('#adalet_yil').val();
        const govde = document.getElementById('adaletTablo');
        govde.innerHTML = '<tr><td colspan="16" class="text-center text-muted py-3">Yükleniyor...</td></tr>';
        try {
            const res = await api('get-adalet-raporu', { yil: yil });
            const skorRenk = res.skor >= 80 ? '#16a34a' : (res.skor >= 60 ? '#d97706' : '#dc2626');
            document.getElementById('adaletOzet').innerHTML =
                '<div><div class="nobet-skor" style="color:' + skorRenk + '">%' + res.skor + '</div><small class="text-muted">Adalet skoru</small></div>' +
                '<div><div class="fw-bold font-size-16">' + res.ortalama + '</div><small class="text-muted">Kişi başı ortalama</small></div>' +
                '<div><div class="fw-bold font-size-16">' + res.en_az + ' - ' + res.en_cok + '</div><small class="text-muted">En az - en çok</small></div>' +
                '<div><div class="fw-bold font-size-16">' + res.sapma + '</div><small class="text-muted">Standart sapma</small></div>';
            if (!res.personeller.length) {
                govde.innerHTML = '<tr><td colspan="16" class="text-center text-muted py-3">Bu yıl için nöbet kaydı yok.</td></tr>';
                return;
            }
            govde.innerHTML = res.personeller.map(function (p) {
                const oran = res.ortalama > 0 ? Math.round(p.toplam / res.ortalama * 100) : 0;
                const renk = oran > 130 ? '#dc2626' : (oran < 70 ? '#d97706' : '#3b82f6');
                let aylar = '';
                for (let a = 1; a <= 12; a++) {
                    const v = p.aylar[a] || 0;
                    aylar += '<td class="text-center' + (v === 0 ? ' text-muted' : '') + '">' + (v || '-') + '</td>';
                }
                return '<tr><td>' + esc(p.ad) + '</td>' + aylar + '<td class="text-end fw-bold">' + p.toplam + '</td><td class="text-end">' + p.ozel_gun + '</td><td><div class="d-flex align-items-center gap-2"><div class="nobet-adalet-bar flex-grow-1"><span style="width:' + Math.min(100, oran / 2) + '%;background:' + renk + '"></span></div><small>%' + oran + '</small></div></td></tr>';
            }).join('');
        } catch (e) {
            govde.innerHTML = '<tr><td colspan="16" class="text-center text-danger py-3">' + esc(e.message) + '</td></tr>';
        }
    }

    async function hatirlat() {
        const onay = await Swal.fire({ title: 'Hatırlatma gönderilsin mi?', text: 'Yarın onaylı nöbeti olan personele push bildirimi gönderilecek.', icon: 'question', showCancelButton: true, confirmButtonText: 'Gönder', cancelButtonText: 'Vazgeç' });
        if (!onay.isConfirmed) return;
        try {
            const res = await api('send-yarin-hatirlatma', {});
            toast(res.message);
        } catch (e) {
            toast(e.message, true);
        }
    }

    // =====================================================
    // Olay bağlama
    // =====================================================
    document.addEventListener('click', function (e) {
        const arac = e.target.closest('[data-nobet-arac]');
        if (arac) {
            e.preventDefault();
            const tur = arac.dataset.nobetArac;
            if (tur === 'dagitim') dagitimAc();
            else if (tur === 'toplu') topluAc();
            else if (tur === 'kopyala') kopyalaAc();
            else if (tur === 'gecmis') gecmisAc();
            else if (tur === 'adalet') { modal('nobetAdaletModal').inst.show(); adaletYukle(); }
            else if (tur === 'bordro') { modal('nobetBordroModal').inst.show(); bordroYukle(); }
            else if (tur === 'yazdir') yazdir();
            else if (tur === 'excel') exportFile('xlsx');
            else if (tur === 'ics') exportFile('ics');
            else if (tur === 'hatirlat') hatirlat();
            return;
        }

        const modeBtn = e.target.closest('#nobet-mode-buttons .btn');
        if (modeBtn) {
            const hedef = modeBtn.dataset.mode;
            setMode(mode === hedef && hedef !== 'takvim' ? 'takvim' : hedef);
            return;
        }

        const kpi = e.target.closest('.nobet-kpi-click');
        if (kpi) { kpiAction(kpi.dataset.kpi); return; }

        const sil = e.target.closest('[data-dag-sil]');
        if (sil) {
            dagitimAtamalar.splice(Number(sil.dataset.dagSil), 1);
            const mevcutOzet = { atamalar: dagitimAtamalar, ozet: [], bos_kalan: [] };
            const sayac = {};
            dagitimAtamalar.forEach(function (a) { sayac[a.ad] = (sayac[a.ad] || 0) + 1; });
            mevcutOzet.ozet = Object.keys(sayac).map(function (k) { return { ad: k, adet: sayac[k] }; }).sort(function (a, b) { return b.adet - a.adet; });
            dagitimRender(mevcutOzet);
            return;
        }

        const geri = e.target.closest('[data-geri-al]');
        if (geri) { geriAl(geri.dataset.geriAl, Number(geri.dataset.adet || 1)); return; }

        if (e.target.closest('#btnDagitimOner')) { dagitimOner(); return; }
        if (e.target.closest('#btnDagitimKaydet')) { dagitimKaydet(); return; }
        if (e.target.closest('#btnTopluKaydet')) { topluKaydet(); return; }
        if (e.target.closest('#btnKopyalaKaydet')) { kopyalaKaydet(); return; }

        const bos = e.target.closest('#nobet-grid .gempty');
        if (bos && window.nobetOpenForm) {
            const d = new Date(bos.dataset.date + 'T00:00:00');
            const bugun = new Date(); bugun.setHours(0, 0, 0, 0);
            if (!window.nobetCanEditPast && d < bugun) { toast('Geçmiş tarihlere nöbet ekleyemezsiniz.', true); return; }
            window.nobetOpenForm(null, bos.dataset.date);
            return;
        }

        const chip = e.target.closest('#nobet-grid .gchip');
        if (chip && cal() && window.nobetShowDetail) {
            const ev = cal().getEventById(chip.dataset.eventId);
            if (ev) window.nobetShowDetail(ev);
        }
    });

    document.addEventListener('keydown', function (e) {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('nobet-kpi-click')) {
            e.preventDefault();
            kpiAction(e.target.dataset.kpi);
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.id === 'gridTumPersonel') { gridTumPersonel = e.target.checked; renderGrid(); }
        if (e.target.id === 'bordro_ay') bordroYukle();
        if (e.target.id === 'adalet_yil') adaletYukle();
    });

    if (window.jQuery) {
        jQuery(document).on('change', '#adalet_yil', adaletYukle);
    }
})();
