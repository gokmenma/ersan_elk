(function () {

function xlsxCiz(kap, buf, secenek) {
  const XLSX = secenek.XLSX;
  const ExcelJS = secenek.ExcelJS;
  const MAKS_SATIR = secenek.maksSatir || 2000;
  const MAKS_SUTUN = 80;
  const TEMA = ['FFFFFF', '000000', 'E7E6E6', '44546A', '4472C4', 'ED7D31', 'A5A5A5', 'FFC000', '5B9BD5', '70AD47'];
  const SINIR = { thin: ['1px', 'solid'], hair: ['1px', 'dotted'], dotted: ['1px', 'dotted'], dashed: ['1px', 'dashed'], mediumDashed: ['2px', 'dashed'], medium: ['2px', 'solid'], thick: ['3px', 'solid'], double: ['3px', 'double'] };

  const renk = (c) => {
    if (!c) return null;
    if (c.argb) { const h = String(c.argb).slice(-6); return /^[0-9a-fA-F]{6}$/.test(h) ? '#' + h : null; }
    if (typeof c.theme === 'number' && TEMA[c.theme]) {
      let hex = TEMA[c.theme];
      const tint = Number(c.tint) || 0;
      if (tint) {
        const ch = [0, 2, 4].map((i) => parseInt(hex.substr(i, 2), 16));
        const don = ch.map((v) => Math.round(tint > 0 ? v + (255 - v) * tint : v * (1 + tint)));
        hex = don.map((v) => Math.max(0, Math.min(255, v)).toString(16).padStart(2, '0')).join('');
      }
      return '#' + hex;
    }
    return null;
  };

  const sj = XLSX.read(buf, { type: 'array', cellDates: true, sheetRows: MAKS_SATIR + 1 });
  const ej = new ExcelJS.Workbook();
  const stilDuzelt = () => {
    if (!secenek.JSZip) return Promise.resolve(buf);
    return secenek.JSZip.loadAsync(buf).then((zip) => {
      const dosya = zip.file('xl/styles.xml');
      if (!dosya) return buf;
      return dosya.async('string').then((xml) => {
        const temiz = xml.replace(/<(b|i|strike|u)\s+val="(?:0|false|none)"\s*\/>/g, '');
        if (temiz === xml) return buf;
        zip.file('xl/styles.xml', temiz);
        return zip.generateAsync({ type: 'arraybuffer' });
      });
    }).catch(() => buf);
  };
  const ejHazir = stilDuzelt().then((veri) => ej.xlsx.load(veri)).then(() => true, () => false);

  return ejHazir.then((stilVar) => {
    const sekmeler = document.createElement('div');
    sekmeler.className = 'onizleme-sekmeler';
    const alan = document.createElement('div');
    alan.className = 'onizleme-tablo onizleme-xl';

    const goster = (ad) => {
      const ws = sj.Sheets[ad];
      const ews = stilVar ? ej.getWorksheet(ad) : null;
      const aralik = ws && ws['!ref'] ? XLSX.utils.decode_range(ws['!ref']) : null;
      if (!aralik) { alan.textContent = 'Bu sayfada görüntülenecek veri yok.'; return; }
      const sonSatir = Math.min(aralik.e.r, MAKS_SATIR - 1);
      const sonSutun = Math.min(aralik.e.c, MAKS_SUTUN - 1);

      const birlesik = {};
      const kapli = {};
      (ws['!merges'] || []).forEach((m) => {
        birlesik[m.s.r + ',' + m.s.c] = { rs: m.e.r - m.s.r + 1, cs: m.e.c - m.s.c + 1 };
        for (let r = m.s.r; r <= Math.min(m.e.r, sonSatir); r++) {
          for (let c = m.s.c; c <= Math.min(m.e.c, sonSutun); c++) { if (r !== m.s.r || c !== m.s.c) kapli[r + ',' + c] = true; }
        }
      });

      const gridlines = !(ews && ews.views && ews.views[0] && ews.views[0].showGridLines === false);
      const tablo = document.createElement('table');
      tablo.className = 'xl';
      tablo.style.borderCollapse = 'collapse';
      tablo.style.tableLayout = 'fixed';

      const colgroup = document.createElement('colgroup');
      let toplam = 0;
      const gizliSutun = {};
      for (let c = 0; c <= sonSutun; c++) {
        const col = document.createElement('col');
        const ec = ews ? ews.getColumn(c + 1) : null;
        const sc = ws['!cols'] && ws['!cols'][c];
        gizliSutun[c] = !!((ec && ec.hidden) || (sc && sc.hidden));
        let px = 72;
        if (ec && ec.width) px = Math.round(ec.width * 7 + 5);
        else if (sc && sc.wpx) px = sc.wpx;
        if (gizliSutun[c]) px = 0;
        col.style.width = px + 'px';
        toplam += px;
        colgroup.appendChild(col);
      }
      tablo.style.width = toplam + 'px';
      tablo.appendChild(colgroup);

      const govde = document.createElement('tbody');
      for (let r = aralik.s.r; r <= sonSatir; r++) {
        const er = ews ? ews.getRow(r + 1) : null;
        const sr = ws['!rows'] && ws['!rows'][r];
        if ((er && er.hidden) || (sr && sr.hidden)) continue;
        const tr = document.createElement('tr');
        let yukseklik = 20;
        if (er && er.height) yukseklik = Math.round(er.height * 1.333);
        else if (sr && sr.hpx) yukseklik = sr.hpx;
        tr.style.height = yukseklik + 'px';

        for (let c = aralik.s.c; c <= sonSutun; c++) {
          if (kapli[r + ',' + c] || gizliSutun[c]) continue;
          const adres = XLSX.utils.encode_cell({ r, c });
          const hucre = ws[adres];
          const td = document.createElement('td');
          const bir = birlesik[r + ',' + c];
          if (bir) { if (bir.rs > 1) td.rowSpan = bir.rs; if (bir.cs > 1) td.colSpan = bir.cs; }
          td.textContent = hucre ? (hucre.w != null ? String(hucre.w) : (hucre.v == null ? '' : String(hucre.v))) : '';

          const st = td.style;
          st.overflow = 'hidden';
          st.padding = '0 4px';
          st.boxSizing = 'border-box';
          st.verticalAlign = 'bottom';
          st.whiteSpace = 'nowrap';
          st.textOverflow = 'clip';
          st.color = '#000';
          st.fontSize = '14.67px';
          st.fontFamily = 'Calibri, Carlito, Arial, sans-serif';
          if (gridlines) st.border = '1px solid #d4d4d4';

          const ec = ews ? ews.getCell(r + 1, c + 1) : null;
          if (ec) {
            const f = ec.font || {};
            if (f.bold) st.fontWeight = '700';
            if (f.italic) st.fontStyle = 'italic';
            if (f.underline) st.textDecoration = 'underline';
            if (f.strike) st.textDecoration = (st.textDecoration ? st.textDecoration + ' ' : '') + 'line-through';
            if (f.size) st.fontSize = Math.round(f.size * 1.333 * 100) / 100 + 'px';
            if (f.name && /^[A-Za-z0-9 \-]+$/.test(f.name)) st.fontFamily = '"' + f.name + '", Calibri, Carlito, Arial, sans-serif';
            const fr = renk(f.color);
            if (fr) st.color = fr;
            const fill = ec.fill;
            if (fill && fill.type === 'pattern' && fill.pattern === 'solid') {
              const zemin = renk(fill.fgColor);
              if (zemin) st.backgroundColor = zemin;
            }
            const al = ec.alignment || {};
            let yatay = al.horizontal;
            if (!yatay || yatay === 'general') yatay = (hucre && hucre.t === 'n') ? 'right' : 'left';
            if (yatay === 'centerContinuous') yatay = 'center';
            st.textAlign = ['left', 'right', 'center', 'justify'].includes(yatay) ? yatay : 'left';
            if (al.vertical === 'top') st.verticalAlign = 'top';
            else if (al.vertical === 'middle' || al.vertical === 'center') st.verticalAlign = 'middle';
            if (al.wrapText) { st.whiteSpace = 'pre-wrap'; st.wordBreak = 'break-word'; }
            if (al.indent) st.paddingLeft = (4 + al.indent * 8) + 'px';
            const kenar = ec.border || {};
            [['top', 'borderTop'], ['bottom', 'borderBottom'], ['left', 'borderLeft'], ['right', 'borderRight']].forEach(([yon, ozellik]) => {
              const b = kenar[yon];
              if (b && b.style && SINIR[b.style]) st[ozellik] = SINIR[b.style][0] + ' ' + SINIR[b.style][1] + ' ' + (renk(b.color) || '#000');
            });
          } else if (hucre && hucre.t === 'n') {
            st.textAlign = 'right';
          }
          tr.appendChild(td);
        }
        govde.appendChild(tr);
      }
      tablo.appendChild(govde);
      alan.replaceChildren(tablo);
      if (aralik.e.r > sonSatir) {
        const not = document.createElement('div');
        not.className = 'onizleme-not';
        not.textContent = 'İlk ' + MAKS_SATIR + ' satır gösteriliyor. Tamamı için dosyayı indirin.';
        alan.appendChild(not);
      }
      sekmeler.querySelectorAll('button').forEach((b) => b.classList.toggle('active', b.dataset.sayfa === ad));
    };

    sj.SheetNames.forEach((ad) => {
      const dugme = document.createElement('button');
      dugme.type = 'button';
      dugme.dataset.sayfa = ad;
      dugme.textContent = ad;
      dugme.addEventListener('click', () => goster(ad));
      sekmeler.appendChild(dugme);
    });
    kap.replaceChildren(sekmeler, alan);
    if (sj.SheetNames.length) goster(sj.SheetNames[0]);
    else alan.textContent = 'Bu dosyada görüntülenecek sayfa yok.';
  });
}

  window.xlsxCiz = xlsxCiz;
})();
